<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Cotizacion;

class ActualizarCotizaciones extends Command
{
    protected $signature = 'cotizaciones:actualizar';
    protected $description = 'Consulta la API de Cambios Chaco y guarda cotizaciones minuto a minuto si hay cambios';

    public function handle()
    {
        $url = config('services.cambioschaco.url');
        $response = Http::get($url);

        if ($response->failed()) {
            $this->error('❌ Error al consultar la API');
            return 1;
        }

        $data = $response->json();
        $items = $data['items'] ?? $data['cotizaciones'] ?? [];

        foreach ($items as $item) {
            $nuevaCompra = round($item['purchasePrice'], 2);
            $nuevaVenta  = round($item['salePrice'], 2);

            // Última cotización registrada hoy para esa moneda
            $ultima = Cotizacion::where('moneda', $item['isoCode'])
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

            // Solo guardar si hubo cambio
            if (!$ultima || $ultima->compra != $nuevaCompra || $ultima->venta != $nuevaVenta) {
                Cotizacion::create([
                    'moneda'    => $item['isoCode'],
                    'compra'    => $nuevaCompra,
                    'venta'     => $nuevaVenta,
                    'fecha'     => now()->toDateString(),
                    'hora'      => now()->toTimeString(),
                    'tendencia' => $tendencia,
                ]);

                $this->info("✅ {$item['isoCode']} guardado ({$tendencia}) Compra={$nuevaCompra} Venta={$nuevaVenta}");
            } else {
                $this->line("⏩ {$item['isoCode']} sin cambios");
            }
        }

        return 0;
    }
}
