# src/Core/OpenApi

Documentation OpenAPI auto-générée pour les endpoints REST (`src/Endpoints/Rest`), exposée sous `{API_REST_PREFIX}/openapi` (défaut `/api/openapi`).

## Pourquoi ici et pas un profil séparé ?

La documentation d'une API REST est une commodité transverse : elle ne porte aucune logique métier, ne dépend d'aucun domaine particulier, et doit rester **toujours synchronisée avec le code** plutôt que maintenue à la main dans un fichier YAML/JSON séparé qui se désynchronise avec le temps. C'est pourquoi elle est générée à la volée, à chaque requête, à partir des attributs PHP 8 `#[OpenApi\Attributes\...]` déjà présents sur les classes d'endpoints — aucune étape de build ni de fichier à committer.

## `ApiDocumentation.php`

Porte uniquement les métadonnées racine de la spécification (`#[OA\Info(title: ..., version: ..., description: ...)]`). Cette classe ne contient aucun code : elle existe pour que le scan par attributs (`zircote/swagger-php`) ait un point d'ancrage pour ces métadonnées, indépendamment de l'existence ou non d'un endpoint particulier.

## Deux routes

- **`{prefix}/openapi.json`** : la spécification OpenAPI brute (JSON), consommable par un outil externe (Postman, Insomnia, générateur de client...).
- **`{prefix}/openapi`** : une interface Swagger UI navigable dans le navigateur, qui charge la spécification ci-dessus. Les assets Swagger UI sont chargés depuis un CDN (`jsdelivr`) plutôt que packagés en local, pour éviter d'ajouter une chaîne d'outillage front (npm, bundler) à ce starter pour une simple page de documentation.

Ces deux routes sont câblées directement dans `app/app.php` (fonction `buildOpenApiSpec()` et `buildSwaggerUiHtml()` de `app/openapi.php`), et non via la convention `RouteAction`/`PATH` des endpoints : elles ne représentent pas une ressource métier, mais une commodité de génération.

## Documenter un endpoint REST

`composer make:rest` génère par défaut une ressource déjà annotée, une classe par méthode HTTP (voir [src/Endpoints/README.md](../../Endpoints/README.md)) :

```php
#[OA\Get(
    path: self::PATH,
    description: "Liste la ressource {{Name}}, avec filtres et pagination (page, perPage).",
    tags: ['{{Name}}'],
    responses: [
        new OA\Response(response: 200, description: 'Liste paginée de {{Name}}'),
    ]
)]
public function __invoke(...): ResponseInterface
```

Chaque action (`{{Name}}GetAction`, `{{Name}}PostAction`, `{{Name}}PutAction`, `{{Name}}PatchAction`, `{{Name}}DeleteAction`) porte l'attribut OpenAPI correspondant à sa méthode (`#[OA\Get]`, `#[OA\Post]`, `#[OA\Put]`, `#[OA\Patch]`, `#[OA\Delete]`). Le `path` référence `self::PATH` (le même chemin **relatif** que celui utilisé pour l'enregistrement de la route) : le builder OpenAPI applique le même préfixe (`API_REST_PREFIX`) que `registerActions()`, et fusionne automatiquement les opérations qui partagent le même chemin (ex. `GET`/`POST` sur `/api/{{name}}`, `PUT`/`PATCH`/`DELETE` sur `/api/{{name}}/{id}`) en une seule entrée de la spécification.

À adapter selon l'opération réelle : description, tags, paramètres et forme de la réponse. Voir la [documentation de swagger-php](https://zircote.github.io/swagger-php/guide/using-attributes.html) pour l'ensemble des attributs disponibles (paramètres de requête, corps de requête, schémas...).

## Limite volontaire : REST uniquement

Seul `src/Endpoints/Rest/` est scanné. Les endpoints RPC (`src/Endpoints/Rpc/`) et les Controllers de domaine (`src/Application/`) n'apparaissent pas dans cette documentation : OpenAPI décrit une API de ressources typée par méthodes HTTP, ce qui correspond à la convention REST du starter, pas aux opérations RPC ni aux pages Web rendues par Twig.
