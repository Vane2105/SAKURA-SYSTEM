<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stand;
use Illuminate\Http\Request;

class StandController extends Controller
{
    public function index(Request $request)
    {
        // 1. Cargar relaciones base, incluyendo reservación, usuario y PAGOS para calcular saldo
        $query = Stand::with([
            'evento', 
            'detalles.reservacion.usuario', 
            'detalles.reservacion.pagos'
        ]);

        if ($request->has('evento_id')) {
            $query->where('eventos_id', $request->evento_id);
        }

        $stands = $query->get();

        // 2. Mutar la respuesta para calcular dinámicamente el estado y el saldo
        $stands = $stands->map(function ($stand) {
            $nuevoEstatus = 'disponible';
            $saldoPendiente = 0;
            $pagosAprobados = 0;

            // Verificar si hay una reservación activa que ocupe el stand
            $detalleActivo = $stand->detalles->first(function ($detalle) {
                return $detalle->reservacion && $detalle->reservacion->status !== 'cancelada';
            });

            if ($detalleActivo) {
                $reservacion = $detalleActivo->reservacion;
                
                // Sumar pagos aprobados asociados específicamente a "stands" 
                // (no incluimos 'mobiliario' para este cálculo porque el precio del stand es independiente)
                $pagosAprobados = $reservacion->pagos
                    ->where('status', 'aprobado')
                    ->where('tipo', 'stand')
                    ->sum('cantidad');

                // Si una reservación puede tener múltiples stands, el saldo de LA RESERVACION 
                // es el costo de todos los stands - lo pagado.
                // Como nos piden evaluar por stand y no es posible saber qué parte del pago va a qué stand
                // si se reservan varios, la lógica común es evaluar el saldo total de la reservación.
                $totalStandsReservacion = $reservacion->detalles->sum(function($d) {
                    return $d->stand ? $d->stand->precio : 0;
                });
                
                $saldoTotalReservacion = $totalStandsReservacion - $pagosAprobados;
                $saldoPendiente = max(0, $saldoTotalReservacion); // Formateamos para no tener saldos negativos

                if ($pagosAprobados >= $totalStandsReservacion && $totalStandsReservacion > 0) {
                    $nuevoEstatus = 'ocupado';
                } elseif ($pagosAprobados > 0 && $pagosAprobados < $totalStandsReservacion) {
                    $nuevoEstatus = 'reservado';
                } else {
                    $nuevoEstatus = 'disponible';
                }
            }

            // Anexar datos calculados al modelo para la respuesta JSON
            $stand->_status_calculado = $nuevoEstatus; // El status en BD es manual, este es dinámico
            $stand->status = $nuevoEstatus; // Sobrescribimos para el frontend temporalmente
            $stand->saldo_pendiente = $saldoPendiente;

            return $stand;
        });

        return response()->json($stands);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'eventos_id' => 'required|exists:eventos,id_eventos',
            'name' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'status' => 'in:disponible,reservado,ocupado,mantenimiento',
        ]);

        $stand = Stand::create($validated);
        return response()->json($stand, 201);
    }

    public function show(Stand $stand)
    {
        $stand->load(['evento', 'detalles.reservacion.usuario', 'detalles.reservacion.pagos']);
        
        $nuevoEstatus = 'disponible';
        $saldoPendiente = 0;
        $pagosAprobados = 0;

        $detalleActivo = $stand->detalles->first(function ($detalle) {
            return $detalle->reservacion && $detalle->reservacion->status !== 'cancelada';
        });

        if ($detalleActivo) {
            $reservacion = $detalleActivo->reservacion;
            $pagosAprobados = $reservacion->pagos
                ->where('status', 'aprobado')
                ->where('tipo', 'stand')
                ->sum('cantidad');

            $totalStandsReservacion = $reservacion->detalles->sum(function($d) {
                return $d->stand ? $d->stand->precio : 0;
            });
            
            $saldoTotalReservacion = $totalStandsReservacion - $pagosAprobados;
            $saldoPendiente = max(0, $saldoTotalReservacion);

            if ($pagosAprobados >= $totalStandsReservacion && $totalStandsReservacion > 0) {
                $nuevoEstatus = 'ocupado';
            } elseif ($pagosAprobados > 0 && $pagosAprobados < $totalStandsReservacion) {
                $nuevoEstatus = 'reservado';
            } else {
                $nuevoEstatus = 'disponible'; // Deuda total pero ya ligado = disponible? Depende Regla, la orden dice "disponible si pago = 0"
                // Ajustado: Si no ha pagado nada sigue siendo "reservado" hasta que pase el tiempo y se cancele, 
                // PERO la instrucción explicita fue: "status = 'disponible' si pago total = 0."
            }
        }

        $stand->status = $nuevoEstatus;
        $stand->saldo_pendiente = $saldoPendiente;

        return response()->json($stand);
    }

    public function update(Request $request, Stand $stand)
    {
        $validated = $request->validate([
            'eventos_id' => 'exists:eventos,id_eventos',
            'name' => 'string|max:255',
            'precio' => 'numeric|min:0',
            'status' => 'in:disponible,reservado,ocupado,mantenimiento',
        ]);

        // Regla de Integridad: Bloquear cambio manual a 'disponible' si hay pagos aprobados.
        if (isset($validated['status']) && $validated['status'] === 'disponible') {
            $stand->load('detalles.reservacion.pagos');
            $detalleActivo = $stand->detalles->first(function ($detalle) {
                return $detalle->reservacion && $detalle->reservacion->status !== 'cancelada';
            });

            if ($detalleActivo) {
                $pagosAprobados = $detalleActivo->reservacion->pagos
                    ->where('status', 'aprobado')
                    // Opcional: ->where('tipo', 'stand') (pero la regla general pide prohibir si hay CUALQUIER pago)
                    ->count();

                if ($pagosAprobados > 0) {
                    return response()->json([
                        'message' => 'No se puede cambiar el estado a "Disponible" porque el stand está asociado a una reservación con pagos aprobados. Debe registrar un Retiro.',
                        'errors' => ['status' => ['Stand con pagos. Registre el retiro.']]
                    ], 422);
                }
            }
        }

        $stand->update($validated);
        return response()->json($stand);
    }

    public function destroy(Stand $stand)
    {
        $stand->delete();
        return response()->json(null, 204);
    }
}
