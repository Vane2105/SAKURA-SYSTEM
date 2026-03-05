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
            $table->foreignId('usuario_2_id')->nullable()->after('usuarios_id')->constrained('usuarios')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservacions', function (Blueprint $table) {
            $table->dropForeign(['usuario_2_id']);
            $table->dropColumn('usuario_2_id');
        });
    }
};
