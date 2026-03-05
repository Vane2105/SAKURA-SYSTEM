<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Gasto;
use App\Models\Pago;
use App\Models\TasaBcv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class FinanzasController extends Controller
{
    public function getGlobalMetrics()
    {
        $totalIngresosStands = Pago::where('status', 'aprobado')->where('tipo', 'stand')->sum('cantidad');
        $totalIngresosMobiliario = Pago::where('status', 'aprobado')->where('tipo', 'mobiliario')->sum('cantidad');
        $totalGastos = Gasto::sum('monto_usd');
        
        // El saldo neto considera TODOS los ingresos vs TODOS los gastos
        $saldoNeto = ($totalIngresosStands + $totalIngresosMobiliario) - $totalGastos;

        // ROI por evento
        $eventos = Evento::with(['gastos'])->get()->map(function($evento) {
            $ingresosStands = DB::table('pagos')
                ->join('reservacions', 'pagos.reservacion_id', '=', 'reservacions.id_reservacion')
                ->where('reservacions.evento_id', $evento->id_eventos)
                ->where('pagos.status', 'aprobado')
                ->where('pagos.tipo', 'stand')
                ->sum('pagos.cantidad');

            $ingresosMobiliario = DB::table('pagos')
                ->join('reservacions', 'pagos.reservacion_id', '=', 'reservacions.id_reservacion')
                ->where('reservacions.evento_id', $evento->id_eventos)
                ->where('pagos.status', 'aprobado')
                ->where('pagos.tipo', 'mobiliario')
                ->sum('pagos.cantidad');

            $gastosOperativos = $evento->gastos->where('categoria', '!=', 'Pago a Proveedor de Mobiliario')->sum('monto_usd');
            $gastosProveedor = $evento->gastos->where('categoria', 'Pago a Proveedor de Mobiliario')->sum('monto_usd');
            $totalGastosEvento = $evento->gastos->sum('monto_usd');

            return [
                'id' => $evento->id_eventos,
                'nombre' => $evento->nombre,
                'ingresos_stands' => (float)$ingresosStands,
                'ingresos_mobiliario' => (float)$ingresosMobiliario,
                'gastos_operativos' => (float)$gastosOperativos,
                'gastos_proveedor' => (float)$gastosProveedor,
                'gastos' => (float)$totalGastosEvento,
                'neto' => (float)(($ingresosStands + $ingresosMobiliario) - $totalGastosEvento)
            ];
        });

        return response()->json([
            'total_ingresos' => (float)$totalIngresosStands,
            'total_mobiliario' => (float)$totalIngresosMobiliario,
            'total_gastos' => (float)$totalGastos,
            'saldo' => (float)$saldoNeto,
            'eventos_roi' => $eventos
        ]);
    }

    public function getHistoricalRate(Request $request)
    {
        $fecha = $request->query('fecha');
        if (!$fecha) {
            return response()->json(['error' => 'Fecha requerida'], 400);
        }

        // 1. Check local cache first
        $cached = TasaBcv::where('fecha', $fecha)->first();
        if ($cached) {
            return response()->json([
                'tasa'   => $cached->tasa,
                'fecha'  => $cached->fecha->format('Y-m-d'),
                'fuente' => $cached->fuente ?? 'BCV (cache local)',
            ]);
        }

        // 2. If not cached, try fetching from external API
        try {
            $fechaBusqueda = date('Y-m-d', strtotime($fecha . ' -10 days'));
            $url = "https://ve.dolarapi.com/v1/historicos/dolares/oficial?fecha={$fechaBusqueda}";
            $response = Http::timeout(7)->get($url);

            if ($response->ok()) {
                $data = $response->json();

                if (!is_array($data) || count($data) === 0) {
                    return response()->json(['error' => 'No se encontró tasa para esa fecha'], 404);
                }

                $rateData = null;
                foreach ($data as $item) {
                    if (isset($item['fecha']) && $item['fecha'] <= $fecha) {
                        $rateData = $item;
                    }
                }

                if (!$rateData) {
                    $rateData = $data[0];
                }

                $tasa = $rateData['promedio'] ?? $rateData['precio'] ?? 0;
                $fechaReal = $rateData['fecha'];
                $fuente = 'BCV (histórico oficial)';

                // 3. Save to local cache
                TasaBcv::updateOrCreate(
                    ['fecha' => $fechaReal],
                    ['tasa' => $tasa, 'fuente' => $fuente]
                );

                // Also cache for the requested date if different
                if ($fechaReal !== $fecha) {
                    TasaBcv::updateOrCreate(
                        ['fecha' => $fecha],
                        ['tasa' => $tasa, 'fuente' => $fuente . ' (interpolada)']
                    );
                }

                return response()->json([
                    'tasa'   => $tasa,
                    'fecha'  => $fechaReal,
                    'fuente' => $fuente,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Error fetching historical rate: " . $e->getMessage());
        }

        return response()->json(['error' => 'No se encontró tasa para esa fecha'], 404);
    }
}
