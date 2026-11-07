-- =====================================================================
--  DEMATERIALISATION — UNIVERSITE DE KARA
--  Remise a zero des donnees de TEST, avant l'ouverture reelle
-- =====================================================================
--
--  CE QUI EST EFFACE
--    - les comptes etudiants et candidats crees AVANT @avant,
--      et en cascade : leur fiche etudiant, leurs dossiers, les pieces
--      deposees, les etudes anterieures, les derogations, les codes
--      d'acces ;
--    - les candidatures deposees AVANT @avant (voeux et pieces compris) ;
--    - les paiements rattaches a ces dossiers et candidatures ;
--    - les roles et sessions de ces comptes.
--
--  CE QUI EST CONSERVE
--    - le personnel (super-administrateurs, agents) ;
--    - toute la configuration : parametres, pieces attendues par niveau,
--      procedures, periodes d'ouverture, annees academiques, facultes,
--      departements, domaines, parcours, roles et permissions ;
--    - le referentiel : les 1165 anciens etudiants et les bacheliers ;
--    - le journal d'audit (voir l'option en fin de script).
--
--  LE PIEGE CORRIGE AU PASSAGE
--    Creer un compte marque la fiche du referentiel « is_registered = 1 ».
--    Supprimer le compte NE remet PAS ce drapeau a zero : la vraie
--    personne ne pourrait plus jamais s'inscrire (« compte deja cree »).
--    Dans le dump du 12 septembre, 4 anciens etudiants etaient concernes
--    — dont les cartes 9089, 88189 et 26627, issues du vrai referentiel.
--    Le script remet ces drapeaux a zero.
--
--  LES FICHIERS DEPOSES
--    Ce script efface les LIGNES en base, pas les FICHIERS sur le disque.
--    Voir le script « nettoyer-fichiers-orphelins.php », a lancer APRES.
--
-- =====================================================================
--  MODE D'EMPLOI — dans cet ordre, sans en sauter
-- =====================================================================
--
--  1. SAUVEGARDER la base (voir REMISE-A-ZERO-DES-TESTS.txt, section 3).
--  2. REGLER @avant ci-dessous.
--  3. Lancer la PARTIE A seule : elle n'efface RIEN, elle MONTRE.
--  4. Relire la liste. Si un compte REEL y figure : NE PAS continuer,
--     avancer @avant et recommencer l'etape 3.
--  5. Lancer la PARTIE B.
--
-- =====================================================================


-- ---------------------------------------------------------------------
--  REGLAGE UNIQUE : la limite dans le temps
-- ---------------------------------------------------------------------
--  Tout ce qui a ete cree AVANT ce moment est considere comme un test.
--  Ce qui a ete cree APRES est conserve.
--
--  /!\ Si de vrais etudiants ont deja cree leur compte (par exemple
--      pendant la presentation), mettez ici l'heure a laquelle l'usage
--      reel a commence. Heure du SERVEUR, en UTC (le Togo est en UTC).
--
--  Exemple : '2026-09-14 08:00:00'

SET @avant = '2026-09-14 08:00:00';


-- =====================================================================
--  PARTIE A — APERCU. N'efface rien. A lancer et relire en premier.
-- =====================================================================

SELECT '--- COMPTES QUI SERONT SUPPRIMES ---' AS `apercu`;
SELECT u.id, u.numero_identifiant, u.role, u.type_etudiant, u.created_at
FROM users u
WHERE u.role IN ('etudiant', 'candidat')
  AND u.created_at < @avant
ORDER BY u.created_at;

SELECT '--- COMPTES CONSERVES (personnel, ou crees apres la limite) ---' AS `apercu`;
SELECT u.id, u.numero_identifiant, u.role, u.created_at
FROM users u
WHERE NOT (u.role IN ('etudiant', 'candidat') AND u.created_at < @avant)
ORDER BY u.role, u.created_at;

