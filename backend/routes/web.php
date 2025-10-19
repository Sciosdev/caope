<?php

use Illuminate\Support\Facades\Route;

// Página de preview (usa resources/views/preview.blade.php)
Route::view('/preview', 'preview')->name('preview');

// Deja el preview como página de inicio temporal
Route::view('/', 'preview');

// (Opcional) Mantén la vista de bienvenida accesible en /welcome
Route::view('/welcome', 'welcome');