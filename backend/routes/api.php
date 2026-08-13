<?php

use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\DeploymentPreparationController;
use App\Http\Controllers\DeploymentVersionController;
use Illuminate\Support\Facades\Route;

Route::get('/deployment/version', DeploymentVersionController::class)
    ->middleware('throttle:60,1')
    ->name('api.deployment.version');
Route::post('/deployment/prepare', DeploymentPreparationController::class)
    ->middleware('throttle:10,1')
    ->name('api.deployment.prepare');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/comentarios', [ComentarioController::class, 'index'])->name('api.comentarios.index');
    Route::post('/comentarios', [ComentarioController::class, 'store'])->name('api.comentarios.store');
    Route::get('/comentarios/{comentario}', [ComentarioController::class, 'show'])->name('api.comentarios.show');
    Route::put('/comentarios/{comentario}', [ComentarioController::class, 'update'])->name('api.comentarios.update');
    Route::delete('/comentarios/{comentario}', [ComentarioController::class, 'destroy'])->name('api.comentarios.destroy');
});
