# Docker

Pile Docker minimale pour développer avec le starter sans installer PHP, Composer ni MariaDB en local : backend PHP, base MariaDB, phpMyAdmin.

**Volontairement pensée pour le développement, pas pour la production** — voir la section [Limites assumées](#limites-assumées-non-prêt-pour-la-production) en fin de document.

> Ce document vit sous `/documentation` mais les commandes ci-dessous s'exécutent depuis la **racine du projet** (là où se trouvent `Dockerfile`, `docker-compose.yml` et `.env.docker`).

## Démarrage

```bash
copy .env.docker .env
docker compose up --build
```

| Service | URL | Rôle |
|---|---|---|
| `app` | http://localhost:8080 | Backend Slim (API REST `/api`, RPC `/rpc`, frontend buildé `/app`) |
| `db` | `localhost:3306` (depuis l'hôte) | MariaDB |
| `phpmyadmin` | http://localhost:8081 | Administration graphique de `db` |

Arrêter la pile : `docker compose down` (ajouter `-v` pour supprimer aussi les données MariaDB persistées).

## Pourquoi `.env.docker` et pas `.env.example` ?

Le starter possède déjà un fichier `.env.example` pour une exécution locale (PHP installé directement sur la machine, `composer start`) : il déclare `DB_HOST=127.0.0.1`, car le serveur PHP et MariaDB tournent alors sur la même machine.

**Dans Docker, ce n'est plus vrai** : le backend tourne dans un conteneur, MariaDB dans un autre. `127.0.0.1` depuis le conteneur `app` désignerait le conteneur `app` lui-même, pas MariaDB. Docker Compose crée un réseau où chaque service est joignable par son **nom de service** — d'où `DB_HOST=db` dans `.env.docker` (`db` étant le nom du service MariaDB dans `docker-compose.yml`).

Retenir la règle simple : **exécution locale → `.env.example`, exécution Docker → `.env.docker`**. Dans les deux cas, on copie le fichier choisi vers `.env` avant de démarrer.

## Une seule source de vérité pour les identifiants

`docker-compose.yml` ne définit aucun identifiant de base de données en dur : il lit `${DB_NAME}`, `${DB_USER}`, `${DB_PASSWORD}`, `${DB_ROOT_PASSWORD}` depuis le fichier `.env` à la racine du projet — le **même fichier** que celui lu par l'application PHP (`app/database.php`). C'est un comportement natif de Docker Compose : un fichier nommé `.env` à la racine du projet sert automatiquement de source de substitution `${VARIABLE}` dans `docker-compose.yml`.

Conséquence pratique : changer le mot de passe de la base ne se fait qu'à un seul endroit (`.env`), jamais dans `docker-compose.yml` ni deux fois dans deux fichiers différents à garder synchronisés.

`DB_ROOT_PASSWORD` fait exception : il configure uniquement le compte root de MariaDB (utile pour des opérations d'administration via phpMyAdmin ou un client SQL), l'application PHP elle-même ne l'utilise jamais (elle se connecte avec `DB_USER`/`DB_PASSWORD`, un compte applicatif non-root).

## Anatomie de la pile

```text
┌─────────────────────────────────────────────────────────────┐
│                     réseau Docker Compose                    │
│                                                                │
│   ┌────────────┐        ┌────────────┐        ┌────────────┐ │
│   │    app     │───────▶│     db     │◀───────│ phpmyadmin │ │
│   │ PHP 8.3    │  DB_*   │  MariaDB   │ PMA_*  │            │ │
│   │ (port 8080)│        │ (port 3306)│        │ (port 8081)│ │
│   └────────────┘        └────────────┘        └────────────┘ │
│         │                      │                              │
└─────────┼──────────────────────┼──────────────────────────────┘
          │                      │
     localhost:8080         volume "db_data"
     (backend + /app)       (persistance des données,
                              survit à `docker compose down`
                              sans le flag -v)
```

- **`app`** : construit depuis le [Dockerfile](../Dockerfile) racine — PHP 8.3 (Alpine), extension `pdo_mysql`, Composer installé lors du build. Sert l'application via le serveur PHP intégré (`php -S`, la même commande que `composer start` hors Docker).
- **`db`** : image officielle `mariadb:11`, sans personnalisation. Les données persistent dans un volume nommé (`db_data`), donc survivent à un redémarrage de conteneur.
- **`phpmyadmin`** : image officielle `phpmyadmin:5`, préconfigurée pour pointer sur `db` (variable `PMA_HOST`) — se connecter avec `DB_USER`/`DB_PASSWORD` définis dans `.env`.

## Développement à chaud (bind mount)

Le code source de l'hôte est monté directement dans le conteneur `app` (`volumes: - .:/var/www/html`) : modifier un fichier PHP sur la machine hôte est immédiatement visible dans le conteneur, sans reconstruire l'image ni redémarrer le conteneur.

**Exception volontaire : `vendor/`**. Un volume anonyme (`/var/www/html/vendor`) masque ce dossier du bind mount : les dépendances installées **à la construction de l'image** restent celles utilisées par le conteneur, indépendamment de la présence ou non d'un `vendor/` sur l'hôte. Sans cette exception, un hôte sans `vendor/` local (cas d'un clone fraîchement récupéré) casserait le conteneur en masquant ses dépendances par un dossier vide.

Conséquence pratique : après avoir modifié `composer.json` (ajout d'une dépendance), il faut reconstruire l'image pour que le conteneur voie la mise à jour :

```bash
docker compose up --build
```

## Migrations Doctrine dans le conteneur

Les commandes `composer doctrine -- <commande>` (voir [README.md](../README.md#persistance--doctrine-orm)) s'exécutent à l'intérieur du conteneur `app`, qui a seul accès au réseau Docker menant à `db` :

```bash
docker compose exec app composer doctrine -- migrations:migrate
```

## Frontend : non containerisé, volontairement

Le frontend (`frontend/`) n'a pas de service Docker dédié : builder un frontend Vue/Vite dans un conteneur ajouterait un service supplémentaire (Node.js) et une étape de build à orchestrer, pour un gain minime dans un contexte de développement local où Node est presque toujours déjà installé (voir [frontend/README.md](../frontend/README.md)).

Le workflow reste donc :

```bash
cd frontend
npm install
npm run build   # dépose le résultat dans public/app
```

`public/app` est ensuite servi par le conteneur `app` comme n'importe quel fichier statique (bind mount du dossier `public/` inclus dans le bind mount global du projet).

## Limites assumées (non prêt pour la production)

Cette pile privilégie la simplicité de mise en route pour une équipe de développement, pas un déploiement de production :

- **Serveur PHP intégré** (`php -S`) au lieu de PHP-FPM + Nginx : suffisant en développement, mais mono-thread et non conçu pour encaisser une charge de production.
- **Bind mount du code source** : pratique en développement, à proscrire en production (l'image doit être auto-suffisante, sans dépendre de l'hôte).
- **Mots de passe par défaut** (`vdl`/`vdl`, `root`/`root`) dans `.env.docker` : à changer impérativement avant tout usage au-delà du poste de développement local.
- **Ports `db` (3306) exposés sur l'hôte** : pratique pour se connecter avec un client SQL externe en développement, à retirer en production (seul le réseau Docker interne devrait pouvoir joindre `db`).
- **Pas de mécanisme de secrets** (Docker secrets, Vault...) : les identifiants transitent en clair dans `.env`, cohérent avec le reste du starter (voir `.env.example`) mais à revoir avant un déploiement réel.

Un profil Docker de production (image multi-stage sans bind mount, PHP-FPM + Nginx, secrets externalisés) est un chantier distinct, non couvert ici — voir `PLAN.md`, section Progression.
