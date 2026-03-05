<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stands', function (Blueprint $table) {
            $table->id('id_stands');
            $table->foreignId('eventos_id')->constrained('eventos', 'id_eventos')->onDelete('cascade');
            $table->string('name', 255);
            $table->decimal('precio', 10, 2);
            $table->enum('status', ['disponible', 'reservado', 'ocupado', 'mantenimiento'])->default('disponible');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stands');
    }
};
