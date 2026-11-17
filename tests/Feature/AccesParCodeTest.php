<?php

namespace Tests\Feature;

use App\Models\Bachelier;
use App\Models\CodeAcces;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\AccesParCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un usager qui vient une fois par an ne retient pas de mot de passe.
 * Il prouve son identité contre le référentiel et reçoit un code à
 * usage unique, valable quelques minutes.
 */
class AccesParCodeTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AccesParCode
    {
        return app(AccesParCode::class);
    }

    private function bachelier(array $extra = []): Bachelier
    {
        return Bachelier::create(array_merge([
            'nom' => 'KOSSI', 'prenom' => 'Ama', 'sexe' => 'F',
            'date_naissance' => '2006-03-14', 'lieu_naissance' => 'Kara',
            'annee_bac' => '2026', 'numero_table' => 'T-1234',
            'email' => 'ama.kossi@test.local',
            'is_registered' => false,
        ], $extra));
    }

    private function identite(array $extra = []): array
    {
        return array_merge([
            'type' => 'nouveau', 'nom' => 'KOSSI', 'prenom' => 'Ama',
            'date_naissance' => '2006-03-14', 'lieu_naissance' => 'Kara', 'sexe' => 'F',
        ], $extra);
    }

    /* ── Identification ─────────────────────────────── */

    public function test_une_identite_inconnue_est_refusee(): void
    {
        $this->socle();
        $this->bachelier();

        $r = $this->service()->identifier($this->identite(['nom' => 'INCONNU']));

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Aucun dossier', $r['message']);
    }

    public function test_le_message_ne_revele_pas_quel_champ_est_faux(): void
    {
        $this->socle();
        $this->bachelier();

        $mauvaisNom  = $this->service()->identifier($this->identite(['nom' => 'AUTRE']));
        $mauvaiseDate = $this->service()->identifier($this->identite(['date_naissance' => '1999-01-01']));

        $this->assertSame($mauvaisNom['message'], $mauvaiseDate['message'],
            "Un message différent aiderait à deviner l'identité d'autrui.");
    }

    public function test_la_premiere_visite_cree_le_compte(): void
    {
        $this->socle();
        $bachelier = $this->bachelier();

        $r = $this->service()->identifier($this->identite());

        $this->assertTrue($r['ok']);
        $this->assertSame('etudiant', $r['user']->role);
        $this->assertTrue($r['user']->hasRole('etudiant'));
        $this->assertDatabaseHas('students', [
            'user_id' => $r['user']->id, 'bachelier_id' => $bachelier->id,
        ]);
        $this->assertTrue($bachelier->fresh()->is_registered);
    }

    public function test_la_visite_suivante_retrouve_le_meme_compte(): void
    {
        $this->socle();
        $this->bachelier();

        $premier = $this->service()->identifier($this->identite());
        $second  = $this->service()->identifier($this->identite());

        $this->assertTrue($second['ok']);
        $this->assertSame($premier['user']->id, $second['user']->id,
            "Revenir ne doit pas créer un second compte.");
        $this->assertSame(1, User::where('role', 'etudiant')->count());
    }

    public function test_un_compte_suspendu_n_obtient_pas_de_code(): void
    {
        $this->socle();
        $this->bachelier();

        $user = $this->service()->identifier($this->identite())['user'];
        $user->update(['is_active' => false]);

        $r = $this->service()->identifier($this->identite());

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('suspendu', $r['message']);
    }

    /* ── Émission et vérification ───────────────────── */

    public function test_le_code_n_est_jamais_stocke_en_clair(): void
    {
        $this->socle();
        $this->bachelier();
        $user = $this->service()->identifier($this->identite())['user'];

        ['code' => $code] = $this->service()->emettre($user);

        $this->assertDatabaseMissing('codes_acces', ['code_hash' => $code]);
        $this->assertTrue(
            \Hash::check($code, CodeAcces::where('user_id', $user->id)->first()->code_hash)
        );
    }

    public function test_un_code_valide_ouvre_l_acces(): void
    {
        $this->socle();
        $this->bachelier();
        $user = $this->service()->identifier($this->identite())['user'];
        ['code' => $code] = $this->service()->emettre($user);

        $r = $this->service()->verifier($user, $code);

        $this->assertTrue($r['ok']);
        $this->assertNotNull(CodeAcces::where('user_id', $user->id)->first()->utilise_at,
            "Le code doit être consommé après usage.");
    }

    public function test_un_code_ne_sert_qu_une_fois(): void
    {
        $this->socle();
        $this->bachelier();
        $user = $this->service()->identifier($this->identite())['user'];
        ['code' => $code] = $this->service()->emettre($user);

        $this->service()->verifier($user, $code);
        $second = $this->service()->verifier($user, $code);

        $this->assertFalse($second['ok'],
            "Un code rejoué ne doit plus ouvrir l'accès.");
    }

    public function test_emettre_un_nouveau_code_invalide_le_precedent(): void
    {
        $this->socle();
        $this->bachelier();
        $user = $this->service()->identifier($this->identite())['user'];

        ['code' => $ancien] = $this->service()->emettre($user);
        ['code' => $nouveau] = $this->service()->emettre($user);

        $this->assertFalse($this->service()->verifier($user, $ancien)['ok'],
            "L'ancien code doit mourir, sinon un vieux courriel reste une porte ouverte.");
        $this->assertTrue($this->service()->verifier($user, $nouveau)['ok']);
    }

    public function test_un_code_expire_ne_fonctionne_plus(): void
    {
        $this->socle();
        $this->bachelier();
        $user = $this->service()->identifier($this->identite())['user'];
        ['code' => $code, 'modele' => $modele] = $this->service()->emettre($user);

        $modele->update(['expire_at' => now()->subMinute()]);

        $r = $this->service()->verifier($user, $code);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('expiré', $r['message']);
    }

    public function test_le_code_est_brule_apres_trop_d_essais(): void
    {
        $this->socle();
        $this->bachelier();
        $user = $this->service()->identifier($this->identite())['user'];
        ['code' => $bon] = $this->service()->emettre($user);

        for ($i = 0; $i < CodeAcces::MAX_TENTATIVES; $i++) {
            $this->service()->verifier($user, 'AAAA-AAAA');
        }

        $this->assertFalse($this->service()->verifier($user, $bon)['ok'],
            "Après le plafond d'essais, même le bon code doit être refusé.");
    }

    public function test_le_code_evite_les_caracteres_ambigus(): void
    {
        $this->socle();
        $this->bachelier();
        $user = $this->service()->identifier($this->identite())['user'];

        for ($i = 0; $i < 25; $i++) {
            ['code' => $code] = $this->service()->emettre($user);
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $code,
                "Ni O/0 ni I/1 : l'usager recopie ce qu'il lit.");
        }
    }

    public function test_la_saisie_tolere_minuscules_et_espaces(): void
    {
        $this->socle();
        $this->bachelier();
        $user = $this->service()->identifier($this->identite())['user'];
        ['code' => $code] = $this->service()->emettre($user);

        $this->assertTrue($this->service()->verifier($user, ' ' . strtolower($code) . ' ')['ok'],
            "Recopier en minuscules ne doit pas bloquer l'usager.");
    }

    /* ── Interrupteur ───────────────────────────────── */

    public function test_le_mode_est_pilotable(): void
    {
        $this->socle();

        Setting::set('acces.code_enabled', '0'); \Cache::forget('settings.all');
        $this->assertFalse($this->service()->actif());

        Setting::set('acces.code_enabled', '1'); \Cache::forget('settings.all');
        $this->assertTrue($this->service()->actif());
    }

    public function test_la_duree_reste_dans_des_bornes_raisonnables(): void
    {
        $this->socle();

        Setting::set('acces.code_duree', '0'); \Cache::forget('settings.all');
        $this->assertGreaterThanOrEqual(2, $this->service()->dureeMinutes());

        Setting::set('acces.code_duree', '99999'); \Cache::forget('settings.all');
        $this->assertLessThanOrEqual(120, $this->service()->dureeMinutes());
    }
}
