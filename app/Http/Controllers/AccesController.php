<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccesParCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Accès des usagers par code, sans mot de passe.
 *
 * Deux écrans : on prouve son identité, on reçoit un code, on le
 * saisit. À chaque visite, un nouveau code — rien à mémoriser.
 */
class AccesController extends Controller
{
    public function __construct(private AccesParCode $service)
    {
    }

    /** Le mode doit être allumé, sinon la connexion classique reste la règle. */
    private function exigerModeActif(): void
    {
        abort_unless($this->service->actif(), 404,
            "L'accès par code n'est pas activé sur cette plateforme.");
    }

    /* ── 1. Identité ───────────────────────────────────── */

    public function identite()
    {
        $this->exigerModeActif();

        return view('auth.acces.identite');
    }

    public function verifierIdentite(Request $request)
    {
        $this->exigerModeActif();

        $data = $request->validate([
            'type_etudiant'  => 'required|in:nouveau,ancien',
            'nom'            => 'required|string|max:100',
            'prenom'         => 'required|string|max:100',
            'date_naissance' => 'required|date|before:today',
            'sexe'           => 'required|in:M,F',
            'lieu_naissance' => 'required_if:type_etudiant,nouveau|nullable|string|max:150',
            'numero_carte'   => 'required_if:type_etudiant,ancien|nullable|string|max:50',
        ], [], [
            'type_etudiant'  => 'profil',
            'date_naissance' => 'date de naissance',
            'lieu_naissance' => 'lieu de naissance',
            'numero_carte'   => 'numéro de carte',
        ]);

        $resultat = $this->service->identifier([
            'type'           => $data['type_etudiant'],
            'nom'            => $data['nom'],
            'prenom'         => $data['prenom'],
            'date_naissance' => $data['date_naissance'],
            'sexe'           => $data['sexe'],
            'lieu_naissance' => $data['lieu_naissance'] ?? null,
            'numero_carte'   => $data['numero_carte'] ?? null,
        ]);

        if (!$resultat['ok']) {
            return back()->withInput()->withErrors(['general' => $resultat['message']]);
        }

        $user = $resultat['user'];
        ['code' => $code] = $this->service->emettre($user, $request->ip());

        $this->expedierParCourriel($user, $code);

        /* Le code transite par la session, jamais par l'URL : une adresse
           reste dans l'historique, les journaux et le presse-papier. */
        $request->session()->put('acces_user_id', $user->id);
        $request->session()->put('acces_code_affiche', $code);

        return redirect()->route('acces.code');
    }

    /* ── 2. Code ───────────────────────────────────────── */

    public function code(Request $request)
    {
        $this->exigerModeActif();

        $user = User::find($request->session()->get('acces_user_id'));
        if (!$user) {
            return redirect()->route('acces.identite')
                ->withErrors(['general' => "Session expirée. Reprenez la vérification d'identité."]);
        }

        // Affiché une seule fois : on le retire de la session dans la foulée
        $code = $request->session()->pull('acces_code_affiche');

        return view('auth.acces.code', [
            'user'          => $user,
            'code'          => $code,
            'envoyeParMail' => $this->service->envoiEmailActif() && filled($user->email),
            'minutes'       => $this->service->dureeMinutes(),
        ]);
    }

    public function verifierCode(Request $request)
    {
        $this->exigerModeActif();

        $data = $request->validate([
            'code' => 'required|string|max:20',
        ]);

        $user = User::find($request->session()->get('acces_user_id'));
        if (!$user) {
            return redirect()->route('acces.identite')
                ->withErrors(['general' => "Session expirée. Reprenez la vérification d'identité."]);
        }

        $resultat = $this->service->verifier($user, $data['code']);

        if (!$resultat['ok']) {
            return back()->withErrors(['code' => $resultat['message']]);
        }

        $request->session()->forget(['acces_user_id', 'acces_code_affiche']);

        Auth::login($user);
        $request->session()->regenerate();   // anti-fixation de session

        \App\Models\AuditLog::record('auth.login', $user,
            "Connexion par code — {$user->numero_identifiant}");

        return redirect()->intended(route('student.dashboard'));
    }

    /** Nouveau code sans refaire la saisie d'identité. */
    public function renvoyer(Request $request)
    {
        $this->exigerModeActif();

        $user = User::find($request->session()->get('acces_user_id'));
        if (!$user) {
            return redirect()->route('acces.identite')
                ->withErrors(['general' => "Session expirée. Reprenez la vérification d'identité."]);
        }

        ['code' => $code] = $this->service->emettre($user, $request->ip());
        $this->expedierParCourriel($user, $code);

        $request->session()->put('acces_code_affiche', $code);

        return redirect()->route('acces.code')
            ->with('success', 'Un nouveau code a été généré. Le précédent ne fonctionne plus.');
    }

    /**
     * Envoi facultatif par courriel. Le code reste affiché à l'écran :
     * un échec d'expédition ne doit jamais bloquer l'usager.
     */
    private function expedierParCourriel(User $user, string $code): void
    {
        if (!$this->service->envoiEmailActif() || blank($user->email)) {
            return;
        }

        try {
            app(\App\Services\NotificationService::class)->notifier(
                'acces.code',
                ['email' => $user->email, 'nom' => $user->student?->nom ?? ''],
                ['code' => $code, 'minutes' => (string) $this->service->dureeMinutes()],
                $user,
                $user->id
            );
        } catch (\Throwable $e) {
            \Log::warning("Envoi du code d'accès impossible : " . $e->getMessage());
        }
    }
}
