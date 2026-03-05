<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleStand extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_detalle_stand';

    protected $fillable = [
        'stands_id',
        'reservacion_id',
        'descripcion',
    ];

    public function stand()
    {
        return $this->belongsTo(Stand::class, 'stands_id', 'id_stands');
    }

    public function reservacion()
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id', 'id_reservacion');
    }
}
