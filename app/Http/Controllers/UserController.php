<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MotDePasse;
use App\Support\Privileges;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /** Rôles assignables au personnel (hors usagers). */
    private function staffRoles()
    {
        return Role::whereNotIn('name', ['etudiant', 'candidat'])->orderBy('name')->get();
    }

    public function index()
    {
        $users = User::whereNotIn('role', ['etudiant', 'candidat'])
            ->orderBy('nom')->orderBy('numero_identifiant')
            ->paginate(\App\Models\Setting::perPage())->withQueryString();
        $roles = $this->staffRoles();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'numero_identifiant' => 'required|string|max:50|unique:users,numero_identifiant',
            'nom'       => 'required|string|max:100',
            'prenom'    => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email',
            // Un agent peut cumuler plusieurs fonctions (dépôt + attestation…)
            'roles'     => 'required|array|min:1',
            'roles.*'   => 'exists:roles,name',
            'password'  => ['required', 'string', MotDePasse::personnel()],
            'signature' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ]);
        $this->exigerRolesAttribuables($request->user(), $data['roles']);

        $user = User::create([
            'numero_identifiant' => $data['numero_identifiant'],
            'nom'       => $data['nom'],
            'prenom'    => $data['prenom'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            // « role » reste le rôle PRINCIPAL : affichage et redirection
            'role'      => $data['roles'][0],
            'is_active' => true,
        ]);
        $user->syncRoles($data['roles']);

        // Le créateur connaît ce mot de passe : le titulaire devra le changer.
        $user->forceFill(['doit_changer_mot_de_passe' => true])->save();

        if ($request->hasFile('signature')) {
            $user->update(['signature_path' => $request->file('signature')->store('signatures', 'local')]);
        }

        \App\Models\AuditLog::record('utilisateur.cree', $user,
            "Compte créé : {$user->staff_name} ({$user->numero_identifiant}) — rôle(s) " . implode(', ', $data['roles']),
            ['roles' => $data['roles']]);

        return back()->with('success', "Compte « {$user->staff_name} » créé.");
    }

    public function update(Request $request, User $user)
    {
        $this->exigerComptePersonnel($user);
        $this->exigerDroitSur($request->user(), $user);

        $data = $request->validate([
            'nom'       => 'required|string|max:100',
            'prenom'    => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email,' . $user->id,
            // Un agent peut cumuler plusieurs fonctions (dépôt + attestation…)
            'roles'     => 'required|array|min:1',
            'roles.*'   => 'exists:roles,name',
            'password'  => ['nullable', 'string', MotDePasse::personnel()],
            'is_active' => 'nullable|boolean',
            'signature' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        /* Seuls les rôles AJOUTÉS sont contrôlés : un gestionnaire peut
           corriger le nom d'un collègue sans avoir à détenir chacun des
           rôles que celui-ci porte déjà. */
        foreach ($data['roles'] as $r) {
            $this->assertStaffRole($r);
        }
        $dejaPortes = $user->getRoleNames()->all();
        $this->exigerRolesAttribuables($request->user(), array_diff($data['roles'], $dejaPortes));

        $moi        = $request->user()->id === $user->id;
        $resteActif = $request->boolean('is_active');
        $resteSuper = in_array('super_admin', $data['roles'], true);

        if ($moi && !$resteActif) {
            return back()->withInput()->withErrors(
                'Vous ne pouvez pas désactiver votre propre compte : vous seriez déconnecté sans retour possible.');
        }
        if (Privileges::estDernierSuperAdmin($user) && (!$resteActif || !$resteSuper)) {
            return back()->withInput()->withErrors(
                "Ce compte est le dernier super-administrateur actif. Le désactiver ou lui retirer ce rôle "
                . "fermerait la plateforme sans personne pour la rouvrir. Nommez d'abord un autre super-administrateur.");
        }

        $update = [
            'nom'       => $data['nom'],
            'prenom'    => $data['prenom'],
            'email'     => $data['email'],
            // « role » reste le rôle PRINCIPAL : affichage et redirection
            'role'      => $data['roles'][0],
            'is_active' => $request->boolean('is_active'),
        ];
        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }
        if ($request->hasFile('signature')) {
            if ($user->signature_path) {
                Storage::disk('local')->delete($user->signature_path);
            }
            $update['signature_path'] = $request->file('signature')->store('signatures', 'local');
        }
        $ancienRole = $user->role;
        $user->update($update);
        $user->syncRoles($data['roles']);

        // Mot de passe posé par un tiers : provisoire, à changer par le titulaire.
        if (!empty($data['password']) && !$moi) {
            $user->forceFill(['doit_changer_mot_de_passe' => true])->save();
        }

        \App\Models\AuditLog::record('utilisateur.modifie', $user,
            "Compte modifié : {$user->staff_name} ({$user->numero_identifiant})"
                . ($ancienRole !== $data['roles'][0] ? " — rôle principal {$ancienRole} → {$data['roles'][0]}" : ''),
            [
                'roles' => $data['roles'],
                'mot_de_passe_change' => !empty($data['password']),
                'desactive' => !$request->boolean('is_active'),
            ]);

        return back()->with('success', 'Compte mis à jour.');
    }

    /**
     * Redonne un mot de passe utilisable a un usager.
     *
     * Un compte cree par la connexion par code recoit un mot de passe
     * aleatoire que personne ne connait : l'acces passe uniquement par
     * code. Le jour ou ce module est eteint, ces comptes n'ont plus
     * aucune porte — et l'application n'offre pas de « mot de passe
     * oublie ». Ceci est cette porte : l'administration remet un mot de
     * passe provisoire, affiche UNE FOIS, a transmettre de vive voix.
     */
    public function reinitialiserMotDePasse(Request $request, User $user)
    {
        abort_unless($request->user()->can('utilisateurs.gerer'), 403);

        // Un super-administrateur ne se fait pas remettre son mot de
        // passe par un autre compte : ce serait une prise de controle.
        if ($user->isSuperAdmin() && !$request->user()->isSuperAdmin()) {
            return back()->withErrors(
                "Le mot de passe d'un super-administrateur ne peut etre reinitialise que par un autre super-administrateur."
            );
        }

        // Meme raisonnement pour tout compte plus puissant que le sien.
        if (!Privileges::peutAdministrer($request->user(), $user)) {
            return back()->withErrors(
                "Ce compte detient des droits que vous n'avez pas : vous ne pouvez pas reinitialiser son mot de passe."
            );
        }

        $provisoire = \Illuminate\Support\Str::password(12, true, true, false, false);

        $user->forceFill([
            'password' => \Illuminate\Support\Facades\Hash::make($provisoire),
            // Provisoire : l'administration le connait, le titulaire devra le changer.
            'doit_changer_mot_de_passe' => true,
        ])->save();

        \App\Models\AuditLog::record('utilisateur.mot_de_passe_reinitialise', $user,
            "Mot de passe reinitialise pour {$user->numero_identifiant}");

        /* Le mot de passe transite par la session le temps d'un
           affichage, jamais par la base ni par le journal. */
        return back()->with('mot_de_passe_provisoire', [
            'identifiant' => $user->numero_identifiant,
            'valeur'      => $provisoire,
        ])->with('success', "Mot de passe reinitialise pour « {$user->numero_identifiant} ».");
    }

    public function destroy(User $user)
    {
        $this->exigerComptePersonnel($user);
        $this->exigerDroitSur(auth()->user(), $user);

        if ($user->id === auth()->id()) {
            return back()->withErrors('Vous ne pouvez pas supprimer votre propre compte.');
        }
        if (Privileges::estDernierSuperAdmin($user)) {
            return back()->withErrors(
                'Ce compte est le dernier super-administrateur actif : il ne peut pas être supprimé.');
        }
        if ($user->signature_path) {
            Storage::disk('local')->delete($user->signature_path);
        }

        \App\Models\AuditLog::record('utilisateur.supprime', null,
            "Compte supprimé : {$user->staff_name} ({$user->numero_identifiant}) — rôle {$user->role}",
            ['role' => $user->role, 'user_id_supprime' => $user->id]);

        $user->delete();

        return back()->with('success', 'Compte supprimé.');
    }

    /** Sert l'image de signature (accès protégé par la permission de route). */
    public function signature(User $user)
    {
        abort_unless(
            $user->signature_path && Storage::disk('local')->exists($user->signature_path),
            404
        );

        return Storage::disk('local')->response($user->signature_path);
    }

    private function assertStaffRole(string $role): void
    {
        abort_if(in_array($role, ['etudiant', 'candidat'], true), 422, 'Rôle non assignable au personnel.');
    }

    /**
     * Nul ne donne ce qu'il n'a pas.
     *
     * Sans ce garde, la permission de gérer les comptes valait toutes
     * les autres : il suffisait de cocher « super_admin » en créant un
     * compte — ou en modifiant le sien.
     *
     * @param  iterable<string>  $roles
     */
    private function exigerRolesAttribuables(User $acteur, iterable $roles): void
    {
        foreach ($roles as $role) {
            $this->assertStaffRole($role);

            abort_unless(Privileges::peutAttribuerRole($acteur, $role), 403,
                $role === 'super_admin'
                    ? 'Seul un super-administrateur peut attribuer le rôle super_admin.'
                    : "Le rôle « {$role} » comporte des droits que vous n'avez pas : vous ne pouvez pas l'attribuer.");
        }
    }

    /** Nul n'agit sur un compte plus puissant que le sien. */
    private function exigerDroitSur(User $acteur, User $cible): void
    {
        abort_unless(Privileges::peutAdministrer($acteur, $cible), 403,
            $cible->isSuperAdmin()
                ? 'Seul un super-administrateur peut modifier ou supprimer un super-administrateur.'
                : "Ce compte détient des droits que vous n'avez pas : vous ne pouvez pas le modifier.");
    }

    /**
     * Cet écran gère le PERSONNEL. Un compte d'étudiant visé par son
     * identifiant dans l'URL y serait modifié — ou supprimé, avec tous
     * ses dossiers en cascade — sans qu'aucun écran ne le propose.
     */
    private function exigerComptePersonnel(User $cible): void
    {
        abort_if(in_array($cible->role, ['etudiant', 'candidat'], true), 404);
    }
}
