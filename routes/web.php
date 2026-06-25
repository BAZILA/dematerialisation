<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentRequestController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminRequestController;
use App\Http\Controllers\RequestTypeController;
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

    /* ─── ÉTUDIANT ───────────────────────────────────── */
    Route::middleware('role:student')->prefix('student')->name('student.')->group(function () {
        Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');

        // Demandes (nouveau flux)
        Route::post('/requests/create', [StudentRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests/submit', [StudentRequestController::class, 'submit'])->name('requests.submit');
        Route::post('/requests/file/upload', [StudentRequestController::class, 'uploadFile'])->name('requests.file.upload');
        Route::delete('/requests/file/{file}', [StudentRequestController::class, 'deleteFile'])->name('requests.file.delete');
        Route::get('/requests/file/{file}/download', [StudentRequestController::class, 'downloadFile'])->name('requests.file.download');

        // Ancien flux Submission (conservé)
        Route::post('/submission/create', [StudentController::class, 'createSubmission'])->name('submission.create');
        Route::post('/submission/{submission}/submit', [StudentController::class, 'submitSubmission'])->name('submission.submit');
        Route::post('/file/upload', [StudentController::class, 'uploadFile'])->name('file.upload');
        Route::delete('/file/{file}/delete', [StudentController::class, 'deleteFile'])->name('file.delete');
        Route::get('/file/{file}/download', [StudentController::class, 'downloadFile'])->name('file.download');
        Route::post('/academic-history/save', [StudentController::class, 'saveAcademicHistory'])->name('academic.save');
        Route::delete('/academic-history/{id}/delete', [StudentController::class, 'deleteAcademicHistory'])->name('academic.delete');
    });

    /* ─── ADMINISTRATEUR ─────────────────────────────── */
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

        // CRUD types de fichiers
        Route::get('/request-types', [RequestTypeController::class, 'index'])->name('request-types.index');
        Route::post('/request-types', [RequestTypeController::class, 'store'])->name('request-types.store');
        Route::put('/request-types/{requestType}', [RequestTypeController::class, 'update'])->name('request-types.update');
        Route::delete('/request-types/{requestType}', [RequestTypeController::class, 'destroy'])->name('request-types.destroy');
        Route::patch('/request-types/{requestType}/toggle', [RequestTypeController::class, 'toggleActive'])->name('request-types.toggle');


        // CRUD types de fichiers
        Route::get(
            '/request-types',
            [RequestTypeController::class, 'index']
        )->name('request-types.index');

        Route::post(
            '/request-types',
            [RequestTypeController::class, 'store']
        )->name('request-types.store');

        Route::put(
            '/request-types/{requestType}',
            [RequestTypeController::class, 'update']
        )->name('request-types.update');

        Route::delete(
            '/request-types/{requestType}',
            [RequestTypeController::class, 'destroy']
        )->name('request-types.destroy');

        Route::patch(
            '/request-types/{requestType}/toggle',
            [RequestTypeController::class, 'toggleActive']
        )->name('request-types.toggle');

        // Optionnel : réordonner via drag-and-drop
        Route::post(
            '/request-types/reorder',
            [RequestTypeController::class, 'reorder']
        )->name('request-types.reorder');

        // Demandes (nouveau flux)
        Route::get('/requests/{category?}', [AdminRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/detail/{studentRequest}', [AdminRequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{studentRequest}/validate', [AdminRequestController::class, 'validate'])->name('requests.validate');
        Route::post('/requests/{studentRequest}/reject', [AdminRequestController::class, 'reject'])->name('requests.reject');
        Route::post('/requests/{studentRequest}/reject-final', [AdminRequestController::class, 'rejectFinal'])->name('requests.reject-final');
        Route::get('/requests/file/{file}/preview', [AdminRequestController::class, 'previewFile'])->name('requests.file.preview');
        Route::get('/requests/file/{file}/download', [AdminRequestController::class, 'downloadFile'])->name('requests.file.download');

        // Ancien flux Submission (conservé)
        Route::get('/submission/{submission}', [AdminController::class, 'show'])->name('submission.show');
        Route::post('/submission/{submission}/validate', [AdminController::class, 'validate'])->name('submission.validate');
        Route::post('/submission/{submission}/reject', [AdminController::class, 'reject'])->name('submission.reject');
        Route::get('/file/{file}/preview', [AdminController::class, 'previewFile'])->name('file.preview');
        Route::get('/file/{file}/download', [AdminController::class, 'downloadFile'])->name('file.download');
    });
});
