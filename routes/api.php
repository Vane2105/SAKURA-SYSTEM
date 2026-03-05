<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventoController;
use App\Http\Controllers\Api\FinanzasController;
use App\Http\Controllers\Api\GastoController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\ReembolsoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\ReservacionController;
use App\Http\Controllers\Api\StandController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Ruta pública - Tasa BCV
Route::get('/tasa-bcv', function () {
    try {
        $response = \Illuminate\Support\Facades\Http::timeout(5)->get('https://pydolarve.org/api/v2/dollar?page=bcv');
        if ($response->ok()) {
            $data = $response->json();
            $usd = $data['monitors']['usd'] ?? null;
            if ($usd) {
                return response()->json([
                    'tasa' => $usd['price'],
                    'fecha' => $usd['last_update'] ?? now()->toDateString(),
                    'fuente' => 'BCV (pydolarve)'
                ]);
            }
        }
    } catch (\Exception $e) {}

    // Fallback: segunda API
    try {
        $response = \Illuminate\Support\Facades\Http::timeout(5)->get('https://ve.dolarapi.com/v1/dolares/oficial');
        if ($response->ok()) {
            $data = $response->json();
            return response()->json([
                'tasa' => $data['promedio'] ?? $data['precio'] ?? 0,
                'fecha' => $data['fechaActualizacion'] ?? now()->toDateString(),
                'fuente' => 'BCV (dolarapi)'
            ]);
        }
    } catch (\Exception $e) {}

    return response()->json(['tasa' => 0, 'fecha' => now()->toDateString(), 'fuente' => 'no disponible'], 200);
});

Route::get('/tasa-bcv/historico', [FinanzasController::class, 'getHistoricalRate']);

// Rutas Públicas de API
Route::post('/login', [AuthController::class, 'login']);


// Rutas Protegidas por Sanctum (Solo Admin)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Finanzas
    Route::get('/finanzas/global', [FinanzasController::class, 'getGlobalMetrics']);

    // Emprendedores (Usuarios)
    Route::apiResource('usuarios', UsuarioController::class);

    // Eventos
    Route::apiResource('eventos', EventoController::class);

    // Stands
    Route::apiResource('stands', StandController::class);

    // Reservaciones
    Route::apiResource('reservaciones', ReservacionController::class)->except(['destroy']);
    Route::patch('/reservaciones/{reservacion}/status', [ReservacionController::class, 'updateStatus']);

    // Gastos
    Route::apiResource('gastos', GastoController::class);

    // Pagos
    Route::post('/pagos', [PagoController::class, 'store']);

    // Reembolsos
    Route::apiResource('reembolsos', ReembolsoController::class)->except(['destroy', 'update']);
    Route::patch('/reembolsos/{reembolso}/procesar', [ReembolsoController::class, 'procesar']);

    // Reportes
    Route::get('/reportes/confirmados', [ReporteController::class, 'getConfirmados']);
});
