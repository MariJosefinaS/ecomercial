<?php

namespace App\Livewire\Traspasos;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\ActivityLog;
use App\Models\Local;
use App\Models\StockLocal;
use App\Models\Traspaso;
use App\Models\TraspasoItem;
use App\Models\UnidadTrazable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Traspasos de mercadería entre sucursales. Se cargan las cajas por su CÓDIGO de
 * trazabilidad; al aprobar (admin) la unidad cambia de sucursal (el código se
 * reutiliza) y queda el evento de traspaso en su historia.
 */
#[Layout('components.layouts.app')]
#[Title('Traspasos — E.Comercial')]
class Index extends Component
{
    use AutorizaPermisos;

    public string $estado = 'todos';
    public ?string $mensaje = null;

    #[Url]
    public ?string $highlight = null;

    // Modal nuevo traspaso.
    public bool $modal = false;
    public ?int $tOrigen = null;
    public ?int $tDestino = null;
    public string $tMotivo = '';
    public array $tCodigos = [];   // lista de códigos a traspasar

    public function nuevo(): void
    {
        $this->autorizar('crear_traspaso');
        $this->reset(['tDestino', 'tMotivo', 'tCodigos']);
        // El origen es la sucursal del usuario (si tiene asignada); si no, elige.
        $this->tOrigen = auth()->user()?->local_id ?? Local::orderBy('id')->value('id');
        $this->tCodigos = [''];
        $this->resetValidation();
        $this->modal = true;
    }

    /** Auto-formatea el guion al tipear/escanear: TRZ-AAMMDD-PPP-SS-NNNN. */
    public function updatedTCodigos($value, $key): void
    {
        $this->tCodigos[$key] = self::formatearCodigo((string) $value);
    }

    private static function formatearCodigo(string $v): string
    {
        $d = preg_replace('/[^0-9]/', '', strtoupper($v));   // solo los dígitos (sirve si tipean o escanean el código entero)
        if ($d === '') {
            return trim($v) === '' ? '' : 'TRZ-';
        }
        $out = 'TRZ-' . substr($d, 0, 6);
        if (strlen($d) > 6)  { $out .= '-' . substr($d, 6, 3); }
        if (strlen($d) > 9)  { $out .= '-' . substr($d, 9, 2); }
        if (strlen($d) > 11) { $out .= '-' . substr($d, 11, 4); }

        return $out;
    }

    /** Validación en vivo de cada código tipeado (existe, en stock, no vendido, en el origen). */
    public function getCodigosEstadoProperty(): array
    {
        $origen = auth()->user()?->local_id ?? $this->tOrigen;
        $out = [];
        foreach ($this->tCodigos as $i => $cod) {
            $cod = trim((string) $cod);
            if ($cod === '' || $cod === 'TRZ-') {
                $out[$i] = null;
                continue;
            }
            $u = UnidadTrazable::with('producto:id,nombre')->where('codigo', $cod)->first();
            if (! $u) {
                $out[$i] = ['ok' => false, 'msg' => 'No existe ese código'];
            } elseif ($u->estado === UnidadTrazable::ENTREGADO) {
                $out[$i] = ['ok' => false, 'msg' => 'Ya fue vendido / entregado'];
            } elseif ($u->estado !== UnidadTrazable::EN_STOCK) {
                $out[$i] = ['ok' => false, 'msg' => 'No disponible (' . $u->estado . ')'];
            } elseif ((int) $u->local_id !== (int) $origen) {
                $out[$i] = ['ok' => false, 'msg' => 'Está en otra sucursal'];
            } else {
                $out[$i] = ['ok' => true, 'msg' => $u->producto?->nombre ?? 'Disponible'];
            }
        }

        return $out;
    }

    public function agregarCodigo(): void
    {
        $this->tCodigos[] = '';
    }

    public function quitarCodigo(int $i): void
    {
        unset($this->tCodigos[$i]);
        $this->tCodigos = array_values($this->tCodigos);
        if (empty($this->tCodigos)) {
            $this->tCodigos = [''];
        }
    }

