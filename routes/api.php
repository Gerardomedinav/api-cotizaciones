<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CotizacionController;

Route::get('/actualizar', [CotizacionController::class, 'actualizar']);
Route::get('/cotizaciones', [CotizacionController::class, 'index']);
Route::get('/convertir', [CotizacionController::class, 'convertir']);
Route::get('/promedio', [CotizacionController::class, 'promedio']);
Route::get('/promedios/diario', [CotizacionController::class, 'promedioDiario']);
Route::get('/promedios/mensual', [CotizacionController::class, 'promedioMensual']);
Route::get('/promedios/anual', [CotizacionController::class, 'promedioAnual']);
Route::get('/fluctuacion', [CotizacionController::class, 'fluctuacion']);
Route::get('/docs', [CotizacionController::class, 'documentacion']);
