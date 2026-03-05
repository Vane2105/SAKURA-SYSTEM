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
        'tipo',
        'cantidad',
        'tasa_bcv',
        'monto_bs',
        'fecha',
        'numero_referencia',
        'status',
        'conciliado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad' => 'float',
        'tasa_bcv' => 'float',
        'monto_bs' => 'float',
        'conciliado' => 'boolean',
    ];

    public function reservacion()
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id', 'id_reservacion');
    }
}
