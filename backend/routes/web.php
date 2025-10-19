<?php

use App\Http\Controllers\ExpedienteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/expedientes');

Route::get('/preview', fn () => view('preview'))->name('preview');
Route::redirect('/dashboard', '/expedientes');

Route::get('/expedientes', [ExpedienteController::class, 'index'])
    ->name('expedientes.index');
