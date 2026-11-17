<?php

namespace Tests\Feature;

use App\Models\NotificationEnvoyee;
use App\Models\Paiement;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quand un module est éteint, tout ce qui s'y rapporte doit disparaître :
 * les écrans, mais aussi les routes. Masquer un lien ne suffit pas — une
 * adresse tapée à la main doit se heurter à un mur.
 */
class InterrupteursModulesTest extends TestCase
{
    use RefreshDatabase;

    private function reglage(string $cle, string $valeur): void
    {
        Setting::set($cle, $valeur);
        \Cache::forget('settings.all');
    }

    public function test_module_paiement_eteint_ferme_la_route(): void
    {
        $this->socle();
        $admin = $this->superAdmin();

        $this->reglage('payments.enabled', '0');
        $this->assertFalse(Paiement::moduleActif());

        $this->actingAs($admin)->get('/admin/paiements')->assertNotFound();
    }

    public function test_module_paiement_allume_ouvre_la_route(): void
    {
        $this->socle();
        $admin = $this->superAdmin();

        $this->reglage('payments.enabled', '1');
        $this->assertTrue(Paiement::moduleActif());

        $this->actingAs($admin)->get('/admin/paiements')->assertOk();
    }

    public function test_aucun_lien_paiement_quand_le_module_est_eteint(): void
    {
        $this->socle();
        $admin = $this->superAdmin();
        $this->reglage('payments.enabled', '0');

        // Le menu latéral est partagé : n'importe quelle page du
        // back-office suffit à vérifier qu'aucun lien ne subsiste.
        $this->actingAs($admin)->get('/admin/candidatures')
            ->assertOk()
            ->assertDontSee('/admin/paiements');
    }

    public function test_notifications_eteintes_suspendent_les_envois(): void
    {
        $this->socle();
        $this->reglage('notifications.enabled', '0');

        $this->assertFalse(NotificationEnvoyee::moduleActif());

        app(\App\Services\NotificationService::class)->notifier(
            'candidature.admise',
            ['email' => 'destinataire@test.local'],
            ['nom' => 'DUPONT', 'reference' => 'CAND-TEST', 'parcours' => 'Informatique']
        );

        $trace = NotificationEnvoyee::latest('id')->first();
        $this->assertNotNull($trace, "La trace doit exister même sans envoi.");
        $this->assertSame('suspendu', $trace->statut,
            "Module éteint : le message est consigné mais suspendu, jamais expédié.");
    }

    public function test_les_donnees_survivent_a_l_extinction(): void
    {
        $this->socle();
        $this->reglage('payments.enabled', '1');

        $paiement = Paiement::create([
            'reference'      => 'PAY-TEST-0001',
            'payable_type'   => \App\Models\Candidature::class,
            'payable_id'     => $this->candidature($this->parcours())->id,
            'procedure_code' => 'candidature',
            'montant'        => 5000,
            'statut'         => 'en_attente',
        ]);

        $this->reglage('payments.enabled', '0');

        $this->assertDatabaseHas('paiements', ['reference' => 'PAY-TEST-0001']);
        $this->assertSame('en_attente', $paiement->fresh()->statut,
            "Éteindre un module masque son interface, il n'efface pas les données.");
    }
}
