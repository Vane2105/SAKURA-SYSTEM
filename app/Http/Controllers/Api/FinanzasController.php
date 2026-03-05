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
            // Pedimos desde 10 días ANTES de la fecha solicitada.
            // La API devuelve datos DESDE esa fecha en adelante.
            // Así garantizamos tener registros que cubran la fecha exacta,
            // incluyendo fines de semana donde el BCV no publica tasa.
            $fechaBusqueda = date('Y-m-d', strtotime($fecha . ' -10 days'));
            $url = "https://ve.dolarapi.com/v1/historicos/dolares/oficial?fecha={$fechaBusqueda}";
            $response = Http::timeout(7)->get($url);

            if ($response->ok()) {
                $data = $response->json();

                if (!is_array($data) || count($data) === 0) {
                    return response()->json(['error' => 'No se encontró tasa para esa fecha'], 404);
                }

                // Buscamos la tasa con fecha <= a la fecha solicitada
                // La lista viene en orden ascendente de fecha, tomamos el último que sea <= fecha solicitada
                $rateData = null;
                foreach ($data as $item) {
                    if (isset($item['fecha']) && $item['fecha'] <= $fecha) {
                        $rateData = $item; // va avanzando hasta el último válido
                    }
                }

                // Si no encontramos ninguno <= fecha (muy raro), tomamos el primero disponible
                if (!$rateData) {
                    $rateData = $data[0];
                }

                return response()->json([
                    'tasa'   => $rateData['promedio'] ?? $rateData['precio'] ?? 0,
                    'fecha'  => $rateData['fecha'],
                    'fuente' => 'BCV (histórico oficial)',
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Error fetching historical rate: " . $e->getMessage());
        }

        return response()->json(['error' => 'No se encontró tasa para esa fecha'], 404);
    }
}
