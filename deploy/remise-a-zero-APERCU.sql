-- =====================================================================
--  APERCU SEUL — n'efface RIEN.
--  Montre ce que remise-a-zero-des-tests.sql supprimerait et conserverait.
--  @avant doit etre IDENTIQUE dans les deux fichiers (voir le memo).
-- =====================================================================

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


