<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cotizacion_promedios', function (Blueprint $table) {
            $table->id();
            $table->string('moneda');           // USD, EUR, BRL, etc.
            $table->string('periodo');          // 'diario', 'semanal', 'mensual', 'anual'
            $table->date('fecha_referencia');   // Fecha del período (ej: 2025-04-05 para diario, 2025-W15 para semanal)
            $table->string('tipo');             // 'compra' o 'venta'
            $table->decimal('promedio_pyg', 12, 4);  // Promedio en PYG
            $table->decimal('promedio_ars', 12, 4);  // Promedio en ARS (¡ESTO ES LO QUE NECESITAS!)
            $table->string('tendencia');        // 'suba', 'baja', 'estable'
            $table->timestamps();

            $table->unique(['moneda', 'periodo', 'fecha_referencia', 'tipo'], 'unique_periodo_moneda_tipo');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cotizacion_promedios');
    }
};