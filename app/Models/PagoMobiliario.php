<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoMobiliario extends Model
{
    protected $table = 'pagos_mobiliario';
    protected $primaryKey = 'id_pago_mobiliario';

    protected $fillable = [
        'alquiler_id',
        'cantidad',
        'tasa_bcv',
        'fecha',
        'numero_referencia',
    ];

    protected $casts = [
        'cantidad'  => 'float',
        'tasa_bcv'  => 'float',
        'fecha'     => 'date',
    ];

    public function alquiler()
    {
        return $this->belongsTo(AlquilerMobiliario::class, 'alquiler_id', 'id_alquiler');
    }
}
