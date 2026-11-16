# Plan de travail — Starter PHP multi-profils

## Compréhension du besoin

Construire un starter PHP réutilisable par une équipe de chefs de projet qui développe régulièrement, mais qui doit pouvoir démarrer rapidement sans traiter chaque projet comme une intervention d'urgence. Le starter doit imposer suffisamment de conventions pour réduire les choix et les erreurs, tout en restant simple à comprendre et adaptable.

Le projet s'inspire de `4.app` pour son socle Slim actuel :

- point d'entrée public et autoload Composer ;
- découverte/enregistrement des handlers ;
- séparation Handler / Service / Repository / Model ;
- commande de génération.

Il doit l'étendre vers un starter complet, constitué d'un socle commun et de profils ou modules optionnels :

- API REST ;
- services HTTP/RPC et traitements batch ;
- application web Twig ;
- front Vue avec cycle d'authentification ;
- authentification Keycloak ;
- Doctrine ORM et migrations ;
- composants transverses (logging, mail, export, réponses JSON, middleware) ;
- documentation OpenAPI/Swagger et conventions de démarrage.

## Décisions retenues

- La cible initiale couvre REST, RPC, web Twig, front Vue et Keycloak.
- L'architecture est un socle commun avec profils/modules activables, pas plusieurs squelettes indépendants.
- Doctrine ORM et les migrations sont inclus dans la base prête à l'emploi.
- Le runtime cible est PHP 8.3 ; les dépendances et la configuration de qualité devront être alignées sur cette version.
- Les APIs REST et RPC ciblent JSON comme format d'échange standard ; XML n'est pas un objectif du premier socle API.
- L'architecture doit rester pragmatique, explicable et compréhensible par l'équipe ; toute abstraction doit répondre à un besoin concret.
- Les commodités de développement sont unifiées autour de commandes `composer make:*`, documentées et cohérentes entre les profils.
- Le code livré doit être systématiquement documenté : responsabilités, points d'extension, conventions, configuration et exemples d'utilisation.
- La simplicité d'usage et la standardisation priment sur l'exhaustivité des fonctionnalités.
- La structure du code distingue explicitement trois natures de composants, chacune avec son mécanisme d'enregistrement/génération propre :
  - **Endpoints** (`src/Endpoints/Rest/`, `src/Endpoints/Rpc/`) : une ressource simple reste un fichier unique (classe `RouteAction`, constante `PATH` relative), auto-enregistrée par scan de dossier et préfixée centralement (`/api` pour REST, `/rpc` pour RPC — configurable via `API_REST_PREFIX`/`API_RPC_PREFIX`). Une ressource REST qui expose plusieurs opérations (une par méthode HTTP) devient un **répertoire** (`src/Endpoints/Rest/NOM/`) : une classe de base abstraite (filtres de requête + pagination, héritées de `RouteAction`) et une classe par méthode (`NOMGetAction`, `NOMPostAction`...), chacune personnalisant si besoin sa propre route — l'usage d'un Repository y reste optionnel. Voir [src/Endpoints/README.md](../src/Endpoints/README.md).
  - **Domaines** (`src/Application/DOMAIN_NAME/`) : un dossier par domaine métier regroupant Controller et templates. Le Controller est multi-actions, déclare ses routes via l'attribut PHP 8 `#[Route('METHODE', '/chemin')]` sur chaque méthode (auto-enregistrées par réflexion), et consomme un ou plusieurs Repositories injectés dans son constructeur (rien ne le limite à un seul).
  - **Repositories** (`src/Repositories/NOM/`) : Model (Entity Doctrine) et Repository d'une même ressource, générés ensemble (`composer make:repository`) dans le même dossier/namespace, et **partagés** entre domaines et endpoints — le Model est une préoccupation de la couche d'accès aux données, pas du Controller, qui n'en consomme que des instances via le(s) Repository(s) qu'il injecte.
