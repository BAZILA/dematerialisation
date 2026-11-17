<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une procédure s'ouvre pour toute l'université, pour un niveau, ou
 * pour un parcours précis. Quand plusieurs règles se rencontrent, la
 * plus précise décide seule : on ne cumule pas, sinon « fermé pour les
 * masters » serait contredit par « ouvert pour tous ».
 */
class OuvertureCibleeTest extends TestCase
{
    use RefreshDatabase;

    private function annee(): AnneeAcademique
    {
        return AnneeAcademique::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-08-31', 'statut' => 'ouverte', 'is_active' => true,
        ]);
    }

    private function regle(AnneeAcademique $annee, string $op, bool $ouverte,
                           ?string $grade = null, ?int $parcoursId = null): Periode
    {
        return Periode::create([
            'annee_academique_id' => $annee->id,
            'operation' => $op, 'grade' => $grade, 'parcours_id' => $parcoursId,
            'is_open' => $ouverte,
        ]);
    }

    public function test_sans_parcours_seule_la_regle_generale_compte(): void
    {
        $this->socle();
        $annee = $this->annee();
        $this->regle($annee, 'depot', true);

        $this->assertTrue($annee->fresh()->operationOpen('depot'),
            "Comportement historique : pas de contexte, on lit la règle générale.");
    }

    public function test_une_regle_de_niveau_ne_deborde_pas_sur_les_autres(): void
    {
        $this->socle();
        $annee = $this->annee();

        $licence = $this->parcours('Lettres');                 // grade licence
        $master  = $this->parcours('Gestion');
        $master->update(['grade' => 'master']);

        // Rien de général : seuls les masters sont ouverts
        $this->regle($annee, 'depot', true, 'master');
        $annee = $annee->fresh(['periodes']);

        $this->assertTrue($annee->operationOpen('depot', $master->fresh()));
        $this->assertFalse($annee->operationOpen('depot', $licence),
            "La licence n'est visée par aucune règle : elle reste fermée.");
    }

    public function test_le_niveau_l_emporte_sur_la_regle_generale(): void
    {
        $this->socle();
        $annee = $this->annee();

        $master = $this->parcours('Gestion');
        $master->update(['grade' => 'master']);

        $this->regle($annee, 'depot', true);              // tout ouvert
        $this->regle($annee, 'depot', false, 'master');   // sauf les masters
        $annee = $annee->fresh(['periodes']);

        $this->assertFalse($annee->operationOpen('depot', $master->fresh()),
            "La règle de niveau doit fermer le master malgré l'ouverture générale.");
    }

    public function test_le_parcours_l_emporte_sur_son_niveau(): void
    {
        $this->socle();
        $annee = $this->annee();

        $masterInfo = $this->parcours('Master Informatique');
        $masterInfo->update(['grade' => 'master']);
        $masterAutre = $this->parcours('Master Gestion');
        $masterAutre->update(['grade' => 'master']);

        $this->regle($annee, 'depot', false, 'master');                       // masters fermés
        $this->regle($annee, 'depot', true, null, $masterInfo->id);           // sauf celui-ci
        $annee = $annee->fresh(['periodes']);

        $this->assertTrue($annee->operationOpen('depot', $masterInfo->fresh()),
            "Le parcours nommé est la règle la plus précise : il gagne.");
        $this->assertFalse($annee->operationOpen('depot', $masterAutre->fresh()));
    }

    public function test_les_dates_s_appliquent_aussi_aux_regles_ciblees(): void
    {
        $this->socle();
        $annee = $this->annee();
        $master = $this->parcours('Gestion');
        $master->update(['grade' => 'master']);

        $regle = $this->regle($annee, 'depot', true, 'master');
        $regle->update([
            'date_debut' => now()->addDays(5)->toDateString(),
            'date_fin'   => now()->addDays(10)->toDateString(),
        ]);

        $this->assertFalse($annee->fresh(['periodes'])->operationOpen('depot', $master->fresh()),
            "Ouverte mais pas encore commencée : la fenêtre doit rester fermée.");
    }

    public function test_une_annee_close_ferme_tout(): void
    {
        $this->socle();
        $annee = $this->annee();
        $master = $this->parcours('Gestion');
        $master->update(['grade' => 'master']);

        $this->regle($annee, 'depot', true);
        $this->regle($annee, 'depot', true, 'master');
        $annee->update(['statut' => 'cloturee']);

        $this->assertFalse($annee->fresh(['periodes'])->operationOpen('depot', $master->fresh()),
            "Une année clôturée prime sur toute règle d'ouverture.");
    }

    public function test_les_regles_ciblees_sont_listables(): void
    {
        $this->socle();
        $annee = $this->annee();
        $master = $this->parcours('Gestion');
        $master->update(['grade' => 'master']);

        $this->regle($annee, 'depot', true);
        $this->regle($annee, 'depot', false, 'master');
        $this->regle($annee, 'depot', true, null, $master->id);
        $annee = $annee->fresh(['periodes']);

        $this->assertTrue($annee->periodeGenerale('depot')->estGenerale());
        $this->assertCount(2, $annee->periodesCiblees('depot'));
        $this->assertSame(2, $annee->periodesCiblees('depot')->first()->precision(),
            "Les règles ciblées sont triées de la plus précise à la moins précise.");
    }
}
