<?php
namespace App\Http\Controllers\Api;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

$email = 'admin@sakurafest.com';
$password = 'password';

$usuario = Usuario::where('email', $email)->first();

if (!$usuario) {
    echo "NO USER FOUND CON ESTE EMAIL: " . $email . "\n";
} else {
    echo "USER FOUND ID: " . $usuario->id . "\n";
    if (Hash::check($password, $usuario->password)) {
        echo "HASH MATCHES\n";
    } else {
        echo "HASH NO MATCH\n";
    }
}
