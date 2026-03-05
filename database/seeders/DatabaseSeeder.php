<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear roles
        $adminRole = Role::create(['nombre' => 'Administrador']);
        $emprendedorRole = Role::create(['nombre' => 'Emprendedor']);

        // Crear usuario admin
        Usuario::create([
            'role_id' => $adminRole->id,
            'nombre' => 'Admin Sakura',
            'apellido' => 'Fest',
            'ci' => 'V-00000000',
            'email' => 'admin@sakurafest.com',
            'password' => Hash::make('password'),
            'direccion' => 'Sede Principal'
        ]);
    }
}
