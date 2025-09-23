<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CotizacionController;

Route::post('/actualizar', [CotizacionController::class, 'actualizar']);
Route::get('/cotizaciones', [CotizacionController::class, 'index']);
Route::get('/convertir', [CotizacionController::class, 'convertir']);
Route::get('/promedio', [CotizacionController::class, 'promedio']);
Route::get('/promedios/diario', [CotizacionController::class, 'promedioDiario']);
Route::get('/promedios/mensual', [CotizacionController::class, 'promedioMensual']);
Route::get('/promedios/anual', [CotizacionController::class, 'promedioAnual']);
Route::get('/fluctuacion', [CotizacionController::class, 'fluctuacion']);
