<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_eventos';

    protected $fillable = [
        'created_by',
        'nombre',
        'descripcion',
        'direccion',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }

    public function stands()
    {
        return $this->hasMany(Stand::class, 'eventos_id', 'id_eventos');
    }
}
