<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function index()
    {
        return response()->json(Usuario::with(['role', 'telefonos'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'nombre' => 'required|string|max:50',
            'apellido' => 'nullable|string|max:50',
            'ci' => 'nullable|string|max:15|unique:usuarios,ci',
            'email' => 'required|email|unique:usuarios,email',
            'password' => 'required|string|min:6',
            'direccion' => 'nullable|string|max:255',
            'telefonos' => 'nullable|array',
            'telefonos.*' => 'string|max:25'
        ]);

        $validated['password'] = Hash::make($validated['password']);
        
        $usuario = Usuario::create($validated);

        if (!empty($validated['telefonos'])) {
            foreach ($validated['telefonos'] as $tel) {
                $usuario->telefonos()->create(['numeros_telefonos' => $tel]);
            }
        }

        return response()->json($usuario->load(['role', 'telefonos']), 201);
    }

    public function show(Usuario $usuario)
    {
        return response()->json($usuario->load(['role', 'telefonos', 'reservacions']));
    }

    public function update(Request $request, Usuario $usuario)
    {
        $validated = $request->validate([
            'role_id' => 'exists:roles,id',
            'nombre' => 'string|max:50',
            'apellido' => 'nullable|string|max:50',
            'ci' => 'nullable|string|max:15|unique:usuarios,ci,' . $usuario->id,
            'email' => 'email|unique:usuarios,email,' . $usuario->id,
            'password' => 'nullable|string|min:6',
            'direccion' => 'nullable|string|max:255',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $usuario->update($validated);

        return response()->json($usuario->load(['role', 'telefonos']));
    }

    public function destroy(Usuario $usuario)
    {
        $usuario->delete();
        return response()->json(null, 204);
    }
}
