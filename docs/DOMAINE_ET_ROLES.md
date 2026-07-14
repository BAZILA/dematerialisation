# Domaine, rôles & structure académique

> Conception métier de la plateforme (Université de Kara — UK).
> Complète `CADRAGE.md`. Points **[À VALIDER]** = à confirmer avec toi.

---

## 1. Structure académique

Hiérarchie :

```
Faculté / Institut
   └── Département
          └── Parcours (filière)   → rattaché à un GRADE
                 └── Inscription de l'étudiant (par année, par semestre)
```

> **Nota (périmètre)** : ces entités académiques et le rattachement des étudiants
> sont des **données de référence importées** depuis les systèmes existants
> (comme les identités). La plateforme **ne gère pas les inscriptions/la
> scolarité** : elle **classe, filtre** les demandes et **archive** les dossiers.
> « Inscription » ci-dessous = le **rattachement** courant (parcours/semestre/
> année) de l'étudiant, importé — pas un processus d'inscription géré ici.

### Entités
- **Établissement composant** : `Faculté` **ou** `Institut` (champ `type`).
- **Département** : appartient à une faculté/institut.
- **Parcours** : appartient à un département ; porte un **grade** et donc un
  cursus de semestres.
- **Année académique** : ex. `2025-2026`, avec un statut (voir §5).
- **Inscription** : lie `étudiant ↔ parcours ↔ semestre ↔ année`, avec un
  **statut d'inscription** (nouveau, passant, redoublant, **recyclage**,
  réinscription). C'est l'inscription qui porte le niveau — pas l'étudiant, car
  il évolue d'année en année.

