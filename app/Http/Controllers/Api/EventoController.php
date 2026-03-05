<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    public function index()
    {
        $eventos = Evento::with(['creador', 'gastos', 'stands.detalles.reservacion.pagos'])->get();
        
        $eventos->each(function($evento) {
            // Calcular ingresos (pagos aprobados de reservaciones vinculadas a este evento)
            $totalIngresos = 0;
            foreach ($evento->stands as $stand) {
                foreach ($stand->detalles as $detalle) {
                    if ($detalle->reservacion) {
                        $totalIngresos += $detalle->reservacion->pagos()
                            ->where('status', 'aprobado')
                            ->sum('cantidad');
                    }
                }
            }
            
            $evento->total_ingresos = $totalIngresos;
            $evento->total_gastos = $evento->gastos->sum('monto_usd');
            $evento->rentabilidad = $totalIngresos - $evento->total_gastos;
        });

        return response()->json($eventos);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'direccion' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'base_precio_stand' => 'nullable|numeric|min:0',
        ]);

        $validated['created_by'] = $request->user()->id;

        $evento = Evento::create($validated);

        // Auto-generar 20 stands para el nuevo evento con el precio base
        $precioBase = $request->base_precio_stand ?? 0;
        for ($i = 1; $i <= 20; $i++) {
            $evento->stands()->create([
                'name' => "Stand $i",
                'precio' => $precioBase,
                'status' => 'disponible'
            ]);
        }

        return response()->json($evento->load('stands'), 201);
    }

    public function show(Evento $evento)
    {
        return response()->json($evento->load(['creador', 'stands']));
    }

    public function update(Request $request, Evento $evento)
    {
        $validated = $request->validate([
            'nombre' => 'string|max:255',
            'descripcion' => 'nullable|string',
            'direccion' => 'string|max:255',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'base_precio_stand' => 'nullable|numeric|min:0',
        ]);

        $previousBasePrice = $evento->base_precio_stand;
        $evento->update($validated);

        // Si el precio base cambió, actualizar todos los stands del evento
        if (isset($validated['base_precio_stand']) && $validated['base_precio_stand'] != $previousBasePrice) {
            $evento->stands()->update(['precio' => $validated['base_precio_stand']]);
        }
        return response()->json($evento);
    }

    public function destroy(Evento $evento)
    {
        $evento->delete();
        return response()->json(null, 204);
    }
}
