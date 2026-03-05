<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobiliarioReservacion extends Model
{
    protected $fillable = [
        'reservacion_id',
        'descripcion',
        'cantidad',
        'precio_unitario_usd',
    ];

    public function reservacion()
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id', 'id_reservacion');
    }
}
