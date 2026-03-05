<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alquileres_mobiliario', function (Blueprint $table) {
            $table->id('id_alquiler');
            $table->foreignId('reservacion_id')->constrained('reservacions', 'id_reservacion')->onDelete('cascade');
            $table->string('descripcion')->nullable();
            $table->decimal('precio_usd', 8, 2)->default(0);
            $table->enum('status', ['pendiente', 'pagado', 'cancelado'])->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alquileres_mobiliario');
    }
};
