<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TasaBcv extends Model
{
    protected $table = 'tasas_bcv';

    protected $fillable = [
        'fecha',
        'tasa',
        'fuente',
    ];

    protected $casts = [
        'fecha' => 'date',
        'tasa'  => 'float',
    ];
}
