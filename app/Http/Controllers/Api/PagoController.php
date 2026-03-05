<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Reservacion;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reservacion_id' => 'required|exists:reservacions,id_reservacion',
            'tipo' => 'required|in:stand,mobiliario',
            'cantidad' => 'required|numeric|min:0.01',
            'monto_bs' => 'nullable|numeric|min:0',
            'numero_referencia' => 'nullable|string|max:255',
            'tasa_bcv' => 'nullable|numeric|min:0',
            'fecha' => 'nullable|date',
        ]);

        $reservacion = Reservacion::with(['pagos', 'detalles.stand', 'mobiliarios'])->find($validated['reservacion_id']);
        
        // Calcular deuda específica por tipo
        if ($validated['tipo'] === 'stand') {
            $totalDeuda = $reservacion->detalles->sum(function($d) { return floatval($d->stand->precio); });
            $totalPagadoAntes = $reservacion->pagos()->where('tipo', 'stand')->sum('cantidad');
        } else {
            $totalDeuda = floatval($reservacion->monto_mobiliario);
            $totalPagadoAntes = $reservacion->pagos()->where('tipo', 'mobiliario')->sum('cantidad');
        }
        
        $deudaRestante = round($totalDeuda - $totalPagadoAntes, 2);

        // Regla de Oro: El pago no puede exceder la deuda restante (específica del tipo)
        if (round($validated['cantidad'], 2) > $deudaRestante) {
            return response()->json([
                'message' => "El pago ($" . $validated['cantidad'] . ") excede la deuda restante de " . $validated['tipo'] . " ($$deudaRestante)."
            ], 422);
        }

        $validated['status'] = 'aprobado';
        $pago = Pago::create($validated);

        // Sincronizar estado global de la reservación (opcional, pero útil)
        $totalPagadoDespues = $totalPagadoAntes + $validated['cantidad'];
        
        // El estado global de la reservación ahora es más complejo, 
        // pero mantengamos una lógica básica para el status general.
        // El frontend mostrará estatus duales basados en los cálculos.
        
        // Lógica de stands (Si el stand está solvente, marcar como ocupado)
        if ($validated['tipo'] === 'stand' && $totalPagadoDespues >= $totalDeuda && $totalDeuda > 0) {
            $standIds = $reservacion->detalles()->pluck('stands_id');
            \App\Models\Stand::whereIn('id_stands', $standIds)->update(['status' => 'ocupado']);
        }

        return response()->json($pago, 201);
    }

    /**
     * Toggle 'conciliado' state for bank reconciliation.
     * Integrity check: cannot conciliate if monto_bs or tasa_bcv are missing/zero.
     */
    public function toggleConciliacion($id)
    {
        $pago = Pago::where('id_pagos', $id)->firstOrFail();

        // If trying to CONCILIATE (not de-conciliate), validate completeness
        if (!$pago->conciliado) {
            $errors = [];
            if (empty($pago->tasa_bcv) || $pago->tasa_bcv <= 0) {
                $errors[] = 'La Tasa BCV debe ser mayor a 0.';
            }
            if (empty($pago->monto_bs) || $pago->monto_bs <= 0) {
                $errors[] = 'El Monto Bs debe ser mayor a 0.';
            }
            if (!empty($errors)) {
                return response()->json([
                    'message' => 'No se puede conciliar un pago con datos incompletos.',
                    'errors' => $errors
                ], 422);
            }
        }

        $pago->update(['conciliado' => !$pago->conciliado]);
        return response()->json([
            'id' => $pago->id_pagos,
            'conciliado' => $pago->conciliado,
            'message' => $pago->conciliado ? 'Pago marcado como conciliado' : 'Pago marcado como pendiente'
        ]);
    }
}
