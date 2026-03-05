<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add conciliado field to pagos
        Schema::table('pagos', function (Blueprint $table) {
            $table->boolean('conciliado')->default(false)->after('status');
        });

        // Create local cache table for historical BCV rates
        Schema::create('tasas_bcv', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->decimal('tasa', 10, 2);
            $table->string('fuente', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('conciliado');
        });

        Schema::dropIfExists('tasas_bcv');
    }
};
