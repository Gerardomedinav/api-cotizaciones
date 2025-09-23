<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionPromedio extends Model
{
    protected $table = 'cotizacion_promedios';
    protected $fillable = [
        'moneda', 'periodo', 'fecha_referencia', 'tipo', 
        'promedio_pyg', 'promedio_ars', 'tendencia'
    ];

    public $timestamps = true;

    // Índices únicos ya definidos en migración
}