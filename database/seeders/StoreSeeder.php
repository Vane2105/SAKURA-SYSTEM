<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $roleId = Role::where('nombre', 'Emprendedor')->first()->id;

        $stores = [
            'INSPIRE STUDIOS', 'SPACE GIRL', 'PANCHI CROCHET', 'FENRIR GEEK',
            'ESTRELLITA SHOP', 'NATURAL GROWTH', 'RYSHOP', 'ARIANITY',
            'HANBEAR SHOP', 'RUCHY SHOP', 'ANGELS BRACELETS', 'MALICE',
            'DARS TEJIDOS', 'ANDREACCESORIOS', 'NIKKI STORE', 'BELLESTORE',
            'PINCELANASHOP', 'CARISTORE', 'BAMSHOP', 'YURISHOP', 'SENOR DULZON'
        ];

        $i = 1;
        foreach ($stores as $storeName) {
            Usuario::create([
                'role_id' => $roleId,
                'nombre' => $storeName,
                'nombre_tienda' => $storeName,
                'ci' => 'V-' . (8000000 + $i),
                'email' => strtolower(str_replace(' ', '.', $storeName)) . $i . '@sakura.com',
                'password' => Hash::make('password'),
                'direccion' => 'Pendiente'
            ]);
            $i++;
        }
    }
}
