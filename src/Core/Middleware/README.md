# src/Core/Middleware

Middlewares PSR-15 transverses, appliqués à **toutes** les routes (Endpoints REST/RPC et Domaines confondus), câblés une seule fois dans `app/app.php`.

## Pourquoi ici et pas un dossier dédié par convention (REST/RPC) ?

Le besoin est strictement identique quelle que soit la nature du composant appelé : une API JSON doit répondre en JSON, gérer le CORS, et être traçable, que la route vienne d'un Endpoint fichier-unique ou d'une action de domaine multi-route. Dupliquer ces middlewares par convention ajouterait de la complexité sans bénéfice : ils vivent donc dans `src/Core/`, aux côtés de `JsonResponse` et `RouteAction`.

## Middlewares disponibles

### `CorsMiddleware`

Ajoute les en-têtes `Access-Control-Allow-*` sur toutes les réponses et répond directement aux requêtes `OPTIONS` de préflight (204, sans passer par le routage — évite un faux 404/405 sur une route qui ne déclare pas explicitement `OPTIONS`).

Origines autorisées configurables via `CORS_ALLOWED_ORIGINS` (`.env`), liste séparée par des virgules, `*` par défaut (développement). À restreindre en production.

### `RequestIdMiddleware`

Garantit qu'une requête possède un identifiant de corrélation `X-Request-Id` : reprend celui fourni par le client s'il est présent, en génère un sinon (`bin2hex(random_bytes(16))`). Restitué en en-tête de réponse et en attribut de requête (`$request->getAttribute(RequestIdMiddleware::ATTRIBUTE)`), exploitable par le logging.

### `RequestLoggingMiddleware`

Journalise chaque requête traitée (méthode, chemin, statut, durée en millisecondes, request id) via un logger PSR-3 (Monolog, construit par `app/logger.php`). Le niveau de log est déduit du code de statut de la réponse finale : `info` en dessous de 400, `warning` à partir de 400 (404/405...), `error` à partir de 500. Une exception non interceptée par un handler d'erreur est également journalisée (niveau `error`) avant d'être re-levée, pour laisser `ErrorMiddleware` produire la réponse JSON habituelle.

Volontairement unique et générique plutôt que dupliqué par handler d'erreur : comme il est le middleware le plus englobant de la pile (ajouté en tout dernier), il reçoit systématiquement la réponse finale déjà normalisée en JSON par les handlers 404/405/500, sans avoir à journaliser depuis chacun d'eux.

Le fichier de sortie (`LOG_PATH`, défaut `logs/app.log`) et le niveau minimal journalisé (`LOG_LEVEL`, défaut `debug`) se configurent via `.env`. Tout Controller de domaine ou Repository peut aussi typer `Psr\Log\LoggerInterface` dans son constructeur pour journaliser un événement métier propre — résolu par le même mécanisme de réflexion temporaire que `Twig` et `EntityManager`.

## Positionnement dans le bootstrap

L'ordre d'exécution de Slim est LIFO : le **dernier** `$app->add(...)` appelé est le **premier** exécuté. Dans `app/app.php`, l'ordre est :

1. `addRoutingMiddleware()`
2. `RequestIdMiddleware`
3. `CorsMiddleware`
4. `addBodyParsingMiddleware()`
5. `addErrorMiddleware()` (avec les handlers JSON 404/405/500)
6. `RequestLoggingMiddleware`

`CorsMiddleware` est ajouté après `addRoutingMiddleware()` afin de s'exécuter **avant** lui, ce qui lui permet d'intercepter un `OPTIONS` de préflight avant que le routage ne le traite comme une méthode potentiellement non supportée — convention standard de Slim pour le CORS.

`RequestLoggingMiddleware` est ajouté **en tout dernier** : c'est donc le middleware le plus englobant de tous, qui reçoit la réponse déjà transformée par `ErrorMiddleware` (y compris pour une exception 500 ou un 404/405), quelle que soit l'origine de l'issue de la requête.

## Limite connue : `requestId` absent sur certaines réponses d'erreur

`RequestIdMiddleware` n'ajoute l'en-tête `X-Request-Id` à la réponse qu'après l'exécution normale du reste de la pile (`$response = $handler->handle($request); return $response->withHeader(...)`). Or une exception 404/405/500 levée plus loin dans la pile (routage, Controller) fait sauter cette ligne : la réponse JSON produite par `ErrorMiddleware` ne porte alors pas cet en-tête, et `requestId` apparaît à `null` dans le journal pour ces cas. Ce n'est pas bloquant (le message d'erreur et le statut restent journalisés), mais reste à corriger si une corrélation systématique devient nécessaire — par exemple en faisant porter l'en-tête par les handlers d'erreur eux-mêmes plutôt que par `RequestIdMiddleware`.

## Erreurs JSON (404 / 405)

Sans câblage explicite, Slim répond en HTML pour une route introuvable ou une méthode non supportée. `app/app.php` enregistre des handlers dédiés via `$errorMiddleware->setErrorHandler(...)` pour `Slim\Exception\HttpNotFoundException` et `Slim\Exception\HttpMethodNotAllowedException`, réutilisant `JsonResponse::write()` comme le handler 500 par défaut, pour qu'une API JSON reste JSON en toute circonstance.
