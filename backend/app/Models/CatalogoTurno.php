<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoTurno extends Model
{
    use HasFactory;

    protected $table = 'catalogo_turnos';

    protected $fillable = ['clave', 'nombre', 'estado'];
}
