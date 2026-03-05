<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservacion;
use App\Models\Stand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservacionController extends Controller
{
    public function index()
    {
        return response()->json(Reservacion::with(['usuario', 'detalles.stand', 'pagos', 'reembolsos'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuarios_id' => 'required|exists:usuarios,id',
            'descripcion' => 'nullable|string|max:45',
            'stands' => 'required|array|min:1',
            'stands.*' => 'exists:stands,id_stands'
        ]);

        return DB::transaction(function () use ($validated) {
            $reservacion = Reservacion::create([
                'usuarios_id' => $validated['usuarios_id'],
                'descripcion' => $validated['descripcion'] ?? null,
                'status' => 'pendiente'
            ]);

            foreach ($validated['stands'] as $standId) {
                $reservacion->detalles()->create([
                    'stands_id' => $standId
                ]);
                
                // Marcar stand como reservado
                Stand::where('id_stands', $standId)->update(['status' => 'reservado']);
            }

            return response()->json($reservacion->load(['usuario', 'detalles.stand']), 201);
        });
    }

    public function show(Reservacion $reservacion)
    {
        return response()->json($reservacion->load(['usuario', 'detalles.stand', 'pagos', 'reembolsos']));
    }

    public function updateStatus(Request $request, Reservacion $reservacion)
    {
        $validated = $request->validate([
            'status' => 'required|in:pendiente,confirmada,cancelada'
        ]);

        DB::transaction(function () use ($request, $reservacion, $validated) {
            $reservacion->update(['status' => $validated['status']]);

            if ($validated['status'] === 'cancelada') {
                // Liberar stands
                $standIds = $reservacion->detalles()->pluck('stands_id');
                Stand::whereIn('id_stands', $standIds)->update(['status' => 'disponible']);
            } elseif ($validated['status'] === 'confirmada') {
                $standIds = $reservacion->detalles()->pluck('stands_id');
                Stand::whereIn('id_stands', $standIds)->update(['status' => 'ocupado']);
            }
        });

        return response()->json($reservacion->fresh(['detalles.stand']));
    }
}
