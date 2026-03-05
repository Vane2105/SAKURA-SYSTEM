<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventoController extends Controller
{
    public function index()
    {
        $eventos = Evento::with(['creador', 'gastos', 'stands.detalles.reservacion.pagos'])->get();
        
        $eventos->each(function($evento) {
            // Ingresos Stand
            $ingresosStand = DB::table('pagos')
                ->join('reservacions', 'pagos.reservacion_id', '=', 'reservacions.id_reservacion')
                ->where('reservacions.evento_id', $evento->id_eventos)
                ->where('pagos.status', 'aprobado')
                ->where('pagos.tipo', 'stand')
                ->whereNull('reservacions.deleted_at')
                ->sum('pagos.cantidad');

            // Ingresos Mobiliario
            $ingresosMob = DB::table('pagos')
                ->join('reservacions', 'pagos.reservacion_id', '=', 'reservacions.id_reservacion')
                ->where('reservacions.evento_id', $evento->id_eventos)
                ->where('pagos.status', 'aprobado')
                ->where('pagos.tipo', 'mobiliario')
                ->whereNull('reservacions.deleted_at')
                ->sum('pagos.cantidad');

            // Egresos desglosados
            $egresosOperativos = $evento->gastos->where('categoria', '!=', 'Pago a Proveedor de Mobiliario')->sum('monto_usd');
            $egresosProveedor = $evento->gastos->where('categoria', 'Pago a Proveedor de Mobiliario')->sum('monto_usd');

            $evento->ingresos_stand = (float)$ingresosStand;
            $evento->ingresos_mob = (float)$ingresosMob;
            $evento->total_ingresos = (float)($ingresosStand + $ingresosMob);
            $evento->egresos_operativos = (float)$egresosOperativos;
            $evento->egresos_proveedor = (float)$egresosProveedor;
            $evento->total_gastos = (float)($egresosOperativos + $egresosProveedor);
            $evento->rentabilidad = $evento->total_ingresos - $evento->total_gastos;
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

    /**
     * Show detailed financial data for a single event.
     * This feeds the "Panel de Control del Evento".
     */
    public function show(Evento $evento)
    {
        $evento->load(['creador', 'stands', 'gastos']);

        // Get all payments for this event through reservations
        $pagos = DB::table('pagos')
            ->join('reservacions', 'pagos.reservacion_id', '=', 'reservacions.id_reservacion')
            ->leftJoin('usuarios', 'reservacions.usuarios_id', '=', 'usuarios.id')
            ->where('reservacions.evento_id', $evento->id_eventos)
            ->where('pagos.status', 'aprobado')
            ->whereNull('reservacions.deleted_at')
            ->select(
                'pagos.id_pagos',
                'pagos.tipo',
                'pagos.cantidad',
                'pagos.tasa_bcv',
                'pagos.monto_bs',
                'pagos.fecha',
                'pagos.numero_referencia',
                'pagos.conciliado',
                'pagos.reservacion_id',
                'usuarios.nombre_tienda',
                'usuarios.nombre as usuario_nombre'
            )
            ->orderBy('pagos.fecha', 'desc')
            ->get();

        // Transform payments into income entries with concept
        $ingresos = $pagos->map(function($pago) {
            $tienda = $pago->nombre_tienda ?: $pago->usuario_nombre;
            $concepto = $pago->tipo === 'stand'
                ? "Pago Stand - {$tienda}"
                : "Pago Mobiliario - {$tienda}";

            return [
                'id' => $pago->id_pagos,
                'fecha' => $pago->fecha,
                'concepto' => $concepto,
                'tipo' => $pago->tipo,
                'monto_usd' => (float)$pago->cantidad,
                'tasa_bcv' => (float)$pago->tasa_bcv,
                'monto_bs' => (float)$pago->monto_bs,
                'referencia' => $pago->numero_referencia,
                'conciliado' => (bool)$pago->conciliado,
                'reservacion_id' => $pago->reservacion_id,
            ];
        });

        // Financial summary calculations
        $ingresosStand = $ingresos->where('tipo', 'stand')->sum('monto_usd');
        $ingresosMob = $ingresos->where('tipo', 'mobiliario')->sum('monto_usd');
        $egresosOperativos = $evento->gastos->where('categoria', '!=', 'Pago a Proveedor de Mobiliario')->sum('monto_usd');
        $egresosProveedor = $evento->gastos->where('categoria', 'Pago a Proveedor de Mobiliario')->sum('monto_usd');

        $totalIngresos = $ingresosStand + $ingresosMob;
        $totalEgresos = $egresosOperativos + $egresosProveedor;

        // BS calculations (based on historic values)
        $ingresosStandBs = $ingresos->where('tipo', 'stand')->sum('monto_bs');
        $ingresosMobBs = $ingresos->where('tipo', 'mobiliario')->sum('monto_bs');
        $egresosOperativosBs = $evento->gastos->where('categoria', '!=', 'Pago a Proveedor de Mobiliario')->sum('monto_bs');
        $egresosProveedorBs = $evento->gastos->where('categoria', 'Pago a Proveedor de Mobiliario')->sum('monto_bs');

        $totalIngresosBs = $ingresosStandBs + $ingresosMobBs;
        $totalEgresosBs = $egresosOperativosBs + $egresosProveedorBs;

        // Conciliation stats
        $conciliadosCollection = $ingresos->where('conciliado', true);
        $totalConciliados = $conciliadosCollection->count();
        $totalPendientes = $ingresos->where('conciliado', false)->count();

        // Conciliated income = money actually received in bank
        $ingresosStandConciliado = $conciliadosCollection->where('tipo', 'stand')->sum('monto_usd');
        $ingresosMobConciliado = $conciliadosCollection->where('tipo', 'mobiliario')->sum('monto_usd');
        $totalIngresosConciliado = $ingresosStandConciliado + $ingresosMobConciliado;

        // Conciliated BS income
        $ingresosStandConciliadoBs = $conciliadosCollection->where('tipo', 'stand')->sum('monto_bs');
        $ingresosMobConciliadoBs = $conciliadosCollection->where('tipo', 'mobiliario')->sum('monto_bs');
        $totalIngresosConciliadoBs = $ingresosStandConciliadoBs + $ingresosMobConciliadoBs;

        return response()->json([
            'evento' => $evento,
            'ingresos' => $ingresos->values(),
            'resumen' => [
                'ingresos_stand' => (float)$ingresosStand,
                'ingresos_mob' => (float)$ingresosMob,
                'total_ingresos' => (float)$totalIngresos,
                'ingresos_stand_bs' => (float)$ingresosStandBs,
                'ingresos_mob_bs' => (float)$ingresosMobBs,
                'total_ingresos_bs' => (float)$totalIngresosBs,
                'ingresos_stand_conciliado' => (float)$ingresosStandConciliado,
                'ingresos_mob_conciliado' => (float)$ingresosMobConciliado,
                'total_ingresos_conciliado' => (float)$totalIngresosConciliado,
                'total_ingresos_conciliado_bs' => (float)$totalIngresosConciliadoBs,
                'egresos_operativos' => (float)$egresosOperativos,
                'egresos_proveedor' => (float)$egresosProveedor,
                'total_egresos' => (float)$totalEgresos,
                'egresos_operativos_bs' => (float)$egresosOperativosBs,
                'egresos_proveedor_bs' => (float)$egresosProveedorBs,
                'total_egresos_bs' => (float)$totalEgresosBs,
                'balance_neto' => (float)($totalIngresos - $totalEgresos),
                'balance_neto_bs' => (float)($totalIngresosBs - $totalEgresosBs),
                'balance_neto_conciliado' => (float)($totalIngresosConciliado - $totalEgresos),
                'balance_neto_conciliado_bs' => (float)($totalIngresosConciliadoBs - $totalEgresosBs),
                'conciliados' => $totalConciliados,
                'pendientes' => $totalPendientes,
            ],
        ]);
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

    /**
     * Group expenses by category for a given event.
     */
    public function gastosPorCategoria(Evento $evento)
    {
        $categorias = $evento->gastos()
            ->selectRaw('categoria, COUNT(*) as cantidad, SUM(monto_usd) as total_usd, SUM(monto_bs) as total_bs')
            ->groupBy('categoria')
            ->orderByDesc('total_usd')
            ->get();

        return response()->json($categorias);
    }
}
