<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventoController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\ReembolsoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\ReservacionController;
use App\Http\Controllers\Api\StandController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas Públicas de API
Route::post('/login', [AuthController::class, 'login']);

// Rutas Protegidas por Sanctum (Solo Admin)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Emprendedores (Usuarios)
    Route::apiResource('usuarios', UsuarioController::class);

    // Eventos
    Route::apiResource('eventos', EventoController::class);

    // Stands
    Route::apiResource('stands', StandController::class);

    // Reservaciones
    Route::apiResource('reservaciones', ReservacionController::class)->except(['destroy']);
    Route::patch('/reservaciones/{reservacion}/status', [ReservacionController::class, 'updateStatus']);

    // Pagos
    Route::post('/pagos', [PagoController::class, 'store']);

    // Reembolsos
    Route::apiResource('reembolsos', ReembolsoController::class)->except(['destroy', 'update']);
    Route::patch('/reembolsos/{reembolso}/procesar', [ReembolsoController::class, 'procesar']);

    // Reportes
    Route::get('/reportes/confirmados', [ReporteController::class, 'getConfirmados']);
});
