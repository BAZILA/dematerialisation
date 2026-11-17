<?php

namespace App\Services;

use App\Models\AncienEtudiant;
use App\Models\AuditLog;
use App\Models\Bachelier;
use App\Models\CodeAcces;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Accès des usagers sans mot de passe.
 *
 * L'usager prouve son identité contre le référentiel (bacheliers ou
 * anciens étudiants), et reçoit un code à usage unique. À chaque
 * visite, un nouveau code : rien à mémoriser, rien à réinitialiser.
 */
class AccesParCode
{
    /** Le mode code est-il activé ? Sinon, connexion classique. */
    public function actif(): bool
    {
        return (bool) Setting::get('acces.code_enabled', false);
    }

    /** Le code est-il aussi envoyé par courriel ? */
    public function envoiEmailActif(): bool
    {
        return (bool) Setting::get('acces.code_email', false);
    }

    /** Durée de validité, bornée pour rester raisonnable. */
    public function dureeMinutes(): int
    {
        return max(2, min(120, (int) Setting::get('acces.code_duree', 15)));
    }

    /**
     * Retrouve l'usager derrière une identité, en créant son compte
     * s'il vient pour la première fois.
     *
     * @param  array{type:string,nom:string,prenom:string,date_naissance:string,sexe:string,lieu_naissance?:string,numero_carte?:string}  $identite
     * @return array{ok:bool, user?:User, message?:string}
     */
    public function identifier(array $identite): array
    {
        $type = $identite['type'] ?? 'nouveau';

        $record = $type === 'nouveau'
            ? $this->chercherBachelier($identite)
            : $this->chercherAncien($identite);

        if (!$record) {
            // Message volontairement identique dans les deux cas : dire
            // « ce nom existe mais la date est fausse » aiderait qui
            // essaie de deviner l'identité d'autrui.
            return ['ok' => false, 'message' =>
                "Aucun dossier ne correspond à ces informations. Vérifiez leur exactitude, "
                . "telles qu'elles figurent sur vos documents officiels."];
        }

        $user = $this->compteExistant($type, $record) ?? $this->creerCompte($type, $record);

        if (!$user->is_active) {
            return ['ok' => false, 'message' =>
                "Cet accès a été suspendu. Rapprochez-vous de la scolarité."];
        }

        return ['ok' => true, 'user' => $user];
    }

    /**
     * Émet un code pour cet usager. Les codes encore vivants sont
     * invalidés : un seul code utilisable à la fois, sinon un ancien
     * courriel resterait une porte ouverte.
     *
     * @return array{code:string, modele:CodeAcces}
     */
    public function emettre(User $user, ?string $ip = null): array
    {
        CodeAcces::where('user_id', $user->id)
            ->whereNull('utilise_at')
            ->update(['utilise_at' => now()]);

        $code = $this->fabriquerCode();

        $modele = CodeAcces::create([
            'user_id'   => $user->id,
            'code_hash' => Hash::make($code),
            'expire_at' => now()->addMinutes($this->dureeMinutes()),
            'canal'     => $this->envoiEmailActif() ? 'email' : 'ecran',
            'ip'        => $ip,
        ]);

        AuditLog::record('acces.code_emis', $user,
            "Code d'accès émis pour {$user->numero_identifiant}"
            . " (valable {$this->dureeMinutes()} min)");

        return ['code' => $code, 'modele' => $modele];
    }

    /**
     * Vérifie un code saisi. Chaque échec est compté : au-delà du
     * plafond le code est brûlé, pour qu'un tirage au sort ne finisse
     * pas par tomber juste.
     *
     * @return array{ok:bool, user?:User, message?:string}
     */
    public function verifier(User $user, string $saisi): array
    {
        $code = CodeAcces::where('user_id', $user->id)
            ->whereNull('utilise_at')
            ->latest('id')->first();

        if (!$code) {
            return ['ok' => false, 'message' =>
                "Aucun code en cours. Recommencez la vérification de votre identité."];
        }

        if ($code->estExpire()) {
            return ['ok' => false, 'message' =>
                "Ce code a expiré. Demandez-en un nouveau."];
        }

        if ($code->estBrule()) {
            return ['ok' => false, 'message' =>
                "Trop de tentatives sur ce code. Demandez-en un nouveau."];
        }

        if (!Hash::check(strtoupper(trim($saisi)), $code->code_hash)) {
            $code->increment('tentatives');

            AuditLog::record('acces.code_echec', $user,
                "Code d'accès incorrect pour {$user->numero_identifiant}"
                . " (tentative {$code->tentatives}/" . CodeAcces::MAX_TENTATIVES . ')');

            $reste = CodeAcces::MAX_TENTATIVES - $code->tentatives;

            return ['ok' => false, 'message' => $reste > 0
                ? "Code incorrect. Il vous reste {$reste} tentative(s)."
                : "Trop de tentatives. Demandez un nouveau code."];
        }

        $code->update(['utilise_at' => now()]);

        AuditLog::record('acces.code_valide', $user,
            "Accès accordé par code à {$user->numero_identifiant}");

        return ['ok' => true, 'user' => $user];
    }

