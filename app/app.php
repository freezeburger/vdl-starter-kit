<?php

declare(strict_types=1);

use App\Core\Attributes\Route;
use App\Core\JsonResponse;
use App\Core\Middleware\CorsMiddleware;
use App\Core\Middleware\RequestIdMiddleware;
use App\Core\Middleware\RequestLoggingMiddleware;
use App\Core\RouteAction;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/openapi.php';

loadEnvironment(dirname(__DIR__) . '/.env');

$logger = buildLogger(
    dirname(__DIR__) . '/' . ($_ENV['LOG_PATH'] ?? 'logs/app.log'),
    $_ENV['LOG_LEVEL'] ?? 'debug'
);

$app = AppFactory::create();
$app->addRoutingMiddleware();

/**
 * Ajoute un identifiant de corrélation (X-Request-Id) à chaque requête,
 * utile pour tracer un appel RPC/batch dans les journaux applicatifs.
 */
$app->add(new RequestIdMiddleware());

/**
 * Ajoute les en-têtes CORS. Ajouté après addRoutingMiddleware() afin
 * d'intercepter les requêtes OPTIONS de préflight avant la résolution de
 * route (convention standard de Slim pour le CORS). Les origines
 * autorisées se configurent via CORS_ALLOWED_ORIGINS (.env), séparées par
 * des virgules ; "*" par défaut en développement.
 */
$corsAllowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*')
)));
$app->add(new CorsMiddleware($app->getResponseFactory(), $corsAllowedOrigins));

/**
 * Décode automatiquement les corps JSON (et form-urlencoded) des requêtes
 * dans $request->getParsedBody() — nécessaire pour que les endpoints
 * REST/RPC lisent un payload JSON sans dupliquer un json_decode() partout.
 */
$app->addBodyParsingMiddleware();

$displayErrors = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL);
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);
$errorMiddleware->setDefaultErrorHandler(
    static function (
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ) use ($app): ResponseInterface {
        $response = $app->getResponseFactory()->createResponse(500);
        $message = $displayErrorDetails ? $exception->getMessage() : 'Internal Server Error';

        return JsonResponse::write($response, ['error' => $message], 500);
    }
);

/**
 * Une API JSON doit répondre en JSON même quand la route est introuvable
 * ou la méthode non supportée : sans ce câblage explicite, Slim renvoie sa
 * page d'erreur HTML par défaut pour ces deux cas très courants (typo de
 * route, mauvais verbe HTTP côté client).
 */
$errorMiddleware->setErrorHandler(
    HttpNotFoundException::class,
    static function (
        ServerRequestInterface $request,
        Throwable $exception
    ) use ($app): ResponseInterface {
        $response = $app->getResponseFactory()->createResponse(404);

        return JsonResponse::write($response, ['error' => $exception->getMessage()], 404);
    }
);
$errorMiddleware->setErrorHandler(
    HttpMethodNotAllowedException::class,
    static function (
        ServerRequestInterface $request,
        HttpMethodNotAllowedException $exception
    ) use ($app): ResponseInterface {
        $response = $app->getResponseFactory()->createResponse(405);

        return JsonResponse::write($response, [
            'error' => $exception->getMessage(),
            'allowedMethods' => $exception->getAllowedMethods(),
        ], 405);
    }
);

/**
 * Journalise chaque requête (méthode, chemin, statut, durée, request id).
 * Ajouté en tout dernier : c'est donc le middleware le plus englobant de
 * la pile, qui reçoit la réponse finale déjà normalisée par le middleware
 * d'erreurs (y compris les 404/405/500 JSON ci-dessus), sans avoir à
 * dupliquer de journalisation dans chaque handler.
 */
$app->add(new RequestLoggingMiddleware($logger));

