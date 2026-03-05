<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_pagos';

    protected $fillable = [
        'reservacion_id',
        'cantidad',
        'tasa_bcv',
        'fecha',
        'numero_referencia',
        'status',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad' => 'float',
        'tasa_bcv' => 'float',
    ];

    public function reservacion()
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id', 'id_reservacion');
    }
}
