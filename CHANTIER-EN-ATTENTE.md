# Chantier en attente — à exécuter au réveil

> Consigne rédigée le 2 septembre 2026 pour une session programmée qui
> démarrera **sans le fil de la conversation d'origine**. Tout ce qui est
> nécessaire est ici.

## Contexte indispensable

- Projet : `C:\Users\MOUTORE\Documents\GitHub\dematerialisation`
  (Laravel 12.63, PHP 8.2, XAMPP, base MariaDB `dematerialisation`, root sans mot de passe).
- **Le répertoire de travail du terminal n'est PAS le projet.** Toujours préfixer
  les commandes du chemin complet ci-dessus.
- **Ne jamais commiter.** L'utilisateur prépare et passe ses commits lui-même.
  Rédiger les messages de commit proposés, et s'arrêter là.
- **Ne jamais toucher à la production.** Uniquement du travail local.
- Tout script de test doit **nettoyer ses propres données** avant de rendre la main.
  Un test antérieur a supprimé le compte ETUD001 par mégarde : ne pas répéter.
- La suite existante : `php artisan test` (77 tests, SQLite en mémoire).
  **Elle doit rester verte à la fin.**

---

## Tâche 1 — Écrans de la dérogation  (priorité haute)

La logique est **déjà écrite, migrée et testée** ; seuls les écrans manquent.
Ne pas réécrire le socle, s'y brancher.

### Ce qui existe déjà

- Migration `2026_09_01_100000_derogations_pieces_manquantes.php`
  - table `derogations` : `student_request_id`, `request_type_id`,
    `statut` (`engagee` / `levee` / `refusee`), `engagee_at`, `levee_at`,
    `levee_par`, `motif`. Unicité sur (dossier, pièce).
  - colonne `request_types.derogation_possible`, **false partout**.
- `app/Models/Derogation.php`
- `app/Models/StudentRequest.php` :
  `derogations()`, `derogationsEnCours()`, `sousDerogation()`,
  `piecesDerogeables()`, `piecesBloquantes()`, `piecesObligatoiresManquantes()`
- `app/Http/Controllers/StudentRequestController::submit()` gère déjà
  `engagement` + `derogations[]` et journalise `depot.derogation`.
- `app/Http/Controllers/AdminRequestController::leverDerogation()` +
  route `POST /admin/derogations/{derogation}/lever`, journalise
  `depot.derogation_levee`.
- Tests : `tests/Feature/DerogationDepotTest.php` (11 tests, verts).

### Ce qu'il reste à construire

1. **Formulaire des types de pièces** —
   `resources/views/admin/request_types/_form.blade.php`
   Ajouter la case `derogation_possible`, sous `is_required` et visible
   uniquement quand la pièce est obligatoire (une pièce facultative n'a rien
   à reporter). Libellé proposé :
   « Autoriser le report de cette pièce » avec l'explication :
   « L'usager pourra déposer sans elle en s'engageant à la fournir plus tard.
   La pièce reste due. »
   Vérifier que `RequestTypeController` accepte le champ en validation
   (`boolean`, `sometimes`).

2. **Écran de dépôt (usager)** — vue du dépôt côté étudiant/candidat.
   Pour chaque pièce obligatoire manquante **dont `derogation_possible` est
   vrai**, afficher une case d'engagement, décochée par défaut, nommée
   `derogations[]` avec l'id du type en valeur.
   Texte de l'engagement : l'usager déclare ne pas disposer de la pièce et
   s'engage à la fournir dès qu'il l'obtiendra.
   Les pièces obligatoires **non** dérogeables continuent de bloquer le bouton
   de soumission, exactement comme avant.

3. **Écran de traitement (agent)** —
   `resources/views/admin/requests/show.blade.php`
   Bloc visible seulement si `$request->sousDerogation()` :
   liste des pièces engagées, date d'engagement, et un bouton
   « Constater la réception » par pièce (POST vers `derogations.lever`).
   Gardé par la permission `depot.traiter`.
   L'agent doit toujours pouvoir **recaler** le dossier normalement.

4. **Archives et exports** — la mention doit suivre le dossier.
   Repérer les vues d'archives et les exports Excel, y faire remonter
   l'état « sous dérogation » et les pièces concernées. C'est le point qui
   fait que les archives disent la vérité sur ce qui manquait.

### Règles à ne pas trahir

- Par défaut **aucune** pièce n'est dérogeable ; c'est un choix explicite.
- Déroger une pièce ne dispense **pas** des autres.
- La pièce reste **due** : la dérogation la diffère, elle ne l'annule pas.
- Pas d'échéance. Le document produit est identique à celui d'un dépôt complet.
- Une requête forgée ne doit pas pouvoir déroger une pièce non autorisée
  (déjà couvert côté contrôleur — ne pas affaiblir).

---

## Tâche 2 — Deux pages publiques trop larges

Les cartes s'étirent sur toute la largeur de l'écran et flottent mal centrées.

- `resources/views/public/candidature/...` — écran « Candidatures fermées »
  (état vide : porte grise, titre, phrase, bouton Retour).
- `resources/views/public/candidature/suivi.blade.php` — « Suivi de candidature »
  (champ `CAND-2026-XXXXXX` + bouton Rechercher).

Attendu : carte resserrée et centrée verticalement comme horizontalement.
La carte de connexion (`auth/login.blade.php`) utilise déjà
`max-width: 820px` — s'aligner sur cette famille de largeurs plutôt que
d'inventer une valeur. Les états vides méritent plus étroit (~520 px).

**Aucune trace visuelle d'IA** : pas d'emoji décoratif, pas de dégradé
gratuit, pas de vocabulaire marketing. Le ton institutionnel existant.

---

## Tâche 3 — Les formulaires perdent la saisie en cas d'erreur

**Bug confirmé.** À l'ajout d'un utilisateur, si la validation échoue,
la fenêtre se referme et tout ce qui avait été tapé est perdu.

Deux défauts distincts :

1. Les champs n'ont **aucun `old()`** :
   `resources/views/admin/users/index.blade.php` — formulaire `#formUser`,
   fenêtre `#modalUser`. `grep -c "old("` y renvoie **0**.
2. Le formulaire vit dans une **fenêtre modale Bootstrap** : après la
   redirection d'erreur, elle est refermée. L'usager ne voit même pas
   les messages de validation.

Il faut donc **repeupler** avec `old()` **et rouvrir la fenêtre**
automatiquement quand `$errors->any()`, en y affichant les erreurs.

### Vues à vérifier ensuite (formulaire POST sans aucun `old()`)

Ne traiter que celles qui portent de vrais champs de saisie ; beaucoup ne
contiennent qu'un bouton d'action, et n'ont donc rien à préserver.

```
admin/users/index.blade.php          <- point de départ, bug constaté
admin/academic/index.blade.php
admin/annees/index.blade.php
admin/procedures/index.blade.php
admin/roles/index.blade.php
admin/perimetres/index.blade.php
admin/request_types/niveau.blade.php
superadmin/settings.blade.php
admin/securite.blade.php
```

Chercher un motif réutilisable plutôt que de rustiner vue par vue :
si plusieurs fenêtres modales souffrent du même défaut, un petit fragment
partagé (réouverture sur erreur) vaut mieux que dix corrections copiées.

---

## À rendre à la fin

1. Ce qui a été fait, écran par écran.
2. Le résultat de `php artisan test` (nombre de tests, verts ou non).
3. Les nouveaux tests écrits pour la dérogation côté interface.
4. **Des messages de commit proposés** — sans commiter.
5. Ce qui reste ouvert ou n'a pas pu être fait, dit franchement.
