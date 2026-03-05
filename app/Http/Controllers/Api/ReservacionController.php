<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservacion;
use App\Models\Stand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservacionController extends Controller
{
    public function index(Request $request)
    {
        $eventoId = $request->query('evento_id');
        
        if (!$eventoId) {
            return response()->json([]);
        }

        $query = Reservacion::with(['usuario', 'usuario2', 'detalles.stand', 'pagos', 'reembolsos', 'mobiliarios'])
            ->where('evento_id', $eventoId)
            ->orderBy('created_at', 'desc');

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuarios_id' => 'required|exists:usuarios,id',
            'evento_id' => 'required|exists:eventos,id_eventos',
            'descripcion' => 'nullable|string|max:45',
            'stands' => 'required|array|min:1',
            'stands.*' => 'exists:stands,id_stands',
            'monto_mobiliario' => 'nullable|numeric|min:0',
            'subido_redes' => 'nullable|boolean',
            'monto_pago' => 'nullable|numeric|min:0',
            'referencia_pago' => 'nullable|string|max:100',
            'tasa_bcv' => 'nullable|numeric|min:0',
            'monto_bs' => 'nullable|numeric|min:0',
            'fecha_pago' => 'nullable|date',
            'usuario_2_id' => 'nullable|exists:usuarios,id'
        ]);

        return DB::transaction(function () use ($validated) {
            $reservacion = Reservacion::create([
                'usuarios_id' => $validated['usuarios_id'],
                'evento_id' => $validated['evento_id'],
                'descripcion' => $validated['descripcion'] ?? null,
                'monto_mobiliario' => $validated['monto_mobiliario'] ?? 0,
                'subido_redes' => $validated['subido_redes'] ?? false,
                'usuario_2_id' => $validated['usuario_2_id'] ?? null,
                'status' => 'pendiente'
            ]);

            foreach ($validated['stands'] as $standId) {
                $reservacion->detalles()->create([
                    'stands_id' => $standId
                ]);
            }

            // Evaluar estado inicial basado en pago (AQUI SOLO STAND)
            $totalStands = Stand::whereIn('id_stands', $validated['stands'])->sum('precio');
            $montoPagado = $validated['monto_pago'] ?? 0;

            // Registrar pago inicial como 'stand'
            if ($montoPagado > 0) {
                // Regla de Oro aplicada al Stand
                if ($montoPagado > $totalStands && $totalStands > 0) {
                    throw new \Exception("El monto del pago inicial ($$montoPagado) no puede exceder el precio de los stands ($$totalStands).");
                }

                $reservacion->pagos()->create([
                    'tipo' => 'stand',
                    'cantidad' => $montoPagado,
                    'monto_bs' => $validated['monto_bs'] ?? null,
                    'tasa_bcv' => $validated['tasa_bcv'] ?? null,
                    'numero_referencia' => $validated['referencia_pago'] ?? null,
                    'fecha' => $validated['fecha_pago'] ?? now()->toDateString(),
                    'status' => 'aprobado'
                ]);
            }

            // Actualizar estado basado en el balance del Stand
            $totalAbonadoStand = $reservacion->pagos()->where('tipo', 'stand')->sum('cantidad');
            $nuevoStatus = 'pendiente';
            if ($totalAbonadoStand >= $totalStands && $totalStands > 0) {
                $nuevoStatus = 'confirmada';
                Stand::whereIn('id_stands', $validated['stands'])->update(['status' => 'ocupado']);
            } elseif ($totalAbonadoStand > 0) {
                $nuevoStatus = 'abonada';
                Stand::whereIn('id_stands', $validated['stands'])->update(['status' => 'reservado']);
            } else {
                Stand::whereIn('id_stands', $validated['stands'])->update(['status' => 'reservado']);
            }
            
            $reservacion->update(['status' => $nuevoStatus]);

            return response()->json($reservacion->load(['usuario', 'detalles.stand', 'pagos', 'mobiliarios']), 201);
        });
    }

    public function show(Reservacion $reservacion)
    {
        return response()->json($reservacion->load(['usuario', 'usuario2', 'evento', 'detalles.stand', 'pagos', 'reembolsos', 'mobiliarios']));
    }

    public function updateStatus(Request $request, Reservacion $reservacion)
    {
        $validated = $request->validate([
            'status' => 'required|in:pendiente,confirmada,cancelada,abonada'
        ]);

        DB::transaction(function () use ($reservacion, $validated) {
            $reservacion->update(['status' => $validated['status']]);

            if ($validated['status'] === 'cancelada') {
                $standIds = $reservacion->detalles()->pluck('stands_id');
                Stand::whereIn('id_stands', $standIds)->update(['status' => 'disponible']);
            } elseif ($validated['status'] === 'confirmada' || $validated['status'] === 'abonada') {
                $standIds = $reservacion->detalles()->pluck('stands_id');
                Stand::whereIn('id_stands', $standIds)->update(['status' => ($validated['status'] === 'confirmada' ? 'ocupado' : 'reservado')]);
            }
        });

        return response()->json($reservacion->fresh(['detalles.stand']));
    }

    public function registrarPagoMobiliario(Request $request, Reservacion $reservacion)
    {
        $validated = $request->validate([
            'cantidad' => 'required|numeric|min:0.01',
            'monto_bs' => 'nullable|numeric|min:0',
            'tasa_bcv' => 'nullable|numeric|min:0.01',
            'numero_referencia' => 'nullable|string|max:100',
            'fecha' => 'nullable|date',
        ]);

        return DB::transaction(function () use ($validated, $reservacion) {
            // Bloquear la fila para evitar condiciones de carrera
            $reservacion = Reservacion::lockForUpdate()->find($reservacion->id_reservacion);

            // Crear el pago
            $pago = $reservacion->pagos()->create([
                'tipo' => 'mobiliario',
                'cantidad' => $validated['cantidad'],
                'monto_bs' => $validated['monto_bs'] ?? null,
                'tasa_bcv' => $validated['tasa_bcv'] ?? null,
                'numero_referencia' => $validated['numero_referencia'] ?? null,
                'fecha' => $validated['fecha'] ?? now()->toDateString(),
                'status' => 'aprobado'
            ]);

            // Incrementar el monto total de mobiliario asignado (Cero deuda)
            $reservacion->increment('monto_mobiliario', $validated['cantidad']);

            return response()->json([
                'message' => 'Mobiliario pagado y asignado correctamente.',
                'reservacion' => $reservacion->fresh(['pagos'])
            ]);
        });
    }

    public function update(Request $request, Reservacion $reservacion)
    {
        $validated = $request->validate([
            'subido_redes' => 'nullable|boolean',
            'descripcion' => 'nullable|string|max:45',
        ]);

        $reservacion->update($validated);

        return response()->json($reservacion->fresh(['usuario', 'detalles.stand', 'pagos']));
    }

    public function destroy(Reservacion $reservacion)
    {
        // Liberar stands antes de borrar lógicamente si no está cancelada
        if ($reservacion->status !== 'cancelada') {
            $standIds = $reservacion->detalles()->pluck('stands_id');
            Stand::whereIn('id_stands', $standIds)->update(['status' => 'disponible']);
        }

        $reservacion->delete();
        return response()->json(['message' => 'Reservación eliminada correctamente.']);
    }
}
