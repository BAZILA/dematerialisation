<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garde-fous d'accès. Le premier test correspond à une fuite réelle :
 * les identifiants du super-administrateur sont restés affichés sur la
 * page de connexion publique après la mise en ligne.
 */
class SecuriteAccesTest extends TestCase
{
    use RefreshDatabase;

    public function test_aucun_identifiant_de_demonstration_en_production(): void
    {
        $this->socle();
        app()->detectEnvironment(fn () => 'production');

        $reponse = $this->get('/login');

        $reponse->assertOk();
        $reponse->assertDontSee('admin123456');
        $reponse->assertDontSee('ADMIN001 </code>', false);
        $reponse->assertDontSee('Démonstration');
    }

    public function test_les_identifiants_restent_visibles_hors_production(): void
    {
        $this->socle();
        app()->detectEnvironment(fn () => 'local');

        $this->get('/login')->assertOk()->assertSee('admin123456');
    }

    public function test_le_back_office_est_ferme_sans_permission(): void
    {
        $this->socle();
        $intrus = $this->agent([]);   // aucune permission

        $this->actingAs($intrus)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($intrus)->get('/admin/candidatures')->assertForbidden();
    }

    public function test_un_compte_desactive_ne_passe_plus(): void
    {
        $this->socle();
        $agent = $this->agent(['acceder-backoffice', 'candidatures.voir']);

        $agent->update(['is_active' => false]);

        $reponse = $this->actingAs($agent->fresh())->get('/admin/candidatures');
        $this->assertContains($reponse->status(), [302, 403],
            'Un compte désactivé doit perdre son accès immédiatement.');
    }

    public function test_les_en_tetes_de_securite_sont_presents(): void
    {
        $this->socle();

        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
