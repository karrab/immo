# GestImmo — Gestion des immobilisations

Système d'information complet de gestion des **biens comptables (immobilisations)** :
PHP 8.1+ · MySQL 8 / MariaDB 10.5+ (InnoDB) · Bootstrap 5 · OPcache · APCu · Dompdf.

## Fonctionnalités

| Module | Contenu |
|---|---|
| **Fiche bien** | Identification, acquisition (fournisseur, facture), catégorie et comptes comptables, **plan d'amortissement** (linéaire prorata temporis / dégressif avec bascule), VNC, localisation, détenteur, **historique complet** (création, modifications champ par champ, transferts, affectations, changements d'état/statut). Code inventaire auto `CAT-AAAA-00001`. |
| **Locaux** | Bureaux, salles, magasins… par bâtiment / étage / service. Biens présents, occupants, **fiche inventaire du local** imprimable à signer. |
| **Transferts** | Bon de transfert multi-biens d'un bureau vers un autre, nouveau détenteur, workflow *en attente → validé / rejeté / annulé*, contrôle d'intégrité transactionnel (`SELECT … FOR UPDATE`), historique automatique sur chaque bien, **bon de transfert PDF** avec cases de signature. |
| **Employés** | Matricule, fonction, service, bureau ; biens détenus ; **fiche de détention (décharge)** PDF. |
| **Utilisateurs** | Comptes liés éventuellement à un employé, activation, réinitialisation de mot de passe, politique de mot de passe. |
| **Permissions** | RBAC : rôles + matrice de 39 permissions par module (voir, créer, modifier, supprimer, exporter, valider). Rôle super-admin. Contrôle côté serveur sur chaque action. |
| **Traces** | Journal d'audit append-only : qui, quoi, quand, IP, navigateur, données avant/après (connexions, échecs, créations, modifications, suppressions, exports, impressions). Filtres + export. |
| **Recherche avancée** | Texte libre FULLTEXT (désignation, marque, modèle, n° série, fournisseur) + code, catégorie, local, service, employé, état, statut, période d'acquisition, fourchette de valeur, fournisseur, biens sans local. Tri par colonnes. |
| **Boutons** | Voir / Modifier / **Supprimer** (POST + CSRF + modale de confirmation) / **Imprimer** (PDF dans un nouvel onglet) / **PDF** / **CSV** sur toutes les listes et fiches — les exports respectent les filtres en cours. |
| **Tableau de bord** | Indicateurs, valeur par catégorie, statuts, acquisitions par année, derniers transferts, activité récente, état d'OPcache et d'APCu. |

## Performances

* **Pagination keyset** (`app/Core/Keyset.php`) : au lieu de `LIMIT n OFFSET m`, on mémorise la clé
  `(valeur_de_tri, id)` de la dernière ligne et on demande les suivantes :
  `WHERE (col < ? OR (col = ? AND id < ?)) ORDER BY col DESC, id DESC LIMIT n+1`.
  Le coût est constant quelle que soit la page. Curseur opaque en base64url, navigation précédent/suivant.
  *Mesuré sur 100 000 biens : ~5 ms par page, première comme 4 000e.*
* **Index InnoDB** composites `(colonne_de_filtre_ou_tri, id)` sur chaque critère de recherche, toutes les
  clés étrangères indexées, index **FULLTEXT** pour la recherche texte. Les jointures de listes sont des
  `LEFT JOIN` pour que la table `biens` pilote la requête et que l'index soit lu dans l'ordre (pas de filesort).
* **APCu** (`app/Core/Cache.php`) : permissions des rôles, listes de référence (catégories, locaux,
  employés…), compteurs de résultats, statistiques du tableau de bord, compteur anti-force-brute.
  Invalidation par **tags versionnés** à chaque écriture. Repli automatique sans APCu.
* **OPcache** : configuration de production dans `docker/php.ini` (JIT, `validate_timestamps=0`) et
  **préchargement** des classes via `preload.php`.
* **MySQL** : réglages InnoDB conseillés dans `docker/my.cnf` (buffer pool, `innodb_ft_min_token_size=3`…).

## Installation

### Avec Docker (le plus simple)

```bash
docker compose up -d --build
```

Ouvrir http://localhost:8080 — compte `admin` / `Admin@123`.

### Installation manuelle (Apache/Nginx + PHP-FPM)

Prérequis : PHP ≥ 8.1 avec `pdo_mysql`, `mbstring`, `gd`, `opcache`, `apcu` ; MySQL 8 ou MariaDB 10.5+ ; Composer.

```bash
composer install --no-dev --optimize-autoloader
```

```bash
cp config/config.local.php.example config/config.local.php
```

Renseigner les accès MySQL dans `config/config.local.php` (ou par variables d'environnement `DB_HOST`,
`DB_NAME`, `DB_USER`, `DB_PASS`, `APP_DEVISE`, `APP_TIMEZONE`, `APP_ORGANISATION`), puis :

```bash
php bin/install.php
```

(`--no-seed` pour n'installer que les rôles, permissions et le compte admin, sans données de démonstration.)

La racine web doit pointer sur **`public/`** (exemple Nginx : `docker/nginx.conf.example`).
Copier les réglages de `docker/php.ini` dans la configuration PHP (adapter `opcache.preload`).

En développement :

```bash
php -S localhost:8000 -t public public/router.php
```

### Test de charge

```bash
php bin/generate.php 100000
```

## Structure

```
app/
  Core/         Database (PDO), Cache (APCu), Auth (RBAC), Csrf, Audit, Keyset, View, Pdf, Csv, Ref
  Controllers/  Biens, Locaux, Transferts, Employes, Users, Roles, Traces, Categories, Services, Dashboard, Auth
  Services/     Amortissement (plans linéaire / dégressif)
  helpers.php   e(), url(), money(), badge(), options()… + énumérations métier
config/         config.php (+ config.local.php non versionné)
database/       schema.sql (InnoDB + index), seed.sql (permissions, rôles, admin, démo)
public/         index.php (contrôleur frontal), assets (Bootstrap 5, icônes, Tom Select embarqués)
views/          layout/, partials/, un dossier par module, pdf/ (gabarits d'impression)
bin/            install.php, generate.php
docker/         Dockerfile, php.ini (OPcache/APCu), my.cnf (InnoDB), nginx.conf.example
preload.php     Préchargement OPcache
```

## Sécurité

Requêtes préparées natives (pas d'émulation), échappement systématique des sorties, jeton CSRF sur
tous les formulaires, suppression uniquement en POST, sessions `HttpOnly`/`SameSite` avec régénération
d'ID et expiration d'inactivité, `password_hash` (bcrypt/argon selon PHP) avec rehash automatique,
blocage après 5 échecs de connexion (15 min), en-têtes `X-Frame-Options` / `nosniff`, seul `public/`
est exposé. Un administrateur ne peut ni supprimer, ni désactiver, ni rétrograder son propre compte.

## Règles métier

* Le local d'un bien ne se modifie pas dans sa fiche : il passe par un **transfert** (traçabilité).
* Un bien ne peut figurer que dans un seul transfert en attente ; à la validation on vérifie qu'il est
  toujours dans le local source.
* Un transfert validé n'est pas supprimable (il fait partie de l'historique).
* Locaux / employés / catégories encore utilisés ne peuvent pas être supprimés (contraintes FK).
