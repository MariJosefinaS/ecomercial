<?php

namespace App\Livewire\Cobranza;

use App\Livewire\Concerns\AutorizaPermisos;
use App\Models\Cuota;
use App\Models\NoVisita;
use App\Models\PlanillaCobranza;
use App\Models\User;
use App\Models\Zona;
use App\Support\Cobranza;
use App\Support\Planilla as PlanillaCalc;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Mi planilla del cobrador (Bloque 2). Muestra las cuotas a cobrar del día, agrupadas por
 * MODALIDAD (diaria/semanal/mensual) — el moroso reaparece en la planilla de su plan. Cada
 * grupo es una PlanillaCobranza con apertura/cierre (hora) + estado auditable. Imprimible y
 * exportable a CSV. El cobrador ve solo la suya; el admin puede ver la de cualquiera y auditar.
 */
#[Layout('components.layouts.app')]
#[Title('Mi planilla — E.Comercial')]
class Planilla extends Component
{
    use AutorizaPermisos, WithFileUploads;

    #[Url]
    public string $fecha = '';

    #[Url(as: 'cob')]
    public ?int $cobradorId = null;

    public ?string $mensaje = null;

    // Último recibo generado (para ofrecer "Ver recibo" tras cobrar).
    public ?int $ultimoReciboCobroId = null;
    public bool $ultimoReciboEnviado = false;

    // ===== Modal de cobro (monto libre + medio + comprobante) =====
    public bool $modalCobro = false;
    public ?int $cobroCuotaId = null;
    public string $cobroCliente = '';
    public float $cobroSugerido = 0;          // total a cobrar de la cuota (referencia)
    public string $cobroMonto = '';           // importe principal (prefill = total)
    public string $cobroMedio = 'efectivo';   // medio del importe principal (efectivo/transferencia/cheque)
    // Pago dividido: un 2º casillero con (total − principal) y su medio (efectivo/transferencia).
    public bool $cobroDividir = false;
    public string $cobroMonto2 = '';
    public string $cobroMedio2 = 'transferencia';
    public $cobroComprobante = null;          // archivo (si alguna parte es transferencia)
    public string $cobroBanco = '';
    public string $cobroChequeNumero = '';

    // ===== Reporte "no cobré este día" (queda PENDIENTE de aprobación del supervisor) =====
    public bool $modalNoVisita = false;
    public string $nvMotivo = 'ausente';
    public string $nvNota = '';

    public function mount(): void
    {
        $this->autorizar('ver_cobranza');
        if ($this->fecha === '') {
            $this->fecha = Carbon::today()->toDateString();
        }
        // El cobrador de campo solo ve SU planilla; el admin puede elegir cobrador.
        if (! $this->esAdmin()) {
            $this->cobradorId = auth()->id();
        }
        $this->cobradorId ??= auth()->id();
    }

    private function esAdmin(): bool
    {
        return auth()->user()?->esRol('super_admin', 'admin_local') ?? false;
    }

    private function fechaCarbon(): Carbon
    {
        return Carbon::parse($this->fecha)->startOfDay();
    }

    /** Planilla persistida (encabezado) de una modalidad, si ya se abrió. */
    private function planilla(string $modalidad): ?PlanillaCobranza
    {
        return PlanillaCobranza::where('cobrador_id', $this->cobradorId)
            ->whereDate('fecha', $this->fechaCarbon())
            ->where('modalidad', $modalidad)
            ->first();
    }

    private function planillaFirstOrNew(string $modalidad): PlanillaCobranza
    {
        return PlanillaCobranza::firstOrNew([
            'cobrador_id' => $this->cobradorId,
            'fecha' => $this->fechaCarbon()->toDateString(),
            'modalidad' => $modalidad,
        ]);
    }

    private function puedeGestionar(): bool
    {
        return $this->cobradorId === auth()->id() || $this->esAdmin();
    }

    // ===== Acciones de la planilla =====
    public function abrir(string $modalidad): void
    {
        $this->autorizar('ver_cobranza');
        if (! $this->puedeGestionar()) {
            return;
        }
        $p = $this->planillaFirstOrNew($modalidad);
        if ($p->cerrada()) {
            return;
        }
        $p->hora_apertura ??= now();
        $p->estado = 'en_confeccion';
        $p->save();
        $this->mensaje = 'Planilla ' . PlanillaCobranza::MODALIDADES[$modalidad] . ' abierta ' . $p->hora_apertura->format('H:i') . '.';
    }

