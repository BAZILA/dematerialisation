# Workflow des demandes, dépôts & candidatures

> Un **cycle de vie générique et configurable** couvrant toutes les démarches
> (dépôt, attestation, relevé, diplôme, duplicata, homologation, candidature).
> Chaque **type de démarche** active/désactive les étapes dont il a besoin.

---

## 1. Principe

Une seule machine à états pour tout, avec des **étapes optionnelles** pilotées
par la configuration du type de démarche (§3). Chaque changement d'état est :
- **tracé** (journal d'audit : qui, quand, avant→après),
- **notifié** (e-mail / SMS selon config),
- **classé** dans le **dossier numérique** de l'usager.

---

## 2. Cycle de vie (états)

```
                        ┌───────────── (usager) ─────────────┐
                        ▼                                     │
  [brouillon] ──soumettre──► [soumise] ──(frais?)──► [paiement] ──payé/confirmé──┐
                                                                                 ▼
                                                                         [à traiter]
                                                                                 │
                                                                        affectation (périmètre)
                                                                                 ▼
                                                                        [en instruction] ◄────┐
                                                                          │        │          │
                                                    renvoi correction ────┘        │      resoumission
                                                          ▼                        │          │
                                                   [en correction] ───(usager corrige)────────┘
                                                                                   │
                                                                       conforme ?  ▼
                                                                    [contrôle] (chef, option)
                                                                                   │
                                                                                   ▼
                                                                    [à signer] (DAAS, option)
                                                                                   │
                                                            ┌──────────────────────┤
                                                résultat = document              résultat = présentiel
                                                            ▼                      ▼
                                                    [document prêt]        [retrait planifié]
                                                (téléchargement signé)   (rendez-vous fixé)
                                                            │                      │
                                                            └──────► [clôturée] ◄──┘
                                                       (téléchargé / retiré / terminé)

  Depuis presque tout état amont : ──► [rejetée] (rejet définitif motivé)
  Depuis [brouillon]/[soumise] : ──► [annulée] (par l'usager)
```

### Détail des états
| État | Description | Acteur |
|---|---|---|
| `brouillon` | L'usager constitue sa demande, dépose les pièces. | usager |
| `soumise` | Envoyée, en attente de prise en charge. | usager |
| `paiement` | *(si frais activés)* attente de paiement en ligne **ou** de confirmation d'un paiement hors ligne. | usager / `agent_financier` |
| `à traiter` | Payée/valide, dans la file d'instruction. | système |
| `en instruction` | Prise en charge par un agent (selon **périmètre**), vérification des pièces. | agent du service |
| `en correction` | Renvoyée à l'usager pour compléter/corriger (boucle ; pièces non verrouillées rechargeables). | usager |
| `contrôle` | *(option)* vérification par le chef de section. | `chef_scolarite` |
| `à signer` | *(option)* validation & **signature** officielle. | **DAAS** (Directeur / Adjoint) |
| `document prêt` | Résultat = document dématérialisé **signé/tamponné**, téléchargeable. | usager (retrait en ligne) |
| `retrait planifié` | Résultat = **retrait présentiel**, rendez-vous fixé (date/lieu/instructions). | usager (présentiel) |
| `clôturée` | Téléchargée / retirée / terminée. | système |
| `rejetée` | Rejet définitif motivé. | agent / chef / DAAS |
| `annulée` | Annulée par l'usager avant instruction. | usager |

---

## 3. Ce qui est **configurable par type de démarche**

Chaque type de démarche définit quelles étapes s'appliquent — d'où un workflow
sur mesure sans changer le code :

| Réglage | Effet |
|---|---|
| `frais_actifs` (+ montant, moyens) | Active/non l'étape **paiement**. |
| `etape_controle` | Insère/non l'étape **contrôle** (chef). |
| `mode_authenticite` | `none` / `visual_stamp` / `stamp_qr` / `esign_qr` → détermine l'étape **à signer** et le scellement. |
| `resultat` | `document` (téléchargement signé) **ou** `presentiel` (rendez-vous de retrait) **ou** les deux. |
| `pieces_requises` | Pièces à fournir (obligatoires/optionnelles). |
| `fenetre_ouverture` | La démarche n'est ouverte que pendant la fenêtre de l'année (voir CADRAGE §5 années). |

Exemples :
- **Attestation** : frais activés → paiement → instruction → signature DAAS →
  **document prêt** (téléchargeable) *ou* **retrait présentiel** selon config.
- **Dépôt définitif** : pas de frais → instruction (contrôle des pièces) →
  éventuel tampon → **retrait présentiel** ou archivage.
- **Candidature** : frais optionnels → instruction `agent_candidature` →
  décision admis/refusé → si admis, bascule vers création d'un compte étudiant.

---

## 4. Règles transverses

- **Périmètre** : un agent ne voit/instruit que les demandes de son périmètre
  (parcours × semestre × [type], voir DOMAINE §3). Affectation par admin/super_admin.
- **Fenêtres & années** : une démarche n'est soumissible que si sa **fenêtre**
  est ouverte pour l'**année active** ; les années clôturées sont en lecture seule.
- **Correction** : en `en correction`, seules les pièces **non validées** sont
  rechargeables (les pièces validées restent verrouillées) — comportement déjà
  corrigé dans le prototype, à conserver.
- **Traçabilité** : chaque transition alimente le **journal d'audit** et le
  **dossier numérique** de l'usager.
- **Notifications** : e-mail (+ SMS si activé) à chaque changement d'état + rappel
  de rendez-vous.
- **Traitements lourds** (signature, génération PDF, e-mails, SMS) exécutés en
  **file d'attente** (voir capacité — CADRAGE §10).

---

## 5. Ce que ça améliore vs le prototype
- Un **seul** moteur pour toutes les démarches (au lieu de branches ad hoc).
- Étapes **paiement**, **contrôle**, **signature** explicites et **activables**.
- Résultat clair : **téléchargement** *ou* **retrait présentiel** (ou les deux).
- **Affectation par périmètre** + files de traitement par service.
- Tout **tracé** et **archivé** dans le dossier numérique.