- L'`EntityManager` Doctrine (et donc les variables `DB_*` du `.env`) n'est construit que si l'application compte au moins un Repository (`src/Repositories/NOM/NOMRepository.php`) : un profil sans persistance (RPC pur, proxy...) n'a rien à configurer pour démarrer.
- Le profil Web s'appuie sur Twig dès le socle initial (et non comme extension ultérieure) : chaque domaine expose ses templates sous un namespace Twig qui porte son nom (`@Product`, `@Order`...), évitant toute collision entre domaines.
- L'étape 4 (persistance) est également avancée dès le socle initial plutôt que reportée : Doctrine ORM et Doctrine Migrations sont intégrés, ciblant **MySQL/MariaDB** par défaut. Le Model généré par `composer make:domain` est directement l'Entity Doctrine du domaine (attributs `#[ORM\Entity]`...) ; le Repository qui y accède est généré séparément par `composer make:repository` et vit dans `src/Repositories/`.
- La résolution des dépendances des Controllers de domaine (Repository, EntityManager, vue Twig...) passe pour l'instant par un mécanisme de réflexion simple et volontairement temporaire, en attendant l'introduction d'un vrai conteneur DI (point 3 du plan de travail) — sans impact prévu sur la signature des constructeurs générés.
- Une partie de l'étape 3 (infrastructure transverse) est avancée dès le socle initial : parsing JSON du corps de requête, erreurs 404/405/500 uniformément en JSON, CORS, Request-Id et logging PSR-3 (Monolog), regroupés dans `src/Core/Middleware/` car leur besoin est identique pour tout composant routé (Endpoint ou Domaine) — voir [src/Core/Middleware/README.md](../src/Core/Middleware/README.md). Le logger est construit une seule fois (`app/logger.php`) et partagé par toute l'application, y compris pour un usage métier explicite dans un Controller/Repository via `Psr\Log\LoggerInterface`.
- Une partie de l'étape 7 (profil Front) est également avancée dès le socle initial : un frontend Vue 3 + Vuetify 3 + `vue-router` (`frontend/`), séparé du backend (écosystème npm distinct de Composer), buildé vers `public/app` (servi par le backend sans étape de copie), avec un proxy dev Vite vers `/api`/`/rpc` pour éviter tout souci de CORS en développement — voir [frontend/README.md](../frontend/README.md). L'authentification Keycloak (point 8) n'y est pas encore intégrée.

## Plan de travail

### 1. Auditer et stabiliser le socle inspiré de `4.app`

- définir les versions PHP 8.3/Slim supportées et les conventions de namespace ;
- remplacer les exemples trop spécifiques par un squelette neutre ;
- structurer le bootstrap, la configuration par environnement, les erreurs et les réponses ;
- formaliser le cycle de requête et l'enregistrement des routes et middlewares.

### 2. Définir l'architecture modulaire et les profils

- distinguer clairement Core, Infrastructure, Application/Domain, REST, RPC, Web et Front ;
- choisir le mécanisme d'activation (configuration, packages Composer, commandes de scaffolding) sans introduire d'abstraction difficile à expliquer ;
- rendre chaque profil optionnel sans casser le profil REST minimal ;
- documenter les dépendances, les points d'extension et les raisons des choix d'architecture.

### 3. Mettre en place l'infrastructure transverse

- conteneur d'injection de dépendances ;
- configuration typée et gestion des secrets ;
- logging PSR-3 — **en place** : Monolog partagé, construit par `app/logger.php` (`LOG_PATH`, `LOG_LEVEL` dans `.env`), journalisation automatique de chaque requête par `RequestLoggingMiddleware` (niveau déduit du code de statut), et disponible pour tout Controller/Repository via `Psr\Log\LoggerInterface` injecté ;
- gestion d'erreurs et format d'API homogène — **partiellement en place** : handler JSON pour les erreurs 500 (déjà existant), 404 (`HttpNotFoundException`) et 405 (`HttpMethodNotAllowedException`), câblés dans `app/app.php` via `ErrorMiddleware::setErrorHandler()`, réutilisant `JsonResponse::write()` ;
- middleware, CORS, validation, sérialisation JSON et négociation de contenu cohérente — **partiellement en place** : parsing JSON du corps de requête (`addBodyParsingMiddleware()`), `CorsMiddleware` (en-têtes `Access-Control-Allow-*` + réponse directe aux préflights `OPTIONS`, configurable via `CORS_ALLOWED_ORIGINS`), `RequestIdMiddleware` (`X-Request-Id`) et `RequestLoggingMiddleware`, tous dans `src/Core/Middleware/` — voir [src/Core/Middleware/README.md](../src/Core/Middleware/README.md). Reste à faire : validation de payload et négociation de contenu (accept autre que JSON) ;
- préfixage centralisé des endpoints — **en place** : tous les endpoints REST sont exposés sous `/api`, tous les RPC sous `/rpc`, appliqué une seule fois par `app/app.php` (`registerActions()`) plutôt que codé en dur dans chaque action générée ; configurable via `API_REST_PREFIX`/`API_RPC_PREFIX` (`.env`) ;
- adaptateurs mail et export de fichiers.

### 4. Intégrer la persistance

