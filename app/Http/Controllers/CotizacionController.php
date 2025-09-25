<?php

namespace App\Http\Controllers;

use App\Models\CotizacionPromedio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Cotizacion;
use App\Models\CotizacionPromedioDiario;
use App\Models\CotizacionPromedioMensual;
use App\Models\CotizacionPromedioAnual;

class CotizacionController extends Controller
{
   
public function actualizar()
{
    $url = config('services.cambioschaco.url');
    $response = Http::timeout(15)->get($url);

    if ($response->failed()) {
        return response()->json(['error' => 'Error al consultar la API externa'], 500);
    }

    $data = $response->json();
    $items = $data['items'] ?? [];

    $fechaHoy = now()->toDateString();
    $horaHoy  = now()->toTimeString();

    foreach ($items as $item) {
        $moneda = $item['isoCode'] ?? null;
        if (!$moneda) continue;

        $compra = (float) ($item['purchasePrice'] ?? 0);
        $venta  = (float) ($item['salePrice'] ?? 0);

        // Buscar registro de hoy
        $registro = Cotizacion::where('moneda', $moneda)
            ->whereDate('fecha', $fechaHoy)
            ->first();

        // Calcular tendencia
        $tendencia = 'estable';
        if ($registro) {
            if ($venta > $registro->venta) {
                $tendencia = 'suba';
            } elseif ($venta < $registro->venta) {
                $tendencia = 'baja';
            }
        }

        if ($registro) {
            $registro->update([
                'compra'    => $compra,
                'venta'     => $venta,
                'tendencia' => $tendencia,
                'hora'      => $horaHoy,
            ]);
        } else {
            Cotizacion::create([
                'moneda'    => $moneda,
                'compra'    => $compra,
                'venta'     => $venta,
                'tendencia' => $tendencia,
                'fecha'     => $fechaHoy,
                'hora'      => $horaHoy,
            ]);
        }
    }

    // ✅ Después de actualizar, recalculamos promedios
    $this->calcularPromedios();

    return response()->json(['message' => 'Cotizaciones y promedios actualizados correctamente']);
}

/**
 * Calcula y guarda promedios diarios, mensuales y anuales
 */
private function calcularPromedios()
{
    $monedas = Cotizacion::select('moneda')
        ->distinct()
        ->where('moneda', '!=', 'ARS')
        ->pluck('moneda')
        ->toArray();

    $hoy = now();
    $ayer = now()->subDay();
    $mes = $hoy->copy()->startOfMonth()->toDateString();
    $ano = $hoy->year;

    $ultimoARS = Cotizacion::where('moneda', 'ARS')
        ->orderBy('fecha', 'desc')
        ->orderBy('hora', 'desc')
        ->first();

    if (!$ultimoARS) {
        return;
    }

    $promedio_ars_pyg = (float) $ultimoARS->venta;

    foreach ($monedas as $moneda) {
        // Diario
        $this->calcularYGuardarPromedio($moneda, 'diario', $ayer->toDateString(), $promedio_ars_pyg);

        // Mensual
        $this->calcularYGuardarPromedio($moneda, 'mensual', $mes, $promedio_ars_pyg);

        // Anual
        $this->calcularYGuardarPromedio($moneda, 'anual', $ano, $promedio_ars_pyg);
    }
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
            $campo_fecha = 'anio_referencia';
            break;
        default:
            return;
    }

    $cotizaciones = $query->get();
    if ($cotizaciones->isEmpty()) return;

    $promedio_compra_pyg = $cotizaciones->avg('compra');
    $promedio_venta_pyg  = $cotizaciones->avg('venta');

    $promedio_compra_ars = $promedio_compra_pyg / $promedio_ars_pyg;
    $promedio_venta_ars  = $promedio_venta_pyg / $promedio_ars_pyg;

    // Guardamos promedio compra
    $model::updateOrCreate(
        ['moneda' => $moneda, 'tipo' => 'compra', $campo_fecha => $fecha_ref],
        ['promedio_pyg' => $promedio_compra_pyg, 'promedio_ars' => $promedio_compra_ars]
    );

    // Guardamos promedio venta
    $model::updateOrCreate(
        ['moneda' => $moneda, 'tipo' => 'venta', $campo_fecha => $fecha_ref],
        ['promedio_pyg' => $promedio_venta_pyg, 'promedio_ars' => $promedio_venta_ars]
    );
}

