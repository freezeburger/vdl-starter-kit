<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/**
 * Base commune de toute **Action** (endpoint REST ou RPC) auto-découverte
 * par le bootstrap.
 *
 * ## Qu'est-ce qu'une Action ?
 *
 * Une Action représente **une seule opération HTTP**, contrairement à un
 * Controller de domaine (`src/Application/DOMAIN_NAME/`) qui est
 * naturellement multi-actions. Une classe = une route = un fichier :
 * pas de sous-dossier, pas de registre de routes séparé à maintenir.
 *
 * ## Anatomie d'une requête traitée par une Action
 *
 * ```text
 * Client HTTP
 *    │  GET /api/product
 *    ▼
 * app/app.php : registerActions()
 *    │  scanne src/Endpoints/Rest (ou Rpc), trouve ProductAction,
 *    │  applique le préfixe de sa famille : /api + PATH ('/product')
 *    ▼
 * Slim Router
 *    │  résout la route vers ProductAction
 *    ▼
 * ProductAction::__invoke($request, $response)
 *    │  logique de l'opération (ex. délègue à un Repository)
 *    ▼
 * JsonResponse::write($response, [...])
 *    │  réponse JSON normalisée (Content-Type, statut)
 *    ▼
 * Client HTTP
 * ```
 *
 * ## Ce qu'une Action doit savoir — et ne doit jamais savoir
 *
 * - Elle déclare **où** elle vit (sa constante `PATH`, relative à sa
 *   famille : `/product`, jamais `/api/product`).
 * - Elle ne connaît **jamais** son préfixe final (`/api`, `/rpc`) : celui-ci
 *   est appliqué une seule fois, centralement, par `app/app.php`
 *   (configurable via `API_REST_PREFIX` / `API_RPC_PREFIX` dans `.env`).
 *   Ainsi, changer la convention de préfixage de toute l'application ne
 *   nécessite de toucher aucune Action déjà générée.
 * - Elle ne porte **pas** de logique métier : une Action REST/RPC délègue
 *   au domaine correspondant (`src/Application/`) ou à un Repository
 *   partagé (`src/Repositories/`) dès que le traitement dépasse la simple
 *   mise en forme de la réponse. L'usage d'un Repository reste
 *   **optionnel** : rien dans cette classe ni dans le mécanisme
 *   d'enregistrement (`registerActions()`) n'impose une dépendance de
 *   persistance — une Action sans constructeur, ou avec un constructeur
 *   qui n'en déclare aucune, est parfaitement valide.
 *
 * ## Fichier unique ou répertoire ?
 *
 * Une ressource qui n'expose qu'une seule opération reste un **fichier
 * unique** (ex. `src/Endpoints/Rest/HealthAction.php`). Une ressource qui
 * expose plusieurs opérations (typiquement une par méthode HTTP : GET,
 * POST, PUT, PATCH, DELETE) devient un **répertoire**
 * `src/Endpoints/Rest/NOM/` contenant :
 *
 * - une classe de base abstraite `NOMEndpoint` (étend `RouteAction`,
 *   fixe `PATH`) : elle ne représente aucune route par elle-même
 *   (`registerActions()` ignore les classes abstraites) ;
 * - une classe concrète par méthode (`NOMGetAction`, `NOMPostAction`...),
 *   chacune avec ses propres `PATH`/`METHODS` — ce qui permet de
 *   personnaliser la route d'une opération précise (ex. `PUT`/`PATCH`/
 *   `DELETE` sur `/nom/{id}`, `GET`/`POST` sur `/nom`).
 *
 * Les deux formes partagent les mêmes commodités de filtrage et de
 * pagination ci-dessous, utilisables par toute Action REST qui liste une
 * collection.
 *
 * Voir un exemple concret annoté dans
 * [src/Endpoints/Rest/HealthAction.php](../Endpoints/Rest/HealthAction.php)
 * (fichier unique) et [src/Endpoints/Rest/User/](../Endpoints/Rest/User)
 * (répertoire), et la convention détaillée dans
 * [src/Endpoints/README.md](../Endpoints/README.md).
 */
abstract class RouteAction
{
    /**
     * Chemin de la route relatif au préfixe de sa famille (ex. '/product'
     * pour un endpoint REST, résolu en '/api/product'), à redéfinir dans
     * chaque action concrète.
     */
    public const PATH = '';

    /**
     * Verbes HTTP acceptés. Une liste vide conserve le comportement
     * historique et autorise tous les verbes.
     *
     * @var list<string>
     */
    public const METHODS = [];

    abstract public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface;

    /**
     * Filtres de requête (query string), en excluant les paramètres
     * réservés à la pagination ('page', 'perPage'). Commodité partagée
     * par toute Action REST qui liste une collection.
     *
     * @return array<string, string>
     */
    protected function filters(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        unset($query['page'], $query['perPage']);

        return array_map('strval', $query);
    }

    /**
     * Découpe une collection selon les paramètres de pagination de la
     * requête ('page', 'perPage' ; défaut 1 et 20) et retourne la page
     * demandée accompagnée d'une méta-donnée de pagination.
     *
     * @param list<mixed> $items
     * @return array{data: list<mixed>, meta: array{page: int, perPage: int, total: int}}
     */
    protected function paginate(array $items, ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, (int) ($query['perPage'] ?? 20));
        $total = count($items);

        return [
            'data' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total],
        ];
    }

    /**
     * Lit un paramètre de route (ex. '{id}' dans '/nom/{id}'), résolu par
     * Slim après le matching de la route. Utilisé par les actions sur un
     * identifiant (GET d'un élément, PUT/PATCH/DELETE).
     */
    protected function routeArgument(ServerRequestInterface $request, string $name): ?string
    {
        return RouteContext::fromRequest($request)->getRoute()?->getArgument($name);
    }
}