    public function cerrar(string $modalidad): void
    {
        $this->autorizar('ver_cobranza');
        if (! $this->puedeGestionar()) {
            return;
        }
        $p = $this->planilla($modalidad);
        if (! $p || $p->cerrada()) {
            return;
        }
        $f = $this->fechaCarbon();
        $tot = PlanillaCalc::totales(PlanillaCalc::cuotasDelDia($this->cobradorId, $f), $f, $modalidad);
        $p->update([
            'hora_cierre' => now(),
            'estado' => 'pend_auditoria',
            'total_esperado' => $tot['esperado'],
            'total_cobrado' => $tot['cobrado'],
        ]);
        $this->mensaje = 'Planilla ' . PlanillaCobranza::MODALIDADES[$modalidad] . ' cerrada — queda pendiente de auditoría.';
    }

    public function auditar(string $modalidad): void
    {
        $this->autorizar('auditar_cobranza');
        $p = $this->planilla($modalidad);
        if (! $p || $p->estado !== 'pend_auditoria') {
            return;
        }
        $p->update(['estado' => 'cerrada', 'auditada_por' => auth()->id(), 'auditada_at' => now()]);
        $this->mensaje = 'Planilla ' . PlanillaCobranza::MODALIDADES[$modalidad] . ' auditada y cerrada.';
    }

    /** Registrar cobro de una cuota (reusa el servicio central; Bloque 3 lo extiende a monto libre + medio). */
    /** Abre el modal de cobro de una cuota (prefill del total sugerido). */
    public function abrirCobro(int $cuotaId): void
    {
        $this->autorizar('registrar_cobro');
        $cuota = $this->cuotaCobrable($cuotaId);
        if (! $cuota) {
            return;
        }
        $this->reset(['cobroDividir', 'cobroMonto2', 'cobroComprobante', 'cobroBanco', 'cobroChequeNumero']);
        $this->resetValidation();
        $this->cobroCuotaId = $cuota->id;
        $this->cobroCliente = $cuota->cliente?->nombre ?? 'Cliente';
        $this->cobroSugerido = round($cuota->totalAcobrar($this->fechaCarbon()), 2);
        $this->cobroMonto = (string) $this->cobroSugerido;   // prefill = total; el cobrador lo edita/divide
        $this->cobroMedio = 'efectivo';
        $this->cobroMedio2 = 'transferencia';
        $this->modalCobro = true;
    }

    /** Al tildar "dividir" (o cambiar el importe principal) el 2º casillero = total − principal. */
    public function updatedCobroDividir(): void
    {
        $this->cobroMonto2 = $this->cobroDividir
            ? (string) max(0, round($this->cobroSugerido - (float) $this->cobroMonto, 2))
            : '';
    }

    public function updatedCobroMonto(): void
    {
        if ($this->cobroDividir) {
            $this->cobroMonto2 = (string) max(0, round($this->cobroSugerido - (float) $this->cobroMonto, 2));
        }
    }

    public function cerrarCobro(): void
    {
        $this->modalCobro = false;
        $this->resetValidation();
    }

    /** Registra el cobro con el monto y medio ingresados (usa el servicio central registrarPago). */
    /** Total del pago = principal + (si está dividido) el 2º importe. */
    public function getCobroTotalProperty(): float
    {
        return round((float) $this->cobroMonto + ($this->cobroDividir ? (float) $this->cobroMonto2 : 0), 2);
    }

