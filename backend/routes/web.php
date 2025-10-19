<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExpedienteController;

Route::view('/', 'welcome');

Route::get('/preview', fn() => view('preview'))->name('preview');

// Dashboard de ejemplo (si ya lo tenías)
Route::view('/dashboard', 'dashboard')->name('dashboard');

Route::redirect('/dashboard', '/expedientes');

// Expedientes
Route::get('/expedientes', [ExpedienteController::class, 'index'])->name('expedientes.index');
Route::get('/', fn () => redirect()->route('expedientes.index'));