<?php

namespace App\Http\Controllers;

use App\Models\AncienEtudiant;
use App\Models\Bachelier;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /** Échecs tolérés sur un même compte avant de le fermer un moment. */
    private const ESSAIS_PAR_COMPTE = 8;

    /** Durée de la fermeture, en secondes. */
    private const VERROU_SECONDES = 900;

    /* ══════════════════════════════════════════════════════════════════════
     |  LOGIN
     ╚══════════════════════════════════════════════════════════════════════ */

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'numero_identifiant' => 'required|string|max:60',
            'password'           => 'required|string|max:200',
        ]);

        /* Verrou PAR COMPTE, en plus de la cadence par adresse IP posée
           sur la route. Limiter par IP seule ne protège pas d'une attaque
           répartie : mille machines essayant chacune quatre mots de passe
           sur le même compte ne dépassent jamais la limite par IP. Ici,
           c'est le compte visé qui se ferme, d'où que viennent les essais. */
        $verrou = 'connexion:' . mb_strtolower($credentials['numero_identifiant']);
        if (RateLimiter::tooManyAttempts($verrou, self::ESSAIS_PAR_COMPTE)) {
            $minutes = (int) ceil(RateLimiter::availableIn($verrou) / 60);

            \App\Models\AuditLog::record('auth.verrouille', null,
                "Connexion refusée : trop d'échecs sur l'identifiant « {$credentials['numero_identifiant']} »");

            return back()->withErrors([
                'numero_identifiant' => "Trop de tentatives sur ce compte. Réessayez dans {$minutes} minute(s).",
            ])->onlyInput('numero_identifiant');
        }

        // Un compte désactivé ne doit PAS pouvoir se connecter
        if (Auth::attempt($credentials + ['is_active' => true])) {
            RateLimiter::clear($verrou);
            $request->session()->regenerate();
            \App\Models\AuditLog::record('auth.login', Auth::user(), 'Connexion réussie');
            return redirect()->intended('/dashboard');
        }

        RateLimiter::hit($verrou, self::VERROU_SECONDES);

        // Identifiants corrects mais compte désactivé : on le dit clairement.
        // Aucune fuite d'information — il faut déjà connaître le bon mot de passe.
        $compte = \App\Models\User::where('numero_identifiant', $credentials['numero_identifiant'])->first();
        if ($compte && !$compte->is_active
            && \Illuminate\Support\Facades\Hash::check($credentials['password'], $compte->password)) {

            \App\Models\AuditLog::record('auth.refus_desactive', $compte,
                "Tentative de connexion sur un compte désactivé ({$compte->numero_identifiant})");

            return back()->withErrors([
                'numero_identifiant' => "Ce compte a été désactivé. Rapprochez-vous de l'administration.",
            ])->onlyInput('numero_identifiant');
        }

        /* Un échec laisse une trace. Sans elle, une attaque par essais
           successifs ne se voit nulle part : le journal ne montrait que
           les connexions réussies. Le mot de passe essayé n'est JAMAIS
           consigné — c'est souvent le bon, à une faute de frappe près. */
        \App\Models\AuditLog::record('auth.echec', $compte,
            "Échec de connexion sur l'identifiant « {$credentials['numero_identifiant']} »"
            . ($compte ? '' : ' (compte inexistant)'));

        return back()->withErrors([
            'numero_identifiant' => 'Identifiant ou mot de passe invalide.',
        ])->onlyInput('numero_identifiant');
    }

    public function logout(Request $request)
    {
        \App\Models\AuditLog::record('auth.logout', Auth::user(), 'Déconnexion');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    /* ══════════════════════════════════════════════════════════════════════
     |  INSCRIPTION — Étape 1 : vérification d'identité
     ╚══════════════════════════════════════════════════════════════════════ */

    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Vérifie l'identité dans la base pré-chargée (bacheliers ou anciens).
     * Si valide → génère un identifiant, stocke en session, redirige vers étape 2.
     */
    public function verifyIdentity(Request $request)
    {
        $request->validate([
            'type_etudiant' => 'required|in:nouveau,ancien',
            'nom'           => 'required|string|max:100',
            'prenom'        => 'required|string|max:100',
            'date_naissance'=> 'required|date',
            'sexe'          => 'required|in:M,F',
        ]);

        $type = $request->type_etudiant;

        /* ── Nouveau bachelier ─────────────────────────────────────── */
        if ($type === 'nouveau') {
            $request->validate([
                'lieu_naissance' => 'required|string|max:150',
            ]);

            $record = Bachelier::parIdentite($request->nom, $request->prenom, $request->date_naissance,
                    $request->lieu_naissance, $request->sexe)
                ->where('is_registered', false)
                ->first();

            if (!$record) {
                return back()
                    ->withInput()
                    ->withErrors(['general' => 'Aucun bachelier correspondant à ces informations n\'a été trouvé dans notre base, ou ce compte existe déjà.']);
            }

            $identifiant = $this->generateIdentifiant($record->nom, $record->prenom);

            session([
                'reg_type'         => 'nouveau',
                'reg_bachelier_id' => $record->id,
                'reg_identifiant'  => $identifiant,
                'reg_nom'          => $record->nom,
                'reg_prenom'       => $record->prenom,
                'reg_date_naissance'  => $record->date_naissance->format('Y-m-d'),
                'reg_lieu_naissance'  => $record->lieu_naissance,
                'reg_sexe'         => $record->sexe,
            ]);
        }

        /* ── Ancien étudiant ───────────────────────────────────────── */
        else {
            $request->validate([
                'numero_carte' => 'required|string|max:50',
            ]);

            $record = AncienEtudiant::where('numero_carte', $request->numero_carte)
                ->whereRaw('LOWER(nom) = ?', [mb_strtolower($request->nom)])
                ->whereRaw('LOWER(prenom) = ?', [mb_strtolower($request->prenom)])
                ->whereDate('date_naissance', $request->date_naissance)
                ->where('sexe', $request->sexe)
                ->where('is_registered', false)
                ->first();

            if (!$record) {
                return back()
                    ->withInput()
                    ->withErrors(['general' => 'Aucun étudiant correspondant à ces informations n\'a été trouvé dans notre base, ou ce compte existe déjà.']);
            }

            $identifiant = $this->generateIdentifiant($record->nom, $record->prenom);

            session([
                'reg_type'              => 'ancien',
                'reg_ancien_id'         => $record->id,
                'reg_identifiant'       => $identifiant,
                'reg_nom'               => $record->nom,
                'reg_prenom'            => $record->prenom,
                'reg_date_naissance'    => $record->date_naissance->format('Y-m-d'),
                'reg_lieu_naissance'    => $record->lieu_naissance,
                'reg_sexe'              => $record->sexe,
                'reg_numero_carte'      => $record->numero_carte,
            ]);
        }

        return redirect()->route('register.complete');
    }

    /* ══════════════════════════════════════════════════════════════════════
     |  INSCRIPTION — Étape 2 : création du mot de passe
     ╚══════════════════════════════════════════════════════════════════════ */

    public function showComplete()
    {
        if (!session('reg_identifiant')) {
            return redirect()->route('register')
                ->withErrors(['general' => 'Session expirée. Veuillez recommencer.']);
        }

        return view('auth.register_complete');
    }

    public function completeRegistration(Request $request)
    {
        if (!session('reg_identifiant')) {
            return redirect()->route('register')
                ->withErrors(['general' => 'Session expirée. Veuillez recommencer.']);
        }

        $request->validate([
            'email'    => 'required|email|max:150|unique:users,email',
            'password' => ['required', 'confirmed', \App\Support\MotDePasse::usager()],
        ]);

        $type        = session('reg_type');
        $identifiant = session('reg_identifiant');

        /* Tout ou rien, et une seule fois par fiche.
           La fiche du référentiel est verrouillée le temps de l'opération,
           puis RE-vérifiée : deux navigateurs ayant prouvé la même identité
           créaient sinon deux comptes pour la même personne. Et si une
           étape échouait à mi-chemin, il restait un compte sans fiche
           étudiant, ou une fiche marquée inscrite sans compte. */
        $cree = DB::transaction(function () use ($request, $type, $identifiant) {
            $fiche = $type === 'nouveau'
                ? Bachelier::whereKey(session('reg_bachelier_id'))->lockForUpdate()->first()
                : AncienEtudiant::whereKey(session('reg_ancien_id'))->lockForUpdate()->first();

            if (!$fiche || $fiche->is_registered) {
                return false;
            }

            // L'identifiant a pu être pris depuis l'étape 1
            if (User::where('numero_identifiant', $identifiant)->exists()) {
                $identifiant = $this->generateIdentifiant($fiche->nom, $fiche->prenom);
            }

            $user = User::create([
                'numero_identifiant' => $identifiant,
                'email'              => $request->email,
                'password'           => Hash::make($request->password),
                'role'               => 'etudiant',
                'type_etudiant'      => $type,
                'is_active'          => true,
            ]);
            $user->assignRole('etudiant');

            Student::create([
                'user_id'        => $user->id,
                'nom'            => session('reg_nom'),
                'prenom'         => session('reg_prenom'),
                'sexe'           => session('reg_sexe'),
                'date_naissance' => session('reg_date_naissance'),
                'lieu_naissance' => session('reg_lieu_naissance'),
                $type === 'nouveau' ? 'bachelier_id' : 'ancien_etudiant_id' => $fiche->id,
            ]);

            $fiche->update(['is_registered' => true]);

            \App\Models\AuditLog::record('compte.cree', $user,
                "Compte usager créé par inscription : {$identifiant}");

            return $identifiant;
        });

        if ($cree === false) {
            session()->forget(['reg_type', 'reg_bachelier_id', 'reg_ancien_id', 'reg_identifiant']);

            return redirect()->route('register')->withErrors([
                'general' => 'Un compte existe déjà pour cette identité. Connectez-vous, ou rapprochez-vous de la scolarité.',
            ]);
        }
        $identifiant = $cree;

        // Nettoyage session
        session()->forget([
            'reg_type', 'reg_bachelier_id', 'reg_ancien_id', 'reg_identifiant',
            'reg_nom', 'reg_prenom', 'reg_date_naissance', 'reg_lieu_naissance',
            'reg_sexe', 'reg_numero_carte',
        ]);

        return redirect()->route('login')
            ->with('success', "Compte créé ! Connectez-vous avec votre identifiant : {$identifiant}");
    }

    /* ══════════════════════════════════════════════════════════════════════
     |  Helpers privés
     ╚══════════════════════════════════════════════════════════════════════ */

    /**
     * Génère un identifiant unique : 1ère lettre du prénom + nom, majuscules, sans accents.
     * Ex. : Jean Dupont → JDUPONT   |   doublon → JDUPONT2, JDUPONT3…
     */
    private function generateIdentifiant(string $nom, string $prenom): string
    {
        $base = strtoupper(substr($this->removeAccents($prenom), 0, 1) . $this->removeAccents($nom));
        $base = preg_replace('/[^A-Z]/', '', $base);
        $base = $base ?: 'ETUDIANT';

        $candidate = $base;
        $counter   = 2;

        while (User::where('numero_identifiant', $candidate)->exists()) {
            $candidate = $base . $counter++;
        }

        return $candidate;
    }

    /** Supprime les accents d'une chaîne UTF-8. */
    private function removeAccents(string $str): string
    {
        $map = [
            'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A',
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
            'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'Ç'=>'C','ç'=>'c','Ñ'=>'N','ñ'=>'n',
        ];
        return strtr($str, $map);
    }
}
