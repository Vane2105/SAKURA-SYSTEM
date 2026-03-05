<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    public function index(Request $request)
    {
        $query = Gasto::query();
        if ($request->has('id_eventos')) {
            $query->where('id_eventos', $request->query('id_eventos'));
        }
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_eventos' => 'required|exists:eventos,id_eventos',
            'concepto' => 'required|string|max:255',
            'categoria' => 'nullable|string|max:100',
            'monto_usd' => 'required|numeric|min:0',
            'monto_bs' => 'nullable|numeric|min:0',
            'tasa_bcv' => 'required|numeric|min:0',
            'fecha' => 'required|date',
            'descripcion' => 'nullable|string',
        ]);

        $gasto = Gasto::create($validated);
        return response()->json($gasto, 201);
    }

    public function update(Request $request, Gasto $gasto)
    {
        $validated = $request->validate([
            'concepto' => 'required|string|max:255',
            'categoria' => 'nullable|string|max:100',
            'monto_usd' => 'required|numeric|min:0',
            'monto_bs' => 'nullable|numeric|min:0',
            'tasa_bcv' => 'required|numeric|min:0',
            'fecha' => 'required|date',
            'descripcion' => 'nullable|string',
        ]);

        $gasto->update($validated);
        return response()->json($gasto);
    }

    public function destroy(Gasto $gasto)
    {
        $gasto->delete();
        return response()->json(null, 204);
    }
}
