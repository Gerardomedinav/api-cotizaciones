<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionPromedioAnual extends Model
{
    protected $table = 'cotizacion_promedios_anuales';
    protected $fillable = [
        'moneda',
        'anio_referencia',  
        'tipo',
        'promedio_pyg',
        'promedio_ars',
        'tendencia'
    ];

    public $timestamps = true;
}