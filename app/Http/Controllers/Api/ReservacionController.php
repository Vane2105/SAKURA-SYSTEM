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
            'stands.*' => 'exists:stands,id_stands',
            'mobiliario_precio' => 'nullable|numeric|min:0',
            'mobiliario_pagado' => 'nullable|boolean',
            'subido_redes' => 'nullable|boolean',
            'monto_pago' => 'nullable|numeric|min:0',
            'referencia_pago' => 'nullable|string|max:100',
            'tasa_bcv' => 'nullable|numeric|min:0'
        ]);

        return DB::transaction(function () use ($validated) {
            $reservacion = Reservacion::create([
                'usuarios_id' => $validated['usuarios_id'],
                'descripcion' => $validated['descripcion'] ?? null,
                'mobiliario_precio' => $validated['mobiliario_precio'] ?? null,
                'mobiliario_pagado' => $validated['mobiliario_pagado'] ?? false,
                'subido_redes' => $validated['subido_redes'] ?? false,
                'status' => 'pendiente'
            ]);

            foreach ($validated['stands'] as $standId) {
                $reservacion->detalles()->create([
                    'stands_id' => $standId
                ]);
                
                // Marcar stand como reservado
                Stand::where('id_stands', $standId)->update(['status' => 'reservado']);
            }

            // Registrar pago inicial si existe
            if (isset($validated['monto_pago']) && $validated['monto_pago'] > 0) {
                $reservacion->pagos()->create([
                    'cantidad' => $validated['monto_pago'],
                    'numero_referencia' => $validated['referencia_pago'] ?? null,
                    'tasa_bcv' => $validated['tasa_bcv'] ?? null,
                    'status' => 'aprobado'
                ]);
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

    public function update(Request $request, Reservacion $reservacion)
    {
        $validated = $request->validate([
            'mobiliario_precio' => 'nullable|numeric|min:0',
            'mobiliario_pagado' => 'nullable|boolean',
            'subido_redes' => 'nullable|boolean',
            'descripcion' => 'nullable|string|max:45',
        ]);

        $reservacion->update($validated);

        return response()->json($reservacion->fresh(['usuario', 'detalles.stand', 'pagos']));
    }
}
