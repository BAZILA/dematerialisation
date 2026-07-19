<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentRequestController;
use App\Http\Controllers\StudentDepotController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminRequestController;
use App\Http\Controllers\RequestTypeController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\AcademicController;
use App\Http\Controllers\AnneeController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProcedureController;
use App\Http\Controllers\PublicCandidatureController;
use App\Http\Controllers\CandidatureController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\PerimetreController;
use App\Http\Controllers\StatistiqueController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AuditController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/* ── Auth ────────────────────────────────────────────── */
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    // Anti-force brute : 5 tentatives / minute / IP
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register/verify', [AuthController::class, 'verifyIdentity'])->middleware('throttle:10,1')->name('register.verify');
    Route::get('/register/complete', [AuthController::class, 'showComplete'])->name('register.complete');
    Route::post('/register/complete', [AuthController::class, 'completeRegistration'])->middleware('throttle:10,1')->name('register.complete.post');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/* ── Candidature publique (soumission ANONYME, sans compte) ── */
Route::prefix('candidature')->name('candidature.')->group(function () {
    Route::get('/', [PublicCandidatureController::class, 'create'])->name('create');
    // Anti-spam : plafonner les soumissions et le suivi anonymes
    Route::post('/', [PublicCandidatureController::class, 'store'])->middleware('throttle:6,1')->name('store');
    Route::get('/confirmation/{reference}', [PublicCandidatureController::class, 'confirmation'])->name('confirmation');
    Route::get('/suivi', [PublicCandidatureController::class, 'suiviForm'])->name('suivi');
    Route::post('/suivi', [PublicCandidatureController::class, 'suivi'])->middleware('throttle:15,1')->name('suivi.post');
});

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
        Route::post('/cachet', [SuperAdminController::class, 'uploadCachet'])->name('cachet.upload');
        Route::delete('/cachet', [SuperAdminController::class, 'deleteCachet'])->name('cachet.delete');
        Route::get('/cachet', [SuperAdminController::class, 'cachet'])->name('cachet');
    });

    /* ─── ÉTUDIANT ───────────────────────────────────── */
    Route::middleware('role:etudiant')->prefix('student')->name('student.')->group(function () {
        Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
        Route::post('/contexte/annee', [StudentController::class, 'switchAnnee'])->name('contexte.annee');

        // Assistant de dépôt (contexte académique + études antérieures)
        Route::get('/depot/nouveau', [StudentDepotController::class, 'start'])->name('depot.start');
        Route::post('/depot/nouveau', [StudentDepotController::class, 'store'])->name('depot.store');
        Route::post('/depot/{studentRequest}/etudes', [StudentDepotController::class, 'saveEtudes'])->name('depot.etudes');

        // Demandes
        Route::post('/requests/create', [StudentRequestController::class, 'create'])->name('requests.create');
        Route::post('/requests/submit', [StudentRequestController::class, 'submit'])->name('requests.submit');
        // Annuler / recommencer un dépôt encore modifiable (avant validation)
        Route::delete('/requests/{studentRequest}/cancel', [StudentRequestController::class, 'cancelRequest'])->name('requests.cancel');
        Route::post('/requests/file/upload', [StudentRequestController::class, 'uploadFile'])->name('requests.file.upload');
        Route::delete('/requests/file/{file}', [StudentRequestController::class, 'deleteFile'])->name('requests.file.delete');
        Route::get('/requests/file/{file}/download', [StudentRequestController::class, 'downloadFile'])->name('requests.file.download');
    });

    /* ─── ADMINISTRATEUR ─────────────────────────────── */
    Route::middleware('permission:acceder-backoffice')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/contexte/annee', [AdminController::class, 'switchAnnee'])->name('contexte.annee');

        // Statistiques
        Route::get('/statistiques', [StatistiqueController::class, 'index'])->name('statistiques.index');

        // Journal d'audit (traçabilité)
        Route::middleware('permission:audit.consulter')->get('/audit', [AuditController::class, 'index'])->name('audit.index');

        // Archives numériques (rôle archiviste)
        Route::middleware('permission:archives.consulter')->prefix('archives')->name('archives.')->group(function () {
            Route::get('/', [ArchiveController::class, 'index'])->name('index');
            Route::get('/fiche/{file}', [ArchiveController::class, 'downloadFiche'])->name('fiche');
            Route::get('/dossier/{studentRequest}', [ArchiveController::class, 'downloadDossier'])->name('dossier');
            Route::match(['get', 'post'], '/lot', [ArchiveController::class, 'downloadLot'])->name('lot');
        });

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
            Route::post('/domaines', [AcademicController::class, 'storeDomaine'])->name('domaines.store');
            Route::put('/domaines/{domaine}', [AcademicController::class, 'updateDomaine'])->name('domaines.update');
            Route::delete('/domaines/{domaine}', [AcademicController::class, 'destroyDomaine'])->name('domaines.destroy');
        });

        // Années académiques & fenêtres d'ouverture
        Route::middleware('permission:annees.gerer')->prefix('annees')->name('annees.')->group(function () {
            Route::get('/', [AnneeController::class, 'index'])->name('index');
            Route::post('/', [AnneeController::class, 'store'])->name('store');
            Route::put('/{annee}', [AnneeController::class, 'update'])->name('update');
            Route::post('/{annee}/activer', [AnneeController::class, 'activate'])->name('activate');
            Route::post('/{annee}/cloner', [AnneeController::class, 'cloner'])->name('cloner');
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

        // Gestion des utilisateurs (personnel)
        Route::middleware('permission:utilisateurs.gerer')->prefix('utilisateurs')->name('utilisateurs.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
            Route::get('/{user}/signature', [UserController::class, 'signature'])->name('signature');
        });

        // Affectation des périmètres (agent ↔ parcours × semestre)
        Route::middleware('permission:utilisateurs.gerer')->prefix('perimetres')->name('perimetres.')->group(function () {
            Route::get('/', [PerimetreController::class, 'index'])->name('index');
            Route::post('/', [PerimetreController::class, 'store'])->name('store');
            Route::delete('/{perimetre}', [PerimetreController::class, 'destroy'])->name('destroy');
        });

        // Procédures / opérations dématérialisées (super-admin)
        Route::middleware('permission:parametres.gerer')->prefix('procedures')->name('procedures.')->group(function () {
            Route::get('/', [ProcedureController::class, 'index'])->name('index');
            Route::post('/', [ProcedureController::class, 'store'])->name('store');
            Route::put('/{procedure}', [ProcedureController::class, 'update'])->name('update');
            Route::delete('/{procedure}', [ProcedureController::class, 'destroy'])->name('destroy');
        });

        // Candidatures (traitement par l'agent candidatures)
        Route::middleware('permission:candidature.traiter')->prefix('candidatures')->name('candidatures.')->group(function () {
            Route::get('/', [CandidatureController::class, 'index'])->name('index');
            Route::get('/file/{file}/preview', [CandidatureController::class, 'previewFile'])->name('file.preview');
            Route::get('/file/{file}/download', [CandidatureController::class, 'downloadFile'])->name('file.download');
            Route::get('/{candidature}', [CandidatureController::class, 'show'])->name('show');
            Route::post('/{candidature}/statut', [CandidatureController::class, 'updateStatus'])->name('status');
        });

        // Rôles & permissions
        Route::middleware('permission:roles.gerer')->prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RolePermissionController::class, 'index'])->name('index');
            Route::post('/', [RolePermissionController::class, 'store'])->name('store');
            Route::put('/{role}', [RolePermissionController::class, 'update'])->name('update');
            Route::delete('/{role}', [RolePermissionController::class, 'destroy'])->name('destroy');
        });

        // Demandes & dépôts
        Route::get('/requests-export/{category?}', [AdminRequestController::class, 'export'])->name('requests.export');
        Route::get('/requests/{category?}', [AdminRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/detail/{studentRequest}', [AdminRequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{studentRequest}/validate', [AdminRequestController::class, 'validate'])->name('requests.validate');
        Route::post('/requests/{studentRequest}/reject', [AdminRequestController::class, 'reject'])->name('requests.reject');
        Route::post('/requests/{studentRequest}/reject-final', [AdminRequestController::class, 'rejectFinal'])->name('requests.reject-final');
        // Annuler une validation : rouvre le dépôt pour que l'étudiant recommence
        Route::post('/requests/{studentRequest}/reopen', [AdminRequestController::class, 'reopen'])->name('requests.reopen');
        Route::get('/requests/file/{file}/preview', [AdminRequestController::class, 'previewFile'])->name('requests.file.preview');
        Route::get('/requests/file/{file}/download', [AdminRequestController::class, 'downloadFile'])->name('requests.file.download');
    });
});
