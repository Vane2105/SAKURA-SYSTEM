<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reembolso extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_reembolsos';

    protected $fillable = [
        'reservacion_id',
        'cantidad',
        'razon',
        'status',
    ];

    public function reservacion()
    {
        return $this->belongsTo(Reservacion::class, 'reservacion_id', 'id_reservacion');
    }
}