/**
 * Auto-enregistre les actions REST et RPC : aucune route n'est déclarée
 * manuellement ici. Chaque classe qui étend RouteAction et déclare sa
 * constante PATH (relative) devient une route préfixée selon sa famille.
 *
 * Une ressource REST simple reste un fichier unique directement dans
 * $directory (ex. HealthAction.php). Une ressource qui expose plusieurs
 * opérations (une par méthode HTTP) devient en revanche un
 * **répertoire** $directory/NOM/ : une classe de base abstraite (filtres,
 * pagination — voir App\Core\RouteAction) et une classe concrète par
 * fichier (NOMGetAction, NOMPostAction...), chacune avec ses propres
 * PATH/METHODS. Les deux formes sont scannées et enregistrées de façon
 * identique ; la classe de base abstraite est ignorée (elle ne représente
 * aucune route par elle-même).
 *
 * @param string $prefix préfixe commun à toute la famille (ex. '/api'),
 *   appliqué une seule fois ici plutôt que dans chaque action générée :
 *   changer la convention de préfixage ne nécessite de toucher aucun
 *   fichier généré.
 * @param array<class-string, object> $shared dépendances partagées
 *   utilisées pour construire les Actions qui ont un constructeur.
 */
function registerActions(
    App $app,
    string $directory,
    string $namespace,
    string $prefix,
    array $shared
): void
{
    $files = array_merge(
        glob($directory . '/*.php') ?: [],
        glob($directory . '/*/*.php') ?: []
    );

    foreach ($files as $file) {
        $relativeClassPath = trim(substr($file, strlen($directory)), '/\\');
        $relativeClassPath = str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relativeClassPath);
        $className = $namespace . '\\' . $relativeClassPath;

        if (!class_exists($className) || !is_subclass_of($className, RouteAction::class)) {
            continue;
        }

        if ((new ReflectionClass($className))->isAbstract()) {
            // Classe de base d'une ressource en répertoire (ex. NOMEndpoint) :
            // ne représente aucune route par elle-même.
            continue;
        }

        $action = resolveDependency($className, $shared);
        if ($className::METHODS === []) {
            $app->any($prefix . $className::PATH, $action);
        } else {
            $app->map($className::METHODS, $prefix . $className::PATH, $action);
        }
    }
}

$restPrefix = $_ENV['API_REST_PREFIX'] ?? '/api';
$rpcPrefix = $_ENV['API_RPC_PREFIX'] ?? '/rpc';

/**
 * Documentation OpenAPI auto-générée à partir des attributs #[OA\Get...]
 * déclarés sur chaque endpoint de src/Endpoints/Rest (voir
 * src/Core/OpenApi/README.md) : toujours synchronisée avec le code, sans
 * fichier de spécification statique à maintenir en parallèle.
 *
 * - {prefix}/openapi.json : spécification brute, consommable par un
 *   outil externe (Postman, Insomnia...) ;
 * - {prefix}/openapi : interface Swagger UI navigable, qui charge la
 *   spécification ci-dessus.
 */
$app->get($restPrefix . '/openapi.json', function (
    ServerRequestInterface $request,
    ResponseInterface $response
) use ($restPrefix): ResponseInterface {
    $spec = buildOpenApiSpec(
        [
            dirname(__DIR__) . '/src/Endpoints/Rest',
            dirname(__DIR__) . '/src/Core/OpenApi',
        ],
        $restPrefix
    );

    return JsonResponse::write($response, $spec);
});

