<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'nombre',
        'apellido',
        'ci',
        'email',
        'password',
        'direccion'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function telefonos()
    {
        return $this->hasMany(Telefono::class, 'usuarios_id');
    }

    public function eventosCreados()
    {
        return $this->hasMany(Evento::class, 'created_by');
    }

    public function reservacions()
    {
        return $this->hasMany(Reservacion::class, 'usuarios_id');
    }
}
