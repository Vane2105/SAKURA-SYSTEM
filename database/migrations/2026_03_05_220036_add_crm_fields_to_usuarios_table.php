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
        Schema::table('usuarios', function (Blueprint $table) {
            $table->enum('estado_registro', ['Por Verificar', 'Documentos OK', 'Bloqueado'])->default('Por Verificar')->after('instagram');
            $table->text('notas_admin')->nullable()->after('estado_registro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['estado_registro', 'notas_admin']);
        });
    }
};
