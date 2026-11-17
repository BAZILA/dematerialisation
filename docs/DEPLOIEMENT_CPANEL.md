# Déploiement sur cPanel / O2switch

Guide pas-à-pas pour mettre la plateforme en ligne à partir d'un **zip**.

---

## 0. Le point le plus important ⚠️

L'application est en **Laravel 12**, qui **exige PHP ≥ 8.2**.
Le PHP par défaut d'O2switch est le **8.0** → **il faut le passer à 8.3**, sinon
page blanche / erreur 500. Deux méthodes :

### Méthode A (recommandée, la plus simple) — tout le compte en 8.3
**cPanel → Logiciels → Sélectionner une version de PHP** → choisir **8.3**.
C'est la méthode recommandée par O2switch : PHP **natif** (rapide) et surtout
**tolérant sur les permissions**. À privilégier si aucun autre site du compte ne
doit rester sur une version différente. Dans ce cas, PAS besoin de `php.ini`
personnalisé ni des lignes de handler dans le `.htaccess`.

> Vérifier que ces extensions sont activées : `mbstring`, `openssl`,
> `pdo_mysql`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`,
> `gd`, `zip`, `curl`.

### Méthode B (par dossier, via `.htaccess`) — si le compte doit rester en 8.0 ailleurs
Ajouter à la fin du `.htaccess` du projet :

```apache
<FilesMatch \.php$>
SetHandler application/x-httpd-php83
</FilesMatch>
AddHandler application/x-httpd-php83 .php
suPHP_ConfigPath /home/<login>/<dossier-du-site>/
```

Puis créer un `php.ini` **dans ce même dossier**. ⚠️ **Deux pièges à éviter :**
1. **Chemins de version** — si tu pars du `php.ini` d'exemple d'O2switch (écrit
   pour php81), **remplace tous les `php81` par `php83`**, ou plus simple :
   **supprime les deux lignes `zend_extension=...`** (opcache/ioncube inutiles au
   démarrage). Un `zend_extension` d'une autre version = *module API mismatch* →
   **500**.
2. **Permissions suPHP** — cette méthode passe par **suPHP**, qui **REFUSE le 775
   et le 777** (voir §5).

Doc O2switch : https://faq.o2switch.fr/guides/php/changer-version-php-et-php-ini/

---

## 1. Où décompresser le projet

Deux options — **A recommandée**.

### Option A — Sous-domaine dont la racine pointe sur `/public` (le plus propre)
1. **cPanel → Domaines → Créer un sous-domaine** (ex. `demat.uk-univ.tg`).
2. Dans « Document Root », mettre `.../demat.uk-univ.tg/public`.
3. Décompresser le zip **dans le dossier du sous-domaine** (au-dessus de `public`).
4. Régler ce sous-domaine sur **PHP 8.3** (étape 0).

### Option B — Décompresser tel quel dans `public_html`
Grâce au fichier **`.htaccess`** fourni à la racine du projet, on peut
décompresser **tout le contenu du zip directement dans `public_html`** sans
changer le document root : les requêtes sont automatiquement redirigées vers
`public/`, et les dossiers sensibles restent inaccessibles.

> Dans les deux cas, **ne jamais** exposer `.env`, `vendor/`, `storage/` au web —
> ce qui est déjà garanti par la structure Laravel + le `.htaccess` fourni.

---

## 2. Base de données MySQL

1. **cPanel → Bases de données MySQL** :
   - Créer la base : `dematerialisation` (nom réel : `compte_dematerialisation`).
   - Créer un utilisateur + mot de passe fort.
   - Ajouter l'utilisateur à la base avec **TOUS LES PRIVILÈGES**.
2. Noter le **nom réel** (préfixé par le compte) — à reporter dans `.env`.

---

## 3. Configurer `.env`

1. Sur le serveur, renommer **`.env.production.example`** en **`.env`**.
2. Renseigner : `APP_URL`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
   et le bloc `MAIL_*` (compte e-mail créé dans cPanel).
3. Générer la clé applicative (voir §4).

---

## 4. Terminal (SSH) — installation

O2switch fournit un **Terminal** (cPanel → Avancé → Terminal) ou un accès **SSH**.
Se placer dans le dossier du projet, puis :

```bash
# S'assurer d'utiliser PHP 8.3 en ligne de commande
/opt/cpanel/ea-php83/root/usr/bin/php -v

# Générer la clé de l'application
/opt/cpanel/ea-php83/root/usr/bin/php artisan key:generate --force

# Créer les tables + données de base (rôles, procédures, comptes de test…)
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force --seed

# Optimiser pour la production
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:cache
```

> Astuce : créer un alias `alias php=/opt/cpanel/ea-php83/root/usr/bin/php`
> pour simplifier les commandes.

### Si tu n'as PAS d'accès SSH
- Le dossier `vendor/` est **déjà inclus dans le zip** → pas besoin de `composer install`.
- Il reste à exécuter `key:generate`, `migrate --seed` et les `*:cache`.
  → Le plus simple est d'ouvrir le **Terminal** cPanel (disponible sur O2switch).
  À défaut, importe la base via **phpMyAdmin** (dump SQL) et mets une `APP_KEY`
  valide à la main dans `.env`.

---

## 5. Droits d'écriture (⚠️ suPHP)

Sur O2switch, PHP tourne en tant que **votre utilisateur**. En **méthode B
(suPHP)**, le **775 et le 777 sont REFUSÉS** → n'utilisez que **755 (dossiers)**
et **644 (fichiers)**. Le 755 suffit pour que `storage/` soit inscriptible
(le propriétaire a les droits d'écriture).

```bash
cd ~/<dossier-du-site>
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

> Ne faites **jamais** `chmod 775`/`777` avec la méthode B : c'est la cause n°1
> des erreurs 500 sous suPHP (message *« SoftException … is writeable by group »*
> dans **cPanel → Métriques → Erreurs**).

---

## 6. Vérifications finales

- [ ] Le domaine est bien en **PHP 8.3**.
- [ ] `https://<domaine>/login` s'affiche (page de connexion UK).
- [ ] Connexion **admin** : `ADMIN001` / `admin123456`.
- [ ] Connexion **étudiant test** : `ETUD001` / `etudiant123`.
- [ ] Bouton **« Candidature en ligne (sans compte) »** fonctionne.
- [ ] Un dépôt PDF se valide et se **télécharge** (test FPDI/TCPDF).
- [ ] `.env` **inaccessible** au web : `https://<domaine>/.env` → **403/404**.

---

## 7. Après mise à jour du code (redéploiements)

À chaque nouvelle version, après avoir remplacé les fichiers :

```bash
php artisan config:clear && php artisan cache:clear
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Notes importantes

- **Aucun `npm run build`** : Bootstrap, Font Awesome et les polices sont
  chargés via CDN. Rien à compiler.
- **Pas de lien symbolique `storage`** nécessaire : les fichiers déposés sont
  servis par des contrôleurs (routes `.../file/{...}/preview` et `/download`),
  pas via `public/storage`.
- **Comptes de test** : à supprimer/renommer avant l'ouverture réelle au public
  (gestion des utilisateurs). Change aussi les mots de passe par défaut.
- **Données de test** : le zip de production ne contient PAS les fichiers
  déposés de test (dossier `storage/app/private/...` vidé par le script de
  packaging).
