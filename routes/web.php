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
    /* Acces des usagers par code, sans mot de passe. Les cadences sont
       serrees : le code est court, et devine-t-on lentement ou pas du tout. */
    Route::get('/acces', [\App\Http\Controllers\AccesController::class, 'identite'])->name('acces.identite');
    Route::post('/acces', [\App\Http\Controllers\AccesController::class, 'verifierIdentite'])
        ->middleware('throttle:8,1')->name('acces.identite.post');
    Route::get('/acces/code', [\App\Http\Controllers\AccesController::class, 'code'])->name('acces.code');
    Route::post('/acces/code', [\App\Http\Controllers\AccesController::class, 'verifierCode'])
        ->middleware('throttle:10,1')->name('acces.code.post');
    Route::post('/acces/renvoyer', [\App\Http\Controllers\AccesController::class, 'renvoyer'])
        ->middleware('throttle:4,1')->name('acces.renvoyer');

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
    // Plafonné : sans cadence, cette page permettait d'essayer des
    // références à la chaîne — elle affiche l'adresse du candidat.
    Route::get('/confirmation/{reference}', [PublicCandidatureController::class, 'confirmation'])
        ->middleware('throttle:20,1')->name('confirmation');
    Route::get('/suivi', [PublicCandidatureController::class, 'suiviForm'])->name('suivi');
    Route::post('/suivi', [PublicCandidatureController::class, 'suivi'])->middleware('throttle:15,1')->name('suivi.post');
    // Complétion du dossier (statut « À compléter ») : référence + date de naissance
    Route::post('/completer', [PublicCandidatureController::class, 'completer'])->middleware('throttle:10,1')->name('completer');
    // Postuler à un parcours supplémentaire (pièces communes reprises)
    Route::post('/parcours', [PublicCandidatureController::class, 'ajouterParcours'])
        ->middleware('throttle:10,1')->name('parcours.ajouter');
    // Déclaration d'un versement (frais de candidature)
    Route::post('/paiement', [PublicCandidatureController::class, 'declarerPaiement'])
        ->middleware(['throttle:10,1', 'module:payments.enabled'])->name('paiement');
});

