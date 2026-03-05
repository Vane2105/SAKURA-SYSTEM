<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservacion;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function getConfirmados(Request $request)
    {
        $query = Reservacion::with(['usuario.telefonos', 'detalles.stand', 'pagos'])
            ->where('status', 'confirmada');

        return response()->json($query->get());
    }
}
