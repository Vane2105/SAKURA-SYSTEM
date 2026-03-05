<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gasto extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_gastos';

    protected $fillable = [
        'id_eventos',
        'concepto',
        'categoria',
        'monto_usd',
        'monto_bs',
        'tasa_bcv',
        'fecha',
        'descripcion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_usd' => 'float',
        'monto_bs' => 'float',
        'tasa_bcv' => 'float',
    ];

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'id_eventos');
    }
}
