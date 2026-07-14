# Cadrage — Plateforme de dématérialisation (version production)

> Document vivant. Il traduit les décisions de lancement en plan technique.
> Statut : **prototype accepté → construction de la vraie plateforme**.
> Les points marqués **[À VALIDER]** attendent une confirmation.

---

## 1. Contexte & objectif

Plateforme universitaire (contexte : Togo) permettant aux étudiants — nouveaux
bacheliers et anciens étudiants — de faire dématérialiser leurs démarches :
dépôt de dossier, attestation, diplôme, relevé de notes, duplicata de carte.
L'administration traite, valide, et produit des documents officiels.

Le prototype a validé le **parcours**. La version production ajoute :
**fiabilité, sécurité, valeur légale des documents, paiement, configurabilité
et exploitation.**

**Périmètre (important)** : la plateforme gère des **demandes, dépôts,
candidatures et l'archivage numérique** (le dossier de chaque usager). Elle **ne
gère PAS les inscriptions ni la scolarité** — d'autres systèmes s'en chargent.
La structure académique (faculté/département/parcours/semestre/grade) et le
rattachement des étudiants sont des **données de référence importées** (comme les
identités), servant au **classement, au filtrage et aux périmètres des agents**.
Objectif clé : tout faire en ligne, ne se déplacer en présentiel que pour le
**retrait**, et disposer d'un **dossier numérique** bien stocké pour chacun.

---

## 2. Décisions actées (lancement)

| Sujet | Décision |
|---|---|
| **Base de départ** | **Faire évoluer le prototype** (on garde le métier, les seeders et le design clair ; on assainit les fondations — voir §12). |
| **Hébergement** | **cPanel O2switch d'abord**, architecture **portable vers le cloud** ensuite (voir §10). |
| **Authenticité des documents** | **Tous les modes disponibles**, activables/désactivables **par type de démarche** via le super-admin (voir §6). |
| **Paiement en ligne** | **Tous les moyens disponibles**, activables/désactivables via une **interface super-admin** (voir §7). |

**Principe directeur : « tout est configurable ».** Les fonctions sensibles ne
sont pas codées en dur : ce sont des **options** pilotées par un super-admin.

---

## 3. Rôles & permissions cibles  **[À VALIDER]**

> **Détail complet dans [`DOMAINE_ET_ROLES.md`](DOMAINE_ET_ROLES.md)** (rôles UK,
> permissions, périmètres par parcours/semestre, structure académique,
> candidatures, années, dossier numérique, déploiement).

Passage de `admin`/`student` à un modèle plus fin (via `spatie/laravel-permission`) :

- **super_admin** — configuration de la plateforme : réglages globaux, types de
  démarches, modes d'authenticité, moyens de paiement, utilisateurs & rôles,
  journal d'audit.
- **agent** (scolarité / service diplômes…) — traite et valide les demandes de
  son périmètre.
- **étudiant** — nouveau bachelier ou ancien étudiant.
- *(option)* **chef de département / validateur N2** — second niveau de validation.

---

## 4. Principe : plateforme configurable

Deux niveaux de configuration, éditables par le super-admin :

1. **Réglages globaux** (table `settings`, clé/valeur, en cache) : moyens de
   paiement actifs, canaux de notification, mentions officielles, etc.
2. **Configuration par type de démarche** (extension de `request_types`) :
   - mode d'authenticité du document produit (§6) ;
   - paiement requis ? montant ? moyens autorisés ? (§7) ;
   - document **déposé** par l'étudiant **ou généré** par le système ;
   - pièces à fournir (déjà géré dans le prototype).

Chaque « fonction » (authenticité, paiement, notification) est un **module
activable** — on peut la brancher/débrancher sans toucher au code.

---

## 5. Modèle de données — évolutions cibles

- `users` : + `spatie` roles/permissions, `2fa`, `last_login`, statut.
- `request_types` : + `authenticity_mode`, `requires_payment`, `fee_amount`,
  `allowed_payment_methods`, `is_auto_generated`.
- `student_requests` : + `payment_status`, `reference` (numéro unique), suivi.
- **nouvelles tables** :
  - `settings` — réglages globaux.
  - `payments` — transactions (moyen, montant, statut, référence passerelle).
  - `document_certifications` — hash, QR, horodatage, signataire, statut.
  - `audit_logs` — qui / quoi / quand / avant-après (traçabilité officielle).
  - `notifications` (Laravel) — journal des envois.

---

## 5.b Import / export du référentiel d'identités

Les vrais bacheliers et anciens étudiants sont chargés par **import Excel/CSV**
(et **export** pour réconciliation), via l'administration, selon un **format de
colonnes fixe**. Outil : `maatwebsite/excel` (basé sur PhpSpreadsheet).

**Bacheliers** — colonnes attendues :
`nom · prenom · sexe (M/F) · date_naissance (AAAA-MM-JJ) · lieu_naissance ·
serie_bac · annee_bac · numero_releve (unique) · academie`

