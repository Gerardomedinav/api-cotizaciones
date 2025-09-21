<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Cotizacion;

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
}