SELECT '--- VOLUMES QUI SERONT EFFACES ---' AS `apercu`;
SELECT
  (SELECT COUNT(*) FROM users
     WHERE role IN ('etudiant','candidat') AND created_at < @avant)            AS comptes,
  (SELECT COUNT(*) FROM student_requests sr
     JOIN students s ON s.id = sr.student_id
     JOIN users u    ON u.id = s.user_id
     WHERE u.role IN ('etudiant','candidat') AND u.created_at < @avant)       AS dossiers,
  (SELECT COUNT(*) FROM request_files rf
     JOIN student_requests sr ON sr.id = rf.student_request_id
     JOIN students s ON s.id = sr.student_id
     JOIN users u    ON u.id = s.user_id
     WHERE u.role IN ('etudiant','candidat') AND u.created_at < @avant)       AS pieces_deposees,
  (SELECT COUNT(*) FROM candidatures WHERE created_at < @avant)                AS candidatures;

SELECT '--- FICHES DU REFERENTIEL QUI POURRONT A NOUVEAU S''INSCRIRE ---' AS `apercu`;
SELECT 'ancien' AS type, a.id, a.numero_carte AS numero, a.nom, a.prenom
FROM anciens_etudiants a
JOIN students s ON s.ancien_etudiant_id = a.id
JOIN users u    ON u.id = s.user_id
WHERE a.is_registered = 1
  AND u.role IN ('etudiant','candidat') AND u.created_at < @avant
UNION ALL
SELECT 'bachelier', b.id, COALESCE(b.numero_suivi, b.numero_releve), b.nom, b.prenom
FROM bacheliers b
JOIN students s ON s.bachelier_id = b.id
JOIN users u    ON u.id = s.user_id
WHERE b.is_registered = 1
  AND u.role IN ('etudiant','candidat') AND u.created_at < @avant;


-- =====================================================================
--  PARTIE B — SUPPRESSION. En une seule transaction : tout ou rien.
-- =====================================================================
--  A lancer SEULEMENT apres avoir relu la partie A.
--  Si une instruction echoue, le client s'arrete et rien n'est applique.
-- ---------------------------------------------------------------------

START TRANSACTION;

