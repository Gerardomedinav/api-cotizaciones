<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones'; // 👈 Forzar nombre correcto
    protected $fillable = ['isoCode', 'compra', 'venta', 'fecha'];
}