- configuration Doctrine ORM — **socle déjà en place** : `EntityManager` construit dans `app/database.php`, mapping par attributs PHP 8 scanné sous `src/Application/*`, connexion MySQL/MariaDB configurée via les variables `DB_*` du `.env` ;
- conventions Entity/Repository — **socle déjà en place** : Entity = Model du domaine (`src/Application/DOMAIN_NAME/`), Repository = fichier partagé généré séparément (`src/Repositories/`, `composer make:repository`), voir [src/Repositories/README.md](../src/Repositories/README.md) ;
- migrations et commandes associées — **socle déjà en place** : Doctrine Migrations configuré via `cli-config.php` (racine), dossier `migrations/`, piloté par `composer doctrine -- <commande>` ;
- exemple de connexion et de modèle sans imposer un domaine métier — couvert par les générateurs `make:domain`/`make:repository`, qui ne présument d'aucun domaine métier réel ;
- gestion explicite des transactions et des erreurs de persistance — **reste à faire** : le Repository généré appelle `flush()` directement sans gestion de transaction explicite ni traduction des erreurs Doctrine en erreurs applicatives ; à affiner avec les premiers cas d'usage réels.

Limite connue : la validation de cette intégration a été faite jusqu'au niveau de la tentative de connexion réelle (DBAL), sans pouvoir vérifier une migration ou une requête aboutie faute de serveur MySQL disponible dans l'environnement de développement de ce socle. À revalider dès qu'un environnement avec MySQL est disponible.

### 5. Construire le profil REST

- génération de ressources, contrôleurs/handlers, DTO et validation ;
- routes et réponses JSON standardisées ;
- pagination, erreurs et authentification ;
- génération et maintenance de la documentation OpenAPI/Swagger — **en place** : chaque action REST (`composer make:rest`) porte par défaut un attribut `#[OpenApi\Attributes\Get(path: self::PATH, ...)]`, assemblé à la volée (`zircote/swagger-php`) et exposé via `GET /api/openapi.json` (spec brute) et `GET /api/openapi` (Swagger UI navigable, assets CDN) — voir [src/Core/OpenApi/README.md](../src/Core/OpenApi/README.md). Reste à faire : documenter les endpoints RPC et les Controllers de domaine si un besoin apparaît, et décider si ces routes doivent être désactivables en production.

### 6. Construire le profil RPC et les traitements

- contrat de service HTTP/RPC ;
- déclenchement de batch et traitements non interactifs ;
- conventions de mapping et d'intégration externes, avec JSON comme format par défaut ;
- observabilité, idempotence et restitution d'erreurs.

### 7. Construire le profil Web et Front

