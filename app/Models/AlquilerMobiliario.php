<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlquilerMobiliario extends Model
{
    protected $table = 'alquileres_mobiliario';
    protected $primaryKey = 'id_alquiler';

    protected $fillable = [
        'reservacion_id',
        'descripcion',
        'precio_usd',
        'monto_bs',
        'tasa_bcv',
        'tasa_fuente',
        'fecha',
        'status',
    ];

    protected $casts = [
        'precio_usd' => 'float',
        'monto_bs'   => 'float',
        'tasa_bcv'   => 'float',
        'fecha'      => 'date',
    ];

    public function reservacion()
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id', 'id_reservacion');
    }

    public function pagos()
    {
        return $this->hasMany(PagoMobiliario::class, 'alquiler_id', 'id_alquiler');
    }
}
