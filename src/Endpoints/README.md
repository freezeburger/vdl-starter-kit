# Endpoints (Rest / Rpc)

Un endpoint est soit un **fichier unique**, soit un **répertoire**, selon le nombre d'opérations qu'il expose :

- `src/Endpoints/Rest/` : ressources REST, exposées sous `/api`.
  - Une ressource simple (une seule opération) reste un fichier unique (ex. `HealthAction.php`).
  - Une ressource qui expose plusieurs opérations (typiquement une par méthode HTTP) devient un **répertoire** `src/Endpoints/Rest/NOM/` (voir « Ressource REST en répertoire » ci-dessous).
- `src/Endpoints/Rpc/` : opérations RPC/batch, une classe par opération (fichier unique), exposées sous `/rpc`.

Chaque classe concrète étend `App\Core\RouteAction` et déclare sa route via la constante `PATH` (et, si besoin, `METHODS`). Le bootstrap (`app/app.php`) scanne ces dossiers (fichiers directement dedans, et fichiers d'un sous-répertoire) et enregistre automatiquement chaque classe non abstraite trouvée : aucune route n'est déclarée à la main.

## Ressource REST en répertoire

Dès qu'une ressource REST expose plusieurs opérations (lister, créer, remplacer, mettre à jour, supprimer...), elle devient un répertoire `src/Endpoints/Rest/NOM/` :

```text
src/Endpoints/Rest/Product/
  ProductEndpoint.php       classe de base abstraite (PATH par défaut, filtres + pagination hérités)
  ProductGetAction.php      GET    /product        (liste, filtres + pagination)
  ProductPostAction.php     POST   /product        (création)
  ProductPutAction.php      PUT    /product/{id}   (remplacement complet)
  ProductPatchAction.php    PATCH  /product/{id}   (mise à jour partielle)
  ProductDeleteAction.php   DELETE /product/{id}   (suppression)
```

- **`NOMEndpoint`** est une classe abstraite qui étend `RouteAction` : elle ne représente aucune route par elle-même (`registerActions()` ignore les classes abstraites), mais fixe le `PATH` par défaut de la ressource et donne accès, par héritage de `App\Core\RouteAction`, à `filters()` (filtres de query string) et `paginate()` (pagination `page`/`perPage`) — voir [src/Core/RouteAction.php](../Core/RouteAction.php).
- **Chaque action concrète** peut redéfinir `PATH`/`METHODS` pour personnaliser sa propre route : `ProductPutAction`/`ProductPatchAction`/`ProductDeleteAction` ciblent `/product/{id}` (`public const PATH = parent::PATH . '/{id}';`) tandis que `ProductGetAction`/`ProductPostAction` gardent le `PATH` hérité (`/product`). Un identifiant de route (`{id}`) se lit via `$this->routeArgument($request, 'id')`.
- **L'usage d'un Repository est optionnel** : aucune action générée par `composer make:rest` n'en déclare par défaut. Pour persister des données, ajouter un paramètre de constructeur typé vers le Repository voulu (généré par `composer make:repository`, voir [src/Repositories/README.md](../Repositories/README.md)) — soit sur `NOMEndpoint` (partagé par toutes les actions, comme dans l'exemple `src/Endpoints/Rest/User/`), soit sur une seule action concrète si les autres n'en ont pas besoin.
- Retirer une action non pertinente pour la ressource (ex. pas de `PATCH` si seul un remplacement complet a du sens) est sans risque : chaque fichier est indépendant.

Exemple concret et complet : [src/Endpoints/Rest/User/](Rest/User) (`UserEndpoint` déclare un `UserRepository` partagé par toutes ses actions).

## Anatomie d'une requête Action

