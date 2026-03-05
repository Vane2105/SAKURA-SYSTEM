<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservacions', function (Blueprint $table) {
            $table->decimal('mobiliario_precio', 10, 2)->nullable()->default(null)->after('descripcion');
            $table->boolean('mobiliario_pagado')->default(false)->after('mobiliario_precio');
            $table->boolean('subido_redes')->default(false)->after('mobiliario_pagado');
        });
    }

    public function down(): void
    {
        Schema::table('reservacions', function (Blueprint $table) {
            $table->dropColumn(['mobiliario_precio', 'mobiliario_pagado', 'subido_redes']);
        });
    }
};
