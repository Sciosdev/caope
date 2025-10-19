<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoCarrera extends Model
{
    use HasFactory;

    protected $table = 'catalogo_carreras';

    protected $fillable = ['clave', 'nombre', 'estado'];
}
