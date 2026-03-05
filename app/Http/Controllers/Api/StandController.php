<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stand;
use Illuminate\Http\Request;

class StandController extends Controller
{
    public function index(Request $request)
    {
        $query = Stand::with('evento');

        if ($request->has('evento_id')) {
            $query->where('eventos_id', $request->evento_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'eventos_id' => 'required|exists:eventos,id_eventos',
            'name' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'status' => 'in:disponible,reservado,ocupado,mantenimiento',
        ]);

        $stand = Stand::create($validated);
        return response()->json($stand, 201);
    }

    public function show(Stand $stand)
    {
        return response()->json($stand->load('evento'));
    }

    public function update(Request $request, Stand $stand)
    {
        $validated = $request->validate([
            'eventos_id' => 'exists:eventos,id_eventos',
            'name' => 'string|max:255',
            'precio' => 'numeric|min:0',
            'status' => 'in:disponible,reservado,ocupado,mantenimiento',
        ]);

        $stand->update($validated);
        return response()->json($stand);
    }

    public function destroy(Stand $stand)
    {
        $stand->delete();
        return response()->json(null, 204);
    }
}
