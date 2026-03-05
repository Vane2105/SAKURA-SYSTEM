<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reembolso;
use App\Models\Reservacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReembolsoController extends Controller
{
    public function index()
    {
        return response()->json(Reembolso::with('reservacion.usuario')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reservacion_id' => 'required|exists:reservacions,id_reservacion',
            'cantidad' => 'required|numeric|min:0.01',
            'razon' => 'required|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $reembolso = Reembolso::create([
                'reservacion_id' => $validated['reservacion_id'],
                'cantidad' => $validated['cantidad'],
                'razon' => $validated['razon'],
                'status' => 'solicitado'
            ]);

            // Cancelar la reservación y liberar stands
            $reservacion = Reservacion::find($validated['reservacion_id']);
            $reservacion->update(['status' => 'cancelada']);
            
            $standIds = $reservacion->detalles()->pluck('stands_id');
            \App\Models\Stand::whereIn('id_stands', $standIds)->update(['status' => 'disponible']);

            return response()->json($reembolso, 201);
        });
    }

    public function procesar(Reembolso $reembolso)
    {
        $reembolso->update(['status' => 'procesado']);
        return response()->json($reembolso);
    }
}
