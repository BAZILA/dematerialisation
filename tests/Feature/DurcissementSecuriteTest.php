<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\AuditLog;
use App\Models\RequestFile;
use App\Models\RequestType;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use App\Support\LiaisonExcelSure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Revue de sécurité de novembre 2026.
 *
 * Chaque test ci-dessous rejoue une attaque qui PASSAIT avant la revue.
 * Ils ne vérifient pas que « ça marche » : ils vérifient que ce qui ne
 * doit pas marcher est refusé — et que ce qui doit continuer de marcher
 * n'a pas été cassé au passage.
 */
class DurcissementSecuriteTest extends TestCase
{
    use RefreshDatabase;

    /* ══════════════════════════════════════════════════════
     |  Jeux d'essai
     ══════════════════════════════════════════════════════ */

    /** Un gestionnaire de comptes, tel que le rôle « admin » livré. */
    private function gestionnaire(string $identifiant = 'GEST001'): User
    {
        $user = User::create([
            'numero_identifiant' => $identifiant, 'nom' => 'GESTION', 'prenom' => 'Compte',
            'email' => strtolower($identifiant) . '@test.local',
            'password' => Hash::make('Secret12345'), 'role' => 'admin', 'is_active' => true,
        ]);
        $user->assignRole('admin');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function etudiant(string $identifiant = 'ATETTEH'): User
    {
        $user = User::create([
            'numero_identifiant' => $identifiant, 'email' => strtolower($identifiant) . '@test.local',
            'password' => Hash::make('etudiant1234'), 'role' => 'etudiant',
            'type_etudiant' => 'ancien', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');

        Student::create([
            'user_id' => $user->id, 'nom' => 'TETTEH', 'prenom' => 'Akua',
            'sexe' => 'F', 'date_naissance' => '2002-03-14', 'lieu_naissance' => 'Kara',
        ]);

        return $user->fresh();
    }

    private function dossier(User $etudiant, string $statut = 'brouillon'): StudentRequest
    {
        $annee = AnneeAcademique::firstOrCreate(
            ['libelle' => '2026-2027'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true]
        );

        return StudentRequest::create([
            'student_id' => $etudiant->student->id, 'category' => 'depot',
            'parcours_id' => $this->parcours()->id, 'annee_academique_id' => $annee->id,
            'status' => $statut, 'version' => 1,
            'submitted_at' => $statut === 'brouillon' ? null : now(),
        ]);
    }

    private function piece(string $libelle = 'Acte de naissance', string $grade = 'licence'): RequestType
    {
        return RequestType::create([
            'label' => $libelle, 'category' => 'depot', 'grade' => $grade,
            'type_etudiant' => 'both', 'is_required' => true, 'is_active' => true,
        ]);
    }

    /** Un vrai fichier dont le CONTENU est un PDF, quel que soit son nom. */
    private function vraiPdf(string $nom): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($chemin, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        return new UploadedFile($chemin, $nom, null, null, true);
    }

    /* ══════════════════════════════════════════════════════
     |  1. Escalade de privilèges par la gestion des comptes
     ══════════════════════════════════════════════════════ */

    public function test_un_gestionnaire_ne_peut_pas_creer_un_super_administrateur(): void
    {
        $this->socle();

        $this->actingAs($this->gestionnaire())->post('/admin/utilisateurs', [
            'prenom' => 'Pirate', 'nom' => 'ROOT', 'numero_identifiant' => 'ROOT01',
            'email' => 'root@test.local', 'roles' => ['super_admin'], 'password' => 'Secret12345',
        ])->assertForbidden();

        $this->assertNull(User::where('numero_identifiant', 'ROOT01')->first(),
            'Le compte ne doit pas avoir été créé.');
    }

    public function test_un_gestionnaire_ne_peut_pas_se_promouvoir_lui_meme(): void
    {
        $this->socle();
        $moi = $this->gestionnaire();

        $this->actingAs($moi)->put("/admin/utilisateurs/{$moi->id}", [
            'prenom' => 'Compte', 'nom' => 'GESTION', 'email' => $moi->email,
            'roles' => ['admin', 'super_admin'], 'is_active' => 1,
        ])->assertForbidden();

        $this->assertFalse($moi->fresh()->hasRole('super_admin'));
    }

    public function test_un_gestionnaire_ne_touche_pas_a_un_super_administrateur(): void
    {
        $this->socle();
        $super = $this->superAdmin();
        $empreinte = $super->password;
        $gestionnaire = $this->gestionnaire();

        // Changer son mot de passe et le désactiver, par la fiche du compte
        $this->actingAs($gestionnaire)->put("/admin/utilisateurs/{$super->id}", [
            'prenom' => 'Admin', 'nom' => 'SUPER', 'email' => $super->email,
            'roles' => ['admin'], 'password' => 'Secret12345',
        ])->assertForbidden();

        // Le supprimer
        $this->actingAs($gestionnaire)->delete("/admin/utilisateurs/{$super->id}")->assertForbidden();

        // Lui retirer sa double authentification
        $this->actingAs($gestionnaire)->post("/admin/utilisateurs/{$super->id}/2fa-reset")->assertForbidden();

        $apres = User::find($super->id);
        $this->assertNotNull($apres, 'Le super-administrateur ne doit pas avoir été supprimé.');
        $this->assertSame($empreinte, $apres->password, 'Son mot de passe ne doit pas avoir changé.');
        $this->assertTrue($apres->is_active);
        $this->assertTrue($apres->hasRole('super_admin'));
    }

    public function test_un_gestionnaire_ne_distribue_pas_les_clefs_qu_il_n_a_pas(): void
    {
        $this->socle();

        // Un rôle qui permet de réécrire les rôles : le gestionnaire ne l'a pas.
        $role = Role::create(['name' => 'maitre_des_roles', 'guard_name' => 'web']);
        $role->givePermissionTo(['acceder-backoffice', 'roles.gerer']);

        $this->actingAs($this->gestionnaire())->post('/admin/utilisateurs', [
            'prenom' => 'Complice', 'nom' => 'X', 'numero_identifiant' => 'COMP01',
            'email' => 'comp@test.local', 'roles' => ['maitre_des_roles'], 'password' => 'Secret12345',
        ])->assertForbidden();

        $this->assertNull(User::where('numero_identifiant', 'COMP01')->first());
    }

    /** Le garde-fou ne doit pas empêcher le gestionnaire de faire son métier. */
    public function test_un_gestionnaire_cree_toujours_un_agent_de_depot(): void
    {
        $this->socle();

        $this->actingAs($this->gestionnaire())->post('/admin/utilisateurs', [
            'prenom' => 'Ama', 'nom' => 'KOSSI', 'numero_identifiant' => 'DEPOT01',
            'email' => 'ama@test.local', 'roles' => ['agent_depot'], 'password' => 'Secret12345',
        ])->assertSessionHasNoErrors();

        $agent = User::where('numero_identifiant', 'DEPOT01')->first();
        $this->assertNotNull($agent, "Un gestionnaire doit pouvoir créer le compte d'un agent.");
        $this->assertTrue($agent->hasRole('agent_depot'));
        $this->assertTrue($agent->doit_changer_mot_de_passe,
            'Le mot de passe posé par le gestionnaire est provisoire.');
    }

    public function test_le_dernier_super_administrateur_ne_peut_pas_disparaitre(): void
    {
        $this->socle();
        $seul = $this->superAdmin();

        // Se retirer le rôle
        $this->actingAs($seul)->put("/admin/utilisateurs/{$seul->id}", [
            'prenom' => 'Admin', 'nom' => 'SUPER', 'email' => $seul->email,
            'roles' => ['admin'], 'is_active' => 1,
        ])->assertSessionHasErrors();

        // Se désactiver
        $this->actingAs($seul)->put("/admin/utilisateurs/{$seul->id}", [
            'prenom' => 'Admin', 'nom' => 'SUPER', 'email' => $seul->email,
            'roles' => ['super_admin'],
        ])->assertSessionHasErrors();

        $apres = $seul->fresh();
        $this->assertTrue($apres->hasRole('super_admin'));
        $this->assertTrue($apres->is_active);
    }

    public function test_un_compte_etudiant_n_est_ni_modifiable_ni_supprimable_par_l_ecran_du_personnel(): void
    {
        $this->socle();
        $etudiant = $this->etudiant();
        $dossier  = $this->dossier($etudiant);

        $super = $this->superAdmin();

        $this->actingAs($super)->delete("/admin/utilisateurs/{$etudiant->id}")->assertNotFound();
        $this->actingAs($super)->put("/admin/utilisateurs/{$etudiant->id}", [
            'prenom' => 'X', 'nom' => 'Y', 'email' => 'x@test.local', 'roles' => ['admin'], 'is_active' => 1,
        ])->assertNotFound();

        $this->assertNotNull(User::find($etudiant->id), "Le compte de l'étudiant doit survivre.");
        $this->assertNotNull(StudentRequest::find($dossier->id),
            'Son dossier ne doit pas avoir été emporté en cascade.');
        $this->assertFalse($etudiant->fresh()->hasRole('admin'));
    }

    /* ══════════════════════════════════════════════════════
     |  2. Escalade par la composition des rôles
     ══════════════════════════════════════════════════════ */

    public function test_nul_ne_met_dans_un_role_une_permission_qu_il_n_a_pas(): void
    {
        $this->socle();

        $editeur = $this->agent(['acceder-backoffice', 'roles.voir', 'roles.editer'], 'ROLES01');
        $role = Role::create(['name' => 'mon_role', 'guard_name' => 'web']);
        $role->givePermissionTo('acceder-backoffice');

        $this->actingAs($editeur)->put("/admin/roles/{$role->id}", [
            'perms' => ['acceder-backoffice' => 1, 'parametres.gerer' => 1, 'utilisateurs.gerer' => 1],
        ])->assertSessionHasErrors();

        $this->assertFalse($role->fresh()->hasPermissionTo('parametres.gerer'));
        $this->assertFalse($role->fresh()->hasPermissionTo('utilisateurs.gerer'));
        $this->assertTrue(AuditLog::where('action', 'role.escalade_refusee')->exists(),
            'Une tentative d\'escalade doit laisser une trace.');
    }

    public function test_le_role_des_etudiants_n_est_modifiable_que_par_un_super_administrateur(): void
    {
        $this->socle();
        $editeur = $this->agent(['acceder-backoffice', 'roles.voir', 'roles.editer'], 'ROLES02');
        $role = Role::where('name', 'etudiant')->first();

        // Ouvrirait le back-office à tous les étudiants d'un seul clic
        $this->actingAs($editeur)->put("/admin/roles/{$role->id}", [
            'perms' => ['acceder-backoffice' => 1],
        ])->assertSessionHasErrors();

        $this->assertFalse($role->fresh()->hasPermissionTo('acceder-backoffice'));
    }

    /* ══════════════════════════════════════════════════════
     |  3. Connexion
     ══════════════════════════════════════════════════════ */

    public function test_un_compte_se_ferme_apres_trop_d_echecs_meme_venus_d_adresses_differentes(): void
    {
        $this->socle();
        $agent = $this->agent(['acceder-backoffice'], 'CIBLE01');

        // Huit machines différentes : aucune ne dépasse la cadence par adresse
        for ($i = 1; $i <= 8; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->post('/login', [
                'numero_identifiant' => 'CIBLE01', 'password' => "mauvais-{$i}",
            ]);
        }

        // Le BON mot de passe, depuis une neuvième machine : refusé, le compte est fermé
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])->post('/login', [
            'numero_identifiant' => 'CIBLE01', 'password' => 'secret1234',
        ])->assertSessionHasErrors('numero_identifiant');

        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'auth.verrouille')->exists());
    }

    public function test_un_echec_de_connexion_est_journalise_sans_le_mot_de_passe(): void
    {
        $this->socle();
        $this->agent(['acceder-backoffice'], 'CIBLE02');

        $this->post('/login', ['numero_identifiant' => 'CIBLE02', 'password' => 'MonVraiMotDePasse1']);

        $trace = AuditLog::where('action', 'auth.echec')->first();
        $this->assertNotNull($trace, 'Un échec de connexion doit apparaître au journal.');
        $this->assertStringNotContainsString('MonVraiMotDePasse1', json_encode($trace->toArray()),
            'Le mot de passe essayé ne doit jamais être écrit — c\'est souvent le bon, à une faute près.');
    }

    public function test_une_connexion_reussie_remet_le_compteur_a_zero(): void
    {
        $this->socle();
        $agent = $this->agent(['acceder-backoffice'], 'CIBLE03');

        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.1.{$i}"])->post('/login', [
                'numero_identifiant' => 'CIBLE03', 'password' => 'faux',
            ]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.50'])->post('/login', [
            'numero_identifiant' => 'CIBLE03', 'password' => 'secret1234',
        ]);
        $this->assertAuthenticatedAs($agent->fresh());
    }

    /* ══════════════════════════════════════════════════════
     |  4. Mot de passe : le sien, et le provisoire
     ══════════════════════════════════════════════════════ */

    public function test_chacun_change_son_propre_mot_de_passe(): void
    {
        $this->socle();
        $agent = $this->agent(['acceder-backoffice'], 'MDP01');

        // Mauvais mot de passe actuel : refusé
        $this->actingAs($agent)->post('/compte/mot-de-passe', [
            'mot_de_passe_actuel' => 'pas-le-bon',
            'password' => 'NouveauSecret42', 'password_confirmation' => 'NouveauSecret42',
        ])->assertSessionHasErrors('mot_de_passe_actuel');
        $this->assertTrue(Hash::check('secret1234', $agent->fresh()->password));

        // Trop faible pour un compte du personnel : refusé
        $this->actingAs($agent)->post('/compte/mot-de-passe', [
            'mot_de_passe_actuel' => 'secret1234',
            'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh',
        ])->assertSessionHasErrors('password');

        // Correct : accepté
        $this->actingAs($agent)->post('/compte/mot-de-passe', [
            'mot_de_passe_actuel' => 'secret1234',
            'password' => 'NouveauSecret42', 'password_confirmation' => 'NouveauSecret42',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NouveauSecret42', $agent->fresh()->password));
        $this->assertTrue(AuditLog::where('action', 'compte.mot_de_passe_change')->exists());
    }

    public function test_un_mot_de_passe_provisoire_n_ouvre_que_la_page_qui_le_remplace(): void
    {
        $this->socle();
        $etudiant = $this->etudiant();
        $super = $this->superAdmin();

        // L'administration remet un mot de passe : elle le connaît
        $this->actingAs($super)->post("/admin/utilisateurs/{$etudiant->id}/mot-de-passe")
            ->assertSessionHas('mot_de_passe_provisoire');
        $provisoire = session('mot_de_passe_provisoire')['valeur'];
        $this->assertTrue($etudiant->fresh()->doit_changer_mot_de_passe);

        // L'étudiant se connecte avec : son espace lui est fermé…
        $etudiant = $etudiant->fresh();
        $this->actingAs($etudiant)->get('/student/dashboard')
            ->assertRedirect(route('compte.mot-de-passe'));

        // …jusqu'à ce qu'il en choisisse un que lui seul connaît
        $this->actingAs($etudiant)->post('/compte/mot-de-passe', [
            'mot_de_passe_actuel' => $provisoire,
            'password' => 'monsecret2026', 'password_confirmation' => 'monsecret2026',
        ])->assertSessionHasNoErrors();

        $etudiant = $etudiant->fresh();
        $this->assertFalse($etudiant->doit_changer_mot_de_passe);
        $this->actingAs($etudiant)->get('/student/dashboard')->assertOk();
    }

    public function test_changer_un_mot_de_passe_ferme_les_sessions_ouvertes_avec_l_ancien(): void
    {
        $this->socle();
        $agent = $this->agent(['acceder-backoffice'], 'SESS01');

        // Une session ouverte avec le mot de passe d'origine
        $this->actingAs($agent)->get('/compte/mot-de-passe')->assertOk();

        // Ailleurs, le mot de passe change (l'administration, ou le titulaire sur un autre poste)
        $agent->forceFill(['password' => Hash::make('AutreSecret2026')])->save();

        // La session d'origine tombe à sa requête suivante
        $this->actingAs($agent->fresh())->get('/compte/mot-de-passe')
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /* ══════════════════════════════════════════════════════
     |  5. Proxys de confiance
     ══════════════════════════════════════════════════════ */

    private function ipVueApresEchecDeConnexion(string $adresseReelle, string $adresseDeclaree): ?string
    {
        $this->withServerVariables(['REMOTE_ADDR' => $adresseReelle])
            ->withHeaders(['X-Forwarded-For' => $adresseDeclaree])
            ->post('/login', ['numero_identifiant' => 'INCONNU', 'password' => 'x']);

        return AuditLog::where('action', 'auth.echec')->latest('id')->first()?->ip_address;
    }

    public function test_derriere_cloudflare_un_visiteur_direct_ne_choisit_pas_son_adresse(): void
    {
        $this->socle();
        config(['trustedproxy.proxies' => (require config_path('trustedproxy.php'))['proxies']]);
        config(['trustedproxy.proxies' => $this->plagesCloudflare()]);

        // Connexion directe au serveur, en se prétendant quelqu'un d'autre
        $this->assertSame('203.0.113.7', $this->ipVueApresEchecDeConnexion('203.0.113.7', '8.8.8.8'),
            "Une adresse déclarée par un inconnu ne doit pas être crue.");
    }

    public function test_derriere_cloudflare_l_adresse_transmise_par_le_proxy_est_retenue(): void
    {
        $this->socle();
        config(['trustedproxy.proxies' => $this->plagesCloudflare()]);

        // 172.71.232.155 : l'adresse Cloudflare réellement observée sur le VPS
        $this->assertSame('196.168.96.223', $this->ipVueApresEchecDeConnexion('172.71.232.155', '196.168.96.223'),
            "L'adresse transmise par Cloudflare est celle du vrai visiteur.");
    }

    /** @return array<int,string> */
    private function plagesCloudflare(): array
    {
        putenv('TRUSTED_PROXIES=cloudflare');
        $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = 'cloudflare';
        $plages = (require config_path('trustedproxy.php'))['proxies'];
        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);

        $this->assertIsArray($plages);
        $this->assertContains('172.64.0.0/13', $plages);

        return $plages;
    }

    /* ══════════════════════════════════════════════════════
     |  6. Fichiers envoyés
     ══════════════════════════════════════════════════════ */

    public function test_l_extension_du_fichier_range_vient_de_son_contenu_pas_de_son_nom(): void
    {
        $this->socle();
        Storage::fake('local');
        $etudiant = $this->etudiant();
        $dossier = $this->dossier($etudiant);
        $piece = $this->piece();

        // Un nom en « .php » est refusé d'emblée, quel que soit le contenu
        $this->actingAs($etudiant)->postJson('/student/requests/file/upload', [
            'student_request_id' => $dossier->id, 'request_type_id' => $piece->id,
            'file' => $this->vraiPdf('facture.php'),
        ])->assertStatus(422);
        $this->assertSame(0, RequestFile::count(), 'Un fichier nommé « .php » ne doit jamais être rangé.');

        // Un PDF au nom trompeur est accepté — mais rangé d'après son contenu
        $this->actingAs($etudiant)->post('/student/requests/file/upload', [
            'student_request_id' => $dossier->id, 'request_type_id' => $piece->id,
            'file' => $this->vraiPdf('facture.html'),
        ]);

        $fichier = RequestFile::first();
        $this->assertNotNull($fichier, 'Un PDF valide doit être accepté, quel que soit son nom.');
        $this->assertStringEndsWith('.pdf', $fichier->stored_filename,
            "Un fichier nommé « .html » mais contenant un PDF doit être rangé en « .pdf ».");
        $this->assertStringNotContainsString('.html', $fichier->stored_filename);
        $this->assertStringEndsWith('.pdf', $fichier->original_filename);
    }

    public function test_une_piece_d_un_autre_niveau_n_entre_pas_dans_le_dossier(): void
    {
        $this->socle();
        Storage::fake('local');
        $etudiant = $this->etudiant();
        $dossier = $this->dossier($etudiant);          // parcours de licence
        $etrangere = $this->piece('Mémoire de master', 'master');

        $this->actingAs($etudiant)->postJson('/student/requests/file/upload', [
            'student_request_id' => $dossier->id, 'request_type_id' => $etrangere->id,
            'file' => $this->vraiPdf('memoire.pdf'),
        ])->assertStatus(422);

        $this->assertSame(0, RequestFile::count());
    }

    public function test_un_nom_de_fichier_piege_ne_casse_pas_l_apercu(): void
    {
        $this->socle();
        Storage::fake('local');
        $etudiant = $this->etudiant();
        $dossier = $this->dossier($etudiant, 'soumis');
        $piece = $this->piece();

        Storage::disk('local')->put("requests/{$dossier->id}/a.pdf", '%PDF-1.4');
        $fichier = RequestFile::create([
            'student_request_id' => $dossier->id, 'request_type_id' => $piece->id,
            'file_path' => "requests/{$dossier->id}/a.pdf", 'stored_filename' => 'a.pdf',
            'original_filename' => 'acte"; filename="autre.html', 'mime_type' => 'application/pdf', 'file_size' => 8,
        ]);

        $reponse = $this->actingAs($this->superAdmin())->get("/admin/requests/file/{$fichier->id}/preview");

        $reponse->assertOk();
        $disposition = $reponse->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline;', $disposition);
        $this->assertStringNotContainsString('filename="autre.html"', $disposition,
            "Le guillemet du nom ne doit pas fermer l'en-tête et en ouvrir un second.");
    }

    public function test_un_type_de_fichier_inattendu_est_telecharge_jamais_affiche(): void
    {
        $entetes = \App\Support\FichierSur::entetesApercu('text/html', 'page.html');

        $this->assertSame('application/octet-stream', $entetes['Content-Type']);
        $this->assertStringStartsWith('attachment;', $entetes['Content-Disposition']);
    }

    /* ══════════════════════════════════════════════════════
     |  7. Exports : pas de formule injectée
     ══════════════════════════════════════════════════════ */

    public function test_une_saisie_d_usager_ne_devient_pas_une_formule_dans_un_export(): void
    {
        foreach (['=HYPERLINK("http://x";"clic")', '@SUM(1+1)', '+cmd|\' /C calc\'!A0', "-2+3+cmd|' /C calc'!A0", "\t=1+1"] as $piege) {
            $this->assertTrue(LiaisonExcelSure::estDangereuse($piege), "Doit être neutralisé : {$piege}");
        }

        // Ce qui ne peut rien exécuter reste intact
        foreach (['+228 90 00 00 00', '-5', '+22890000000', 'KOSSI', 'Jean-Marc', '—', ''] as $sain) {
            $this->assertFalse(LiaisonExcelSure::estDangereuse($sain), "Ne doit pas être modifié : {$sain}");
        }

        // Et dans une vraie cellule : du texte, pas une formule
        $feuille = (new \PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet();
        $cellule = $feuille->getCell('A1');
        (new LiaisonExcelSure())->bindValue($cellule, '=1+1');

        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $cellule->getDataType());
        $this->assertSame("'=1+1", $cellule->getValue());
        $this->assertSame(LiaisonExcelSure::class, config('excel.value_binder.default'),
            'La liaison sûre doit être celle de tous les exports.');
    }

    /* ══════════════════════════════════════════════════════
     |  8. Rien en cache
     ══════════════════════════════════════════════════════ */

    public function test_aucune_page_n_est_gardee_en_cache(): void
    {
        $this->socle();
        $etudiant = $this->etudiant();

        foreach ([$this->get('/login'), $this->actingAs($etudiant)->get('/student/dashboard')] as $reponse) {
            $cache = $reponse->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cache);
            $this->assertStringContainsString('private', $cache);
        }
    }

    /* ══════════════════════════════════════════════════════
     |  9. Portail public : la date de naissance ne se devine pas à la chaîne
     ══════════════════════════════════════════════════════ */

    public function test_un_dossier_de_candidature_se_ferme_apres_trop_de_dates_fausses(): void
    {
        $this->socle();
        \App\Models\Setting::set('candidatures.enabled', true);
        $candidature = $this->candidature($this->parcours());   // né le 2004-05-12
        $candidature->update(['status' => 'en_correction']);

        // Six dates fausses, depuis six adresses différentes
        for ($i = 1; $i <= 6; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.2.{$i}"])->post('/candidature/completer', [
                'reference' => $candidature->reference, 'date_naissance' => "2001-01-0{$i}",
                'files' => [1 => $this->vraiPdf('piece.pdf')],
            ]);
        }

        // La BONNE date, depuis une septième adresse : le dossier est fermé
        $reponse = $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.99'])->post('/candidature/completer', [
            'reference' => $candidature->reference, 'date_naissance' => '2004-05-12',
            'files' => [1 => $this->vraiPdf('piece.pdf')],
        ]);

        $reponse->assertSessionHasErrors();
        $this->assertStringContainsString('Trop de tentatives',
            implode(' ', session('errors')->all()));
    }

    /* ══════════════════════════════════════════════════════
     |  10. Décisions sur un dossier
     ══════════════════════════════════════════════════════ */

    public function test_un_dossier_valide_ne_se_rejette_pas(): void
    {
        $this->socle();
        $dossier = $this->dossier($this->etudiant(), 'validé');

        $this->actingAs($this->superAdmin())->post("/admin/requests/{$dossier->id}/reject-final", [
            'rejection_message' => 'Un motif suffisamment long.',
        ])->assertSessionHasErrors();

        $this->assertSame('validé', $dossier->fresh()->status);
    }

    public function test_lever_une_derogation_exige_le_droit_de_traiter_la_procedure(): void
    {
        $this->socle();
        $dossier = $this->dossier($this->etudiant(), 'soumis');
        $piece = $this->piece();

        $derogation = \App\Models\Derogation::create([
            'student_request_id' => $dossier->id, 'request_type_id' => $piece->id,
            'statut' => 'engagee', 'engagee_at' => now(),
        ]);

        // Agent du back-office, mais d'un autre service : pas de droit sur les dépôts
        $autre = $this->agent(['acceder-backoffice', 'attestation.traiter'], 'ATTEST01');

        $this->actingAs($autre)->post("/admin/derogations/{$derogation->id}/lever")->assertForbidden();
        $this->assertSame('engagee', $derogation->fresh()->statut);
    }

    /* ══════════════════════════════════════════════════════
     |  11. Inscription : une fiche, un compte
     ══════════════════════════════════════════════════════ */

    public function test_deux_navigateurs_ne_creent_pas_deux_comptes_pour_la_meme_fiche(): void
    {
        $this->socle();
        $fiche = \App\Models\AncienEtudiant::create([
            'numero_carte' => 'C123', 'nom' => 'KOSSI', 'prenom' => 'Ama', 'sexe' => 'F',
            'date_naissance' => '2001-02-03', 'lieu_naissance' => 'Kara', 'is_registered' => false,
        ]);

        $session = [
            'reg_type' => 'ancien', 'reg_ancien_id' => $fiche->id, 'reg_identifiant' => 'AKOSSI',
            'reg_nom' => 'KOSSI', 'reg_prenom' => 'Ama', 'reg_date_naissance' => '2001-02-03',
            'reg_lieu_naissance' => 'Kara', 'reg_sexe' => 'F',
        ];

        // Les deux ont passé l'étape 1 avant que l'un ou l'autre ne termine
        $this->withSession($session)->post('/register/complete', [
            'email' => 'ama1@test.local', 'password' => 'monsecret2026', 'password_confirmation' => 'monsecret2026',
        ])->assertRedirect(route('login'));

        $this->withSession($session)->post('/register/complete', [
            'email' => 'ama2@test.local', 'password' => 'monsecret2026', 'password_confirmation' => 'monsecret2026',
        ])->assertRedirect(route('register'));

        $this->assertSame(1, Student::where('ancien_etudiant_id', $fiche->id)->count(),
            'Une fiche du référentiel ne doit porter qu\'un seul compte.');
        $this->assertNull(User::where('email', 'ama2@test.local')->first());
    }

    /* ══════════════════════════════════════════════════════
     |  12. Le compte d'installation
     ══════════════════════════════════════════════════════ */

    public function test_relancer_le_seeder_en_production_ne_recree_pas_un_compte_au_mot_de_passe_connu(): void
    {
        $this->socle();
        $this->superAdmin();                         // la plateforme a déjà son super-administrateur
        $this->app['env'] = 'production';

        // Appel direct : en production, la commande « db:seed » demande
        // une confirmation au clavier, que le test ne peut pas donner.
        (new \Database\Seeders\UserSeeder())->run();

        $this->assertNull(User::where('numero_identifiant', 'ADMIN001')->first(),
            'ADMIN001 ne doit pas renaître sur une plateforme en service.');
    }

    public function test_un_compte_d_installation_cree_en_production_n_a_pas_le_mot_de_passe_du_depot(): void
    {
        $this->socle();
        $this->app['env'] = 'production';

        (new \Database\Seeders\UserSeeder())->run();

        $admin = User::where('numero_identifiant', 'ADMIN001')->first();
        $this->assertNotNull($admin);
        $this->assertFalse(Hash::check('admin123456', $admin->password),
            'Le mot de passe écrit dans le dépôt ne doit plus ouvrir aucun compte en production.');
        $this->assertTrue($admin->doit_changer_mot_de_passe);
    }
}
