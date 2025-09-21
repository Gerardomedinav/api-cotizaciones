<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->time('hora')->nullable()->after('fecha'); // Para registrar la hora exacta
            $table->enum('tendencia', ['suba', 'baja', 'estable'])->nullable()->after('venta'); // Estado
        });
    }

    public function down()
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['hora', 'tendencia']);
        });
    }
};
