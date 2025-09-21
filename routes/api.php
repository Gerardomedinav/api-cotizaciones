<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CotizacionController;

Route::post('/actualizar', [CotizacionController::class, 'actualizar']);
Route::get('/cotizaciones', [CotizacionController::class, 'index']);
Route::get('/convertir', [CotizacionController::class, 'convertir']);
Route::get('/promedio', [CotizacionController::class, 'promedio']);
