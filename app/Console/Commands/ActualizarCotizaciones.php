<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Cotizacion;

class ActualizarCotizaciones extends Command
{
    protected $signature = 'cotizaciones:actualizar';
    protected $description = 'Consulta la API de Cambios Chaco y guarda cotizaciones solo si hay cambios';

    public function handle()
    {
        $url = config('services.cambioschaco.url');
        $response = Http::timeout(15)->get($url);

        if ($response->failed()) {
            $this->error("❌ Error al consultar la API: {$response->status()}");
            return 1;
        }

        $data = $response->json();
        $items = $data['items'] ?? $data['cotizaciones'] ?? [];

        if (empty($items)) {
            $this->warn('⚠️ No se encontraron cotizaciones en la respuesta');
            return 1;
        }

        foreach ($items as $item) {
            $moneda = $item['isoCode'] ?? null;
            $nuevaCompra = round($item['purchasePrice'] ?? 0, 2);
            $nuevaVenta  = round($item['salePrice'] ?? 0, 2);

            if (!$moneda) continue;

            // Última cotización de HOY para esta moneda
            $ultima = Cotizacion::where('moneda', $moneda)
                ->whereDate('fecha', now()->toDateString())
                ->latest('hora')
                ->first();

            $tendencia = 'estable';
            if ($ultima) {
                if ($nuevaVenta > $ultima->venta) {
                    $tendencia = 'suba';
                } elseif ($nuevaVenta < $ultima->venta) {
                    $tendencia = 'baja';
                }
            }

            // Solo guardar si hay cambios
            if (!$ultima || $ultima->compra != $nuevaCompra || $ultima->venta != $nuevaVenta) {
                Cotizacion::create([
                    'moneda'    => $moneda,
                    'compra'    => $nuevaCompra,
                    'venta'     => $nuevaVenta,
                    'fecha'     => now()->toDateString(),
                    'hora'      => now()->toTimeString('H:i:s'), // Formato limpio
                    'tendencia' => $tendencia,
                ]);

                $this->info("✅ {$moneda} guardado ({$tendencia}) → C: {$nuevaCompra} | V: {$nuevaVenta}");
            } else {
                $this->line("⏩ {$moneda} sin cambios");
            }
        }

        $this->info('✅ Actualización completada');
        return 0;
    }
}