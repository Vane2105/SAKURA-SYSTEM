<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_stands', function (Blueprint $table) {
            $table->id('id_detalle_stand');
            $table->foreignId('stands_id')->constrained('stands', 'id_stands')->onDelete('cascade');
            $table->foreignId('reservacion_id')->constrained('reservacions', 'id_reservacion')->onDelete('cascade');
            $table->string('descripcion', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_stands');
    }
};
