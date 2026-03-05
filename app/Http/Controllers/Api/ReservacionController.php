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
            'mobiliarios' => 'nullable|array',
            'mobiliarios.*.descripcion' => 'required_with:mobiliarios|string|max:100',
            'mobiliarios.*.cantidad' => 'required_with:mobiliarios|integer|min:1',
            'mobiliarios.*.precio_unitario_usd' => 'required_with:mobiliarios|numeric|min:0',
            'subido_redes' => 'nullable|boolean',
            'monto_pago' => 'nullable|numeric|min:0',
            'referencia_pago' => 'nullable|string|max:100',
            'tasa_bcv' => 'nullable|numeric|min:0',
            'fecha_pago' => 'nullable|date',
            'usuario_2_id' => 'nullable|exists:usuarios,id'
        ]);

        return DB::transaction(function () use ($validated) {
            $reservacion = Reservacion::create([
                'usuarios_id' => $validated['usuarios_id'],
                'evento_id' => $validated['evento_id'],
                'descripcion' => $validated['descripcion'] ?? null,
                'subido_redes' => $validated['subido_redes'] ?? false,
                'usuario_2_id' => $validated['usuario_2_id'] ?? null,
                'status' => 'pendiente'
            ]);

            foreach ($validated['stands'] as $standId) {
                $reservacion->detalles()->create([
                    'stands_id' => $standId
                ]);
            }

            $totalMobiliario = 0;
            if (!empty($validated['mobiliarios'])) {
                foreach ($validated['mobiliarios'] as $mob) {
                    $reservacion->mobiliarios()->create([
                        'descripcion' => $mob['descripcion'],
                        'cantidad' => $mob['cantidad'],
                        'precio_unitario_usd' => $mob['precio_unitario_usd']
                    ]);
                    $totalMobiliario += ($mob['cantidad'] * $mob['precio_unitario_usd']);
                }
            }

            // Evaluar estado inicial basado en pago
            $totalStands = Stand::whereIn('id_stands', $validated['stands'])->sum('precio');
            $totalDeuda = floatval($totalStands) + floatval($totalMobiliario);
            $montoPagado = $validated['monto_pago'] ?? 0;

            if ($montoPagado >= $totalDeuda && $totalDeuda > 0) {
                $reservacion->update(['status' => 'confirmada']);
                foreach ($validated['stands'] as $id) {
                    Stand::where('id_stands', $id)->update(['status' => 'ocupado']);
                }
            } else {
                foreach ($validated['stands'] as $id) {
                    Stand::where('id_stands', $id)->update(['status' => 'reservado']);
                }
            }

            // Registrar pago inicial si existe
            if ($montoPagado > 0) {
                $reservacion->pagos()->create([
                    'cantidad' => $montoPagado,
                    'numero_referencia' => $validated['referencia_pago'] ?? null,
                    'tasa_bcv' => $validated['tasa_bcv'] ?? null,
                    'fecha' => $validated['fecha_pago'] ?? now()->toDateString(),
                    'status' => 'aprobado'
                ]);
            }

            return response()->json($reservacion->load(['usuario', 'detalles.stand']), 201);
        });
    }

    public function show(Reservacion $reservacion)
    {
        return response()->json($reservacion->load(['usuario', 'usuario2', 'evento', 'detalles.stand', 'pagos', 'reembolsos', 'mobiliarios']));
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
            'subido_redes' => 'nullable|boolean',
            'descripcion' => 'nullable|string|max:45',
        ]);

        $reservacion->update($validated);

        return response()->json($reservacion->fresh(['usuario', 'detalles.stand', 'pagos']));
    }
}