-- Les comptes vises, figes une fois pour toutes.
-- (Une table temporaire ne declenche pas de validation implicite.)
DROP TEMPORARY TABLE IF EXISTS tmp_comptes;
CREATE TEMPORARY TABLE tmp_comptes (id BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO tmp_comptes (id)
  SELECT id FROM users
  WHERE role IN ('etudiant', 'candidat') AND created_at < @avant;

DROP TEMPORARY TABLE IF EXISTS tmp_candidatures;
CREATE TEMPORARY TABLE tmp_candidatures (id BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO tmp_candidatures (id)
  SELECT id FROM candidatures WHERE created_at < @avant;


-- 1. Le referentiel D'ABORD, tant que le lien avec le compte existe
--    encore. Une fois le compte supprime, on ne saurait plus quelle
--    fiche il occupait.
UPDATE anciens_etudiants a
JOIN students s   ON s.ancien_etudiant_id = a.id
JOIN tmp_comptes t ON t.id = s.user_id
SET a.is_registered = 0;

UPDATE bacheliers b
JOIN students s   ON s.bachelier_id = b.id
JOIN tmp_comptes t ON t.id = s.user_id
SET b.is_registered = 0;


-- 2. Les paiements : rattaches par reference polymorphe, sans cle
--    etrangere. La cascade ne les atteindrait pas.
DELETE p FROM paiements p
JOIN student_requests sr ON sr.id = p.payable_id
JOIN students s          ON s.id = sr.student_id
JOIN tmp_comptes t       ON t.id = s.user_id
WHERE p.payable_type = 'App\\Models\\StudentRequest';

DELETE p FROM paiements p
JOIN tmp_candidatures c ON c.id = p.payable_id
WHERE p.payable_type = 'App\\Models\\Candidature';


-- 3. Les roles : Spatie ne pose pas de cle etrangere sur les comptes.
--    Sans cette ligne, les roles des comptes supprimes resteraient en
--    base, orphelins.
DELETE mr FROM model_has_roles mr
JOIN tmp_comptes t ON t.id = mr.model_id
WHERE mr.model_type = 'App\\Models\\User';

DELETE mp FROM model_has_permissions mp
JOIN tmp_comptes t ON t.id = mp.model_id
WHERE mp.model_type = 'App\\Models\\User';


-- 4. Les sessions ouvertes par ces comptes.
DELETE se FROM sessions se
JOIN tmp_comptes t ON t.id = se.user_id;


-- 5. Les comptes. La base supprime en cascade : fiche etudiant, dossiers,
--    pieces deposees, etudes anterieures, derogations, codes d'acces.
DELETE u FROM users u
JOIN tmp_comptes t ON t.id = u.id;


-- 6. Les candidatures publiques. Cascade : voeux et pieces.
DELETE c FROM candidatures c
JOIN tmp_candidatures t ON t.id = c.id;


-- 7. Filet de securite : aucune fiche du referentiel ne doit rester
--    marquee inscrite sans compte pour l'occuper. Couvre aussi une
--    incoherence anterieure a ce script.
UPDATE anciens_etudiants a
SET a.is_registered = 0
WHERE a.is_registered = 1
  AND NOT EXISTS (SELECT 1 FROM students s WHERE s.ancien_etudiant_id = a.id);

UPDATE bacheliers b
SET b.is_registered = 0
WHERE b.is_registered = 1
  AND NOT EXISTS (SELECT 1 FROM students s WHERE s.bachelier_id = b.id);


-- 8. OPTIONNEL — le journal d'audit.
--    Il est CONSERVE par defaut : un journal d'audit sert justement a
--    ne rien perdre, et il contient aussi les reglages faits par le
--    personnel. Decommentez les deux lignes seulement si vous voulez
--    repartir d'un journal vierge, tests compris.
-- DELETE FROM audit_logs WHERE created_at < @avant;
-- DELETE FROM notifications_envoyees WHERE created_at < @avant;


DROP TEMPORARY TABLE IF EXISTS tmp_comptes;
DROP TEMPORARY TABLE IF EXISTS tmp_candidatures;


-- ---------------------------------------------------------------------
--  CONTROLE avant de valider. Relire les chiffres :
--    comptes_etudiants_restants  -> 0, sauf ceux crees apres @avant
--    fiches_inscrites_orphelines -> 0, obligatoirement
--    roles_orphelins             -> 0, obligatoirement
-- ---------------------------------------------------------------------
SELECT
  (SELECT COUNT(*) FROM users WHERE role IN ('etudiant','candidat'))          AS comptes_etudiants_restants,
  (SELECT COUNT(*) FROM users WHERE role NOT IN ('etudiant','candidat'))      AS personnel_conserve,
  (SELECT COUNT(*) FROM student_requests)                                     AS dossiers_restants,
  (SELECT COUNT(*) FROM candidatures)                                         AS candidatures_restantes,
  (SELECT COUNT(*) FROM anciens_etudiants)                                    AS referentiel_anciens,
  (SELECT COUNT(*) FROM anciens_etudiants a WHERE a.is_registered = 1
     AND NOT EXISTS (SELECT 1 FROM students s WHERE s.ancien_etudiant_id = a.id)) AS fiches_inscrites_orphelines,
  (SELECT COUNT(*) FROM model_has_roles mr WHERE mr.model_type = 'App\\Models\\User'
     AND NOT EXISTS (SELECT 1 FROM users u WHERE u.id = mr.model_id))         AS roles_orphelins,
  (SELECT COUNT(*) FROM request_types)                                        AS pieces_configurees,
  (SELECT COUNT(*) FROM settings)                                             AS parametres;

-- /!\ Lance DEPUIS UN FICHIER (mysql ... < fichier.sql), le script valide
--     AUTOMATIQUEMENT ci-dessous : les chiffres ci-dessus se relisent APRES
--     coup. Le vrai filet de securite, ce sont l'APERCU (a lancer avant) et
--     la SAUVEGARDE. Si une instruction echoue avant cette ligne, le client
--     s'arrete et la transaction est annulee d'elle-meme.
--
--     Lance A LA MAIN (phpMyAdmin, ou mysql interactif) : relisez les
--     chiffres, puis tapez COMMIT; s'ils sont bons, ROLLBACK; sinon.
COMMIT;