### Grades & semestres
| Grade | Semestres | Durée type | Recyclage |
|---|---|---|---|
| **Licence** | S1→S6 | 3 ans | oui (redoublement / reprise, même grade, jusqu'à ~10 ans) |
| **Master** | S1→S4 | 2 ans | oui |
| **Doctorat** | par années (Année 1…N) | variable | oui |
| **Spécialisation** | S1→S2 *(à préciser)* | ~1 an | oui |
| **Certificat** | **S1→S2 uniquement** | 1 an | — |

- Le mapping grade → semestres est **paramétrable** (pas codé en dur).
- **Recyclage** = maintien dans le même grade au-delà du cursus normal,
  **par dérogation**, sur un **nombre d'années paramétrable** (réglage super-admin).
  Porté par le `statut_inscription` (+ référence de dérogation), pas par un
  semestre spécial.

---

## 2. Acteurs & rôles

### 2.1 Tes rôles + noms proposés
| Rôle demandé | Rôle système | Rôle principal |
|---|---|---|
| Super admin | `super_admin` | Configuration plateforme (tout) |
| Admin | `admin` | Gestion fonctionnelle (référentiels, utilisateurs, années) |
| Chef section scolarité | `chef_scolarite` | Contrôle / supervision des traitements |
| Directeur DAAS | `directeur_daas` | Validation finale & **signature** officielle |
| Directeur Adjoint DAAS | `directeur_adjoint_daas` | Validation/signature (délégation) |
| Agent de dépôt *(nom ?)* | `agent_depot` | Traite les **dépôts définitifs** de dossiers |
| Agent de traitement de carte | `agent_carte` | Cartes étudiant & duplicatas |
| Agent d'attestation | `agent_attestation` | Attestations |
| Agent de relevé de notes | `agent_releve` | Relevés de notes |
| Agent d'homologation | `agent_homologation` | Homologation / équivalences |
| Agent de candidatures | `agent_candidature` | Candidatures (admissions, public) |

**Noms proposés pour « Agent de dépôt »** (à choisir) :
`Agent d'enregistrement des dépôts`, `Gestionnaire des dossiers d'inscription`,
ou simplement `Agent des dépôts définitifs`.

### 2.2 Rôles supplémentaires que j'anticipe **[À VALIDER]**
- **`agent_diplome`** — délivrance des diplômes (distinct de l'homologation).
- **`agent_financier`** (Caisse) — confirme les paiements (surtout si hors ligne).
- **`auditeur`** — **lecture seule** transverse (contrôle, statistiques), aucune action.
- **`gestionnaire_academique`** — gère le référentiel académique
  (facultés, départements, parcours, années) ; peut être une facette de `admin`.
- **Signataire** — plutôt une **permission** (`documents.sign`) attachée aux
  Directeurs qu'un rôle séparé.

### 2.3 Usagers
- **`etudiant`** — étudiant inscrit (login par identifiant).
- **`candidat`** — usager **public** qui postule à une admission, pas encore
  étudiant (voir §4).

### 2.4 Distinction super_admin / admin (proposition)
- **super_admin** : tout `admin` **+** configuration plateforme sensible
  (feature-flags, moyens de paiement, modes d'authenticité, sécurité, rôles &
  permissions, périodes d'ouverture).
- **admin** : référentiels académiques, comptes utilisateurs & affectations,
  import/export des identités, années académiques — **sans** les leviers plateforme.

---

## 3. Permissions & périmètres (le point clé)

Accès = **ce que le rôle autorise** **ET** **le périmètre affecté à l'agent**.

### 3.1 Permissions (exemples, via `spatie/laravel-permission`)
Par service : `*.view`, `*.process` (traiter), `*.return` (renvoyer en
correction), `*.reject`, plus transverses :
`documents.validate`, `documents.sign`, `payments.confirm`,
`students.import`, `academic.manage`, `years.manage`, `settings.manage`,
`users.manage`, `audit.view`.

### 3.2 Périmètre (scoping) — au-delà du simple rôle
Un agent n'agit que sur **son périmètre** : un ensemble de
**parcours × semestres** (et/ou départements, grades, types de démarche).

→ Table d'**affectations** : `user ↔ (parcours, semestre, [type de démarche])`.
Exemple : « Agent relevé X → parcours Informatique L, semestres S1–S6 ».
La liste des demandes qu'il voit/traite est **filtrée** par ce périmètre.

C'est exactement ta demande du point 3 : *« à quel agent on assigne quel(s)
parcours de quel(s) semestre(s) »*. Le super_admin/admin gère ces affectations.

### 3.3 Matrice rôles → capacités (synthèse)
| Capacité | super_admin | admin | chef_scol. | Dir./Adj. DAAS | agents service | agent_financier | auditeur | étudiant | candidat |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Config plateforme | ✅ | — | — | — | — | — | — | — | — |
| Rôles & permissions | ✅ | ➖ | — | — | — | — | — | — | — |
| Référentiel académique | ✅ | ✅ | — | — | — | — | 👁 | — | — |
| Années & périodes | ✅ | ✅ | — | — | — | — | 👁 | — | — |
| Import/export identités | ✅ | ✅ | — | — | — | — | — | — | — |
| Affecter périmètres | ✅ | ✅ | ➖ | — | — | — | — | — | — |
| Traiter demandes *(périmètre)* | ✅ | — | ✅ | — | ✅ | — | 👁 | — | — |
| Contrôler / superviser | ✅ | — | ✅ | ✅ | — | — | 👁 | — | — |
| Valider & **signer** | ✅ | — | ➖ | ✅ | — | — | — | — | — |
| Confirmer paiement | ✅ | — | — | — | ➖ | ✅ | 👁 | — | — |
| Consulter dossier étudiant | ✅ | ✅ | ✅ | ✅ | *(périmètre)* | — | 👁 | *(le sien)* | — |
| Déposer / demander | — | — | — | — | — | — | — | ✅ | ➖(candidature) |

✅ = oui · ➖ = partiel/paramétrable · 👁 = lecture seule · — = non

---

## 4. Candidatures & accès public

Nouveauté : des **candidats** (public, non étudiants) postulent à une admission
dans les **parcours ouverts** de l'année.

### Page d'accueil repensée — 3 portes
1. **Espace étudiant** — login par identifiant (existant).
2. **Candidature en ligne** — public : postuler à une admission.
3. **Personnel / Administration** — login du personnel (idéalement durci / séparé).

### Compte candidat **[À VALIDER]**
Deux options :
- **A. Compte léger** (email + mot de passe) : le candidat suit sa (ses)
  candidature(s), reçoit les notifications. *(recommandé)*
- **B. Sans compte / anonyme** : soumission + **code de suivi** pour consulter.
> Recommandation : **A** (meilleure traçabilité, notifications, sécurité), avec
> possibilité de plusieurs candidatures par candidat.

### Cycle candidature
Candidat postule (parcours ouvert + pièces + éventuels frais de candidature) →
**agent_candidature** instruit → décision (admis / refusé / complément) → si
**admis**, le candidat devient **étudiant** (ajout au référentiel + conversion
du compte). Ce pont candidature → inscription définitive relie les deux mondes.

---

## 5. Gestion des années académiques

- **Statuts d'une année** : `en_preparation` → `ouverte` → `cloturee` → `archivee`.
- **Périodes / fenêtres** (paramétrées par le super_admin, **par année**) pour
  chaque opération : *candidatures*, *dépôts définitifs*, *attestations*,
  *relevés*, *diplômes*, *duplicatas*, *homologations*. Chaque fenêtre = date
  début/fin + interrupteur on/off. Hors fenêtre → opération fermée (message clair).
- **Switch d'année** : le personnel et l'étudiant peuvent **changer l'année de
  contexte** pour consulter. Les années **clôturées/archivées sont en lecture
  seule** — aucune opération, uniquement consultation (ton point 4).
- C'est le prolongement du principe « tout configurable » : ouvrir/fermer les
  opérations = réglages du super_admin, par année et par type.

---

## 6. Dossier numérique de l'étudiant

Pour chaque étudiant, une **vue dossier** consolidée et consultable :
- tous ses **dépôts** et **demandes** (attestation, relevé, diplôme, duplicata,
  homologation), **tous** les fichiers (déposés + générés + signés/tamponnés),
- ses **paiements**, l'**historique/traçabilité** (audit), sur **toutes les
  années** (trajectoire complète).
- Accessible par l'**étudiant** (le sien) et par le **personnel autorisé**
  (dans son périmètre). C'est ton point 6 : un dossier numérique unique et vivant.

---

## 7. Options de déploiement

| Option | Description | Pour | Contre |
|---|---|---|---|
| **1. Monolithe, espaces séparés** *(reco)* | Une seule app Laravel, 3 espaces : Étudiant, Candidature (public), Back-office personnel. Portails éventuellement sur sous-domaines mais **même code/BD**. | Simple à construire, déployer et maintenir sur O2switch ; modèle de données partagé ; cohérent. | Tout dans un même déploiement. |
| **2. Applications séparées** | Portail étudiant + back-office + portail candidature, séparés, partageant une **API/BD**. | Forte isolation (le public séparé de l'admin), déploiements/scaling indépendants. | Bien plus complexe ; lourd sur mutualisé ; plusieurs bases de code. |
| **3. Hybride** | Monolithe, mais **portail candidature public** isolé sur son sous-domaine/entrée durcie. | Bon compromis sécurité pour la partie publique. | Un peu plus de config. |

**Ma recommandation : Option 1 (→ évoluable vers 3).** Une seule application
Laravel avec des **espaces bien séparés par rôle**, conçue proprement pour qu'on
puisse **isoler plus tard** le portail candidature public si la sécurité/charge
l'exige. Le déploiement indépendant étudiant/admin n'apporte pas assez à ce stade
face au surcoût sur O2switch mutualisé.

---

## 8. Impact sur les interfaces

- On **garde et étend le design clair** actuel (déjà pro) — pas de refonte.
- Nouveaux écrans : **portail candidature public**, **dossier numérique
  étudiant**, **back-office multi-rôles** (files de traitement filtrées par
  périmètre), **paramétrage super-admin** (années/périodes, feature-flags,
  affectations, référentiel académique).

---

## 9. Décisions & points ouverts

✅ **Tranché :**
1. « **Agent de dépôt** » : nom conservé. Rôles ajoutés au §2.2 retenus (sauf avis contraire).
2. Compte **candidat léger** (email + mot de passe).
3. **Signature officielle = la DAAS** (Directeur + Directeur Adjoint).
5. **Affectation flexible** : cœur = parcours × semestre, mais le modèle doit
   **tout permettre** (département, grade, type de démarche…) car la réalité peut
   varier → table d'affectation **générique/paramétrable** (§3.2).
6. **Déploiement** : monolithe à espaces séparés **validé**, **sécurité en
   priorité** (portail candidature public isolable — option 3 du §7).
7. **Frais de candidature** : possibles mais **activables/désactivables** par le
   super-admin (comme tous les frais).

4. ✅ **Recyclage** : par **dérogation**, sur un **nombre d'années paramétrable**
   (réglage super-admin). Cursus détaillés Master/Doctorat/Spécialisation à
   affiner au besoin lors de l'implémentation.
