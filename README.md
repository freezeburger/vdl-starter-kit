# VDL Starter Kit

Starter PHP 8.3 pragmatique pour créer des applications REST, RPC et Web avec des conventions simples et explicables.

## Démarrage

```bash
git clone https://github.com/freezeburger/vdl-starter-kit.git
cd vdl-starter-kit
composer install
copy .env.example .env
composer start
```

L'endpoint `GET http://localhost:8080/api/health` retourne une réponse JSON. La documentation interactive de l'API REST est disponible sur `http://localhost:8080/api/openapi`.

Alternative sans installer PHP/MariaDB en local : `docker compose up --build` (backend + MariaDB + phpMyAdmin) — voir [documentation/DOCKER.md](documentation/DOCKER.md).

## Structure

```text
Dockerfile, docker-compose.yml     Pile Docker minimale (backend + MariaDB + phpMyAdmin), voir documentation/DOCKER.md

documentation/                     Documentation étendue (plan de travail, Docker, besoin d'origine, présentation)
app/                              Construction de l'application Slim + Doctrine (bootstrap)
public/                           Point d'entrée HTTP + build du frontend (public/app)
frontend/                         Frontend Vue 3 + Vuetify 3, buildé vers public/app
migrations/                       Migrations de schéma Doctrine
scripts/make.php                  Dispatcher des commandes composer make:*
scripts/generators/                Un fichier par type de composant généré
src/Core/                         Composants transverses simples
src/Core/Middleware/              CORS et Request-Id, partagés par tous les composants routés
src/Core/OpenApi/                 Documentation OpenAPI/Swagger auto-générée des endpoints REST
src/Endpoints/Rest/                Ressources REST : fichier unique (simple) ou répertoire (filtres/pagination + une classe par méthode HTTP)
src/Endpoints/Rpc/                 Actions RPC, fichiers uniques (auto-enregistrées)
src/Application/DOMAIN_NAME/       Un domaine = Controller + templates (consomme un ou plusieurs Repositories)
src/Repositories/NOM/              Model (Entity Doctrine) + Repository d'une ressource, partagés entre domaines et endpoints
```

Le code est organisé par responsabilité. Une abstraction n'est ajoutée que lorsqu'elle apporte une commodité clairement identifiable.

Trois natures de composants coexistent délibérément :

- **Endpoint** (`src/Endpoints/Rest`, `src/Endpoints/Rpc`) : sans logique métier, expose une ou plusieurs routes et délègue à un ou plusieurs Repositories si besoin (leur usage reste optionnel).
- **Domaine** (`src/Application/DOMAIN_NAME`) : Controller et templates d'une fonctionnalité métier web, groupés ensemble plutôt que dispersés par couche technique. Le Controller consomme un ou plusieurs Repositories (voir « Model / Repository / Controller » ci-dessous).
- **Repository** (`src/Repositories`) : l'accès aux données d'une Entity, partagé entre domaines et endpoints — volontairement séparé du dossier de domaine (voir [src/Repositories/README.md](src/Repositories/README.md)).

Voir [src/Endpoints/README.md](src/Endpoints/README.md), [src/Application/README.md](src/Application/README.md) et [src/Repositories/README.md](src/Repositories/README.md) pour le détail de chaque convention.

## Principe d'auto-registration des routes

Les routes ne sont **jamais déclarées manuellement** dans le bootstrap, mais selon deux conventions distinctes, propres à la nature de chaque composant :

