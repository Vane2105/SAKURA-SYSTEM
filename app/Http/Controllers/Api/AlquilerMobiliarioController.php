<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlquilerMobiliario;
use App\Models\PagoMobiliario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlquilerMobiliarioController extends Controller
{
    public function index(Request $request)
    {
        $query = AlquilerMobiliario::with(['reservacion.usuario', 'reservacion.usuario2', 'pagos']);

        if ($request->has('reservacion_id')) {
            $query->where('reservacion_id', $request->reservacion_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reservacion_id' => 'required|exists:reservacions,id_reservacion',
            'descripcion'    => 'nullable|string|max:255',
            'precio_usd'     => 'required|numeric|min:0.01',
            'monto_bs'       => 'nullable|numeric|min:0',
            'tasa_bcv'       => 'nullable|numeric|min:0',
            'tasa_fuente'    => 'nullable|string|max:100',
            'fecha'          => 'nullable|date',
        ]);

        $validated['fecha'] = $validated['fecha'] ?? now()->toDateString();

        $alquiler = AlquilerMobiliario::create($validated);

        return response()->json($alquiler->load(['reservacion.usuario', 'reservacion.usuario2', 'pagos']), 201);
    }

    public function update(Request $request, AlquilerMobiliario $alquilerMobiliario)
    {
        $validated = $request->validate([
            'descripcion' => 'nullable|string|max:255',
            'precio_usd'  => 'required|numeric|min:0',
            'monto_bs'    => 'nullable|numeric|min:0',
            'tasa_bcv'    => 'nullable|numeric|min:0',
            'tasa_fuente' => 'nullable|string|max:100',
            'fecha'       => 'nullable|date',
            'status'      => 'in:pendiente,pagado,cancelado',
        ]);

        $alquilerMobiliario->update($validated);

        return response()->json($alquilerMobiliario->load(['reservacion.usuario', 'reservacion.usuario2', 'pagos']));
    }

    public function storePago(Request $request, AlquilerMobiliario $alquilerMobiliario)
    {
        $validated = $request->validate([
            'cantidad'          => 'required|numeric|min:0.01',
            'tasa_bcv'          => 'nullable|numeric|min:0',
            'fecha'             => 'nullable|date',
            'numero_referencia' => 'nullable|string|max:255',
        ]);

        $validated['alquiler_id'] = $alquilerMobiliario->id_alquiler;
        $validated['fecha'] = $validated['fecha'] ?? now()->toDateString();

        return DB::transaction(function () use ($validated, $alquilerMobiliario) {
            $pago = PagoMobiliario::create($validated);

            // Verificar si el alquiler está completamente pagado
            $totalPagado = $alquilerMobiliario->pagos()->sum('cantidad');
            if ($totalPagado >= $alquilerMobiliario->precio_usd) {
                $alquilerMobiliario->update(['status' => 'pagado']);
            }

            return response()->json($pago, 201);
        });
    }

    public function destroy(AlquilerMobiliario $alquilerMobiliario)
    {
        $alquilerMobiliario->delete();
        return response()->json(null, 204);
    }
}
