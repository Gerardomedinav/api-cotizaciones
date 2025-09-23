<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Models\Cotizacion;
use App\Models\CotizacionPromedioDiario;
use App\Models\CotizacionPromedioMensual;
use App\Models\CotizacionPromedioAnual;

class CalcularPromedios extends Command
{
    protected $signature = 'promedios:calcular';
    protected $description = 'Calcula y guarda promedios diarios, mensuales y anuales en sus tablas separadas';

    public function handle()
    {
        $this->info('🚀 Iniciando cálculo de promedios (diario, mensual, anual)...');

        $monedas = Cotizacion::select('moneda')
            ->distinct()
            ->where('moneda', '!=', 'ARS')
            ->pluck('moneda')
            ->toArray();

        if (empty($monedas)) {
            $this->warn('⚠️ No hay monedas distintas de ARS para calcular.');
            return 0;
        }

        $hoy = Carbon::today();
        $ayer = $hoy->copy()->subDay();
        $mes = $hoy->copy()->startOfMonth()->toDateString(); // '2025-04-01'
        $ano = $hoy->year;

        $promedio_ars_pyg = $this->getPromedioARS();
        if ($promedio_ars_pyg === null || $promedio_ars_pyg <= 0) {
            $this->error('❌ No se pudo obtener promedio válido de ARS. Abortando.');
            return 1;
        }

        foreach ($monedas as $moneda) {
            $this->info("🔄 Procesando moneda: {$moneda}");

            // 1. DIARIO
            $this->calcularYGuardarPromedio($moneda, 'diario', $ayer->toDateString(), $promedio_ars_pyg);

            // 2. MENSUAL
            $this->calcularYGuardarPromedio($moneda, 'mensual', $mes, $promedio_ars_pyg);

            // 3. ANUAL
            $this->calcularYGuardarPromedio($moneda, 'anual', $ano, $promedio_ars_pyg);
        }

        $this->info('✅ Promedios diarios, mensuales y anuales actualizados correctamente.');
        return 0;
    }

    private function getPromedioARS()
    {
        $ultimoARS = Cotizacion::where('moneda', 'ARS')
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->first();

        if (!$ultimoARS) {
            $this->error('❌ No se encontró ningún registro de ARS en la base de datos.');
            return null;
        }

        $this->info("✅ ARS encontrado: {$ultimoARS->venta} PYG (último registro del {$ultimoARS->fecha})");
        return (float) $ultimoARS->venta;
    }

    private function calcularYGuardarPromedio($moneda, $periodo, $fecha_ref, $promedio_ars_pyg)
    {
        $query = Cotizacion::where('moneda', $moneda);

        switch ($periodo) {
            case 'diario':
                $query->whereDate('fecha', $fecha_ref);
                $model = CotizacionPromedioDiario::class;
                $campo_fecha = 'fecha_referencia';
                break;
            case 'mensual':
                $query->whereYear('fecha', substr($fecha_ref, 0, 4))
                      ->whereMonth('fecha', substr($fecha_ref, 5, 2));
                $model = CotizacionPromedioMensual::class;
                $campo_fecha = 'fecha_referencia';
                break;
            case 'anual':
                $query->whereYear('fecha', $fecha_ref);
                $model = CotizacionPromedioAnual::class;
                $campo_fecha = 'anio_referencia'; // ✅ ¡USAMOS EL NOMBRE REAL!
                break;
            default:
                $this->warn("⚠️ Periodo desconocido: {$periodo}");
                return;
        }

        $cotizaciones = $query->get();

        if ($cotizaciones->isEmpty()) {
            $this->line("⏩ Sin datos para {$moneda} en {$periodo} {$fecha_ref}");
            return;
        }

        $promedio_compra_pyg = $cotizaciones->avg('compra');
        $promedio_venta_pyg  = $cotizaciones->avg('venta');

        $promedio_compra_ars = $promedio_compra_pyg / $promedio_ars_pyg;
        $promedio_venta_ars  = $promedio_venta_pyg / $promedio_ars_pyg;

        $tendencia_compra = $this->getTendencia($moneda, $periodo, $fecha_ref, 'compra', $promedio_compra_ars, $model, $campo_fecha);
        $tendencia_venta  = $this->getTendencia($moneda, $periodo, $fecha_ref, 'venta', $promedio_venta_ars, $model, $campo_fecha);

        $this->guardarPromedio($model, $moneda, $fecha_ref, $periodo, 'compra', $promedio_compra_pyg, $promedio_compra_ars, $tendencia_compra);
        $this->guardarPromedio($model, $moneda, $fecha_ref, $periodo, 'venta', $promedio_venta_pyg, $promedio_venta_ars, $tendencia_venta);
    }

    private function getTendencia($moneda, $periodo, $fecha_ref, $tipo, $valor_actual, $model, $campo_fecha)
    {
        // ✅ ¡USAMOS $campo_fecha, que es 'anio_referencia' para anual!
        $anterior = $model::where('moneda', $moneda)
            ->where('tipo', $tipo)
            ->where($campo_fecha, '<', $fecha_ref) // ✅ Dinámico: usa 'anio_referencia' o 'fecha_referencia'
            ->orderBy($campo_fecha, 'desc')
            ->first();

        if (!$anterior) {
            return 'estable';
        }

        if ($valor_actual > $anterior->promedio_ars) return 'suba';
        if ($valor_actual < $anterior->promedio_ars) return 'baja';
        return 'estable';
    }

    private function guardarPromedio($model, $moneda, $fecha_ref, $periodo, $tipo, $promedio_pyg, $promedio_ars, $tendencia)
    {
        // Definir los campos únicos para buscar
        $where = [
            'moneda' => $moneda,
            'tipo' => $tipo
        ];

        // Añadir el campo de referencia según la tabla
        if ($model === CotizacionPromedioAnual::class) {
            $where['anio_referencia'] = $fecha_ref; // ✅ Solo aquí
        } else {
            $where['fecha_referencia'] = $fecha_ref; // ✅ Solo aquí
        }

        // Buscar el registro existente
        $registro = $model::where($where)->first();

        if ($registro) {
            // Si existe, actualizamos
            $registro->update([
                'promedio_pyg' => $promedio_pyg,
                'promedio_ars' => $promedio_ars,
                'tendencia' => $tendencia,
            ]);
        } else {
            // Si no existe, creamos con todos los campos
            $data = [
                'moneda' => $moneda,
                'tipo' => $tipo,
                'promedio_pyg' => $promedio_pyg,
                'promedio_ars' => $promedio_ars,
                'tendencia' => $tendencia,
            ];

            // Añadir el campo de referencia según la tabla
            if ($model === CotizacionPromedioAnual::class) {
                $data['anio_referencia'] = $fecha_ref; // ✅ ¡CLAVE! ¡Aquí se inserta!
            } else {
                $data['fecha_referencia'] = $fecha_ref; // ✅ ¡Aquí también!
            }

            $model::create($data); // ✅ ¡Ahora sí incluye todos los campos!
        }

        $this->line("   ✅ {$periodo} {$moneda} [{$tipo}]: " . number_format($promedio_ars, 6, '.', '') . " ARS ({$tendencia})");
    }
}