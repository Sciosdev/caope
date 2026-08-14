<?php

use App\Http\Controllers\DeploymentPreparationController;
use App\Http\Controllers\DeploymentVersionController;
use Illuminate\Support\Facades\Route;

Route::get('/deployment/version', DeploymentVersionController::class)
    ->middleware('throttle:60,1')
    ->name('api.deployment.version');
Route::post('/deployment/prepare', DeploymentPreparationController::class)
    ->middleware('throttle:6,1')
    ->name('api.deployment.prepare');
