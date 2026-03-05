<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alquileres_mobiliario', function (Blueprint $table) {
            $table->decimal('monto_bs', 12, 2)->default(0)->after('precio_usd');
            $table->decimal('tasa_bcv', 10, 4)->nullable()->after('monto_bs');
            $table->string('tasa_fuente')->nullable()->after('tasa_bcv');
            $table->date('fecha')->nullable()->after('tasa_fuente');
        });
    }

    public function down(): void
    {
        Schema::table('alquileres_mobiliario', function (Blueprint $table) {
            $table->dropColumn(['monto_bs', 'tasa_bcv', 'tasa_fuente', 'fecha']);
        });
    }
};