    /** Purge les codes périmés : ils n'ont plus d'usage. */
    public function purger(): int
    {
        return CodeAcces::where('expire_at', '<', now()->subDay())->delete();
    }

    /* ── Interne ───────────────────────────────────────── */

    /**
     * Code lisible à voix haute et sans ambiguïté : ni O/0, ni I/1,
     * qu'un usager recopierait de travers.
     */
    private function fabriquerCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // Groupé par 4 : on le relit plus facilement à l'écran
        return substr($code, 0, 4) . '-' . substr($code, 4, 4);
    }

    private function chercherBachelier(array $i): ?Bachelier
    {
        if (blank($i['lieu_naissance'] ?? null)) {
            return null;
        }

        return Bachelier::whereRaw('LOWER(nom) = ?', [mb_strtolower($i['nom'])])
            ->whereRaw('LOWER(prenom) = ?', [mb_strtolower($i['prenom'])])
            ->whereDate('date_naissance', $i['date_naissance'])
            ->whereRaw('LOWER(lieu_naissance) = ?', [mb_strtolower($i['lieu_naissance'])])
            ->where('sexe', $i['sexe'])
            ->first();
    }

    private function chercherAncien(array $i): ?AncienEtudiant
    {
        if (blank($i['numero_carte'] ?? null)) {
            return null;
        }

        return AncienEtudiant::where('numero_carte', $i['numero_carte'])
            ->whereRaw('LOWER(nom) = ?', [mb_strtolower($i['nom'])])
            ->whereRaw('LOWER(prenom) = ?', [mb_strtolower($i['prenom'])])
            ->whereDate('date_naissance', $i['date_naissance'])
            ->where('sexe', $i['sexe'])
            ->first();
    }

    private function compteExistant(string $type, $record): ?User
    {
        $colonne = $type === 'nouveau' ? 'bachelier_id' : 'ancien_etudiant_id';

        return Student::where($colonne, $record->id)->first()?->user;
    }

    /**
     * Première visite : le compte est créé à la volée. Aucun mot de
     * passe utilisable n'est posé — l'accès passe uniquement par code.
     */
    private function creerCompte(string $type, $record): User
    {
        $user = User::create([
            'numero_identifiant' => $this->identifiantUnique($record->nom, $record->prenom),
            'email'              => $record->email ?: null,
            'password'           => Hash::make(Str::random(40)),
            'role'               => 'etudiant',
            'type_etudiant'      => $type,
            'is_active'          => true,
        ]);
        $user->assignRole('etudiant');

        $donnees = [
            'user_id'        => $user->id,
            'nom'            => $record->nom,
            'prenom'         => $record->prenom,
            'sexe'           => $record->sexe,
            'date_naissance' => $record->date_naissance,
            'lieu_naissance' => $record->lieu_naissance ?? null,
            'telephone'      => $record->telephone ?? null,
        ];
        $donnees[$type === 'nouveau' ? 'bachelier_id' : 'ancien_etudiant_id'] = $record->id;

        Student::create($donnees);
        $record->update(['is_registered' => true]);

        AuditLog::record('acces.compte_cree', $user,
            "Compte usager créé à la première connexion : {$user->numero_identifiant}");

        return $user;
    }

    /** Première lettre du prénom + nom, sans accent, suffixé si déjà pris. */
    private function identifiantUnique(string $nom, ?string $prenom): string
    {
        $base = strtoupper(Str::ascii(mb_substr($prenom ?: '', 0, 1) . $nom));
        $base = preg_replace('/[^A-Z0-9]/', '', $base) ?: 'USAGER';

        $identifiant = $base;
        $n = 1;
        while (User::where('numero_identifiant', $identifiant)->exists()) {
            $identifiant = $base . (++$n);
        }

        return $identifiant;
    }
}
