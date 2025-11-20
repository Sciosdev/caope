<?php

use App\Http\Controllers\Admin\CatalogoCarreraController;
use App\Http\Controllers\Admin\CatalogoPadecimientoController;
use App\Http\Controllers\Admin\CatalogoTratamientoController;
use App\Http\Controllers\Admin\CatalogoTurnoController;
use App\Http\Controllers\Admin\ParametrosController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnexoController;
use App\Http\Controllers\ConsentimientoPdfController;
use App\Http\Controllers\ConsentimientoRequeridoController;
use App\Http\Controllers\ConsentimientoUploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteExpedienteController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\TimelineEventoExportController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/pendientes', [DashboardController::class, 'pending'])->name('dashboard.pending');
    Route::get('/dashboard/metricas', [DashboardController::class, 'metrics'])->name('dashboard.metrics');
    Route::get('/dashboard/alertas', [DashboardController::class, 'alerts'])->name('dashboard.alerts');
    Route::post('expedientes/{expediente}/estado', [ExpedienteController::class, 'changeState'])
        ->name('expedientes.change-state');

    Route::prefix('admin/usuarios')->name('admin.users.')->middleware('role:admin')->group(function (): void {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('crear', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('{user}/editar', [UserController::class, 'edit'])->name('edit');
        Route::put('{user}', [UserController::class, 'update'])->name('update');
        Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin/catalogos')->name('admin.catalogos.')->middleware('role:admin')->group(function (): void {
        Route::resource('carreras', CatalogoCarreraController::class)->except('show');
        Route::resource('tratamientos', CatalogoTratamientoController::class)->except('show');
        Route::resource('padecimientos', CatalogoPadecimientoController::class)->except('show');
        Route::resource('turnos', CatalogoTurnoController::class)->except('show');
    });

    Route::prefix('admin/parametros')->name('admin.parametros.')->middleware('role:admin')->group(function (): void {
        Route::get('/', [ParametrosController::class, 'index'])->name('index');
        Route::put('{parametro}', [ParametrosController::class, 'update'])->name('update');
    });

    Route::middleware('role:admin|coordinador')->group(function (): void {
        Route::get('reportes/expedientes', [ReporteExpedienteController::class, 'index'])->name('reportes.index');
        Route::post('reportes/expedientes/export', [ReporteExpedienteController::class, 'export'])->name('reportes.expedientes.export');
        Route::get('reportes/expedientes/export/{token}', [ReporteExpedienteController::class, 'download'])->name('reportes.expedientes.download');
        Route::get('reportes/expedientes/export/{token}/status', [ReporteExpedienteController::class, 'status'])->name('reportes.expedientes.export.status');
    });

    Route::get('expedientes/{expediente}/consentimientos/pdf', ConsentimientoPdfController::class)
        ->name('expedientes.consentimientos.pdf');

    Route::post('expedientes/{expediente}/timeline/export', [TimelineEventoExportController::class, 'export'])
        ->name('expedientes.timeline.export');
    Route::get('expedientes/{expediente}/timeline/export/{token}/status', [TimelineEventoExportController::class, 'status'])
        ->name('expedientes.timeline.export.status');
    Route::get('expedientes/{expediente}/timeline/export/{token}', [TimelineEventoExportController::class, 'download'])
        ->name('expedientes.timeline.export.download');

    Route::post('consentimientos/{consentimiento}/archivo', [ConsentimientoUploadController::class, 'store'])
        ->middleware('throttle:uploads.consentimientos')
        ->name('consentimientos.upload');
    Route::get('consentimientos/{consentimiento}/archivo', [ConsentimientoUploadController::class, 'show'])
        ->name('consentimientos.archivo');
    Route::post('expedientes/{expediente}/consentimientos/observaciones', [ConsentimientoUploadController::class, 'storeObservaciones'])
        ->middleware('throttle:uploads.consentimientos')
        ->name('expedientes.consentimientos.observaciones');
    Route::get('expedientes/{expediente}/consentimientos/observaciones', [ConsentimientoUploadController::class, 'showObservaciones'])
        ->name('expedientes.consentimientos.observaciones.show');

    Route::post('expedientes/{expediente}/anexos', [AnexoController::class, 'store'])
        ->middleware('throttle:uploads.anexos')
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
