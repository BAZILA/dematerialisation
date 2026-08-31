<?php

namespace Tests\Feature;

use App\Models\AgentPerimetre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un agent affecté à un parcours ne doit voir que les dossiers de ce
 * parcours. Sans périmètre, il voit tout : c'est le défaut assumé.
 */
class PerimetreAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_sans_perimetre_l_agent_couvre_tout(): void
    {
        $this->socle();
        $agent = $this->agent(['acceder-backoffice', 'candidatures.voir', 'candidature.traiter']);

        $this->assertNull($agent->perimeterParcoursIds(),
            "Aucune affectation doit signifier « couvre tout ».");
        $this->assertTrue($agent->coversParcours($this->parcours()->id));
    }

    public function test_le_perimetre_restreint_bien_l_agent(): void
    {
        $this->socle();
        $sien   = $this->parcours('Informatique');
        $autre  = $this->parcours('Gestion');
        $agent  = $this->agent(['acceder-backoffice', 'candidatures.voir', 'candidature.traiter']);

        AgentPerimetre::create(['user_id' => $agent->id, 'parcours_id' => $sien->id]);
        $agent = $agent->fresh();

        $this->assertSame([$sien->id], $agent->perimeterParcoursIds());
        $this->assertTrue($agent->coversParcours($sien->id));
        $this->assertFalse($agent->coversParcours($autre->id),
            "Un parcours hors affectation ne doit pas être couvert.");
    }

    public function test_une_candidature_hors_perimetre_est_refusee(): void
    {
        $this->socle();
        $sien  = $this->parcours('Informatique');
        $autre = $this->parcours('Gestion');
        $agent = $this->agent(['acceder-backoffice', 'candidatures.voir', 'candidature.traiter']);
        AgentPerimetre::create(['user_id' => $agent->id, 'parcours_id' => $sien->id]);

        $dedans = $this->candidature($sien, 'DEDANS');
        $dehors = $this->candidature($autre, 'DEHORS');

        $this->actingAs($agent->fresh())->get("/admin/candidatures/{$dedans->id}")->assertOk();
        $this->actingAs($agent->fresh())->get("/admin/candidatures/{$dehors->id}")->assertForbidden();
    }

    public function test_le_super_admin_passe_partout(): void
    {
        $this->socle();
        $parcours = $this->parcours();
        $super = $this->superAdmin();

        AgentPerimetre::create(['user_id' => $super->id, 'parcours_id' => $parcours->id]);

        $this->assertNull($super->fresh()->perimeterParcoursIds(),
            "Le super-administrateur ignore les périmètres.");
    }
}