    public function registrarCobro(): void
    {
        $this->autorizar('registrar_cobro');
        $m1 = round((float) $this->cobroMonto, 2);
        $m2 = $this->cobroDividir ? round((float) $this->cobroMonto2, 2) : 0.0;
        // ¿qué medios intervienen? (para pedir comprobante/cheque)
        $medios = array_filter([$m1 > 0 ? $this->cobroMedio : null, $m2 > 0 ? $this->cobroMedio2 : null]);
        $hayTransfer = in_array('transferencia', $medios, true);
        $hayCheque = in_array('cheque', $medios, true);

        $this->validate([
            'cobroMonto' => 'required|numeric|min:0',
            'cobroMonto2' => $this->cobroDividir ? 'required|numeric|min:0' : 'nullable',
            'cobroComprobante' => $hayTransfer ? 'required|image|max:4096' : 'nullable|image|max:4096',
            'cobroChequeNumero' => $hayCheque ? 'required' : 'nullable',
        ], attributes: [
            'cobroMonto' => 'importe', 'cobroMonto2' => 'segundo importe',
            'cobroComprobante' => 'comprobante', 'cobroChequeNumero' => 'número de cheque',
        ]);

        if (round($m1 + $m2, 2) <= 0) {
            $this->addError('cobroMonto', 'Ingresá un importe mayor a cero.');
            return;
        }

        $cuota = $this->cuotaCobrable($this->cobroCuotaId);
        if (! $cuota) {
            $this->cerrarCobro();
            return;
        }

        // Subir el comprobante una sola vez (aplica a la parte que sea transferencia).
        $comp = $hayTransfer && $this->cobroComprobante ? $this->cobroComprobante->store('comprobantes', 'public') : null;
        $parte = function (string $medio, float $monto) use ($comp) {
            $p = ['medio' => $medio, 'monto' => $monto];
            if ($medio === 'transferencia') {
                $p['comprobante'] = $comp;
                $p['banco'] = $this->cobroBanco ?: null;
            } elseif ($medio === 'cheque') {
                $p['banco'] = $this->cobroBanco ?: null;
                $p['cheque_numero'] = $this->cobroChequeNumero ?: null;
            }

            return $p;
        };

        $partes = [];
        if ($m1 > 0) {
            $partes[] = $parte($this->cobroMedio, $m1);
        }
        if ($m2 > 0) {
            $partes[] = $parte($this->cobroMedio2, $m2);
        }

        $r = Cobranza::registrarPago($cuota, $partes, ['cobrador_id' => $this->cobradorId], $this->fechaCarbon());
        $this->ultimoReciboCobroId = $r['cobro_id'] ?? null;
        $this->ultimoReciboEnviado = (bool) ($r['recibo_enviado'] ?? false);
        $this->mensaje = $this->cobroCliente . ' — ' . $r['mensaje']
            . ($this->ultimoReciboEnviado ? ' 📧 Recibo enviado al cliente.' : '');
        $this->cerrarCobro();
    }

    /** Reenvía por mail el recibo de un cobro (anti-IDOR: solo cobros de la zona del cobrador). */
    public function reenviarRecibo(int $cobroId): void
    {
        $this->autorizar('registrar_cobro');
        $cobro = \App\Models\Cobro::find($cobroId);
        if (! $cobro) {
            return;
        }
        // El cobrador de campo solo puede reenviar recibos de SU zona/cobro.
        if (! $this->esAdmin() && $cobro->cobrador_id !== auth()->id()
            && ! Zona::where('id', $cobro->zona_id)->where('cobrador_id', auth()->id())->exists()) {
            return;
        }
        $ok = \App\Support\Recibo::enviarPorMail($cobro);
        $this->mensaje = $ok
            ? 'Recibo reenviado al cliente por mail.'
            : 'No se pudo enviar: el cliente no tiene email cargado.';
    }

    /** Cuota cobrable por este usuario (existe, pendiente, de su zona salvo admin). Anti-IDOR. */
    private function cuotaCobrable(?int $cuotaId): ?Cuota
    {
        $cuota = Cuota::with('cliente:id,nombre', 'zonaRel')->find($cuotaId);
        if (! $cuota || $cuota->estado !== 'pendiente' || ! $this->puedeGestionar()) {
            return null;
        }
        if (! $this->esAdmin() && $cuota->zonaRel?->cobrador_id !== $this->cobradorId) {
            $this->mensaje = 'Esa cuota no pertenece a tu zona de cobranza.';
            return null;
        }

        return $cuota;
    }

    // ===== Reporte "no cobré este día" (pendiente de aprobación del supervisor) =====
    public function abrirNoVisita(): void
    {
        $this->autorizar('reportar_no_visita');
        $this->reset(['nvNota']);
        $this->nvMotivo = 'ausente';
        $this->resetValidation();
        $this->modalNoVisita = true;
    }

    public function cerrarNoVisita(): void
    {
        $this->modalNoVisita = false;
    }

    public function reportarNoVisita(): void
    {
        $this->autorizar('reportar_no_visita');
        $this->validate(['nvMotivo' => 'required|in:' . implode(',', array_keys(NoVisita::MOTIVOS))]);

        // Reporta para TODAS las zonas del cobrador en la fecha vista → queda PENDIENTE de aprobación.
        $zonas = Zona::where('cobrador_id', $this->cobradorId)->pluck('id');
        foreach ($zonas as $zid) {
            NoVisita::firstOrCreate(
                ['zona_id' => $zid, 'fecha' => $this->fechaCarbon()->toDateString()],
                [
                    'motivo' => $this->nvMotivo, 'nota' => $this->nvNota ?: null,
                    'estado' => 'pendiente', 'solicitado_por' => auth()->id(), 'registrado_por' => auth()->id(),
                ],
            );
        }
        NoVisita::limpiarCache();
        $this->modalNoVisita = false;
        $this->mensaje = 'Reporte enviado — queda pendiente de aprobación del supervisor (la mora sigue hasta que aprueben).';
    }

