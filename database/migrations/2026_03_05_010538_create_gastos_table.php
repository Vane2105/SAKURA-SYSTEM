<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id('id_gastos');
            $table->foreignId('id_eventos')->constrained('eventos', 'id_eventos')->onDelete('cascade');
            $table->string('concepto', 255);
            $table->string('categoria', 100)->nullable();
            $table->decimal('monto_usd', 10, 2);
            $table->decimal('monto_bs', 15, 2);
            $table->decimal('tasa_bcv', 10, 2);
            $table->date('fecha');
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
