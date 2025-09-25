<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    protected $fillable = [
        'moneda',
        'compra',
        'venta',
        'tendencia',
        'fecha',
        'hora',
    ];

    protected $casts = [
        'compra' => 'float',
        'venta'  => 'float',
    ];
}