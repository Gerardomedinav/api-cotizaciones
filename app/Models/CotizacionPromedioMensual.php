<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionPromedioMensual extends Model
{
    protected $table = 'cotizacion_promedios_mensuales';
    protected $fillable = [
        'moneda', 'fecha_referencia', 'tipo',
        'promedio_pyg', 'promedio_ars', 'tendencia'
    ];

    public $timestamps = true;
}