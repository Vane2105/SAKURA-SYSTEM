<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Gasto;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class FinanzasController extends Controller
{
    public function getGlobalMetrics()
    {
        $totalIngresos = Pago::where('status', 'aprobado')->sum('cantidad');
        $totalGastos = Gasto::sum('monto_usd');
        $saldo = $totalIngresos - $totalGastos;

        // ROI por evento
        $eventos = Evento::with(['gastos'])->get()->map(function($evento) {
            $ingresos = DB::table('pagos')
                ->join('reservacions', 'pagos.reservacion_id', '=', 'reservacions.id_reservacion')
                ->join('detalle_stands', 'reservacions.id_reservacion', '=', 'detalle_stands.reservacion_id')
                ->join('stands', 'detalle_stands.stands_id', '=', 'stands.id_stands')
                ->where('stands.eventos_id', $evento->id_eventos)
                ->where('pagos.status', 'aprobado')
                ->sum('pagos.cantidad');

            $gastos = $evento->gastos->sum('monto_usd');

            return [
                'id' => $evento->id_eventos,
                'nombre' => $evento->nombre,
                'ingresos' => $ingresos,
                'gastos' => $gastos,
                'neto' => $ingresos - $gastos
            ];
        });

        return response()->json([
            'total_ingresos' => (float)$totalIngresos,
            'total_gastos' => (float)$totalGastos,
            'saldo' => (float)$saldo,
            'eventos_roi' => $eventos
        ]);
    }

    public function getHistoricalRate(Request $request)
    {
        $fecha = $request->query('fecha');
        if (!$fecha) {
            return response()->json(['error' => 'Fecha requerida'], 400);
        }

        try {
            // Intentar con DolarAPI histórico
            $url = "https://ve.dolarapi.com/v1/historicos/dolares/oficial?fecha={$fecha}";
            $response = Http::timeout(5)->get($url);

            if ($response->ok()) {
                $data = $response->json();
                // DolarAPI retorna un array de historicos, buscamos la fecha exacta o el ultimo disponible
                // Nota: DolarAPI historico a veces devuelve una lista, filtramos por la fecha exacta si es posible
                $rateData = collect($data)->firstWhere('fecha', $fecha);
                
                // Si no hay match exacto en la lista (raro), probar fallback o el primero
                if (!$rateData && count($data) > 0) {
                    $rateData = $data[count($data) - 1];
                }

                if ($rateData) {
                    return response()->json([
                        'tasa' => $rateData['promedio'] ?? $rateData['precio'] ?? 0,
                        'fecha' => $rateData['fecha'],
                        'fuente' => 'BCV (dolarapi historico)'
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error fetching historical rate: " . $e->getMessage());
        }

        return response()->json(['error' => 'No se encontró tasa para esa fecha'], 404);
    }
}