    /** Exporta a CSV la planilla de una modalidad (Excel la abre directo). */
    public function exportarCsv(string $modalidad)
    {
        $this->autorizar('ver_cobranza');
        $f = $this->fechaCarbon();
        $cobrador = User::find($this->cobradorId);
        $filas = PlanillaCalc::filas(PlanillaCalc::cuotasDelDia($this->cobradorId, $f), $f, $modalidad);

        $nombre = 'planilla_' . $modalidad . '_' . ($cobrador?->name ? str($cobrador->name)->slug() : $this->cobradorId) . '_' . $f->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($filas, $cobrador, $f, $modalidad) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para acentos en Excel
            fputcsv($out, ["Planilla de cobranza {$modalidad}", 'Cobrador: ' . ($cobrador?->name ?? '—'), 'Fecha: ' . $f->format('d/m/Y')]);
            fputcsv($out, ['Cliente', 'Domicilio', 'Zona', 'Crédito', 'Plan', 'Cuota Nº', 'Vence', 'Días atraso', 'Saldo', 'Total a cobrar', 'Estado']);
            foreach ($filas as $r) {
                fputcsv($out, [
                    $r['cliente'], $r['domicilio'], $r['zona'], $r['credito'], $r['plan'], $r['numero'],
                    $r['vence'], $r['dias'], number_format($r['saldo'], 2, ',', '.'),
                    number_format($r['total'], 2, ',', '.'), $r['estado'],
                ]);
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv']);
    }

    /** Exporta a PDF (descarga) la planilla de una modalidad usando dompdf (landscape, cabe en A4). */
    public function exportarPdf(string $modalidad)
    {
        $this->autorizar('ver_cobranza');
        $f = $this->fechaCarbon();
        $cobrador = User::find($this->cobradorId);
        $cuotas = PlanillaCalc::cuotasDelDia($this->cobradorId, $f);
        $filas = PlanillaCalc::filas($cuotas, $f, $modalidad);
        $tot = PlanillaCalc::totales($cuotas, $f, $modalidad);

        $nombre = 'planilla_' . $modalidad . '_' . ($cobrador?->name ? str($cobrador->name)->slug() : $this->cobradorId) . '_' . $f->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cobranza.planilla-pdf', [
            'filas' => $filas, 'tot' => $tot, 'cobrador' => $cobrador, 'fecha' => $f, 'modalidad' => $modalidad,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), $nombre, ['Content-Type' => 'application/pdf']);
    }

    public function render()
    {
        $f = $this->fechaCarbon();
        $cuotas = PlanillaCalc::cuotasDelDia($this->cobradorId, $f);
        $cobrador = User::find($this->cobradorId);

        // Mapa cuota → último cobro (para el enlace "Ver recibo" en las filas cobradas).
        $recibosPorCuota = $cuotas->isNotEmpty()
            ? \App\Models\Cobro::whereIn('cuota_id', $cuotas->pluck('id'))
                ->selectRaw('cuota_id, max(id) as id')->groupBy('cuota_id')->pluck('id', 'cuota_id')
            : collect();

        $grupos = PlanillaCalc::modalidadesPresentes($cuotas)->map(function (string $modalidad) use ($f, $cuotas) {
            $p = $this->planilla($modalidad);
            $tot = PlanillaCalc::totales($cuotas, $f, $modalidad);

            return [
                'modalidad' => $modalidad,
                'label' => PlanillaCobranza::MODALIDADES[$modalidad] ?? ucfirst($modalidad),
                'estado' => $p?->estado ?? 'sin_abrir',
                'estado_label' => $p ? $p->estadoLabel() : 'Sin abrir',
                'apertura' => $p?->hora_apertura?->format('H:i'),
                'cierre' => $p?->hora_cierre?->format('H:i'),
                'auditor' => $p?->auditor?->name,
                'esperado' => $tot['esperado'],
                'cobrado' => $tot['cobrado'],
                'eficacia' => $tot['eficacia'],
                'filas' => PlanillaCalc::filas($cuotas, $f, $modalidad),
            ];
        })->all();

        // Cobradores disponibles (solo para el admin que elige).
        $cobradores = $this->esAdmin()
            ? User::whereIn('id', Zona::whereNotNull('cobrador_id')->distinct()->pluck('cobrador_id'))->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('livewire.cobranza.planilla', [
            'cobrador' => $cobrador,
            'grupos' => $grupos,
            'esAdmin' => $this->esAdmin(),
            'puedeAuditar' => \App\Support\Permisos::puede(auth()->user()?->rol, 'auditar_cobranza'),
            'cobradores' => $cobradores,
            'recibosPorCuota' => $recibosPorCuota,
        ]);
    }
}
