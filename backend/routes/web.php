<?php

use App\Http\Controllers\AnexoController;
use App\Http\Controllers\ConsentimientoPdfController;
use App\Http\Controllers\ConsentimientoRequeridoController;
use App\Http\Controllers\ConsentimientoUploadController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SesionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard.index')->name('dashboard');
    Route::post('expedientes/{expediente}/estado', [ExpedienteController::class, 'changeState'])
        ->name('expedientes.change-state');

    Route::get('expedientes/{expediente}/consentimientos/pdf', ConsentimientoPdfController::class)
        ->name('expedientes.consentimientos.pdf');

    Route::post('consentimientos/{consentimiento}/archivo', [ConsentimientoUploadController::class, 'store'])
        ->name('consentimientos.upload');

    Route::post('expedientes/{expediente}/anexos', [AnexoController::class, 'store'])
        ->name('expedientes.anexos.store');
    Route::get('expedientes/{expediente}/anexos/{anexo}/preview', [AnexoController::class, 'preview'])
        ->middleware('signed')
        ->name('expedientes.anexos.preview');

    Route::get('expedientes/{expediente}/anexos/{anexo}', [AnexoController::class, 'show'])
        ->middleware('signed')
        ->name('expedientes.anexos.show');
    Route::delete('expedientes/{expediente}/anexos/{anexo}', [AnexoController::class, 'destroy'])
        ->name('expedientes.anexos.destroy');

    Route::post('expedientes/{expediente}/sesiones/{sesion}/observe', [SesionController::class, 'observe'])
        ->name('expedientes.sesiones.observe');
    Route::post('expedientes/{expediente}/sesiones/{sesion}/validate', [SesionController::class, 'validateSesion'])
        ->name('expedientes.sesiones.validate');

    Route::resource('expedientes', ExpedienteController::class)->middleware('auth');
    Route::resource('expedientes.sesiones', SesionController::class)
        ->parameters(['sesiones' => 'sesion'])
        ->middleware('auth');

    Route::get('consentimientos/requeridos', [ConsentimientoRequeridoController::class, 'index'])
        ->name('consentimientos.requeridos.index');
    Route::put('consentimientos/requeridos', [ConsentimientoRequeridoController::class, 'update'])
        ->name('consentimientos.requeridos.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