- rendu Twig et structure Controller/Service/Domain — **socle déjà en place** : templates par domaine, namespace Twig dédié, routage par attribut `#[Route]` (voir [src/Application/README.md](../src/Application/README.md)) ;
- ressources statiques optionnelles et mode serveur statique ;
- base Vue standardisée (authentification, layout, appels API, gestion d'erreurs) — **socle en place, sans authentification** : squelette Vue 3 + Vuetify 3 + `vue-router` sous `frontend/`, buildé vers `public/app`, proxy dev `/api`/`/rpc` vers le backend (voir [frontend/README.md](../frontend/README.md)). Reste à faire : cycle d'authentification (dépend du point 8), gestion d'erreurs centralisée, et une règle explicite `try_files` pour le SPA fallback en production (fonctionne "par défaut" seulement avec les serveurs de dev PHP/Vite) ;
- articulation claire entre session/jetons Keycloak, API et interface.

### 8. Intégrer Keycloak et la sécurité applicative

- configuration de l'issuer, client et audiences ;
- middleware d'authentification et contrôle des rôles/scopes ;
- comportement documenté pour les projets API, web et front ;
- absence de secrets dans le dépôt et stratégie de configuration locale/production.

### 9. Créer l'expérience de génération et de démarrage

- commandes `composer make:rest`, `composer make:domain`, `composer make:rpc`, `composer make:repository` et variantes nécessaires — **socle déjà en place** pour ces quatre types ;
- génération cohérente des fichiers, namespaces, routes et tests ;
- commandes de démarrage, migrations, documentation et vérification — **migrations déjà en place** via `composer doctrine -- <commande>` ;
- messages d'erreur actionnables, prévention des collisions et aide intégrée ;
- conventions identiques de nommage, options et sorties pour toutes les commandes `make:*`.

### 10. Valider et documenter

- tests unitaires et d'intégration des composants du socle ;
- tests des générateurs et d'un projet généré ;
- vérification des profils indépendamment puis combinés ;
- documentation systématique du code public et des composants non évidents ;
- README de démarrage rapide, guide d'architecture pragmatique, recettes par profil et conventions d'équipe ;
- documentation de chaque commande `composer make:*`, de ses options, de ses fichiers générés et d'un exemple d'usage ;
- exemple de projet démontrant un parcours réaliste sans logique métier imposée.

## Points à confirmer pendant la réalisation

- versions Node et outils supportées, avec PHP 8.3 comme prérequis ;
- choix précis du conteneur DI définitif (PHP-DI ou alternative), qui remplacera la résolution de dépendances par réflexion utilisée pour Twig, l'EntityManager et les Repositories ;
- stratégie de packaging du starter (template clonable, package Composer, ou les deux) ;
- éventuel support SOAP/XML ultérieur et niveau de support du serveur statique dans le premier incrément ;
- CI/CD et outils de qualité attendus ;
- disponibilité d'un serveur MySQL local pour la validation complète de la persistance (migrations + requêtes réelles), non disponible dans l'environnement de développement actuel ;
- désactivation possible des routes `/api/openapi` et `/api/openapi.json` en production (ex. variable `OPENAPI_ENABLED` ou gate sur `APP_DEBUG`), à trancher plus tard — non bloquant pour le socle actuel ;
- validation de la pile Docker (`docker compose up --build`) par une exécution réelle : **Docker n'est pas disponible dans l'environnement où ce socle a été écrit**. Le Dockerfile, docker-compose.yml et DOCKER.md ont été relus, le YAML validé syntaxiquement, mais aucun `docker compose up` n'a pu être exécuté ; à confirmer dès qu'un environnement avec Docker est disponible.

## Progression

Synthèse à date, pour une équipe qui reprendrait ce starter sans avoir suivi sa construction pas à pas.

### Réalisé

- **Socle Slim** (`app/app.php`, `public/index.php`) : bootstrap unique, chargement `.env`, DI par réflexion temporaire.
- **Trois natures de composants** distinguées et documentées : Endpoints (`src/Endpoints/Rest`, `src/Endpoints/Rpc` — fichier unique pour une ressource simple, répertoire avec classe de base + une classe par méthode HTTP pour une ressource à plusieurs opérations, Repository optionnel), Domaines (`src/Application/DOMAIN_NAME`, Controller multi-actions via `#[Route]` + templates Twig, consomme un ou plusieurs Repositories), Repositories (`src/Repositories/NOM/`, Model + Repository partagés, générés ensemble).
- **Préfixage centralisé** : tous les REST sous `/api`, tous les RPC sous `/rpc`, configurable (`API_REST_PREFIX`/`API_RPC_PREFIX`), sans jamais toucher un fichier généré.
- **Persistance Doctrine** : ORM + Migrations en place (mapping par attributs scanné sous `src/Repositories/*`, `EntityManager` partagé), ciblant MySQL/MariaDB, construit **uniquement si l'application compte au moins un Repository** (les variables `DB_*` du `.env` restent sinon inutiles). **Non validé avec un vrai serveur MySQL** (absent de l'environnement de développement, et l'extension PHP `pdo_mysql` n'y est pas installée) — validé jusqu'au niveau connexion/tentative uniquement.
- **Infrastructure transverse** : parsing JSON du corps de requête, erreurs 404/405/500 uniformément en JSON, CORS configurable, Request-Id (`X-Request-Id`), logging PSR-3 (Monolog) de chaque requête.
- **Documentation OpenAPI/Swagger** auto-générée pour les endpoints REST (`/api/openapi`, `/api/openapi.json`), toujours synchronisée avec le code via attributs PHP 8, une annotation par méthode HTTP, fusionnées automatiquement par chemin.
- **Frontend** Vue 3 + Vuetify 3 + `vue-router` (`frontend/`), buildé vers `public/app`, proxy dev vers le backend — sans authentification.
- **Générateurs** `composer make:rest|rpc|domain|repository`, un fichier de gabarit par type sous `scripts/generators/` (ordre : `repository` avant `domain`, le Controller référençant le Repository déjà généré).
- **Documentation pédagogique** : schémas d'anatomie de requête (Action, Doctrine) et explications au plus près du code (docblocks des classes de base, des gabarits générés) en plus des README par dossier.
- **Pile Docker minimale** (`Dockerfile`, `docker-compose.yml`) : backend PHP (serveur intégré), MariaDB, phpMyAdmin, développement à chaud par bind mount — voir [DOCKER.md](DOCKER.md). **Non exécutée réellement** (Docker absent de l'environnement de développement de ce socle) : YAML validé syntaxiquement et relu, mais pas de `docker compose up` effectif — à confirmer dès qu'un environnement Docker est disponible.

Chaque item ci-dessus a été **validé en conditions réelles** (lint PHP systématique, serveur HTTP local, requêtes effectives, et navigateur pour le frontend/Swagger UI) à l'exception explicite de la persistance MySQL et de la pile Docker, faute d'environnement disponible pour les deux. En particulier, la refonte Model/Repository/Endpoint (Model déplacé vers `src/Repositories/`, variables `DB_*` optionnelles, endpoint REST en répertoire une classe par méthode HTTP) a été vérifiée par un cycle complet `composer make:repository` → `composer make:rest` → `composer make:domain` sur une ressource jetable, un boot réel de l'application exposant ses routes `GET`/`POST`/`PUT`/`PATCH`/`DELETE`, une vérification de la spécification OpenAPI générée, et un boot réel **sans aucun Repository ni variable `DB_*`** pour confirmer le caractère optionnel de la persistance.

### Reste à faire

- **Conteneur d'injection de dépendances réel** (PHP-DI ou alternative) : remplace la résolution par réflexion actuelle, volontairement temporaire. C'est le chantier le plus structurant restant, car Keycloak et les futures commodités (mail, export) vont vouloir s'y injecter.
- **Authentification Keycloak** : rien n'existe encore (middleware, configuration issuer/client, articulation avec le frontend).
- **Tests automatisés** : aucun test unitaire ni d'intégration n'existe à ce jour. Toute la validation faite jusqu'ici est manuelle (lint + requêtes live), ce qui ne protège pas contre une régression silencieuse lors d'une prochaine modification.
- **Stratégies mail et export de fichiers** : mentionnées dans le besoin initial, pas encore conçues.
- **Validation de payload et négociation de contenu** (Accept autre que JSON).
- **Gestion transactionnelle explicite** des Repositories (actuellement un `flush()` par appel, sans regroupement ni traduction des erreurs Doctrine).
- **Règle `try_files`** pour le SPA fallback du frontend en production (Nginx/Apache) — fonctionne seulement "par accident" avec les serveurs de dev actuels.
- **Décisions ouvertes** listées dans « Points à confirmer » ci-dessus (packaging, CI/CD, SOAP/XML, toggle production d'OpenAPI...).

### Recommandations d'évolution

1. **Traiter le conteneur DI avant Keycloak.** L'authentification va typiquement s'injecter dans plusieurs couches (middleware, Controllers, endpoints) ; la poser sur la réflexion actuelle coûterait plus cher à migrer ensuite que de stabiliser d'abord un vrai conteneur.
2. **Introduire des tests automatisés dès maintenant**, avant que le code ne grossisse davantage. PHPUnit pour le backend (au minimum : un test par générateur, un test des middlewares transverses) ; possiblement Vitest pour le frontend. Le mode de validation actuel (manuel, à chaque requête utilisateur) ne passera pas à l'échelle sur plusieurs projets dérivés du starter.
3. **Mettre en place une CI légère** (GitHub Actions ou équivalent) exécutant `php -l`, les futurs tests PHPUnit, et `npm run build` du frontend à chaque changement — cohérent avec l'objectif « simplicité et commodité », mais actuellement absent : toute la vérification repose sur l'exécution manuelle documentée dans ce plan.
4. **Ajouter un outil d'analyse statique** (PHPStan ou Psalm, niveau modéré pour commencer) : cohérent avec l'exigence de code « explicable et compréhensible », et utile pour un starter destiné à être dupliqué par plusieurs équipes qui n'auront pas toutes le même niveau de rigueur spontanée.
5. **Trancher la stratégie de packaging tôt** (template clonable vs package Composer) : ce choix influence la façon dont les futures mises à jour du starter seront répercutées sur les projets déjà générés — plus coûteux à changer une fois plusieurs projets réels démarrés.
6. **Documenter un « chemin de mise à jour »** une fois 2-3 projets réels démarrés à partir de ce starter : comment un projet existant récupère les améliorations ultérieures du socle sans tout refondre (pertinent seulement une fois le mode de packaging choisi, point 5).
7. **Valider réellement la pile Docker** (`docker compose up --build`) dès qu'un environnement avec Docker est disponible, puis envisager un profil Docker de production distinct (image multi-stage sans bind mount, PHP-FPM + Nginx, secrets externalisés) — non couvert par la pile de développement actuelle, volontairement minimale (voir [DOCKER.md](DOCKER.md), section "Limites assumées").