public function index(Request $request)
{
    // Parámetros
    $moneda = $request->query('moneda');
    $tipo   = $request->query('tipo');
    $anio   = $request->query('anio');
    $mes    = $request->query('mes');
    $dia    = $request->query('dia');

    // Validar tipo
    if ($tipo && !in_array(strtolower($tipo), ['compra', 'venta'])) {
        return response()->json([
            'error' => 'El parámetro "tipo" debe ser "compra" o "venta".'
        ], 400);
    }

    // ¿Se pasó algún filtro de fecha?
    $conFiltroFecha = $anio || $mes || $dia;

    // ¿Se pasó algún filtro (fecha o moneda)?
    $conFiltros = $conFiltroFecha || $moneda;

    $query = Cotizacion::query();

    // Aplicar filtros
    if ($moneda) {
        $query->where('moneda', strtoupper($moneda));
    }
    if ($anio) {
        $query->whereYear('fecha', $anio);
    }
    if ($mes) {
        $query->whereMonth('fecha', $mes);
    }
    if ($dia) {
        $query->whereDay('fecha', $dia);
    }

    if ($conFiltros) {
        // 🟢 Modo histórico: devolver TODOS los registros que coincidan
        $resultados = $query->orderBy('fecha', 'desc')->orderBy('hora', 'desc')->get();
    } else {
        // 🔵 Modo por defecto: última cotización de cada moneda
        $subquery = Cotizacion::selectRaw('MAX(id) as max_id')
            ->groupBy('moneda');

        $resultados = Cotizacion::whereIn('id', $subquery)
            ->orderBy('moneda')
            ->get();
    }

    // Si se pide un tipo específico, formatear la respuesta
    if ($tipo) {
        $tipo = strtolower($tipo);
        $resultados = $resultados->map(function ($item) use ($tipo) {
            return [
                'moneda' => $item->moneda,
                'valor'  => (float) $item->$tipo,
                'tipo'   => $tipo,
                'fecha'  => $item->fecha,
                'hora'   => $item->hora,
            ];
        });
    }

    return response()->json($resultados);
}



    public function convertir(Request $request)
    {
        $from = strtoupper($request->query('from'));
        $to = strtoupper($request->query('to'));
        $amount = $request->query('amount');
        $tipo = strtolower($request->query('tipo', 'venta'));

        if (!$from || !$to || !$amount || !is_numeric($amount)) {
            return response()->json(['error' => 'Parámetros inválidos: from, to, amount'], 400);
        }

        if (!in_array($tipo, ['compra', 'venta'])) {
            return response()->json(['error' => 'Tipo inválido. Debe ser "compra" o "venta".'], 400);
        }

        $cotFrom = Cotizacion::where('moneda', $from)->latest('fecha')->first();
        $cotTo   = Cotizacion::where('moneda', $to)->latest('fecha')->first();

        if (!$cotFrom || !$cotTo) {
            return response()->json(['error' => 'Cotización no encontrada para una de las monedas'], 404);
        }

        $valorFrom = $cotFrom->$tipo;
        $valorTo   = $cotTo->$tipo;

        $resultado = ($amount * $valorFrom) / $valorTo;

        return response()->json([
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
            'tipo' => $tipo,
            'tasa_origen' => $valorFrom,
            'tasa_destino' => $valorTo,
            'resultado' => round($resultado, 2),
            'mensaje' => "Convertido usando: 1 {$from} = {$valorFrom} PYG, 1 {$to} = {$valorTo} PYG (tipo: {$tipo})"
        ]);
    }

    public function promedio(Request $request)
    {
        $moneda = strtoupper($request->query('moneda'));
        $tipo   = strtolower($request->query('tipo', 'venta'));
        $mes    = $request->query('mes', date('m'));
        $ano    = $request->query('ano', date('Y'));
        

        if (!in_array($tipo, ['compra', 'venta'])) {
            return response()->json(['error' => 'Tipo inválido: debe ser "compra" o "venta"'], 400);
        }

        $promedioMonedaPYG = Cotizacion::where('moneda', $moneda)
            ->whereYear('fecha', $ano)
            ->whereMonth('fecha', $mes)
            ->avg($tipo);

        if ($promedioMonedaPYG === null) {
            return response()->json([
                'message' => 'No hay datos para el período solicitado.',
                'moneda' => $moneda,
                'tipo' => $tipo,
                'mes' => $mes,
                'ano' => $ano
            ], 404);
        }

        $promedioARS_PYG = Cotizacion::where('moneda', 'ARS')
            ->whereYear('fecha', $ano)
            ->whereMonth('fecha', $mes)
            ->avg($tipo);

        if ($promedioARS_PYG === null || $promedioARS_PYG == 0) {
            return response()->json([
                'error' => 'No se puede calcular el promedio porque no hay datos válidos para ARS.',
                'moneda' => $moneda,
                'tipo' => $tipo,
                'mes' => $mes,
                'ano' => $ano
            ], 404);
        }

        $promedioEnARS = $promedioMonedaPYG / $promedioARS_PYG;

        return response()->json([
            'moneda' => $moneda,
            'tipo' => $tipo,
            'mes' => $mes,
            'ano' => $ano,
            'promedio' => round($promedioEnARS, 4),
            'detalle' => [
                'promedio_moneda_en_PYG' => $promedioMonedaPYG,
                'promedio_ARS_en_PYG' => $promedioARS_PYG,
                'formula' => "({$promedioMonedaPYG} ÷ {$promedioARS_PYG}) = {$promedioEnARS}",
                'mensaje' => "Este promedio está en Pesos Argentinos (ARS). 1 {$moneda} = {$promedioEnARS} ARS"
            ]
        ]);
    }

    // === SOLO LOS PERIODOS QUE USAS ===
    public function promedioDiario(Request $request)
    {
        return $this->getPromediosPorPeriodo($request, 'diario');
    }

    public function promedioMensual(Request $request)
    {
        return $this->getPromediosPorPeriodo($request, 'mensual');
    }

    public function promedioAnual(Request $request)
    {
        return $this->getPromediosPorPeriodo($request, 'anual');
    }

 
