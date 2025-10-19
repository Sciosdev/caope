<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expediente extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero','paciente','estado','apertura','carrera','turno','alerta'
    ];

    protected $casts = [
        'apertura' => 'date',
        'alerta'   => 'bool',
    ];
}