**Anciens étudiants** — colonnes attendues :
`numero_carte (unique) · nom · prenom · sexe · date_naissance · lieu_naissance ·
specialite · derniere_universite`

Règles : validation ligne par ligne, rapport d'erreurs, dédoublonnage sur la
clé unique (`numero_releve` / `numero_carte`), aperçu avant import, et export
incluant le statut `is_registered` (inscrit ou non). **[À VALIDER : ordre/format
exact des colonnes fourni par l'établissement]**

---

## 6. Authenticité des documents (modes configurables)

Le tampon actuel est **décoratif**. Cible : 4 modes, choisis **par type de démarche**.

| Mode | Contenu | Usage |
|---|---|---|
| `none` | Aucun | Documents internes |
| `visual_stamp` | Cachet visuel (l'actuel, amélioré) | Faible enjeu |
| `stamp_qr` | Cachet + **numéro/QR vérifiable** en ligne | Enjeu moyen |
| `esign_qr` | **Signature électronique + hash horodaté + QR** | Valeur probante |

- **Portail public de vérification** : `/verifier/{reference}` → confirme
  l'authenticité et le contenu (scan QR ou saisie du numéro).
- Le scellement (hash/signature) est fait en **file d'attente** à la validation.
- **[À VALIDER]** : pour `esign_qr`, niveau visé — signature « maison »
  (hash + clé serveur, suffisant pour de la vérification interne) **ou**
  certificat qualifié d'un prestataire (valeur juridique forte, coût/contrat).

---

## 7. Paiement en ligne (moyens configurables)

Architecture en **connecteurs (drivers)** activables via le super-admin :

- **Mobile Money** (Flooz / T-Money) — via un agrégateur. **[À VALIDER : lequel]**
- **Carte bancaire / autre passerelle** — optionnel.
- **Hors ligne** — l'étudiant dépose un reçu, l'agent confirme le paiement.

Flux : paiement requis (selon le type) → règlement → passage automatique de la
demande à l'étape suivante. Journalisé dans `payments`. Interface super-admin
pour activer/désactiver chaque moyen et fixer les frais par démarche.

---

## 8. Notifications

- **E-mail** (SMTP O2switch) à chaque changement de statut + rappels de rendez-vous.
- **SMS** (Mobile Money/opérateurs) — **[À VALIDER]**, activable par le super-admin.
- Tout envoi passe par une **file d'attente** (ne bloque pas l'utilisateur).

---

## 9. Sécurité & conformité

- **2FA** pour super_admin/agents, politique de mots de passe, HTTPS, rate-limiting.
- **Fichiers** : validation MIME/extension/taille stricte, quotas, chiffrement au
  repos ; **antivirus ImunifyAV+ / TigerGuard inclus sur O2switch** → scan des
  fichiers déposés (plus besoin d'API tierce).
- **Données personnelles** : durée de conservation, droit d'accès/suppression,
  **journal d'audit** complet (obligatoire pour des documents officiels).

---

## 10. Hébergement : cPanel maintenant, cloud plus tard

### Maintenant — cPanel / O2switch
- **PHP + MySQL** fournis. Compte en **PHP 8.0 par défaut**, mais O2switch permet
  de choisir la version **par domaine/sous-domaine (ou dossier)** — jusqu'à
  **PHP 8.3**. On dédie au projet un (sous-)domaine en **PHP 8.3** via
  **MultiPHP Manager** (natif, sans perte de perf ; la méthode `.htaccess`
  `SetHandler ...php83` existe aussi mais est plus lente). → cible **Laravel 12**.
  Déploiement via Git/FTP dans le dossier du (sous-)domaine.
- **Permissions (suPHP)** : refuse les fichiers en 777/775 → fichiers en **644**,
  dossiers en **755** (avec `storage/` et `bootstrap/cache` accessibles en écriture).
- **php.ini** (via MultiPHP INI Editor ou `.user.ini`) : ajuster
  `upload_max_filesize`, `post_max_size`, `memory_limit`, `max_execution_time`
  pour les dépôts de fichiers.
- Réf. : FAQ O2switch « Changer version PHP et php.ini ».
- **Files d'attente** : driver `database` + un **cron** qui lance
  `queue:work --stop-when-empty` chaque minute (pas de démon permanent).
- **Tâches planifiées** : `schedule:run` via cron O2switch (rappels, nettoyage).
- **Fichiers** : disque du compte (quota O2switch) + chiffrement applicatif ;
  sauvegardes via cron + backups O2switch.
- **Limites** : pas de démon long, pas de Redis garanti (cache/queue en DB),
  ressources partagées, antivirus natif indisponible.

### Plus tard — cloud (si montée en charge)
- **VPS/serveur dédié** (O2switch en propose) ou cloud (OVH / Scaleway / AWS…).
- **Redis** (cache + queues avec worker démon via Supervisor), **stockage objet
  S3**, sauvegardes automatisées, HTTPS Let's Encrypt, monitoring, scaling.

### La clé : concevoir **portable** dès maintenant
En s'appuyant sur les abstractions Laravel — `filesystem` (disk), `queue`
(connection), `cache` (store), `mail`, jobs pour tout traitement lourd, aucun
chemin en dur — **passer de cPanel au cloud = surtout changer le `.env` et le
provisionnement, pas le code.** C'est la règle qu'on suit dès la Phase 1.

---

### Capacité & performance (tenir sur mutualisé O2switch)

**Profil de charge attendu** : faible/modérée en régime courant, **pics** pendant
les fenêtres de **candidature** et **dépôt** (beaucoup d'usagers simultanés qui
**uploadent des fichiers**). Les opérations coûteuses : uploads (bande passante,
mémoire PHP, I/O), génération/signature PDF (CPU), listes admin volumineuses.

**Concevoir léger dès le départ** (pour tenir sur mutualisé) :
- **Files d'attente** (queue `database` + cron) pour tout traitement lourd
  (signature, PDF, e-mails, SMS) → les requêtes web restent rapides.
- **Caches** Laravel (config/route/view) + cache applicatif ; **pagination** et
  **index BD** sur les listes/recherches.
- **Fichiers** : taille/format limités, stockés hors racine web, nettoyage.
- **Assets** statiques avec en-têtes de cache ; possibilité de mettre
  **Cloudflare** devant O2switch (cache + protection).
- **Étaler** via les fenêtres d'ouverture (candidature/dépôt) pour lisser les pics.

**Capacité constatée — offre O2switch « Cloud » (relevé cPanel)** :
- **12 threads CPU · 48 Go RAM · I/O 42 Mo/s · 1024 IOPS · disque NVMe illimité**.
- Concurrence : **Entry Processes (EP) = 80** (requêtes PHP simultanées),
  **400 processus**. Usage actuel quasi nul (EP 1/80, RAM 115 Mo/48 Go, CPU 0 %).
- **Antivirus inclus** : **ImunifyAV+ / TigerGuard** → scan des fichiers déposés.
- Sauvegardes 45 jours ; 8 sous-comptes ; MySQL **et** PostgreSQL disponibles
  (on utilise MySQL).

**Verdict : largement suffisant** pour la charge prévue. Le facteur limitant est
l'**EP = 80** (exécutions PHP concurrentes) : en gardant les requêtes **courtes**
(traitements lourds en file d'attente, uploads plafonnés), 80 EP absorbent un
débit très élevé — bien au-delà des besoins d'un portail de demandes, même en pic
de candidature/dépôt. Seul point de vigilance : **de très nombreux uploads
simultanés à la même seconde** (atténué par plafonds de taille + fenêtres
d'ouverture + files d'attente). **Bascule VPS/cloud non nécessaire à ce stade.**

**Étape restante** : confirmer sous charge réelle par un **test de charge sur
staging** (`k6` / `ab` / `siege`, scénarios login/upload/listes, dans le respect
des CGU) — je prépare le script quand on aura un déploiement. Seuil de bascule :
EP durablement saturés ou temps de réponse dégradés en pic malgré les optimisations.

---

## 11. Roadmap phasée

- **Phase 0 — Cadrage** *(en cours)* : specs, rôles, modes, maquettes validés.
- **Phase 1 — Socle** : mise à niveau des fondations (framework à jour, Spatie,
  `settings`, tests, CI), portabilité cPanel/cloud, reprise du design clair.
- **Phase 2 — Cœur configurable** : parcours étudiant + agent (dépôt, demandes,
  validation, corrections) + super-admin (types de démarches, notifications).
- **Phase 3 — Officialisation** : modes d'authenticité (stamp/QR/e-sign) +
  portail public de vérification + génération auto des documents + paiement.
- **Phase 4 — Durcissement** : sécurité, audit, tests, recette.
- **Phase 5 — Production** : déploiement O2switch, sauvegardes, reprise de
  données, formation des utilisateurs.

---

## 12. Points à valider avant de démarrer

1. ✅ **Mise à niveau du framework — TRANCHÉ** : Laravel mis à jour vers la
   dernière version supportée. **Cible confirmée : Laravel 12 / PHP 8.3** — le
   compte O2switch est en 8.0 par défaut mais autorise PHP 8.3 par (sous-)domaine
   (voir §10). On **conserve tout le code métier, les vues et le design** ;
   approche : squelette Laravel moderne + portage du code existant.
2. ✅ **Référentiel d'identités — TRANCHÉ** : **import / export Excel/CSV** géré
   par l'administration, format de colonnes défini (voir §5.b). Pas de connexion
   à un SI externe pour l'instant.
3. **Rôles exacts** (§3) : liste et périmètres définitifs.
4. **Niveau de signature** (§6) : maison vs certificat qualifié.
5. **Agrégateur Mobile Money** (§7) et **fournisseur SMS** (§8).
6. ✅ **Antivirus** : ImunifyAV+ / TigerGuard inclus sur O2switch (scan des dépôts).
7. **Langues** : FR uniquement, ou FR + EN ?
