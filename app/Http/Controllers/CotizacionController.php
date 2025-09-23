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
        $response = Http::get($url);

        if ($response->failed()) {
            return response()->json(['error' => 'Error al consultar la API externa'], 500);
        }

        $data = $response->json();
        $items = $data['items'] ?? $data['cotizaciones'] ?? [];

        $cotizaciones = [];
        foreach ($items as $item) {
            $cotizaciones[] = [
                'moneda' => $item['isoCode'],
                'compra' => $item['purchasePrice'],
                'venta'  => $item['salePrice'],
                'fecha'  => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Cotizacion::insert($cotizaciones);

        return response()->json([
            'message' => 'Cotizaciones guardadas correctamente',
            'count'   => count($cotizaciones)
        ]);
    }

    public function index()
    {
        return Cotizacion::orderBy('fecha', 'desc')->get();
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


}