private function getPromediosPorPeriodo(Request $request, $periodo)
{
    $moneda = strtoupper($request->query('moneda'));
    $tipo   = strtolower($request->query('tipo', 'venta'));
    $ano    = $request->query('ano');
    $mes    = $request->query('mes');
    $dia    = $request->query('dia');

    if (!in_array($tipo, ['compra', 'venta'])) {
        return response()->json(['error' => 'Tipo inválido. Debe ser "compra" o "venta".'], 400);
    }

    // Selección dinámica del modelo
    switch ($periodo) {
        case 'diario':
            $model = CotizacionPromedioDiario::class;
            $campoFecha = 'fecha_referencia';
            break;
        case 'mensual':
            $model = CotizacionPromedioMensual::class;
            $campoFecha = 'fecha_referencia';
            break;
        case 'anual':
            $model = CotizacionPromedioAnual::class;
            $campoFecha = 'anio_referencia';
            break;
        default:
            return response()->json(['error' => 'Periodo inválido.'], 400);
    }

    $query = $model::where('tipo', $tipo);

    if ($moneda) {
        $query->where('moneda', $moneda);
    }

    // ✅ Filtros según el periodo
    if ($periodo === 'diario' && $ano && $mes && $dia) {
        $fecha = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        $query->whereDate($campoFecha, $fecha);
    }
    if ($periodo === 'mensual' && $ano && $mes) {
        $fecha = sprintf('%04d-%02d-01', $ano, $mes);
        $query->whereDate($campoFecha, $fecha);
    }
    if ($periodo === 'anual' && $ano) {
        $query->where($campoFecha, $ano);
    }

    $promedios = $query->orderBy($campoFecha, 'desc')->get();

    if ($promedios->isEmpty()) {
        return response()->json([
            'message' => 'No hay promedios para este período y moneda.',
            'periodo' => $periodo,
            'moneda'  => $moneda,
            'tipo'    => $tipo
        ], 404);
    }

    return response()->json([
        'periodo' => $periodo,
        'tipo'    => $tipo,
        'data'    => $promedios,
        'mensaje' => 'Promedios expresados en Pesos Argentinos (ARS).'
    ]);
}

    public function fluctuacion(Request $request)
{
    $moneda = strtoupper($request->query('moneda'));
    $tipo   = strtolower($request->query('tipo', 'venta'));
    $periodo = strtolower($request->query('periodo', 'mensual')); 
    $ano    = $request->query('ano', date('Y'));
    $mes    = $request->query('mes', date('m'));
    $dia    = $request->query('dia', date('d'));

    if (!in_array($tipo, ['compra', 'venta'])) {
        return response()->json(['error' => 'Tipo inválido: debe ser "compra" o "venta"'], 400);
    }

    // Construir query base
    $query = Cotizacion::where('moneda', $moneda);

    switch ($periodo) {
        case 'diario':
            $query->whereDate('fecha', "$ano-$mes-$dia");
            break;
        case 'mensual':
            $query->whereYear('fecha', $ano)
                  ->whereMonth('fecha', $mes);
            break;
        case 'anual':
            $query->whereYear('fecha', $ano);
            break;
        default:
            return response()->json(['error' => 'Periodo inválido: diario, mensual o anual'], 400);
    }

    $primer = (clone $query)->orderBy('fecha')->orderBy('hora')->first();
    $ultimo = (clone $query)->orderBy('fecha', 'desc')->orderBy('hora', 'desc')->first();

    if (!$primer || !$ultimo) {
        return response()->json([
            'message' => 'No hay datos para el período solicitado.',
            'moneda' => $moneda,
            'tipo'   => $tipo,
            'periodo'=> $periodo
        ], 404);
    }

    $valor_inicial = $primer->$tipo;
    $valor_final   = $ultimo->$tipo;

    $variacion = $valor_final - $valor_inicial;
    $tendencia = $valor_final > $valor_inicial ? 'suba' : ($valor_final < $valor_inicial ? 'baja' : 'estable');

    return response()->json([
        'moneda' => $moneda,
        'tipo'   => $tipo,
        'periodo'=> $periodo,
        'fecha_inicio' => $primer->fecha,
        'fecha_fin'    => $ultimo->fecha,
        'valor_inicial'=> $valor_inicial,
        'valor_final'  => $valor_final,
        'variacion'    => round($variacion, 4),
        'tendencia'    => $tendencia,
        'mensaje' => "En el período {$periodo}, la tendencia fue {$tendencia}."
    ]);
}
public function documentacion()
{
    $data = [
        'nombre' => 'API de Cotizaciones - Sistema Financiero',
        'version' => '1.1',
        'descripcion' => 'Esta API consume datos en tiempo real de Cambios Chaco (PYG) y los convierte a Pesos Argentinos (ARS) para ofrecer cotizaciones, conversiones, promedios y fluctuaciones.',
        'fuente_de_datos' => [
            'nombre' => 'Cambios Chaco',
            'url' => 'https://www.cambioschaco.com.py/api/branch_office/1/exchange',
            'moneda_base' => 'PYG (Guaraní paraguayo)',
            'frecuencia_actualizacion' => 'Manual o programada (vía comando Artisan)'
        ],
        'conversion_a_ars' => [
            'metodo' => 'División cruzada',
            'formula' => 'Valor_en_ARS = (Valor_moneda_en_PYG) / (Cotizacion_ARS_en_PYG)',
            'ejemplo' => 'Si USD = 7120 PYG y ARS = 5.6 PYG → 1 USD = 7120 / 5.6 = 1271.43 ARS'
        ],
        'endpoints' => [
            'actualizar' => [
                'ruta' => '/api/actualizar',
                'metodo' => 'POST',
                'descripcion' => 'Actualiza las cotizaciones desde Cambios Chaco',
                'ejemplo' => 'curl -X POST http://localhost:8000/api/actualizar'
            ],
            'cotizaciones' => [
                'ruta' => '/api/cotizaciones',
                'metodo' => 'GET',
                'descripcion' => 'Lista cotizaciones (últimas o filtradas por fecha/moneda)',
                'parametros' => [
                    'moneda' => 'USD, EUR, ARS, etc. (opcional)',
                    'anio' => '2025 (opcional)',
                    'mes' => '1-12 (opcional)',
                    'dia' => '1-31 (opcional)',
                    'tipo' => 'compra o venta (opcional)'
                ],
                'ejemplos' => [
                    'todas_las_ultimas' => '/api/cotizaciones',
                    'usd_hoy' => '/api/cotizaciones?moneda=USD&anio=2025&mes=9&dia=24',
                    'todas_en_septiembre' => '/api/cotizaciones?anio=2025&mes=9',
                    'solo_compra_ars' => '/api/cotizaciones?moneda=ARS&tipo=compra'
                ]
            ],
            'convertir' => [
                'ruta' => '/api/convertir',
                'metodo' => 'GET',
                'descripcion' => 'Convierte montos entre monedas usando cotizaciones en ARS',
                'parametros' => [
                    'from' => 'Moneda origen (ej: USD)',
                    'to' => 'Moneda destino (ej: ARS)',
                    'amount' => 'Monto a convertir',
                    'tipo' => 'compra o venta (por defecto: venta)'
                ],
                'ejemplo' => '/api/convertir?from=USD&to=ARS&amount=100&tipo=venta',
                'ejemplos' => [
                    'usd_a_ars_venta' => '/api/convertir?from=USD&to=ARS&amount=100&tipo=venta',
                    'eur_a_usd_compra' => '/api/convertir?from=EUR&to=USD&amount=50&tipo=compra'
                ]
            ],
            'promedios' => [
                'descripcion' => 'Promedios expresados en Pesos Argentinos (ARS)',
                'diario' => '/api/promedios/diario?moneda=USD&tipo=venta&ano=2025&mes=9&dia=24',
                'mensual' => '/api/promedios/mensual?moneda=USD&tipo=venta&ano=2025&mes=9',
                'anual' => '/api/promedios/anual?moneda=USD&tipo=venta&ano=2025',
                'ejemplos' => [
                    'promedio_usd_hoy' => '/api/promedios/diario?moneda=USD&tipo=venta&ano=2025&mes=9&dia=24',
                    'promedio_eur_septiembre' => '/api/promedios/mensual?moneda=EUR&tipo=compra&ano=2025&mes=9',
                    'promedio_ars_anual' => '/api/promedios/anual?moneda=ARS&tipo=venta&ano=2025'
                ]
            ],
            'fluctuacion' => [
                'ruta' => '/api/fluctuacion',
                'metodo' => 'GET',
                'descripcion' => 'Compara primer y último valor en un período',
                'parametros' => [
                    'moneda' => 'USD, EUR, etc.',
                    'tipo' => 'compra o venta',
                    'periodo' => 'diario, mensual o anual',
                    'ano' => '2025',
                    'mes' => '1-12 (solo si diario/mensual)',
                    'dia' => '1-31 (solo si diario)'
                ],
                'ejemplos' => [
                    'diario' => '/api/fluctuacion?moneda=USD&tipo=venta&periodo=diario&ano=2025&mes=9&dia=24',
                    'mensual' => '/api/fluctuacion?moneda=EUR&tipo=compra&periodo=mensual&ano=2025&mes=9',
                    'anual' => '/api/fluctuacion?moneda=ARS&tipo=venta&periodo=anual&ano=2025'
                ],
                'respuesta_ejemplo' => [
                    'moneda' => 'USD',
                    'tipo' => 'venta',
                    'periodo' => 'diario',
                    'fecha_inicio' => '2025-09-24',
                    'fecha_fin' => '2025-09-24',
                    'valor_inicial' => 7100,
                    'valor_final' => 7120,
                    'variacion' => 20,
                    'tendencia' => 'suba',
                    'mensaje' => 'En el período diario, la tendencia fue suba.'
                ]
            ]
        ],
        'monedas_soportadas' => [
            'ARS' => 'Peso Argentino',
            'AUD' => 'Dólar Australiano',
            'BOB' => 'Boliviano',
            'BRL' => 'Real Brasileño',
            'CAD' => 'Dólar Canadiense',
            'CHF' => 'Franco Suizo',
            'CLP' => 'Peso Chileno',
            'CNY' => 'Yuan Chino (Renminbi)',
            'COP' => 'Peso Colombiano',
            'DKK' => 'Corona Danesa',
            'EUR' => 'Euro',
            'GBP' => 'Libra Esterlina',
            'ILS' => 'Nuevo Shekel Israelí',
            'JPY' => 'Yen Japonés',
            'KWD' => 'Dinar Kuwaití',
            'MXN' => 'Peso Mexicano',
            'NOK' => 'Corona Noruega',
            'PEN' => 'Sol Peruano',
            'RUB' => 'Rublo Ruso',
            'SEK' => 'Corona Sueca',
            'TWD' => 'Nuevo Dólar Taiwanés',
            'USD' => 'Dólar Estadounidense',
            'UYU' => 'Peso Uruguayo',
            'ZAR' => 'Rand Sudafricano'
        ],
        'notas' => [
            'Todos los valores se expresan en Pesos Argentinos (ARS).',
            'La tendencia (suba/baja/estable) se calcula comparando con la última cotización del mismo día.',
            'La hora y fecha se registran en zona horaria de Argentina (America/Argentina/Buenos_Aires).'
        ]
    ];

    return response()->json($data, 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}


}
