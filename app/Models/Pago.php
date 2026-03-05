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
        'numero_referencia',
        'status',
    ];

    public function reservacion()
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id', 'id_reservacion');
    }
}
