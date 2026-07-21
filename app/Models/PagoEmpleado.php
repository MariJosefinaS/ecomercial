<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pago del tesorero a un empleado (genera egreso en caja + recibo firmable). Ver App\Support\CuentaEmpleado. */
class PagoEmpleado extends Model
{
    protected $table = 'pagos_empleado';

    protected $fillable = [
        'empleado_id', 'monto', 'medio', 'comprobante', 'banco', 'nota',
        'saldo_antes', 'saldo_despues', 'fecha', 'firmado', 'firmado_at', 'registrado_por',
    ];

    protected $casts = [
        'monto' => 'decimal:2', 'saldo_antes' => 'decimal:2', 'saldo_despues' => 'decimal:2',
        'fecha' => 'datetime', 'firmado' => 'boolean', 'firmado_at' => 'datetime',
    ];

    public const MEDIOS = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia'];

    public function empleado(): BelongsTo { return $this->belongsTo(User::class, 'empleado_id'); }
    public function tesorero(): BelongsTo { return $this->belongsTo(User::class, 'registrado_por'); }

    public function medioLabel(): string { return self::MEDIOS[$this->medio] ?? ucfirst($this->medio); }
    public function numero(): string { return 'PAG-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }
}
