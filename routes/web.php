<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentRequestController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminRequestController;
use App\Http\Controllers\RequestTypeController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\AcademicController;
use App\Http\Controllers\AnneeController;
use App\Http\Controllers\IdentityController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/* ── Auth ────────────────────────────────────────────── */
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register/verify', [AuthController::class, 'verifyIdentity'])->name('register.verify');
    Route::get('/register/complete', [AuthController::class, 'showComplete'])->name('register.complete');
    Route::post('/register/complete', [AuthController::class, 'completeRegistration'])->name('register.complete.post');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/* ── Routes protégées ────────────────────────────────── */
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('student.dashboard');
    })->name('dashboard');

    /* ─── SUPER-ADMIN ─────────────────────────────────── */
    Route::middleware('role:super_admin')->prefix('super-admin')->name('superadmin.')->group(function () {
        Route::get('/parametres', [SuperAdminController::class, 'settings'])->name('settings');
        Route::post('/parametres', [SuperAdminController::class, 'updateSettings'])->name('settings.update');
    });

    /* ─── ÉTUDIANT ───────────────────────────────────── */
    Route::middleware('role:etudiant')->prefix('student')->name('student.')->group(function () {
        Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');

        // Demandes
        Route::post('/requests/create', [StudentRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests/submit', [StudentRequestController::class, 'submit'])->name('requests.submit');
        Route::post('/requests/file/upload', [StudentRequestController::class, 'uploadFile'])->name('requests.file.upload');
        Route::delete('/requests/file/{file}', [StudentRequestController::class, 'deleteFile'])->name('requests.file.delete');
        Route::get('/requests/file/{file}/download', [StudentRequestController::class, 'downloadFile'])->name('requests.file.download');
    });

    /* ─── ADMINISTRATEUR ─────────────────────────────── */
    Route::middleware('permission:acceder-backoffice')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

        // CRUD types de documents
        Route::get('/request-types', [RequestTypeController::class, 'index'])->name('request-types.index');
        Route::post('/request-types', [RequestTypeController::class, 'store'])->name('request-types.store');
        Route::put('/request-types/{requestType}', [RequestTypeController::class, 'update'])->name('request-types.update');
        Route::delete('/request-types/{requestType}', [RequestTypeController::class, 'destroy'])->name('request-types.destroy');
        Route::patch('/request-types/{requestType}/toggle', [RequestTypeController::class, 'toggleActive'])->name('request-types.toggle');

        // Référentiel académique
        Route::middleware('permission:academique.gerer')->prefix('academique')->name('academique.')->group(function () {
            Route::get('/', [AcademicController::class, 'index'])->name('index');
            Route::post('/facultes', [AcademicController::class, 'storeFaculte'])->name('facultes.store');
            Route::put('/facultes/{faculte}', [AcademicController::class, 'updateFaculte'])->name('facultes.update');
            Route::delete('/facultes/{faculte}', [AcademicController::class, 'destroyFaculte'])->name('facultes.destroy');
            Route::post('/departements', [AcademicController::class, 'storeDepartement'])->name('departements.store');
            Route::put('/departements/{departement}', [AcademicController::class, 'updateDepartement'])->name('departements.update');
            Route::delete('/departements/{departement}', [AcademicController::class, 'destroyDepartement'])->name('departements.destroy');
            Route::post('/parcours', [AcademicController::class, 'storeParcours'])->name('parcours.store');
            Route::put('/parcours/{parcours}', [AcademicController::class, 'updateParcours'])->name('parcours.update');
            Route::delete('/parcours/{parcours}', [AcademicController::class, 'destroyParcours'])->name('parcours.destroy');
        });

        // Années académiques & fenêtres d'ouverture
        Route::middleware('permission:annees.gerer')->prefix('annees')->name('annees.')->group(function () {
            Route::get('/', [AnneeController::class, 'index'])->name('index');
            Route::post('/', [AnneeController::class, 'store'])->name('store');
            Route::put('/{annee}', [AnneeController::class, 'update'])->name('update');
            Route::post('/{annee}/activer', [AnneeController::class, 'activate'])->name('activate');
            Route::delete('/{annee}', [AnneeController::class, 'destroy'])->name('destroy');
            Route::post('/{annee}/periodes', [AnneeController::class, 'savePeriode'])->name('periodes.save');
            Route::delete('/periodes/{periode}', [AnneeController::class, 'destroyPeriode'])->name('periodes.destroy');
        });

        // Identités — import / export
        Route::middleware('permission:identites.importer')->prefix('identites')->name('identites.')->group(function () {
            Route::get('/', [IdentityController::class, 'index'])->name('index');
            Route::post('/bacheliers/import', [IdentityController::class, 'importBacheliers'])->name('bacheliers.import');
            Route::get('/bacheliers/export', [IdentityController::class, 'exportBacheliers'])->name('bacheliers.export');
            Route::get('/bacheliers/modele', [IdentityController::class, 'templateBacheliers'])->name('bacheliers.template');
            Route::post('/anciens/import', [IdentityController::class, 'importAnciens'])->name('anciens.import');
            Route::get('/anciens/export', [IdentityController::class, 'exportAnciens'])->name('anciens.export');
            Route::get('/anciens/modele', [IdentityController::class, 'templateAnciens'])->name('anciens.template');
        });

        // Demandes & dépôts
        Route::get('/requests/{category?}', [AdminRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/detail/{studentRequest}', [AdminRequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{studentRequest}/validate', [AdminRequestController::class, 'validate'])->name('requests.validate');
        Route::post('/requests/{studentRequest}/reject', [AdminRequestController::class, 'reject'])->name('requests.reject');
        Route::post('/requests/{studentRequest}/reject-final', [AdminRequestController::class, 'rejectFinal'])->name('requests.reject-final');
        Route::get('/requests/file/{file}/preview', [AdminRequestController::class, 'previewFile'])->name('requests.file.preview');
        Route::get('/requests/file/{file}/download', [AdminRequestController::class, 'downloadFile'])->name('requests.file.download');
    });
});
