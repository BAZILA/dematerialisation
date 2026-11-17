<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Tests\TestCase;

/**
 * « Action impossible » au dépôt d'une pièce.
 *
 * Quand un envoi dépasse la limite « post_max_size » du serveur, Laravel
 * lève PostTooLargeException avec un message VIDE par défaut. Côté
 * navigateur, une chaîne vide vaut « faux » : l'écran retombait sur son
 * texte de repli, sans rien dire de la cause. L'usager renvoyait le même
 * fichier en boucle.
 *
 * Ce défaut n'apparaît pas en local : XAMPP autorise des envois bien
 * plus lourds que la configuration du serveur de production.
 */
class EnvoiTropVolumineuxTest extends TestCase
{
    use RefreshDatabase;

    public function test_laravel_leve_bien_un_message_vide_par_defaut(): void
    {
        // Le constat qui explique tout : sans intervention, le message
        // transmis au navigateur est une chaîne vide.
        $this->assertSame('', (new PostTooLargeException())->getMessage());
    }

    public function test_un_envoi_trop_lourd_renvoie_un_message_explicite(): void
    {
        $this->socle();

        // On simule le dépassement, qui ne peut pas être reproduit tel quel
        // en test : la limite est celle du processus PHP.
        \Illuminate\Support\Facades\Route::post('/_essai/trop-lourd', function () {
            throw new PostTooLargeException();
        });

        $reponse = $this->postJson('/_essai/trop-lourd');

        $reponse->assertStatus(413);
        $message = $reponse->json('message');

        $this->assertNotSame('', $message,
            "Un message vide fait afficher « Action impossible » à l'usager.");
        $this->assertStringContainsString('trop volumineux', $message);
    }

    public function test_le_message_donne_la_limite_reelle_du_serveur(): void
    {
        $this->socle();

        \Illuminate\Support\Facades\Route::post('/_essai/limite', function () {
            throw new PostTooLargeException();
        });

        // C'est la limite du serveur qui décide, pas celle de l'application :
        // l'afficher permet de savoir quoi régler.
        $limite = ini_get('post_max_size');

        $this->postJson('/_essai/limite')
            ->assertStatus(413)
            ->assertJsonFragment(['message' =>
                "Fichier trop volumineux pour le serveur (limite : {$limite}). "
                . 'Réduisez sa taille — une numérisation en 150 dpi suffit — puis réessayez.',
            ]);
    }

    public function test_sans_json_l_usager_revient_au_formulaire_avec_l_erreur(): void
    {
        $this->socle();

        \Illuminate\Support\Facades\Route::post('/_essai/formulaire', function () {
            throw new PostTooLargeException();
        });

        $this->from('/student/dashboard')
            ->post('/_essai/formulaire')
            ->assertRedirect('/student/dashboard')
            ->assertSessionHasErrors('file');
    }

    public function test_l_ecran_de_depot_ne_retombe_plus_sur_action_impossible(): void
    {
        // Le texte de repli, qui ne disait rien, a disparu de l'écran.
        $vue = file_get_contents(resource_path('views/student/dashboard.blade.php'));

        $this->assertStringNotContainsString("'Action impossible.'", $vue,
            "Le repli muet ne doit plus exister.");
        $this->assertStringContainsString('ukMessageErreur', $vue);
    }
}