/* ── Routes protégées ────────────────────────────────── */
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('student.dashboard');
    })->name('dashboard');

    /* ─── Mon compte : chacun change son propre mot de passe ── */
    Route::middleware(\App\Http\Middleware\EnsureTwoFactor::class)->group(function () {
        Route::get('/compte/mot-de-passe', [\App\Http\Controllers\CompteController::class, 'motDePasse'])
            ->name('compte.mot-de-passe');
        Route::post('/compte/mot-de-passe', [\App\Http\Controllers\CompteController::class, 'changerMotDePasse'])
            ->middleware('throttle:6,1')->name('compte.mot-de-passe.changer');
    });

    /* ─── 2FA : challenge après login (hors du garde 2FA lui-même) ── */
    Route::get('/2fa/challenge', [\App\Http\Controllers\TwoFactorController::class, 'challenge'])->name('2fa.challenge');
    Route::post('/2fa/challenge', [\App\Http\Controllers\TwoFactorController::class, 'verify'])
        ->middleware('throttle:5,1')->name('2fa.verify');

    /* ─── SUPER-ADMIN ─────────────────────────────────── */
    Route::middleware(['role:super_admin', \App\Http\Middleware\EnsureTwoFactor::class])->prefix('super-admin')->name('superadmin.')->group(function () {
        Route::get('/parametres', [SuperAdminController::class, 'settings'])->name('settings');
        Route::post('/parametres', [SuperAdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/cachet', [SuperAdminController::class, 'uploadCachet'])->name('cachet.upload');
        Route::delete('/cachet', [SuperAdminController::class, 'deleteCachet'])->name('cachet.delete');
        Route::get('/cachet', [SuperAdminController::class, 'cachet'])->name('cachet');

        // Sauvegardes : une archive contient toute la base et toutes les pièces
        Route::prefix('sauvegardes')->name('sauvegardes.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SauvegardeController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\SauvegardeController::class, 'executer'])
                ->middleware('throttle:5,10')->name('executer');
            Route::get('/{nom}/telecharger', [\App\Http\Controllers\SauvegardeController::class, 'telecharger'])->name('telecharger');
            Route::delete('/{nom}', [\App\Http\Controllers\SauvegardeController::class, 'supprimer'])->name('supprimer');
        });
    });

    /* ─── ÉTUDIANT ───────────────────────────────────── */
    Route::middleware('role:etudiant')->prefix('student')->name('student.')->group(function () {
        Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
        Route::post('/contexte/annee', [StudentController::class, 'switchAnnee'])->name('contexte.annee');

        // Assistant de dépôt (contexte académique + études antérieures)
        Route::get('/depot/nouveau', [StudentDepotController::class, 'start'])->name('depot.start');
        Route::post('/depot/nouveau', [StudentDepotController::class, 'store'])
            ->middleware('throttle:10,1')->name('depot.store');
        Route::post('/depot/{studentRequest}/etudes', [StudentDepotController::class, 'saveEtudes'])
            ->middleware('throttle:60,1')->name('depot.etudes'); // auto-save : marge large
        // Demandes (throttle anti-abus sur toutes les écritures)
        Route::post('/requests/create', [StudentRequestController::class, 'create'])
            ->middleware('throttle:10,1')->name('requests.create');
        Route::post('/requests/submit', [StudentRequestController::class, 'submit'])
            ->middleware('throttle:10,1')->name('requests.submit');
        // Annuler / recommencer un dépôt encore modifiable (avant validation)
        Route::delete('/requests/{studentRequest}/cancel', [StudentRequestController::class, 'cancelRequest'])
            ->middleware('throttle:10,1')->name('requests.cancel');
        Route::post('/requests/file/upload', [StudentRequestController::class, 'uploadFile'])
            ->middleware('throttle:30,1')->name('requests.file.upload');
        Route::delete('/requests/file/{file}', [StudentRequestController::class, 'deleteFile'])
            ->middleware('throttle:30,1')->name('requests.file.delete');
        Route::get('/requests/file/{file}/download', [StudentRequestController::class, 'downloadFile'])->name('requests.file.download');
        // Frais de dossier : déclaration du versement par l'étudiant
        Route::post('/paiements/{paiement}/declarer', [StudentRequestController::class, 'declarerPaiement'])
            ->middleware(['throttle:10,1', 'module:payments.enabled'])->name('paiements.declarer');
    });

    /* ─── ADMINISTRATEUR ─────────────────────────────── */
    Route::middleware(['permission:acceder-backoffice', \App\Http\Middleware\EnsureTwoFactor::class])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/contexte/annee', [AdminController::class, 'switchAnnee'])->name('contexte.annee');

        // Sécurité du compte (2FA)
        Route::get('/securite', [\App\Http\Controllers\TwoFactorController::class, 'show'])->name('securite');
        Route::post('/securite/2fa/activer', [\App\Http\Controllers\TwoFactorController::class, 'enable'])
            ->middleware('throttle:5,1')->name('securite.2fa.activer');
        Route::post('/securite/2fa/desactiver', [\App\Http\Controllers\TwoFactorController::class, 'disable'])->name('securite.2fa.desactiver');
        Route::post('/securite/2fa/codes', [\App\Http\Controllers\TwoFactorController::class, 'regenererCodes'])
            ->middleware('throttle:2fa-codes')->name('securite.2fa.codes');

        // Statistiques (réservées : permission dédiée) + exports en un clic
        Route::middleware('permission:statistiques.consulter')->group(function () {
            Route::get('/statistiques', [StatistiqueController::class, 'index'])->name('statistiques.index');
            Route::get('/statistiques/export/{format}', [StatistiqueController::class, 'export'])
                ->name('statistiques.export');
        });

        // Journal d'audit (traçabilité)
        Route::middleware('permission:audit.consulter')->group(function () {
            Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
            Route::get('/audit/export/{format}', [AuditController::class, 'export'])->name('audit.export');
        });

        // Archives numériques (rôle archiviste)
        Route::middleware('permission:archives.consulter')->prefix('archives')->name('archives.')->group(function () {
            Route::get('/', [ArchiveController::class, 'index'])->name('index');
            Route::get('/fiche/{file}', [ArchiveController::class, 'downloadFiche'])->name('fiche');
            Route::get('/dossier/{studentRequest}', [ArchiveController::class, 'downloadDossier'])->name('dossier');
            Route::match(['get', 'post'], '/lot', [ArchiveController::class, 'downloadLot'])->name('lot');
        });

        /* ── Permissions par ACTION : chaque route exige la permission fine
           |  correspondante OU l'ancienne permission « globale » du module
           |  (compatibilité : les rôles existants continuent de fonctionner). */

        // CRUD types de documents
        Route::prefix('request-types')->name('request-types.')->group(function () {
            Route::middleware('permission:types.voir|parametres.gerer')->group(function () {
                Route::get('/', [RequestTypeController::class, 'index'])->name('index');
                Route::get('/niveau/{grade}', [RequestTypeController::class, 'niveau'])->name('niveau');
                // Recopier les pièces d'une procédure vers un autre niveau
                Route::post('/niveau/{grade}/dupliquer',
                    [RequestTypeController::class, 'dupliquerVersNiveau'])->name('niveau.dupliquer');
            });
            // Ouvrir/fermer une procédure pour un niveau ou l'un de ses parcours
            Route::middleware('permission:annees.gerer|parametres.gerer')->group(function () {
                Route::post('/niveau/{grade}/ouverture',
                    [RequestTypeController::class, 'enregistrerOuverture'])->name('ouverture');
                Route::delete('/ouverture/{periode}',
                    [RequestTypeController::class, 'supprimerOuverture'])->name('ouverture.supprimer');
            });
            Route::middleware('permission:types.creer|parametres.gerer')
                ->post('/', [RequestTypeController::class, 'store'])->name('store');
            Route::middleware('permission:types.editer|parametres.gerer')->group(function () {
                Route::put('/{requestType}', [RequestTypeController::class, 'update'])->name('update');
                Route::patch('/{requestType}/toggle', [RequestTypeController::class, 'toggleActive'])->name('toggle');
            });
            Route::middleware('permission:types.supprimer|parametres.gerer')
                ->delete('/{requestType}', [RequestTypeController::class, 'destroy'])->name('destroy');
        });

        // Référentiel académique
        Route::prefix('academique')->name('academique.')->group(function () {
            Route::middleware('permission:academique.voir|academique.gerer')
                ->get('/', [AcademicController::class, 'index'])->name('index');
            Route::middleware('permission:academique.creer|academique.gerer')->group(function () {
                Route::post('/facultes', [AcademicController::class, 'storeFaculte'])->name('facultes.store');
                Route::post('/departements', [AcademicController::class, 'storeDepartement'])->name('departements.store');
                Route::post('/parcours', [AcademicController::class, 'storeParcours'])->name('parcours.store');
                Route::post('/domaines', [AcademicController::class, 'storeDomaine'])->name('domaines.store');
            });
            Route::middleware('permission:academique.editer|academique.gerer')->group(function () {
                Route::put('/facultes/{faculte}', [AcademicController::class, 'updateFaculte'])->name('facultes.update');
                Route::put('/departements/{departement}', [AcademicController::class, 'updateDepartement'])->name('departements.update');
                Route::put('/parcours/{parcours}', [AcademicController::class, 'updateParcours'])->name('parcours.update');
                Route::put('/domaines/{domaine}', [AcademicController::class, 'updateDomaine'])->name('domaines.update');
            });
            Route::middleware('permission:academique.supprimer|academique.gerer')->group(function () {
                Route::delete('/facultes/{faculte}', [AcademicController::class, 'destroyFaculte'])->name('facultes.destroy');
                Route::delete('/departements/{departement}', [AcademicController::class, 'destroyDepartement'])->name('departements.destroy');
                Route::delete('/parcours/{parcours}', [AcademicController::class, 'destroyParcours'])->name('parcours.destroy');
                Route::delete('/domaines/{domaine}', [AcademicController::class, 'destroyDomaine'])->name('domaines.destroy');
            });
        });

        // Années académiques & fenêtres d'ouverture
        Route::prefix('annees')->name('annees.')->group(function () {
            Route::middleware('permission:annees.voir|annees.gerer')
                ->get('/', [AnneeController::class, 'index'])->name('index');
            Route::middleware('permission:annees.creer|annees.gerer')
                ->post('/', [AnneeController::class, 'store'])->name('store');
            Route::middleware('permission:annees.editer|annees.gerer')->group(function () {
                Route::put('/{annee}', [AnneeController::class, 'update'])->name('update');
                Route::post('/{annee}/activer', [AnneeController::class, 'activate'])->name('activate');
                Route::post('/{annee}/periodes', [AnneeController::class, 'savePeriode'])->name('periodes.save');
            });
            Route::middleware('permission:annees.supprimer|annees.gerer')->group(function () {
                Route::delete('/{annee}', [AnneeController::class, 'destroy'])->name('destroy');
            });
        });

        // Identités — consultation/export (voir) séparée de l'import (écriture)
        Route::prefix('identites')->name('identites.')->group(function () {
            Route::middleware('permission:identites.voir|identites.importer')->group(function () {
                Route::get('/', [IdentityController::class, 'index'])->name('index');
                Route::get('/bacheliers/export', [IdentityController::class, 'exportBacheliers'])->name('bacheliers.export');
                Route::get('/bacheliers/modele', [IdentityController::class, 'templateBacheliers'])->name('bacheliers.template');
                Route::get('/anciens/export', [IdentityController::class, 'exportAnciens'])->name('anciens.export');
                Route::get('/anciens/modele', [IdentityController::class, 'templateAnciens'])->name('anciens.template');
            });
            Route::middleware('permission:identites.importer')->group(function () {
                Route::post('/bacheliers/import', [IdentityController::class, 'importBacheliers'])->name('bacheliers.import');
                Route::post('/anciens/import', [IdentityController::class, 'importAnciens'])->name('anciens.import');
                // Ajout manuel : un étudiant absent du fichier doit pouvoir déposer
                Route::post('/bacheliers', [IdentityController::class, 'storeBachelier'])->name('bacheliers.store');
                // Corriger une fiche : une erreur de saisie bloque la connexion
                Route::put('/bacheliers/{bachelier}', [IdentityController::class, 'updateBachelier'])->name('bacheliers.update');
                Route::put('/anciens/{ancien}', [IdentityController::class, 'updateAncien'])->name('anciens.update');
                Route::post('/anciens', [IdentityController::class, 'storeAncien'])->name('anciens.store');
                Route::delete('/bacheliers/{bachelier}', [IdentityController::class, 'destroyBachelier'])->name('bacheliers.destroy');
                Route::delete('/anciens/{ancien}', [IdentityController::class, 'destroyAncien'])->name('anciens.destroy');
            });
        });

        // Gestion des utilisateurs (personnel)
        Route::prefix('utilisateurs')->name('utilisateurs.')->group(function () {
            Route::middleware('permission:utilisateurs.voir|utilisateurs.gerer')->group(function () {
                Route::get('/', [UserController::class, 'index'])->name('index');
                Route::get('/{user}/signature', [UserController::class, 'signature'])->name('signature');
            });
            Route::middleware('permission:utilisateurs.creer|utilisateurs.gerer')
                ->post('/', [UserController::class, 'store'])->name('store');
            Route::middleware('permission:utilisateurs.editer|utilisateurs.gerer')->group(function () {
                Route::put('/{user}', [UserController::class, 'update'])->name('update');
                // Réinitialiser la 2FA d'un compte (téléphone perdu, etc.)
                Route::post('/{user}/2fa-reset', [\App\Http\Controllers\TwoFactorController::class, 'reset'])->name('2fa-reset');
                // Filet : un compte cree par code n'a pas de mot de passe utilisable
                Route::post('/{user}/mot-de-passe', [\App\Http\Controllers\UserController::class, 'reinitialiserMotDePasse'])
                    ->name('mot-de-passe');
            });
            Route::middleware('permission:utilisateurs.supprimer|utilisateurs.gerer')
                ->delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        });

        // Affectation des périmètres (agent ↔ parcours × année d'études)
        Route::prefix('perimetres')->name('perimetres.')->group(function () {
            Route::middleware('permission:perimetres.voir|perimetres.affecter|utilisateurs.gerer')
                ->get('/', [PerimetreController::class, 'index'])->name('index');
            Route::middleware('permission:perimetres.affecter|utilisateurs.gerer')->group(function () {
                Route::post('/', [PerimetreController::class, 'store'])->name('store');
                Route::delete('/{perimetre}', [PerimetreController::class, 'destroy'])->name('destroy');
            });
        });

        // Procédures / opérations dématérialisées
        Route::prefix('procedures')->name('procedures.')->group(function () {
            Route::middleware('permission:procedures.voir|parametres.gerer')
                ->get('/', [ProcedureController::class, 'index'])->name('index');
            Route::middleware('permission:procedures.creer|parametres.gerer')
                ->post('/', [ProcedureController::class, 'store'])->name('store');
            Route::middleware('permission:procedures.editer|parametres.gerer')
                ->put('/{procedure}', [ProcedureController::class, 'update'])->name('update');
            Route::middleware('permission:procedures.supprimer|parametres.gerer')
                ->delete('/{procedure}', [ProcedureController::class, 'destroy'])->name('destroy');
        });

        // Candidatures (traitement par l'agent candidatures)
        Route::middleware('permission:candidature.traiter')->prefix('candidatures')->name('candidatures.')->group(function () {
            Route::get('/', [CandidatureController::class, 'index'])->name('index');
            Route::get('/export/{format}', [CandidatureController::class, 'export'])->name('export');
            // Vue d'ensemble par parcours, pour les commissions d'admission
            Route::get('/tableau-bord', [CandidatureController::class, 'tableauBord'])->name('tableau');
            Route::get('/tableau-bord/export/{format}', [CandidatureController::class, 'exportTableau'])->name('tableau.export');
            // Téléchargement des pièces en ZIP (un candidat, ou un lot filtré)
            Route::get('/telecharger/lot', [CandidatureController::class, 'telechargerLot'])->name('telecharger.lot');
            Route::get('/{candidature}/telecharger', [CandidatureController::class, 'telechargerDossier'])->name('telecharger');
            Route::get('/file/{file}/preview', [CandidatureController::class, 'previewFile'])->name('file.preview');
            Route::get('/file/{file}/download', [CandidatureController::class, 'downloadFile'])->name('file.download');
            Route::post('/file/{file}/revue', [CandidatureController::class, 'reviewFile'])->name('file.review');
            Route::get('/{candidature}', [CandidatureController::class, 'show'])->name('show');
            // Décision parcours par parcours (un candidat peut être admis partout)
            Route::post('/section/{section}/decision', [CandidatureController::class, 'decisionSection'])->name('section.decision');
        });

        // Paiements — frais de dossier (consultation / rapprochement)
        Route::middleware('module:payments.enabled')->prefix('paiements')->name('paiements.')->group(function () {
            Route::middleware('permission:paiements.voir|paiements.confirmer')->group(function () {
                Route::get('/', [\App\Http\Controllers\PaiementController::class, 'index'])->name('index');
                Route::get('/export/{format}', [\App\Http\Controllers\PaiementController::class, 'export'])->name('export');
                Route::get('/{paiement}', [\App\Http\Controllers\PaiementController::class, 'show'])->name('show');
                Route::get('/{paiement}/recu', [\App\Http\Controllers\PaiementController::class, 'recu'])->name('recu');
            });
            Route::middleware('permission:paiements.confirmer')->group(function () {
                Route::post('/{paiement}/valider', [\App\Http\Controllers\PaiementController::class, 'valider'])->name('valider');
                Route::post('/{paiement}/rejeter', [\App\Http\Controllers\PaiementController::class, 'rejeter'])->name('rejeter');
            });
        });

        // Rôles & permissions
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::middleware('permission:roles.voir|roles.gerer')
                ->get('/', [RolePermissionController::class, 'index'])->name('index');
            Route::middleware('permission:roles.creer|roles.gerer')
                ->post('/', [RolePermissionController::class, 'store'])->name('store');
            Route::middleware('permission:roles.editer|roles.gerer')
                ->put('/{role}', [RolePermissionController::class, 'update'])->name('update');
            Route::middleware('permission:roles.supprimer|roles.gerer')
                ->delete('/{role}', [RolePermissionController::class, 'destroy'])->name('destroy');
        });

        // Demandes & dépôts
        Route::get('/requests-export/{category?}', [AdminRequestController::class, 'export'])->name('requests.export');
        Route::get('/requests/{category?}', [AdminRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/detail/{studentRequest}', [AdminRequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{studentRequest}/validate', [AdminRequestController::class, 'validate'])->name('requests.validate');
        // Levée d'une dérogation : la pièce promise est arrivée
        Route::post('/derogations/{derogation}/lever', [AdminRequestController::class, 'leverDerogation'])
            ->name('derogations.lever');
        Route::post('/requests/{studentRequest}/reject', [AdminRequestController::class, 'reject'])->name('requests.reject');
        Route::post('/requests/{studentRequest}/reject-final', [AdminRequestController::class, 'rejectFinal'])->name('requests.reject-final');
        // Annuler une validation : réservé aux superviseurs (permission dédiée,
        // JAMAIS donnée aux simples agents par défaut)
        Route::middleware('permission:demandes.rouvrir')
            ->post('/requests/{studentRequest}/reopen', [AdminRequestController::class, 'reopen'])->name('requests.reopen');
        Route::get('/requests/file/{file}/preview', [AdminRequestController::class, 'previewFile'])->name('requests.file.preview');
        Route::get('/requests/file/{file}/download', [AdminRequestController::class, 'downloadFile'])->name('requests.file.download');
    });
});
