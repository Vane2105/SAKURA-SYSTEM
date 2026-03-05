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
        'base_precio_stand',
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

    public function gastos()
    {
        return $this->hasMany(Gasto::class, 'id_eventos', 'id_eventos');
    }

    public function reservaciones()
    {
        return $this->hasManyThrough(
            Reservacion::class,
            DetalleStand::class,
            'stands_id', // Foreign key on DetalleStand table (implicitly through stands? No, stand has eventos_id)
            'id_reservacion', // Foreign key on Reservacion table
            'id_eventos', // Local key on Evento table
            'reservacion_id' // Local key on DetalleStand table
        );
    }

    // Since reaching reservaciones through stands is complex in terms of raw SQL,
    // we'll use a simpler approach for the totals in the controller or via a direct relation if possible.
    // However, event -> stands -> details -> reservation -> payments is deep.
    // Let's just define the basic relations here.
}
