<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Local extends Model
{
    protected $table = 'locales';

    protected $fillable = ['nombre', 'direccion', 'telefono', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function usuarios(): HasMany { return $this->hasMany(User::class); }
    public function stock(): HasMany { return $this->hasMany(StockLocal::class); }
    public function ventas(): HasMany { return $this->hasMany(Venta::class); }
    public function compras(): HasMany { return $this->hasMany(Compra::class); }
}