    public function guardar(): void
    {
        $this->autorizar('crear_traspaso');

        // Origen: si el usuario tiene sucursal, se fuerza.
        if ($fija = auth()->user()?->local_id) {
            $this->tOrigen = $fija;
        }

        $this->validate([
            'tOrigen' => 'required|exists:locales,id',
            'tDestino' => 'required|exists:locales,id|different:tOrigen',
        ], [], ['tOrigen' => 'origen', 'tDestino' => 'destino']);

        // Resolver y validar cada código (en stock, en el origen, sin repetir).
        $vistos = [];
        $unidades = [];
        foreach ($this->tCodigos as $i => $cod) {
            $cod = trim((string) $cod);
            if ($cod === '') {
                continue;
            }
            if (in_array($cod, $vistos, true)) {
                $this->addError("tCodigos.{$i}", 'Código repetido.');
                continue;
            }
            $vistos[] = $cod;
            $u = UnidadTrazable::where('codigo', $cod)->first();
            if (! $u) {
                $this->addError("tCodigos.{$i}", 'No existe ese código.');
            } elseif ($u->estado === UnidadTrazable::ENTREGADO) {
                $this->addError("tCodigos.{$i}", 'Esa caja ya fue vendida / entregada.');
            } elseif ($u->estado !== UnidadTrazable::EN_STOCK) {
                $this->addError("tCodigos.{$i}", "No disponible (estado {$u->estado}).");
            } elseif ((int) $u->local_id !== (int) $this->tOrigen) {
                $this->addError("tCodigos.{$i}", 'Esa caja no está en la sucursal de origen.');
            } else {
                $unidades[] = $u;
            }
        }
        if (empty($unidades) && $this->getErrorBag()->isEmpty()) {
            $this->addError('tCodigos.0', 'Cargá al menos un código de caja.');
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $maxNum = (int) (Traspaso::selectRaw("MAX(CAST(REGEXP_REPLACE(numero, '[^0-9]', '') AS UNSIGNED)) as n")->value('n') ?? 0);
        $num = 'TRAS-' . ($maxNum + 1);

        DB::transaction(function () use ($num, $unidades) {
            $traspaso = Traspaso::create([
                'numero' => $num,
                'local_origen_id' => $this->tOrigen,
                'local_destino_id' => $this->tDestino,
                'usuario_id' => auth()->id(),
                'fecha' => now(),
                'motivo' => $this->tMotivo ?: null,
                'estado' => 'pendiente',
            ]);
            foreach ($unidades as $u) {
                TraspasoItem::create([
                    'traspaso_id' => $traspaso->id,
                    'unidad_id' => $u->id,
                    'producto_id' => $u->producto_id,
                ]);
            }
        });

        $this->modal = false;
        $this->mensaje = "Traspaso {$num} creado con " . count($unidades) . ' caja(s). Pendiente de aprobación.';
    }

    /** Aprobar: mueve las unidades a la sucursal destino y ajusta el stock. */
    public function aprobar(int $id): void
    {
        $this->autorizar('aprobar_traspasos');
        $traspaso = Traspaso::with('items.unidad')->find($id);
        if (! $traspaso || $traspaso->estado !== 'pendiente') {
            return;
        }

        $movidas = 0;
        DB::transaction(function () use ($traspaso, &$movidas) {
            foreach ($traspaso->items as $item) {
                $u = $item->unidad;
                // Solo mover si sigue en stock en el origen (evita inconsistencias).
                if (! $u || $u->estado !== UnidadTrazable::EN_STOCK || (int) $u->local_id !== (int) $traspaso->local_origen_id) {
                    continue;
                }

                $slO = StockLocal::where('producto_id', $u->producto_id)->where('local_id', $traspaso->local_origen_id)->first();
                if ($slO && $slO->cantidad > 0) {
                    $slO->decrement('cantidad', 1);
                }
                StockLocal::firstOrCreate(
                    ['producto_id' => $u->producto_id, 'local_id' => $traspaso->local_destino_id],
                    ['cantidad' => 0, 'stock_minimo' => 0, 'precio_venta' => 0]
                )->increment('cantidad', 1);

                $u->update(['local_id' => $traspaso->local_destino_id]);   // el código se reutiliza
                $u->registrar('traspaso', $traspaso->local_destino_id, $traspaso->numero,
                    'De ' . ($traspaso->origen?->nombre ?? '—') . ' a ' . ($traspaso->destino?->nombre ?? '—'));
                $movidas++;
            }

            $traspaso->update(['estado' => 'aprobada', 'aprobada_por' => auth()->id()]);

            ActivityLog::create([
                'tipo' => 'stock',
                'titulo' => 'Traspaso aprobado',
                'detalle' => "Traspaso {$traspaso->numero}: {$movidas} caja(s) de "
                    . ($traspaso->origen?->nombre ?? '—') . ' a ' . ($traspaso->destino?->nombre ?? '—') . '.',
                'usuario_id' => auth()->id(),
                'local_id' => $traspaso->local_destino_id,
            ]);
        });

        $this->mensaje = "Traspaso {$traspaso->numero} aprobado — {$movidas} caja(s) movida(s).";
    }

    public function rechazar(int $id): void
    {
        $this->autorizar('aprobar_traspasos');
        Traspaso::where('id', $id)->where('estado', 'pendiente')->update(['estado' => 'rechazada']);
        $this->mensaje = 'Traspaso rechazado.';
    }

    public function render()
    {
        $filas = Traspaso::with(['origen:id,nombre', 'destino:id,nombre', 'usuario:id,name'])
            ->withCount('items')
            ->when($this->estado !== 'todos', fn ($q) => $q->where('estado', $this->estado))
            ->orderByDesc('id')->get();

        return view('livewire.traspasos.index', [
            'filas' => $filas,
            'locales' => Local::where('activo', true)->orderBy('id')->get(['id', 'nombre']),
            'sucursalFija' => (bool) auth()->user()?->local_id,
            'pendientes' => Traspaso::where('estado', 'pendiente')->count(),
        ]);
    }
}
