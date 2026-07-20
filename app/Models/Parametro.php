<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parametro extends Model
{
    protected $table = 'parametros';
    protected $primaryKey = 'clave';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor'];

    /** Lee un parámetro con default (tolera que la tabla no exista todavía). */
    public static function get(string $clave, mixed $default = null): mixed
    {
        try {
            $v = static::find($clave)?->valor;
            return $v ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /** Lee un parámetro numérico (float). */
    public static function num(string $clave, float $default = 0): float
    {
        return (float) static::get($clave, $default);
    }

    public static function set(string $clave, mixed $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => (string) $valor]);
    }
}
