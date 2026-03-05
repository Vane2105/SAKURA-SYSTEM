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
            'cantidad' => 'required|numeric|min:0.01',
            'numero_referencia' => 'nullable|string|max:255',
            'tasa_bcv' => 'nullable|numeric|min:0',
            'fecha' => 'nullable|date',
        ]);

        $validated['status'] = 'aprobado'; // Auto-aprobado por el admin
        
        $pago = Pago::create($validated);

        // Verificar si la deuda total está cubierta
        $reservacion = Reservacion::with(['pagos', 'detalles.stand', 'mobiliarios'])->find($validated['reservacion_id']);
        
        $totalPagado = $reservacion->pagos()->sum('cantidad');
        $totalStands = $reservacion->detalles->sum(function($d) { return floatval($d->stand->precio); });
        $totalMobiliario = $reservacion->mobiliarios->sum(function($m) { return floatval($m->cantidad) * floatval($m->precio_unitario_usd); });
        $totalDeuda = $totalStands + $totalMobiliario;

        if ($totalPagado >= $totalDeuda && $reservacion->status === 'pendiente') {
            $reservacion->update(['status' => 'confirmada']);
            $standIds = $reservacion->detalles()->pluck('stands_id');
            \App\Models\Stand::whereIn('id_stands', $standIds)->update(['status' => 'ocupado']);
        }

        return response()->json($pago, 201);
    }
}
