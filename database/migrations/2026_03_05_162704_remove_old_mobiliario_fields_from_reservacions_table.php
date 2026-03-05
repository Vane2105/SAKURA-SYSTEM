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
        Schema::table('reservacions', function (Blueprint $table) {
            if (Schema::hasColumn('reservacions', 'mobiliario_precio')) {
                $table->dropColumn('mobiliario_precio');
            }
            if (Schema::hasColumn('reservacions', 'mobiliario_pagado')) {
                $table->dropColumn('mobiliario_pagado');
            }
            if (!Schema::hasColumn('reservacions', 'evento_id')) {
                $table->foreignId('evento_id')->nullable()->constrained('eventos', 'id_eventos')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservacions', function (Blueprint $table) {
            if (!Schema::hasColumn('reservacions', 'mobiliario_precio')) {
                $table->decimal('mobiliario_precio', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('reservacions', 'mobiliario_pagado')) {
                $table->boolean('mobiliario_pagado')->default(false);
            }
            if (Schema::hasColumn('reservacions', 'evento_id')) {
                $table->dropForeign(['evento_id']);
                $table->dropColumn('evento_id');
            }
        });
    }
};