- **Endpoints** (`src/Endpoints/Rest/`, `src/Endpoints/Rpc/`) : `app/app.php` scanne ces dossiers (fichiers directement dedans, et fichiers d'un sous-répertoire). Toute classe non abstraite qui étend `App\Core\RouteAction` et déclare sa constante `PATH` (relative, ex. `/product`) est automatiquement enregistrée comme route, préfixée selon sa famille. Une ressource simple reste un fichier unique (une classe = une route) ; une ressource qui expose plusieurs opérations devient un répertoire `NOM/` (une classe de base abstraite ignorée par le scan + une classe par méthode HTTP, chacune avec sa propre route) — voir [src/Endpoints/README.md](src/Endpoints/README.md).
- **Domaines** (`src/Application/DOMAIN_NAME/`) : un Controller de domaine est multi-actions (index, show, store...). `app/app.php` scanne `src/Application/*/*Controller.php` et, par réflexion, enregistre chaque méthode publique annotée `#[App\Core\Attributes\Route('METHODE', '/chemin')]` comme route indépendante.

Conséquence pratique commune : ajouter un composant routé ne nécessite qu'un seul fichier — sa ou ses routes sont déduites de son code, sans registre séparé à maintenir à jour. Le détail et la justification de cette coexistence sont documentés dans [src/Endpoints/README.md](src/Endpoints/README.md) et [src/Application/README.md](src/Application/README.md).

## Préfixes REST / RPC

Tous les endpoints REST sont exposés sous `/api` et tous les endpoints RPC sous `/rpc` — appliqué une seule fois par `app/app.php` (fonction `registerActions()`), et non répété dans chaque fichier généré. Une action REST/RPC déclare un `PATH` relatif (ex. `/product`), résolu en route finale (`/api/product`) au moment de l'enregistrement.

Ces préfixes se configurent via `API_REST_PREFIX` et `API_RPC_PREFIX` (`.env`), `/api` et `/rpc` par défaut. Changer la convention de préfixage (par exemple pour verser vers `/v1/api`) ne nécessite donc de modifier qu'une variable d'environnement, jamais les endpoints déjà générés.

## Documentation OpenAPI / Swagger

Chaque endpoint REST (`src/Endpoints/Rest/`) se documente lui-même via des attributs PHP 8 (`#[OpenApi\Attributes\Get(...)]`), générés par défaut par `composer make:rest`. Cette documentation est assemblée à la volée, jamais figée dans un fichier séparé à maintenir à la main :

- `GET /api/openapi.json` : la spécification OpenAPI brute (JSON) ;
- `GET /api/openapi` : une interface Swagger UI navigable, qui charge la spécification ci-dessus (assets chargés depuis un CDN, sans outillage front à installer).

Détails de fonctionnement et convention d'annotation dans [src/Core/OpenApi/README.md](src/Core/OpenApi/README.md).

## Frontend Vue 3 / Vuetify 3

Un squelette frontend minimaliste (`frontend/`) est disponible pour le profil Front : Vue 3, Vuetify 3, `vue-router` (plusieurs pages, déclarées explicitement, sans découverte automatique). Il se build vers `public/app`, servi par le backend comme n'importe quelle ressource statique :

```bash
cd frontend
npm install
npm run dev      # développement, http://localhost:5173/app/, proxy /api et /rpc vers le backend
npm run build    # production, dépose le résultat dans public/app
```

Détails, structure et point de vigilance sur le SPA fallback en production dans [frontend/README.md](frontend/README.md). Le cycle d'authentification Keycloak n'est pas encore implémenté (voir [documentation/PLAN.md](documentation/PLAN.md), point 8).

## Génération

```bash
composer make:rest -- Product
composer make:rpc -- Import
composer make:repository -- Product
composer make:domain -- Product
```

- `composer make:rest` produit un **répertoire** `src/Endpoints/Rest/Product/` : `ProductEndpoint.php` (classe de base abstraite, fixe le `PATH`, hérite des commodités de filtrage/pagination de `App\Core\RouteAction`) et une classe par méthode HTTP (`ProductGetAction`, `ProductPostAction`, `ProductPutAction`, `ProductPatchAction`, `ProductDeleteAction`) — `GET`/`POST` sur `/product`, `PUT`/`PATCH`/`DELETE` sur `/product/{id}` (route personnalisée par action, voir [src/Endpoints/README.md](src/Endpoints/README.md)). Aucune ne déclare de Repository par défaut : son usage est optionnel, à ajouter au besoin.
- `composer make:rpc` produit un seul fichier dans `src/Endpoints/Rpc/`, exposé sous `/rpc/...` (voir « Préfixes REST / RPC » ci-dessus).
- `composer make:repository` produit `src/Repositories/Product/` : `ProductModel.php` (Entity Doctrine) et `ProductRepository.php`, dans le même dossier/namespace (`App\Repositories\Product`) — à générer **avant** `composer make:domain` si le domaine doit persister des données.
- `composer make:domain` produit `src/Application/Product/` : `ProductController.php` (méthodes annotées `#[Route]`, consomme `ProductRepository` généré au préalable) et `templates/product.html.twig`, rendu via Twig.

Chaque commande valide le nom, refuse d'écraser un fichier existant et affiche chaque fichier créé.

Les générateurs sont séparés du dispatcher :

- `scripts/make.php` : point d'entrée commun, ne connaît aucun détail de template (validation des arguments, résolution des chemins, écriture des fichiers) ;
- `scripts/generators/rest.php`, `scripts/generators/rpc.php`, `scripts/generators/domain.php`, `scripts/generators/repository.php` : un fichier par type de composant, documenté indépendamment, retournant la liste des fichiers à générer (chemin + gabarit).

Ajouter un nouveau type de composant se fait en créant un nouveau fichier dans `scripts/generators/` sans modifier les générateurs existants.

## Model / Repository / Controller

Le Model (Entity Doctrine) est défini **au niveau du Repository** (`src/Repositories/NOM/NOMModel.php`), pas du Controller : il représente une préoccupation de la couche d'accès aux données, pas de l'orchestration web. Un Controller de domaine (ou un endpoint REST/RPC) ne fait que consommer des instances du Model via le(s) Repository(s) qu'il injecte dans son constructeur.

Rien n'impose qu'un Controller ne consomme qu'un seul Repository : le mécanisme d'injection par réflexion (`app/app.php`, `resolveDependency()`) résout chaque paramètre typé du constructeur indépendamment des autres, qu'il s'agisse de `ProductRepository`, `CategoryRepository`, `Twig` ou `EntityManager` :

```php
public function __construct(
    private readonly ProductRepository $products,
    private readonly CategoryRepository $categories,
    private readonly Twig $view,
) {
}
```

Voir [src/Repositories/README.md](src/Repositories/README.md) et [src/Application/README.md](src/Application/README.md) pour le détail.

## Persistance : Doctrine ORM

Le socle intègre Doctrine ORM et Doctrine Migrations dès l'origine, ciblant MySQL/MariaDB par défaut :

- l'`EntityManager` est construit par `app/database.php` (fonctions `loadEnvironment()` et `buildEntityManager()`), partagées entre le bootstrap HTTP (`app/app.php`) et la configuration des migrations (`cli-config.php`) ;
- le mapping des Entities se fait par **attributs PHP 8** (pas d'annotations docblock ni de XML), scanné sous `src/Repositories/*` (où vit le Model, à côté de son Repository) ;
- la connexion est configurée via les variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` du fichier `.env`, **requises uniquement si l'application compte au moins un Repository** (`src/Repositories/NOM/NOMRepository.php`) : sans Repository, `app/app.php` ne construit pas d'`EntityManager` et ces variables peuvent rester absentes du `.env` — un profil sans persistance (RPC pur, proxy...) n'a donc rien à configurer pour démarrer ;
- les migrations de schéma vivent dans `migrations/` et se pilotent via `composer doctrine -- <commande>` (voir [migrations/README.md](migrations/README.md)).

Comme pour Twig, la résolution du Repository et de l'EntityManager dans le constructeur d'un Controller de domaine passe par le mécanisme de réflexion temporaire de `app/app.php`, en attendant le conteneur DI définitif.

## Middlewares transverses

Cinq commodités sont actives par défaut sur toutes les routes (Endpoints et Domaines), câblées dans `app/app.php` :

- **Parsing JSON du corps de requête** (`addBodyParsingMiddleware()`) : `$request->getParsedBody()` contient directement le tableau décodé pour un corps `application/json` (ou form-urlencoded), sans `json_decode()` manuel dans chaque Action/Controller.
- **Erreurs 404 et 405 en JSON** : par défaut, Slim répond en HTML pour une route introuvable ou une méthode non supportée. Le bootstrap enregistre un handler JSON dédié pour `Slim\Exception\HttpNotFoundException` et `Slim\Exception\HttpMethodNotAllowedException` (en plus du handler 500 déjà en place), pour qu'une API JSON reste JSON en toute circonstance.
- **CORS** (`src/Core/Middleware/CorsMiddleware.php`) : ajoute les en-têtes `Access-Control-Allow-*` et répond directement aux requêtes `OPTIONS` de préflight (204, sans passer par le routage). Origines autorisées configurables via `CORS_ALLOWED_ORIGINS` (`.env`), `*` par défaut.
- **Request-Id** (`src/Core/Middleware/RequestIdMiddleware.php`) : garantit un en-tête `X-Request-Id` sur chaque réponse (repris du client si fourni, généré sinon), disponible aussi en attribut de requête pour le logging.
- **Logging PSR-3** (`src/Core/Middleware/RequestLoggingMiddleware.php`) : journalise chaque requête (méthode, chemin, statut, durée, request id) via Monolog, avec un niveau adapté au code retour (`info`/`warning`/`error`). Fichier configurable via `LOG_PATH` et `LOG_LEVEL` (`.env`).

Ces middlewares vivent dans `src/Core/Middleware/` (et non dupliqués par convention REST/RPC) car leur besoin est strictement identique quelle que soit la nature du composant appelé — voir [src/Core/Middleware/README.md](src/Core/Middleware/README.md).

## Journalisation

Un logger PSR-3 (Monolog) est construit par `app/logger.php` (fonction `buildLogger()`) et partagé par toute l'application :

- chaque requête HTTP est journalisée automatiquement par `RequestLoggingMiddleware` (voir ci-dessus) ;
- tout Controller de domaine ou Repository peut typer `Psr\Log\LoggerInterface` dans son constructeur pour journaliser un événement métier propre — résolu par le même mécanisme temporaire de réflexion que `Twig` et `EntityManager` ;
- le fichier de sortie (`LOG_PATH`, défaut `logs/app.log`) et le niveau minimal journalisé (`LOG_LEVEL`, défaut `debug`) se configurent via `.env` ;
- ajouter un canal supplémentaire (Sentry, syslog...) se fait en ajoutant un Handler Monolog dans `app/logger.php`, sans modifier le reste du code applicatif (tout composant ne dépend que de `LoggerInterface`).

## Configuration

La configuration locale se fait dans `.env`, qui ne doit jamais être commité. Les variables prévues par le socle initial sont `APP_ENV`, `APP_NAME`, `APP_DEBUG`, `API_REST_PREFIX`, `API_RPC_PREFIX`, `CORS_ALLOWED_ORIGINS`, `LOG_PATH`, `LOG_LEVEL` et les variables `DB_*` de connexion Doctrine — ces dernières optionnelles, voir « Persistance : Doctrine ORM » ci-dessus.

## Suite prévue

Les prochaines étapes sont l'ajout progressif de la configuration typée, du conteneur DI (qui remplacera la résolution de dépendances par réflexion actuellement utilisée pour les Controllers de domaine et Repositories), des middlewares d'authentification Keycloak, du front Vue, de l'OpenAPI et des tests.

Le détail complet (décisions retenues, avancement par étape, points ouverts) vit dans [documentation/PLAN.md](documentation/PLAN.md), section « Progression ».

## Documentation

Toute la documentation transversale (celle qui dépasse le périmètre d'un seul dossier) vit sous [documentation/](documentation) :

| Fichier | Contenu |
|---|---|
| [documentation/PLAN.md](documentation/PLAN.md) | Plan de travail complet : décisions retenues, avancement par étape, section « Progression » (réalisé / reste à faire / recommandations). |
| [documentation/MISSION.md](documentation/MISSION.md) | Propositions d'améliorations complémentaires en consultance, chiffrées en jours/homme. |
| [documentation/PRESENTATION.md](documentation/PRESENTATION.md) | Résumé de présentation du starter kit à destination d'un consultant externe. |
| [documentation/DOCKER.md](documentation/DOCKER.md) | Pile Docker (backend + MariaDB + phpMyAdmin) : démarrage, architecture, limites assumées. |
| [documentation/STARTER_KIT.md](documentation/STARTER_KIT.md) | Besoin d'origine tel qu'exprimé au démarrage du projet, conservé à titre de référence historique. |

Chaque dossier de code (`src/Endpoints/`, `src/Application/`, `src/Repositories/`, `src/Core/Middleware/`, `src/Core/OpenApi/`, `frontend/`, `migrations/`) garde en revanche son propre `README.md` **au plus près du code qu'il documente**, référencé depuis les sections correspondantes ci-dessus.
