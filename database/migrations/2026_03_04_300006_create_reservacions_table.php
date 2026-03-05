<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservacions', function (Blueprint $table) {
            $table->id('id_reservacion');
            $table->foreignId('usuarios_id')->constrained('usuarios')->onDelete('cascade');
            $table->datetime('fecha_reserva')->useCurrent();
            $table->enum('status', ['pendiente', 'confirmada', 'cancelada'])->default('pendiente');
            $table->string('descripcion', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservacions');
    }
};
