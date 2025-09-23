<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_promedios_anuales', function (Blueprint $table) {
            $table->id();
            $table->string('moneda', 10);
            $table->enum('tipo', ['compra', 'venta']);
            $table->year('anio_referencia'); // Ej: 2025
            $table->decimal('promedio_pyg', 15, 4);
            $table->decimal('promedio_ars', 15, 4);
            $table->enum('tendencia', ['suba', 'baja', 'estable'])->default('estable');
            $table->timestamps();

            $table->unique(['moneda', 'tipo', 'anio_referencia'], 'anuales_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_promedios_anuales');
    }
};
