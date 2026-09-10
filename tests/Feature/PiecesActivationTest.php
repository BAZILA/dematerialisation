<?php

namespace Tests\Feature;

use App\Models\Derogation;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Désactiver, réactiver, supprimer une pièce attendue.
 *
 * Le piège : une case décochée n'est PAS envoyée par le navigateur.
 * Avec « true » en valeur de repli, décocher revenait à cocher — la
 * désactivation ne passait jamais, sans le moindre message d'erreur.
 */
class PiecesActivationTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function pilote(): User
    {
        return $this->admin ??= $this->superAdmin();
    }

    private function piece(string $libelle = 'Acte de naissance', bool $active = true): RequestType
    {
        return RequestType::create([
            'label' => $libelle, 'category' => 'depot', 'type_etudiant' => 'both',
            'is_required' => true, 'is_active' => $active,
        ]);
    }

    private function champs(RequestType $type, array $ecrase = []): array
    {
        return array_merge([
            'label' => $type->label,
            'category' => $type->category,
            'type_etudiant' => 'both',
            'niveau' => 'licence',
        ], $ecrase);
    }

    /* ── Désactivation ──────────────────────────────── */

    public function test_decocher_actif_desactive_vraiment_la_piece(): void
    {
        $this->socle();
        $type = $this->piece();

        // Case décochée = champ absent, exactement ce qu'envoie le navigateur.
        $this->actingAs($this->pilote())
            ->put("/admin/request-types/{$type->id}", $this->champs($type, ['is_required' => 1]));

        $this->assertFalse((bool) $type->fresh()->is_active,
            'Décocher « Actif » doit désactiver la pièce.');
    }

    public function test_cocher_actif_reactive_la_piece(): void
    {
        $this->socle();
        $type = $this->piece('Acte de naissance', false);

        $this->actingAs($this->pilote())
            ->put("/admin/request-types/{$type->id}", $this->champs($type, [
                'is_required' => 1, 'is_active' => 1,
            ]));

        $this->assertTrue((bool) $type->fresh()->is_active);
    }

    public function test_decocher_obligatoire_rend_bien_la_piece_facultative(): void
    {
        $this->socle();
        $type = $this->piece();

        $this->actingAs($this->pilote())
            ->put("/admin/request-types/{$type->id}", $this->champs($type, ['is_active' => 1]));

        $this->assertFalse((bool) $type->fresh()->is_required,
            'Décocher « Obligatoire » doit rendre la pièce facultative.');
    }

    public function test_une_piece_desactivee_n_est_plus_demandee(): void
    {
        $this->socle();
        $type = $this->piece();

        $this->actingAs($this->pilote())
            ->put("/admin/request-types/{$type->id}", $this->champs($type, ['is_required' => 1]));

        $this->assertFalse(
            RequestType::forCategory('depot', 'nouveau')->contains(fn ($t) => $t->id === $type->id),
            "Une pièce désactivée ne doit plus figurer parmi les pièces attendues."
        );
    }

    /* ── Suppression ────────────────────────────────── */

    public function test_une_piece_inutilisee_se_supprime(): void
    {
        $this->socle();
        $type = $this->piece('Pièce jamais servie');

        $this->actingAs($this->pilote())->delete("/admin/request-types/{$type->id}");

        $this->assertDatabaseMissing('request_types', ['id' => $type->id]);
    }

    public function test_une_piece_engagee_au_report_resiste_a_la_suppression(): void
    {
        $this->socle();
        $type = $this->piece('Certificat de nationalité');

        $etudiant = \App\Models\Student::create([
            'user_id' => User::create([
                'numero_identifiant' => 'ETU777', 'email' => 'etu777@test.local',
                'password' => \Illuminate\Support\Facades\Hash::make('secret1234'),
                'role' => 'etudiant', 'type_etudiant' => 'nouveau', 'is_active' => true,
            ])->id,
            'nom' => 'ADJO', 'prenom' => 'Kodjo', 'sexe' => 'M',
            'date_naissance' => '2006-01-10', 'lieu_naissance' => 'Lomé',
        ]);

        $sr = \App\Models\StudentRequest::create([
            'student_id' => $etudiant->id, 'category' => 'depot',
            'status' => 'soumis', 'version' => 1,
        ]);

        Derogation::create([
            'student_request_id' => $sr->id, 'request_type_id' => $type->id,
            'statut' => 'engagee', 'engagee_at' => now(),
        ]);

        $this->actingAs($this->pilote())
            ->delete("/admin/request-types/{$type->id}")
            ->assertSessionHasErrors();

        $this->assertNotNull(RequestType::find($type->id),
            "Supprimer une pièce attendue effacerait ce que le dossier réclame.");
    }

    /* ── L'écran expose bien les gestes ─────────────── */

    public function test_l_ecran_offre_desactivation_et_suppression(): void
    {
        $this->socle();
        $type = $this->piece();
        $type->grades()->create(['grade' => 'licence']);

        $this->actingAs($this->pilote())
            ->get('/admin/request-types/niveau/licence')
            ->assertOk()
            ->assertSee(route('admin.request-types.toggle', $type), false)
            ->assertSee(route('admin.request-types.destroy', $type), false);
    }

    public function test_le_bouton_bascule_l_etat(): void
    {
        $this->socle();
        $type = $this->piece();

        $this->actingAs($this->pilote())
            ->patch("/admin/request-types/{$type->id}/toggle", ['niveau' => 'licence']);
        $this->assertFalse((bool) $type->fresh()->is_active);

        $this->actingAs($this->pilote())
            ->patch("/admin/request-types/{$type->id}/toggle", ['niveau' => 'licence']);
        $this->assertTrue((bool) $type->fresh()->is_active);
    }
}
