<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_promedios_mensuales', function (Blueprint $table) {
            $table->id();
            $table->string('moneda', 10);
            $table->enum('tipo', ['compra', 'venta']);
            $table->string('mes_referencia', 7); // YYYY-MM
            $table->decimal('promedio_pyg', 15, 4);
            $table->decimal('promedio_ars', 15, 4);
            $table->enum('tendencia', ['suba', 'baja', 'estable'])->default('estable');
            $table->timestamps();

            $table->unique(['moneda', 'tipo', 'mes_referencia'], 'mensuales_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_promedios_mensuales');
    }
};
