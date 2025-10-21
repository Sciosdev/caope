<?php

use App\Http\Controllers\ComentarioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::get('/comentarios', [ComentarioController::class, 'index'])->name('api.comentarios.index');
    Route::post('/comentarios', [ComentarioController::class, 'store'])->name('api.comentarios.store');
    Route::get('/comentarios/{comentario}', [ComentarioController::class, 'show'])->name('api.comentarios.show');
    Route::put('/comentarios/{comentario}', [ComentarioController::class, 'update'])->name('api.comentarios.update');
    Route::delete('/comentarios/{comentario}', [ComentarioController::class, 'destroy'])->name('api.comentarios.destroy');
});
