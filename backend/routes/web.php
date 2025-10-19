<?php

use Illuminate\Support\Facades\Route;

// Mantén tu /preview si quieres seguir probando el standalone
Route::view('/preview', 'preview')->name('preview');

// Dashboard como home
Route::redirect('/', '/dashboard');
Route::view('/dashboard', 'dashboard.index')->name('dashboard');