```text
Client HTTP
   │  GET /api/product
   ▼
app/app.php : registerActions()
   │  scanne src/Endpoints/Rest (fichiers + sous-répertoires), trouve ProductGetAction,
   │  résout PATH ('/product') + préfixe de famille ('/api')
   ▼
Slim Router
   │  route trouvée → ProductGetAction
   ▼
ProductGetAction::__invoke($request, $response)
   │  délègue si besoin à un Repository (src/Repositories/), optionnel
   ▼
JsonResponse::write(...)
   │  réponse JSON normalisée (Content-Type, statut HTTP)
   ▼
Client HTTP
```

Exemple concret annoté : [src/Endpoints/Rest/HealthAction.php](Rest/HealthAction.php) (voir aussi [src/Core/RouteAction.php](../Core/RouteAction.php) pour le détail de chaque étape, fichier unique ou répertoire).

## Rest vs Rpc : même mécanique, deux usages différents

| | REST (`src/Endpoints/Rest/`) | RPC (`src/Endpoints/Rpc/`) |
|---|---|---|
| Représente | une ressource (`/product`) | une opération (`/import`) |
| Forme | fichier unique (simple) ou répertoire (plusieurs opérations) | toujours un fichier unique |
| Préfixe | `/api` (`API_REST_PREFIX`) | `/rpc` (`API_RPC_PREFIX`) |
| Documentation Swagger | oui, auto-générée (`#[OA\Get]`, `#[OA\Post]`...) | non (une commande n'est pas une ressource) |
| Générateur | `composer make:rest -- Product` | `composer make:rpc -- Import` |

## Préfixes appliqués centralement

`PATH` est **relatif à sa famille** (ex. `/product`, jamais `/api/product`) : le préfixe commun est appliqué une seule fois par `app/app.php` (fonction `registerActions()`), pas par chaque action générée. Ainsi, changer la convention de préfixage (`API_REST_PREFIX`, `API_RPC_PREFIX` dans `.env`, `/api` et `/rpc` par défaut) ne nécessite de toucher aucun fichier généré.

Génération :

```bash
composer make:rest -- Product   # src/Endpoints/Rest/Product/, routes /api/product (+ /api/product/{id})
composer make:rpc -- Import     # src/Endpoints/Rpc/ImportAction.php, route /rpc/import
```

Un endpoint ne porte pas de logique métier : il délègue à un Repository (`src/Repositories/`) dès que le traitement dépasse la simple mise en forme de la réponse.

## Auto-documentation OpenAPI

Chaque action REST générée porte par défaut un attribut `#[OpenApi\Attributes\Get(path: self::PATH, ...)]` (ou `Post`/`Put`/`Patch`/`Delete` selon la méthode) sur sa méthode `__invoke()` : la documentation Swagger (`/api/openapi`) reste ainsi toujours à jour avec le code, sans fichier de spécification séparé à maintenir. Le builder OpenAPI fusionne automatiquement les opérations qui partagent le même chemin (ex. `GET`/`POST` sur `/api/product`, `PUT`/`PATCH`/`DELETE` sur `/api/product/{id}`) en une seule entrée de la spécification. Voir [src/Core/OpenApi/README.md](../Core/OpenApi/README.md) pour la convention détaillée et comment enrichir la documentation d'un endpoint (paramètres, corps de requête, schémas...).

## Pourquoi un fichier unique ou un répertoire, mais toujours plusieurs classes plutôt qu'un registre séparé ?

Un endpoint REST ou RPC n'a pas la même forme qu'un Controller de domaine (une méthode par route via `#[Route]`, voir [src/Application/README.md](../Application/README.md) pour la convention symétrique). Ce choix n'est pas accidentel : chaque opération d'une ressource REST (lister, créer, remplacer, mettre à jour, supprimer) a des besoins propres (filtres différents, corps de requête différent, code de statut différent) qu'une classe dédiée exprime plus clairement qu'une méthode partagée — tout en gardant, via la classe de base `NOMEndpoint`, un seul endroit où personnaliser `PATH` ou ajouter un Repository commun.
