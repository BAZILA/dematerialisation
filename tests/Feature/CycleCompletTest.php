<?php

namespace Tests\Feature;

use App\Models\AncienEtudiant;
use App\Models\AnneeAcademique;
use App\Models\Periode;
use App\Models\Procedure;
use App\Models\RequestType;
use App\Models\Student;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Le dossier de bout en bout, pour CHAQUE procédure.
 *
 * Cinq procédures sur sept n'avaient jamais été jouées entièrement :
 * attestation, diplôme, duplicata, homologation, relevé. Leurs écrans
 * s'ouvraient, mais personne n'avait jamais mené un dossier de la
 * création à la validation. C'est ce que ce test fait, procédure par
 * procédure : créer, déposer la pièce, soumettre, corriger, valider.
 */
class CycleCompletTest extends TestCase
{
    use RefreshDatabase;

    private ?AnneeAcademique $annee = null;
    private ?\App\Models\Parcours $parcoursTest = null;

    /** Le parcours decide du niveau, et le niveau decide des pieces. */
    private function parcoursTest(): \App\Models\Parcours
    {
        return $this->parcoursTest ??= $this->parcours();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function annee(): AnneeAcademique
    {
        return $this->annee ??= AnneeAcademique::firstOrCreate(
            ['libelle' => '2026-2027'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2027-08-31',
             'statut' => 'ouverte', 'is_active' => true]
        );
    }

    /** Ouvre la procédure pour tout le monde. */
    private function ouvrir(string $operation): void
    {
        Periode::updateOrCreate(
            ['annee_academique_id' => $this->annee()->id, 'operation' => $operation,
             'grade' => null, 'parcours_id' => null],
            ['is_open' => true, 'date_debut' => null, 'date_fin' => null]
        );

        /* L'annee active est memoisee pour la duree d'une requete HTTP, et
           avec elle ses periodes deja chargees. En production chaque requete
           repart a zero ; ici tout vit dans le meme processus, il faut donc
           invalider la memo apres avoir pose une periode. */
        AnneeAcademique::flushMemo();
        $this->annee = null;
    }

    /** Un ancien étudiant : seul profil autorisé hors dépôt. */
    private function ancien(): User
    {
        $fiche = AncienEtudiant::create([
            'numero_carte' => 'CARTE-777', 'nom' => 'KOSSI', 'prenom' => 'Ama',
            'sexe' => 'F', 'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
            'is_registered' => true,
        ]);

        $user = User::create([
            'numero_identifiant' => 'ANC777', 'email' => 'anc777@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'ancien', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');

        Student::create([
            'user_id' => $user->id, 'ancien_etudiant_id' => $fiche->id,
            'nom' => 'KOSSI', 'prenom' => 'Ama', 'sexe' => 'F',
            'date_naissance' => '2001-07-02', 'lieu_naissance' => 'Kara',
        ]);

        return $user->fresh();
    }

    private function agentTraitant(): User
    {
        $agent = $this->agent([
            'acceder-backoffice', 'demandes.voir', 'dossiers.consulter',
            'depot.traiter', 'attestation.traiter', 'diplome.traiter',
            'releve.traiter', 'duplicat.traiter', 'homologation.traiter',
            'candidature.traiter', 'demandes.valider', 'demandes.rejeter',
        ]);

        // Aucun périmètre posé = l'agent voit tout : c'est le comportement
        // par défaut, et celui qu'on veut exercer ici.
        return $agent;
    }

    private function pieceAttendue(string $categorie): RequestType
    {
        return RequestType::create([
            'label' => 'Pièce justificative — ' . $categorie,
            'category' => $categorie, 'grade' => $this->parcoursTest()->grade,
            'type_etudiant' => 'both',
            'is_required' => true, 'is_active' => true, 'has_stamp' => false,
        ]);
    }

    private function deposer(User $usager, StudentRequest $dossier, RequestType $type)
    {
        return $this->actingAs($usager)->post('/student/requests/file/upload', [
            'student_request_id' => $dossier->id,
            'request_type_id'    => $type->id,
            'file' => UploadedFile::fake()->create('justificatif.pdf', 120, 'application/pdf'),
        ]);
    }

    /**
     * Le cycle complet, pour une procédure donnée.
     *
     * @return array{0:StudentRequest,1:User} le dossier et son auteur
     */
    private function cycle(string $categorie): array
    {
        $this->ouvrir($categorie);
        $type = $this->pieceAttendue($categorie);
        $usager = $this->ancien();

        // 1. Création
        $this->actingAs($usager)->post('/student/requests/create', [
            'category' => $categorie, 'parcours_id' => $this->parcoursTest()->id,
        ]);
        $dossier = StudentRequest::where('category', $categorie)->latest('id')->first();
        $this->assertNotNull($dossier, "Création impossible pour « {$categorie} ».");

        // 2. Dépôt de la pièce
        $this->deposer($usager, $dossier, $type);
        $this->assertSame(1, $dossier->fresh()->files->count(),
            "La pièce ne s'enregistre pas pour « {$categorie} ».");

        // 3. Soumission
        $this->actingAs($usager)->post('/student/requests/submit', [
            'student_request_id' => $dossier->id, 'engagement' => 1,
        ]);
        $this->assertSame('soumis', $dossier->fresh()->status,
            "La soumission ne passe pas pour « {$categorie} ».");

        return [$dossier->fresh(), $usager];
    }

    /* ── Une procédure = un cycle ───────────────────── */

    public static function procedures(): array
    {
        return [
            'attestation'  => ['attestation'],
            'diplôme'      => ['diplome'],
            'relevé'       => ['releve'],
            'duplicata'    => ['duplicat'],
            'homologation' => ['homologation'],
        ];
    }

    /**
     * @dataProvider procedures
     */
    public function test_un_dossier_va_de_la_creation_a_la_validation(string $categorie): void
    {
        $this->socle();
        $this->assertNotNull(Procedure::where('code', $categorie)->first(),
            "La procédure « {$categorie} » n'existe pas dans le socle.");

        [$dossier] = $this->cycle($categorie);

        // 4. Validation par l'agent — un rendez-vous est exigé hors dépôt
        $this->actingAs($this->agentTraitant())
            ->post("/admin/requests/{$dossier->id}/validate", [
                'admin_comment' => 'Dossier conforme.',
                'rendez_vous_at' => now()->addWeek()->format('Y-m-d H:i'),
            ]);

        $this->assertSame('validé', $dossier->fresh()->status,
            "La validation ne passe pas pour « {$categorie} ».");
        $this->assertNotNull($dossier->fresh()->validated_at);
    }

    /**
     * @dataProvider procedures
     */
    public function test_un_dossier_incomplet_ne_se_soumet_pas(string $categorie): void
    {
        $this->socle();
        $this->ouvrir($categorie);
        $this->pieceAttendue($categorie);
        $usager = $this->ancien();

        $this->actingAs($usager)->post('/student/requests/create', [
            'category' => $categorie, 'parcours_id' => $this->parcoursTest()->id,
        ]);
        $dossier = StudentRequest::where('category', $categorie)->latest('id')->first();

        // Aucune pièce déposée
        $this->actingAs($usager)->post('/student/requests/submit', [
            'student_request_id' => $dossier->id, 'engagement' => 1,
        ])->assertSessionHasErrors();

        $this->assertSame('brouillon', $dossier->fresh()->status,
            "Un dossier vide ne doit pas partir — « {$categorie} ».");
    }

    /* ── La boucle de correction ────────────────────── */

    public function test_le_dossier_renvoye_revient_corrige_et_se_valide(): void
    {
        $this->socle();
        [$dossier, $usager] = $this->cycle('attestation');
        $agent = $this->agentTraitant();

        // L'agent renvoie en correction
        $this->actingAs($agent)->post("/admin/requests/{$dossier->id}/reject", [
            'rejection_message' => 'La copie fournie est illisible, merci de la refaire.',
        ]);

        $dossier = $dossier->fresh();
        $this->assertNotSame('soumis', $dossier->status,
            'Le renvoi en correction ne change pas le statut.');
        $this->assertTrue($dossier->canEdit(),
            "Un dossier renvoyé doit redevenir modifiable, sinon l'usager est bloqué.");

        // L'usager remplace la pièce
        $type = RequestType::where('category', 'attestation')->first();
        $this->deposer($usager, $dossier, $type);

        // Et resoumet — hors période, cette fois : un dossier déjà entré
        // pendant l'ouverture doit rester soumissible.
        Periode::where('annee_academique_id', $this->annee()->id)
            ->where('operation', 'attestation')->update(['is_open' => false]);
        AnneeAcademique::flushMemo();
        $this->annee = null;

        $this->actingAs($usager)->post('/student/requests/submit', [
            'student_request_id' => $dossier->id, 'engagement' => 1,
        ]);

        $this->assertSame('soumis', $dossier->fresh()->status,
            "Une correction doit pouvoir revenir même après la fermeture.");

        $this->actingAs($agent)->post("/admin/requests/{$dossier->id}/validate", [
            'admin_comment' => 'Conforme après correction.',
            'rendez_vous_at' => now()->addWeek()->format('Y-m-d H:i'),
        ]);

        $this->assertSame('validé', $dossier->fresh()->status);
    }

    /* ── Les fenêtres d'ouverture ───────────────────── */

    public function test_hors_periode_aucun_dossier_ne_se_cree(): void
    {
        $this->socle();
        $this->pieceAttendue('attestation');
        $usager = $this->ancien();

        // Aucune période posée : la procédure est fermée.
        $this->actingAs($usager)->post('/student/requests/create', ['category' => 'attestation'])
            ->assertSessionHasErrors();

        $this->assertSame(0, StudentRequest::where('category', 'attestation')->count());
    }

    public function test_un_nouveau_bachelier_n_accede_pas_aux_procedures_d_anciens(): void
    {
        $this->socle();
        $this->ouvrir('attestation');
        $this->pieceAttendue('attestation');

        $user = User::create([
            'numero_identifiant' => 'NEW777', 'email' => 'new777@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'nouveau', 'is_active' => true,
        ]);
        $user->assignRole('etudiant');
        Student::create([
            'user_id' => $user->id, 'nom' => 'ADJO', 'prenom' => 'Kodjo',
            'sexe' => 'M', 'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
        ]);

        $this->actingAs($user->fresh())
            ->post('/student/requests/create', ['category' => 'attestation'])
            ->assertSessionHasErrors();

        $this->assertSame(0, StudentRequest::where('category', 'attestation')->count());
    }

    /* ── Cloisonnement ──────────────────────────────── */

    public function test_un_usager_ne_depose_pas_dans_le_dossier_d_un_autre(): void
    {
        $this->socle();
        [$dossier] = $this->cycle('attestation');

        $intrus = User::create([
            'numero_identifiant' => 'ANC888', 'email' => 'anc888@test.local',
            'password' => Hash::make('secret1234'), 'role' => 'etudiant',
            'type_etudiant' => 'ancien', 'is_active' => true,
        ]);
        $intrus->assignRole('etudiant');
        Student::create([
            'user_id' => $intrus->id, 'nom' => 'AUTRE', 'prenom' => 'Personne',
            'sexe' => 'M', 'date_naissance' => '2000-01-01', 'lieu_naissance' => 'Lomé',
        ]);

        $type = RequestType::where('category', 'attestation')->first();

        $this->actingAs($intrus->fresh())->post('/student/requests/file/upload', [
            'student_request_id' => $dossier->id,
            'request_type_id'    => $type->id,
            'file' => UploadedFile::fake()->create('intrus.pdf', 50, 'application/pdf'),
        ])->assertForbidden();

        $this->assertSame(1, $dossier->fresh()->files->count(),
            "Le dossier d'autrui ne doit pas recevoir de pièce.");
    }

    public function test_une_piece_validee_ne_se_remplace_plus(): void
    {
        $this->socle();
        [$dossier, $usager] = $this->cycle('attestation');
        $type = RequestType::where('category', 'attestation')->first();

        // L'agent verrouille la pièce en validant le dossier
        $this->actingAs($this->agentTraitant())
            ->post("/admin/requests/{$dossier->id}/validate", [
                'admin_comment' => 'Conforme.',
                'rendez_vous_at' => now()->addWeek()->format('Y-m-d H:i'),
            ]);

        $avant = $dossier->fresh()->files->first()->file_path;

        $this->deposer($usager, $dossier->fresh(), $type);

        $this->assertSame($avant, $dossier->fresh()->files->first()->file_path,
            "Une pièce d'un dossier validé ne doit plus pouvoir être remplacée.");
    }
}
