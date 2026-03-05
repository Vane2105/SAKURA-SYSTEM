<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'id_reservacion';

    protected $fillable = [
        'usuarios_id',
        'fecha_reserva',
        'status',
        'descripcion',
        'subido_redes',
        'usuario_2_id',
        'evento_id',
        'monto_mobiliario',
    ];

    protected $casts = [
        'fecha_reserva' => 'datetime',
        'subido_redes' => 'boolean',
        'monto_mobiliario' => 'float',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuarios_id');
    }

    public function usuario2()
    {
        return $this->belongsTo(Usuario::class, 'usuario_2_id');
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function mobiliarios()
    {
        return $this->hasMany(MobiliarioReservacion::class, 'reservacion_id', 'id_reservacion');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleStand::class, 'reservacion_id', 'id_reservacion');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'reservacion_id', 'id_reservacion');
    }

    public function reembolsos()
    {
        return $this->hasMany(Reembolso::class, 'reservacion_id', 'id_reservacion');
    }
}
