<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\Local;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\StockLocal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa el catálogo histórico del cliente (CSV del sistema viejo) de forma
 * IDEMPOTENTE: hace upsert por `codigo_externo`, así se puede reimportar sin
 * duplicar. Pensado para dos archivos:
 *   - PROVEEDORES.csv       (ID_Proveedor, NombreProveedor, Contacto_WhatsApp, Demora_Estimada_Dias)
 *   - STOCK_MEJORADO.csv    (ProductoID, CodigoUsuario, NombreProducto, Categoria, Marca,
 *                            StockTotal, PrecioVenta, ID_Proveedor, Tags_Busqueda, URL_Imagen)
 *
 * Reglas / decisiones (documentadas — revisar con la data REAL):
 *   - Clave de idempotencia: proveedor = ID_Proveedor, producto = ProductoID.
 *   - `codigo` (SKU interno, único): usa CodigoUsuario si viene y no está repetido;
 *     si no, cae a `MIG-{ProductoID}`. CodigoUsuario siempre queda además en `sku`.
 *   - Proveedor referido pero inexistente en PROVEEDORES.csv → se auto-crea placeholder.
 *   - Stock/precio del CSV son ÚNICOS, pero el modelo es por local: el precio va a
 *     TODOS los locales activos; la cantidad (StockTotal) va al local elegido (--local),
 *     0 en el resto.
 *   - No hay costo de compra en el CSV → precio_compra/precio_neto NO se tocan.
 */
class ImportarMigracion extends Command
{
    protected $signature = 'importar:migracion
        {--dry-run : Simula todo y hace rollback; no persiste nada}
        {--path= : Carpeta de los CSV (default: ../../supuestas tablas)}
        {--proveedores=PROVEEDORES.csv : Nombre del CSV de proveedores}
        {--stock=STOCK_MEJORADO.csv : Nombre del CSV de stock}
        {--local= : Local (id) que recibe el StockTotal (default: 1ra sucursal activa)}';

    protected $description = 'Importa proveedores + catálogo/stock desde los CSV del sistema viejo (idempotente, upsert por codigo_externo).';

    /** @var array<string,int> */
    private array $stats = [];

    /** @var list<string> */
    private array $anomalias = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $dir = $this->option('path') ?: base_path('../../supuestas tablas');
        $dir = rtrim($dir, '/\\');
        $fileProv = $dir . DIRECTORY_SEPARATOR . $this->option('proveedores');
        $fileStock = $dir . DIRECTORY_SEPARATOR . $this->option('stock');

        foreach (['proveedores' => $fileProv, 'stock' => $fileStock] as $etq => $f) {
            if (! is_file($f)) {
                $this->error("No encuentro el CSV de {$etq}: {$f}");

                return self::FAILURE;
            }
        }

        // Local destino del stock.
        $local = $this->option('local')
            ? Local::find((int) $this->option('local'))
            : Local::where('activo', true)->orderBy('id')->first();
        if (! $local) {
            $this->error('No hay locales para asignar el stock. Sembrá al menos uno.');

            return self::FAILURE;
        }
        $localesActivos = Local::where('activo', true)->get();

        $this->info(($dryRun ? '🔎 DRY-RUN — ' : '💾 IMPORT — ')
            . "proveedores={$this->option('proveedores')} stock={$this->option('stock')} "
            . "stock→local «{$local->nombre}» (precio a {$localesActivos->count()} locales)");

        DB::beginTransaction();
        try {
            $mapProv = $this->importarProveedores($fileProv);
            $this->importarStock($fileStock, $mapProv, $local, $localesActivos);

            if ($dryRun) {
                DB::rollBack();
                $this->line('');
                $this->warn('DRY-RUN: rollback hecho, nada persistido.');
            } else {
                DB::commit();
                $this->line('');
                $this->info('✅ Import confirmado y persistido.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Abortado (rollback): ' . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());

            return self::FAILURE;
        }

        $this->resumen();

        return self::SUCCESS;
    }

    /** @return array<string,int> codigo_externo → proveedor_id */
    private function importarProveedores(string $file): array
    {
        $map = [];
        foreach ($this->leerCsv($file) as $row) {
            $ext = trim($row['ID_Proveedor'] ?? '');
            $nombre = trim($row['NombreProveedor'] ?? '');
            if ($ext === '') {
                $this->anomalias[] = "Proveedor sin ID_Proveedor (nombre: {$nombre}) — omitido";
                $this->inc('prov_omitidos');

                continue;
            }
            $prov = Proveedor::updateOrCreate(
                ['codigo_externo' => $ext],
                [
                    'nombre' => $nombre !== '' ? $nombre : $ext,
                    'telefono' => trim($row['Contacto_WhatsApp'] ?? '') ?: null,
                    'dias_entrega' => is_numeric($row['Demora_Estimada_Dias'] ?? null)
                        ? (int) $row['Demora_Estimada_Dias'] : null,
                ]
            );
            $this->inc($prov->wasRecentlyCreated ? 'prov_creados' : 'prov_actualizados');
            $map[$ext] = $prov->id;
        }

        return $map;
    }