$app->get($restPrefix . '/openapi', function (
    ServerRequestInterface $request,
    ResponseInterface $response
) use ($restPrefix): ResponseInterface {
    $response->getBody()->write(buildSwaggerUiHtml($restPrefix . '/openapi.json'));

    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

/**
 * Construit le moteur Twig à partir des templates de chaque domaine.
 * Chaque src/Application/DOMAIN_NAME/templates devient un namespace Twig
 * `@DOMAIN_NAME`, ce qui évite toute collision entre domaines et rend
 * explicite l'origine d'un template (ex. `@Product/product.html.twig`).
 */
function buildTwig(string $applicationDirectory): Twig
{
    $paths = [];
    foreach (glob($applicationDirectory . '/*/templates', GLOB_ONLYDIR) as $templatesDirectory) {
        $domain = basename(dirname($templatesDirectory));
        $paths[$domain] = $templatesDirectory;
    }

    return Twig::create($paths, ['cache' => false]);
}

$twig = buildTwig(dirname(__DIR__) . '/src/Application');
$app->add(TwigMiddleware::create($app, $twig));

/**
 * L'EntityManager (et donc les variables DB_* du .env) ne sont requis que
 * si l'application compte au moins un Repository généré (src/Repositories/
 * NOM/NOMRepository.php) : un profil sans persistance (RPC pur, proxy...)
 * ne doit pas avoir à configurer de base de données pour démarrer.
 */
$repositoriesDirectory = dirname(__DIR__) . '/src/Repositories';
$entityManager = repositoriesExist($repositoriesDirectory)
    ? buildEntityManager($repositoriesDirectory, filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL))
    : null;

/**
 * Résout récursivement les dépendances du constructeur d'une classe par
 * réflexion. Solution volontairement simple et temporaire : elle sera
 * remplacée par le conteneur DI prévu dans une étape ultérieure du
 * starter, sans changer la signature des Controllers générés.
 *
 * @param array<class-string, object> $shared instances déjà construites,
 *   indexées par nom de classe (ex. Twig::class => $twig), réutilisées
 *   telles quelles au lieu d'être reconstruites.
 */
function resolveDependency(string $className, array $shared): object
{
    if (isset($shared[$className])) {
        return $shared[$className];
    }

    $reflection = new ReflectionClass($className);
    $constructor = $reflection->getConstructor();

    if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
        return new $className();
    }

    $arguments = [];
    foreach ($constructor->getParameters() as $parameter) {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            throw new RuntimeException(
                "Cannot autowire parameter '{$parameter->getName()}' of {$className}: " .
                'only typed class dependencies are supported by this temporary resolver.'
            );
        }

        $arguments[] = resolveDependency($type->getName(), $shared);
    }

    return $reflection->newInstanceArgs($arguments);
}

/**
 * Auto-enregistre les Controllers de domaine. Contrairement aux endpoints
 * REST/RPC (une classe = une route), un Controller de domaine est
 * multi-actions : chaque méthode publique annotée #[Route('METHOD', path)]
 * devient une route indépendante. Les classes sont recherchées sous
 * src/Application/DOMAIN_NAME/DOMAIN_NAMEController.php.
 *
 * @param array<class-string, object> $shared voir resolveDependency().
 */
function registerControllers(App $app, string $applicationDirectory, string $namespace, array $shared): void
{
    foreach (glob($applicationDirectory . '/*/*Controller.php') as $file) {
        $domain = basename(dirname($file));
        $className = $namespace . '\\' . $domain . '\\' . pathinfo($file, PATHINFO_FILENAME);

        if (!class_exists($className)) {
            continue;
        }

        $reflection = new ReflectionClass($className);
        $routedMethods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Route::class);
            if ($attributes !== []) {
                $routedMethods[] = [$method, $attributes];
            }
        }

        if ($routedMethods === []) {
            continue;
        }

        $controller = resolveDependency($className, $shared);

        foreach ($routedMethods as [$method, $attributes]) {
            foreach ($attributes as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();
                $app->map([$route->method], $route->path, [$controller, $method->getName()]);
            }
        }
    }
}

$sharedDependencies = [
    Twig::class => $twig,
    LoggerInterface::class => $logger,
];

if ($entityManager !== null) {
    $sharedDependencies[EntityManager::class] = $entityManager;
}

registerActions(
    $app,
    dirname(__DIR__) . '/src/Endpoints/Rest',
    'App\\Endpoints\\Rest',
    $restPrefix,
    $sharedDependencies
);
registerActions(
    $app,
    dirname(__DIR__) . '/src/Endpoints/Rpc',
    'App\\Endpoints\\Rpc',
    $rpcPrefix,
    $sharedDependencies
);

registerControllers(
    $app,
    dirname(__DIR__) . '/src/Application',
    'App\\Application',
    $sharedDependencies
);

return $app;
