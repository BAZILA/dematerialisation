<?php

namespace App\Http\Controllers;

use App\Models\AncienEtudiant;
use App\Models\Bachelier;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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
            'numero_identifiant' => 'required|string',
            'password'           => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            \App\Models\AuditLog::record('auth.login', Auth::user(), 'Connexion réussie');
            return redirect()->intended('/dashboard');
        }

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

            $record = Bachelier::whereRaw('LOWER(nom) = ?', [mb_strtolower($request->nom)])
                ->whereRaw('LOWER(prenom) = ?', [mb_strtolower($request->prenom)])
                ->whereDate('date_naissance', $request->date_naissance)
                ->whereRaw('LOWER(lieu_naissance) = ?', [mb_strtolower($request->lieu_naissance)])
                ->where('sexe', $request->sexe)
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
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $type        = session('reg_type');
        $identifiant = session('reg_identifiant');

        // Création de l'utilisateur
        $user = User::create([
            'numero_identifiant' => $identifiant,
            'email'              => $request->email,
            'password'           => Hash::make($request->password),
            'role'               => 'etudiant',
            'type_etudiant'      => $type,
            'is_active'          => true,
        ]);
        $user->assignRole('etudiant');

        // Création du profil étudiant
        $studentData = [
            'user_id'        => $user->id,
            'nom'            => session('reg_nom'),
            'prenom'         => session('reg_prenom'),
            'sexe'           => session('reg_sexe'),
            'date_naissance' => session('reg_date_naissance'),
            'lieu_naissance' => session('reg_lieu_naissance'),
        ];

        if ($type === 'nouveau') {
            $studentData['bachelier_id'] = session('reg_bachelier_id');
            Bachelier::find(session('reg_bachelier_id'))?->update(['is_registered' => true]);
        } else {
            $studentData['ancien_etudiant_id'] = session('reg_ancien_id');
            AncienEtudiant::find(session('reg_ancien_id'))?->update(['is_registered' => true]);
        }

        Student::create($studentData);

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