    /** @param array<string,int> $mapProv */
    private function importarStock(string $file, array $mapProv, Local $local, $localesActivos): void
    {
        $catCache = [];       // nombre → id
        $codigosUsados = [];  // codigo interno ya asignado (para no violar unique)

        foreach ($this->leerCsv($file) as $row) {
            $pid = trim($row['ProductoID'] ?? '');
            $nombre = trim($row['NombreProducto'] ?? '');
            if ($pid === '') {
                $this->anomalias[] = "Producto sin ProductoID (nombre: {$nombre}) — omitido";
                $this->inc('prod_omitidos');

                continue;
            }

            // Categoría (find-or-create por nombre).
            $catId = null;
            $catNom = trim($row['Categoria'] ?? '');
            if ($catNom !== '') {
                $catId = $catCache[$catNom] ??= Categoria::firstOrCreate(['nombre' => $catNom])->id;
            }

            // Proveedor: resuelve por ID; si falta, auto-crea placeholder.
            $provExt = trim($row['ID_Proveedor'] ?? '');
            $provId = null;
            if ($provExt !== '') {
                if (! isset($mapProv[$provExt])) {
                    $ph = Proveedor::updateOrCreate(
                        ['codigo_externo' => $provExt],
                        ['nombre' => $provExt]
                    );
                    $mapProv[$provExt] = $ph->id;
                    $this->anomalias[] = "Proveedor {$provExt} no estaba en PROVEEDORES.csv → creado placeholder";
                    $this->inc('prov_placeholder');
                }
                $provId = $mapProv[$provExt];
            }

            // Código interno único.
            $codUsuario = trim($row['CodigoUsuario'] ?? '');
            $codigo = $codUsuario;
            if ($codigo === '' || isset($codigosUsados[$codigo])) {
                if ($codigo !== '') {
                    $this->anomalias[] = "CodigoUsuario duplicado/blank «{$codUsuario}» (ProductoID {$pid}) → uso MIG-{$pid}";
                    $this->inc('cod_fallback');
                }
                $codigo = "MIG-{$pid}";
            }
            $codigosUsados[$codigo] = true;

            $precio = $this->num($row['PrecioVenta'] ?? null);
            $cantidad = (int) round($this->num($row['StockTotal'] ?? null));
            if ($precio <= 0) {
                $this->anomalias[] = "PrecioVenta <= 0 (ProductoID {$pid}: {$nombre})";
                $this->inc('precio_cero');
            }

            $prod = Producto::updateOrCreate(
                ['codigo_externo' => $pid],
                [
                    'codigo' => $codigo,
                    'sku' => $codUsuario ?: null,
                    'nombre' => $nombre !== '' ? $nombre : $codigo,
                    'marca' => trim($row['Marca'] ?? '') ?: null,
                    'tags' => trim($row['Tags_Busqueda'] ?? '') ?: null,
                    'imagen' => trim($row['URL_Imagen'] ?? '') ?: null,
                    'categoria_id' => $catId,
                    'proveedor_id' => $provId,
                ]
            );
            $this->inc($prod->wasRecentlyCreated ? 'prod_creados' : 'prod_actualizados');

            // Stock por local: precio a todos, cantidad solo al local elegido.
            foreach ($localesActivos as $l) {
                StockLocal::updateOrCreate(
                    ['producto_id' => $prod->id, 'local_id' => $l->id],
                    [
                        'precio_venta' => $precio,
                        'cantidad' => $l->id === $local->id ? $cantidad : DB::raw('cantidad'),
                    ]
                );
            }
        }
    }

    /** Lee un CSV con cabecera y devuelve filas asociativas. */
    private function leerCsv(string $file): \Generator
    {
        $fh = fopen($file, 'r');
        // Saltar BOM UTF-8 si está.
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        $head = fgetcsv($fh);
        $head = array_map(fn ($h) => trim((string) $h), $head ?: []);
        while (($data = fgetcsv($fh)) !== false) {
            if ($data === [null] || $data === []) {
                continue; // línea vacía
            }
            $data = array_pad($data, count($head), null);
            yield array_combine($head, array_slice($data, 0, count($head)));
        }
        fclose($fh);
    }

    /** Parsea número (soporta 1273513.32). */
    private function num($v): float
    {
        $v = trim((string) $v);

        return $v === '' ? 0.0 : (float) str_replace([' ', ','], ['', '.'], $v);
    }

    private function inc(string $k): void
    {
        $this->stats[$k] = ($this->stats[$k] ?? 0) + 1;
    }

    private function resumen(): void
    {
        $this->line('');
        $this->line('── Resumen ──');
        $orden = ['prov_creados', 'prov_actualizados', 'prov_placeholder', 'prov_omitidos',
            'prod_creados', 'prod_actualizados', 'prod_omitidos', 'cod_fallback', 'precio_cero'];
        $rows = [];
        foreach ($orden as $k) {
            if (isset($this->stats[$k])) {
                $rows[] = [$k, $this->stats[$k]];
            }
        }
        $this->table(['métrica', 'n'], $rows);

        if ($this->anomalias) {
            $this->warn('Anomalías (' . count($this->anomalias) . '), primeras 15:');
            foreach (array_slice($this->anomalias, 0, 15) as $a) {
                $this->line('  • ' . $a);
            }
        }
    }
}
