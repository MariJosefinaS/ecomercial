<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['clave', 'nombre', 'variante', 'es_sistema'];

    protected $casts = ['es_sistema' => 'boolean'];
}
