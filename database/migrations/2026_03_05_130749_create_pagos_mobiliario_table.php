<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_mobiliario', function (Blueprint $table) {
            $table->id('id_pago_mobiliario');
            $table->foreignId('alquiler_id')->constrained('alquileres_mobiliario', 'id_alquiler')->onDelete('cascade');
            $table->decimal('cantidad', 8, 2);
            $table->decimal('tasa_bcv', 10, 4)->nullable();
            $table->date('fecha')->nullable();
            $table->string('numero_referencia')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_mobiliario');
    }
};
