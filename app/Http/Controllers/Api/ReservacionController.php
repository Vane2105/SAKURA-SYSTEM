<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservacion;
use App\Models\Stand;
use App\Models\Evento;
use App\Models\DetalleStand;
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

        // CRM Validation: Check if blocked
        $usuario = \App\Models\Usuario::find($validated['usuarios_id']);
        if ($usuario && $usuario->estado_registro === 'Bloqueado') {
            return response()->json(['message' => 'El emprendedor seleccionado está bloqueado y no puede realizar reservaciones.'], 422);
        }

        // Uniqueness Validation (Optional/Alert based, here we implement a hard check first for the same event)
        $existe = Reservacion::where('usuarios_id', $validated['usuarios_id'])
            ->where('evento_id', $validated['evento_id'])
            ->whereIn('status', ['pendiente', 'confirmada', 'abonada'])
            ->exists();

        if ($existe) {
             return response()->json(['message' => 'Este emprendedor ya tiene una reservación activa en este evento.'], 422);
        }

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

    public function procesarRetiro(Request $request, Reservacion $reservacion)
    {
        $validated = $request->validate([
            'accion_financiera' => 'required|in:reembolso,credito', // qué hacer con el dinero
            'motivo' => 'nullable|string|max:255'
        ]);

        return DB::transaction(function () use ($reservacion, $validated) {
            // 1. Bloquear y verificar
            $reservacion = Reservacion::lockForUpdate()->with(['pagos', 'detalles'])->find($reservacion->id_reservacion);
            
            if ($reservacion->status === 'cancelada') {
                return response()->json(['message' => 'La reservación ya está cancelada'], 422);
            }

            // 2. Grabar "auditoría" u observación del motivo cuidando el límite de 45 caracteres de la DB
            $notaAdicional = "Retiro (" . strtoupper(substr($validated['accion_financiera'], 0, 4)) . ")";
            if (!empty($validated['motivo'])) {
                $notaAdicional .= ": " . $validated['motivo'];
            }
            $nuevaDesc = ($reservacion->descripcion ? $reservacion->descripcion . " | " : "") . $notaAdicional;
            $reservacion->descripcion = \Illuminate\Support\Str::limit($nuevaDesc, 45, '');
            $reservacion->status = 'cancelada';
            $reservacion->save();

            // 3. Obtener stands involucrados y liberarlos en el mapa (hard delete del detalle)
            $standIds = $reservacion->detalles()->pluck('stands_id');
            DetalleStand::where('reservacion_id', $reservacion->id_reservacion)->delete();
            
            // Actualizar status del stand directo a disponible (aunque index ya lo calcula dinámicamente, mantenemos consistencia en bd)
            Stand::whereIn('id_stands', $standIds)->update(['status' => 'disponible']);

            // 4. Lógica Financiera
            $pagosAprobados = $reservacion->pagos->where('status', 'aprobado');
            $totalPagado = $pagosAprobados->sum('cantidad');

            if ($totalPagado > 0) {
                if ($validated['accion_financiera'] === 'reembolso') {
                    // Generar un registro de Reembolso por el total o por cada pago si es necesario.
                    // Para simplificar, generaremos un solo registro agrupado del total adeudado.
                    $reservacion->reembolsos()->create([
                        'monto_usd' => $totalPagado,
                        'motivo' => 'Reembolso por Retiro de Emprendedor. ' . ($validated['motivo'] ?? ''),
                        'fecha_solicitud' => now(),
                        'estado' => 'aprobado', // o pendiente si requiere flujo manual, asumimos aprobado por la acción directa
                    ]);
                    // Nota: Si el módulo de reembolsos requiere registrar como Gasto, 
                    // se debe hacer (ReembolsoController ya lo maneja al procesarlo), 
                    // aquí solo lo creamos como aprobado/pagado directamente.
                    
                    // Asumiendo que crear el reembolso 'aprobado' ya implica que salió el dinero, 
                    // si no, el usuario debe ir al módulo de reembolsos y pagarlo. 
                    // Lo dejamos en 'pendiente' para que pase por el flujo normal de tesorería:
                    $reservacion->reembolsos()->update(['estado' => 'pendiente']);
                }
                // Si es 'credito', no creamos reembolso. Los pagos quedan ahí asociados 
                // a la reservación cancelada y generan el "saldo a favor".
            }

            return response()->json([
                'message' => 'Retiro procesado correctamente.',
                'reservacion' => $reservacion->load(['pagos', 'reembolsos']),
            ]);
        });
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
