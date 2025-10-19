<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expediente extends Model
{
    use HasFactory;

    protected $fillable = ['no', 'paciente', 'estado', 'apertura', 'carrera', 'turno'];

    protected $casts = [
        'apertura' => 'date',
    ];
}
