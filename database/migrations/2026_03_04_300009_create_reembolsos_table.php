<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reembolsos', function (Blueprint $table) {
            $table->id('id_reembolsos');
            $table->foreignId('reservacion_id')->constrained('reservacions', 'id_reservacion')->onDelete('cascade');
            $table->decimal('cantidad', 10, 2);
            $table->text('razon');
            $table->enum('status', ['solicitado', 'aprobado', 'rechazado', 'procesado'])->default('solicitado');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reembolsos');
    }
};
