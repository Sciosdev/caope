<?php

use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SesionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard.index')->name('dashboard');
    Route::post('expedientes/{expediente}/estado', [ExpedienteController::class, 'changeState'])
        ->name('expedientes.change-state');

    Route::resource('expedientes', ExpedienteController::class)->middleware('auth');
    Route::resource('expedientes.sesiones', SesionController::class)->middleware('auth');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
