<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Categoria;
use App\Models\Proveedor;
use App\Support\StockValorizado as Motor;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stock valorizado: cuánta plata hay inmovilizada a COSTO y a VENTA, con el margen
 * potencial, por sucursal / proveedor / categoría. Gateado `gestionar_stock` porque
 * expone el precio de compra (el vendedor no lo ve).
 */
#[Layout('components.layouts.app')]
#[Title('Stock valorizado — E.Comercial')]
class Valorizado extends Component
{
    use AutorizaPermisos;

    #[Url(as: 'sub')]
    public string $tab = 'detalle';   // detalle | proveedor | sucursal | categoria

    public ?int $filtroLocal = null;
    public ?int $filtroProveedor = null;
    public ?int $filtroCategoria = null;
    public string $buscar = '';
    public bool $soloConStock = true;

    public function mount(): void
    {
        $this->autorizar('gestionar_stock');
    }

    public function setTab(string $t): void { $this->tab = $t; }

    private function filas()
    {
        return Motor::filas(
            $this->filtroLocal ?: null,
            $this->filtroProveedor ?: null,
            $this->filtroCategoria ?: null,
            $this->buscar,
            $this->soloConStock
        );
    }

    /** Exporta el detalle valorizado a CSV (con BOM para que Excel lea los acentos). */
    public function exportarCsv(): StreamedResponse
    {
        $this->autorizar('gestionar_stock');
        $filas = $this->filas();
        $nombre = 'stock_valorizado_' . Carbon::today()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Código', 'Producto', 'Marca', 'Categoría', 'Proveedor', 'Sucursal', 'Cantidad',
                'Costo unit.', 'Valor a costo', 'Venta unit.', 'Valor a venta', 'Margen', 'Margen %'], ';');
            foreach ($filas as $f) {
                fputcsv($out, [
                    $f['codigo'], $f['nombre'], $f['marca'], $f['categoria'], $f['proveedor'], $f['local'], $f['cantidad'],
                    number_format($f['costo_unit'], 2, ',', ''), number_format($f['valor_costo'], 2, ',', ''),
                    number_format($f['venta_unit'], 2, ',', ''), number_format($f['valor_venta'], 2, ',', ''),
                    number_format($f['margen'], 2, ',', ''), number_format($f['margen_pct'], 1, ',', ''),
                ], ';');
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $filas = $this->filas();

        return view('livewire.stock.valorizado', [
            'filas' => $this->tab === 'detalle' ? $filas->sortByDesc('valor_costo')->values() : collect(),
            'grupos' => match ($this->tab) {
                'proveedor' => Motor::agrupado($filas, 'proveedor'),
                'sucursal' => Motor::agrupado($filas, 'local'),
                'categoria' => Motor::agrupado($filas, 'categoria'),
                default => [],
            },
            'totales' => Motor::totales($filas),
            'locales' => Motor::locales(),
            'proveedores' => Proveedor::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
