<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stand extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_stands';

    protected $fillable = [
        'eventos_id',
        'name',
        'precio',
        'status',
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'eventos_id', 'id_eventos');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleStand::class, 'stands_id', 'id_stands');
    }
}
