<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'écran des paramètres, après refonte.
 *
 * Le danger d'une refonte par rubriques : qu'un réglage sorte de la
 * liste sans que personne ne s'en aperçoive. Il resterait en base,
 * appliqué, mais plus modifiable — et introuvable. On vérifie donc que
 * CHAQUE réglage existant est bien présent à l'écran.
 */
class EcranDesParametresTest extends TestCase
{
    use RefreshDatabase;

    /* ── Rien ne disparaît ──────────────────────────── */

    public function test_chaque_reglage_est_present_a_l_ecran(): void
    {
        $this->socle();

        $page = $this->actingAs($this->superAdmin())->get('/super-admin/parametres');
        $page->assertOk();

        $absents = [];
        foreach (Setting::all() as $reglage) {
            if (!str_contains($page->getContent(), 'settings[' . $reglage->key . ']')) {
                $absents[] = $reglage->key;
            }
        }

        $this->assertSame([], $absents,
            "Reglage(s) sorti(s) de l'ecran, donc plus modifiable(s) :\n  - "
            . implode("\n  - ", $absents));
    }

    public function test_chaque_reglage_porte_son_libelle(): void
    {
        $this->socle();
        $page = $this->actingAs($this->superAdmin())->get('/super-admin/parametres');

        // Un reglage sans libelle n'affiche que sa cle technique : illisible.
        $sansLibelle = Setting::whereNull('label')->orWhere('label', '')->pluck('key')->all();

        $this->assertSame([], $sansLibelle,
            'Reglage(s) sans libelle lisible : ' . implode(', ', $sansLibelle));
    }

    /* ── L'enregistrement passe toujours ────────────── */

    public function test_enregistrer_un_interrupteur(): void
    {
        $this->socle();
        Setting::set('notifications.enabled', '0');

        $this->actingAs($this->superAdmin())->post('/super-admin/parametres', [
            'settings' => ['notifications.enabled' => '1'],
        ]);

        $this->assertTrue((bool) Setting::get('notifications.enabled'));
    }

    public function test_eteindre_un_interrupteur_decoche(): void
    {
        $this->socle();
        Setting::set('notifications.enabled', '1');

        // Une case decochee n'est pas envoyee par le navigateur : c'est le
        // defaut qui avait deja piege les pieces attendues.
        $this->actingAs($this->superAdmin())->post('/super-admin/parametres', [
            'settings' => ['app.short_name' => 'UK'],
        ]);

        $this->assertFalse((bool) Setting::get('notifications.enabled'),
            "Decocher un module doit vraiment l'eteindre.");
    }

    public function test_enregistrer_une_valeur_texte(): void
    {
        $this->socle();

        $this->actingAs($this->superAdmin())->post('/super-admin/parametres', [
            'settings' => ['app.short_name' => 'UK-TEST'],
        ]);

        $this->assertSame('UK-TEST', Setting::get('app.short_name'));
    }

    /* ── Les dépendances sont annoncées ─────────────── */

    public function test_l_ecran_previent_qu_une_relance_sans_notifications_ne_part_pas(): void
    {
        $this->socle();
        Setting::set('notifications.enabled', '0');
        Setting::set('relances.enabled', '1');

        // Le piege vecu en production : le SMTP fonctionne, les relances
        // sont allumees, et rien ne part. L'ecran doit le dire.
        $this->actingAs($this->superAdmin())->get('/super-admin/parametres')
            ->assertOk()
            ->assertSee('aucun effet');
    }

    public function test_un_reglage_commande_reste_modifiable(): void
    {
        $this->socle();
        Setting::set('notifications.enabled', '0');

        // On le signale, on ne le supprime pas : le masquer ferait croire
        // qu'il n'existe pas.
        $this->actingAs($this->superAdmin())->get('/super-admin/parametres')
            ->assertSee('settings[notifications.email.enabled]', false);
    }

    public function test_le_module_de_paiement_reste_rallumable_quand_il_est_eteint(): void
    {
        $this->socle();
        Setting::set('payments.enabled', '0');

        /* Le defaut trouve : « payments.enabled » appartient au groupe
           « paiement », que l'ecran retirait entierement quand le module
           etait eteint. L'interrupteur disparaissait donc avec ses
           propres reglages, et le paiement ne pouvait plus etre rallume
           autrement qu'en base. */
        $this->actingAs($this->superAdmin())->get('/super-admin/parametres')
            ->assertOk()
            ->assertSee('settings[payments.enabled]', false);
    }

    public function test_on_peut_preparer_les_cles_sms_avant_d_allumer_les_notifications(): void
    {
        $this->socle();
        Setting::set('notifications.enabled', '0');

        // Regler avant d'allumer, plutot que d'allumer a moitie regle.
        $this->actingAs($this->superAdmin())->post('/super-admin/parametres', [
            'settings' => ['sms.togocom.cle' => 'CLE-TEST-123'],
        ]);

        $this->assertSame('CLE-TEST-123', Setting::get('sms.togocom.cle'));
        $this->assertFalse((bool) Setting::get('notifications.enabled'),
            "Preparer la configuration ne doit pas allumer le module.");
    }

    /* ── Accès ──────────────────────────────────────── */

    public function test_un_agent_n_accede_pas_aux_parametres(): void
    {
        $this->socle();
        $agent = $this->agent(['acceder-backoffice', 'parametres.gerer'], 'AGENT900');

        $this->actingAs($agent)->get('/super-admin/parametres')->assertForbidden();
    }

    public function test_un_agent_ne_peut_pas_enregistrer_les_parametres(): void
    {
        $this->socle();
        Setting::set('notifications.enabled', '0');
        $agent = $this->agent(['acceder-backoffice', 'parametres.gerer'], 'AGENT901');

        $this->actingAs($agent)->post('/super-admin/parametres', [
            'settings' => ['notifications.enabled' => '1'],
        ])->assertForbidden();

        $this->assertFalse((bool) Setting::get('notifications.enabled'));
    }
}
