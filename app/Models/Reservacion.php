<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservacion extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_reservacion';

    protected $fillable = [
        'usuarios_id',
        'fecha_reserva',
        'status',
        'descripcion',
        'mobiliario_precio',
        'mobiliario_pagado',
        'subido_redes',
        'usuario_2_id',
    ];

    protected $casts = [
        'fecha_reserva' => 'datetime',
        'mobiliario_precio' => 'float',
        'mobiliario_pagado' => 'boolean',
        'subido_redes' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuarios_id');
    }

    public function usuario2()
    {
        return $this->belongsTo(Usuario::class, 'usuario_2_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleStand::class, 'reservacion_id', 'id_reservacion');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'reservacion_id', 'id_reservacion');
    }

    public function reembolsos()
    {
        return $this->hasMany(Reembolso::class, 'reservacion_id', 'id_reservacion');
    }
}
