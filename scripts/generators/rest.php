<?php

declare(strict_types=1);

/**
 * Générateur "rest" : crée une ressource REST sous forme de **répertoire**
 * src/Endpoints/Rest/{{Name}}/, plutôt qu'un fichier unique — une
 * ressource expose typiquement plusieurs opérations (une par méthode
 * HTTP), chacune auto-enregistrée par le bootstrap (voir
 * App\Core\RouteAction et registerActions() dans app/app.php).
 *
 * Produit :
 *   src/Endpoints/Rest/{{Name}}/{{Name}}Endpoint.php    classe de base abstraite
 *   src/Endpoints/Rest/{{Name}}/{{Name}}GetAction.php    GET    {{name}} (liste, filtres + pagination)
 *   src/Endpoints/Rest/{{Name}}/{{Name}}PostAction.php   POST   {{name}} (création)
 *   src/Endpoints/Rest/{{Name}}/{{Name}}PutAction.php    PUT    {{name}}/{id} (remplacement complet)
 *   src/Endpoints/Rest/{{Name}}/{{Name}}PatchAction.php  PATCH  {{name}}/{id} (mise à jour partielle)
 *   src/Endpoints/Rest/{{Name}}/{{Name}}DeleteAction.php DELETE {{name}}/{id} (suppression)
 *
 * - La classe de base ({{Name}}Endpoint) fixe le PATH par défaut de la
 *   ressource ; chaque action peut le personnaliser (voir PutAction,
 *   PatchAction, DeleteAction, qui ciblent '/{{name}}/{id}'). Les
 *   commodités de filtrage et de pagination (filters(), paginate()) sont
 *   héritées de App\Core\RouteAction — voir ce fichier pour le détail.
 * - L'usage d'un Repository est **optionnel** : aucune action générée
 *   n'en déclare un par défaut. Pour persister des données, ajouter un
 *   paramètre de constructeur typé vers le Repository voulu (généré par
 *   `composer make:repository -- {{Name}}`), résolu automatiquement par
 *   le mécanisme d'injection (voir src/Repositories/README.md).
 * - Retirer les actions non pertinentes pour la ressource (ex. pas de
 *   PATCH si seul un remplacement complet a du sens) ne casse rien :
 *   chaque fichier est indépendant.
 *
 * Usage : composer make:rest -- Product
 */

return [
    'files' => [
        [
            'path' => 'src/Endpoints/Rest/{{Name}}/{{Name}}Endpoint.php',
            'template' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\{{Name}};

use App\Core\RouteAction;

/**
 * Base commune des actions REST de la ressource {{Name}}.
 *
 * Ne représente aucune route par elle-même (classe abstraite, ignorée
 * par registerActions()) : elle ne fait que fixer le PATH par défaut de
 * la ressource et regrouper, par héritage, les commodités de filtrage et
 * de pagination de App\Core\RouteAction (filters(), paginate(),
 * routeArgument()).
 *
 * Chaque action concrète (voir {{Name}}GetAction, {{Name}}PostAction...)
 * peut redéfinir PATH/METHODS pour personnaliser sa propre route.
 */
abstract class {{Name}}Endpoint extends RouteAction
{
    public const PATH = '/{{name}}';
}
PHP,
        ],
        [
            'path' => 'src/Endpoints/Rest/{{Name}}/{{Name}}GetAction.php',
            'template' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\{{Name}};

use App\Core\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET {{name}} : liste la ressource {{Name}}, filtrée et paginée via les
 * commodités héritées de {{Name}}Endpoint (query string : page, perPage,
 * et tout autre paramètre traité comme filtre).
 *
 * Repository optionnel : ajoutez un constructeur typé vers
 * App\Repositories\{{Name}}\{{Name}}Repository (composer make:repository
 * -- {{Name}}) dès que cette action doit lire des données persistées ;
 * en son absence, elle reste un squelette fonctionnel sans dépendance.
 */
final class {{Name}}GetAction extends {{Name}}Endpoint
{
    public const METHODS = ['GET'];

    #[OA\Get(
        path: self::PATH,
        description: "Liste la ressource {{Name}}, avec filtres et pagination (page, perPage).",
        tags: ['{{Name}}'],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée de {{Name}}'),
        ]
    )]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $page = $this->paginate([], $request);

        return JsonResponse::write($response, [
            'resource' => '{{Name}}',
            'filters' => $this->filters($request),
            'data' => $page['data'],
            'meta' => $page['meta'],
        ]);
    }
}
PHP,
        ],
        [
            'path' => 'src/Endpoints/Rest/{{Name}}/{{Name}}PostAction.php',
            'template' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\{{Name}};

use App\Core\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST {{name}} : crée une ressource {{Name}}.
 *
 * Repository optionnel : voir {{Name}}GetAction.
 */
final class {{Name}}PostAction extends {{Name}}Endpoint
{
    public const METHODS = ['POST'];

    #[OA\Post(
        path: self::PATH,
        description: "Crée une ressource {{Name}}.",
        tags: ['{{Name}}'],
        responses: [
            new OA\Response(response: 201, description: '{{Name}} créé'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];

        return JsonResponse::write($response, [
            'resource' => '{{Name}}',
            'created' => $data,
        ], 201);
    }
}
PHP,
        ],
        [
            'path' => 'src/Endpoints/Rest/{{Name}}/{{Name}}PutAction.php',
            'template' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\{{Name}};

use App\Core\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PUT {{name}}/{id} : remplace intégralement une ressource {{Name}}.
 *
 * Exemple de route personnalisée par action : contrairement à
 * {{Name}}GetAction/{{Name}}PostAction (PATH hérité de {{Name}}Endpoint,
 * '/{{name}}'), cette action cible un élément précis et redéfinit son
 * propre PATH ('/{{name}}/{id}').
 *
 * Repository optionnel : voir {{Name}}GetAction.
 */
final class {{Name}}PutAction extends {{Name}}Endpoint
{
    public const PATH = parent::PATH . '/{id}';
    public const METHODS = ['PUT'];

    #[OA\Put(
        path: self::PATH,
        description: "Remplace intégralement une ressource {{Name}}.",
        tags: ['{{Name}}'],
        responses: [
            new OA\Response(response: 200, description: '{{Name}} remplacé'),
            new OA\Response(response: 404, description: '{{Name}} introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $id = $this->routeArgument($request, 'id');
        $data = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];

        return JsonResponse::write($response, [
            'resource' => '{{Name}}',
            'id' => $id,
            'replaced' => $data,
        ]);
    }
}
PHP,
        ],
        [
            'path' => 'src/Endpoints/Rest/{{Name}}/{{Name}}PatchAction.php',
            'template' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\{{Name}};

use App\Core\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PATCH {{name}}/{id} : met à jour partiellement une ressource {{Name}}
 * (PATH personnalisé, voir {{Name}}PutAction).
 *
 * Repository optionnel : voir {{Name}}GetAction.
 */
final class {{Name}}PatchAction extends {{Name}}Endpoint
{
    public const PATH = parent::PATH . '/{id}';
    public const METHODS = ['PATCH'];

    #[OA\Patch(
        path: self::PATH,
        description: "Met à jour partiellement une ressource {{Name}}.",
        tags: ['{{Name}}'],
        responses: [
            new OA\Response(response: 200, description: '{{Name}} mis à jour'),
            new OA\Response(response: 404, description: '{{Name}} introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $id = $this->routeArgument($request, 'id');
        $data = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];

        return JsonResponse::write($response, [
            'resource' => '{{Name}}',
            'id' => $id,
            'updated' => $data,
        ]);
    }
}
PHP,
        ],
        [
            'path' => 'src/Endpoints/Rest/{{Name}}/{{Name}}DeleteAction.php',
            'template' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\{{Name}};

use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * DELETE {{name}}/{id} : supprime une ressource {{Name}} (PATH
 * personnalisé, voir {{Name}}PutAction).
 *
 * Repository optionnel : voir {{Name}}GetAction.
 */
final class {{Name}}DeleteAction extends {{Name}}Endpoint
{
    public const PATH = parent::PATH . '/{id}';
    public const METHODS = ['DELETE'];

    #[OA\Delete(
        path: self::PATH,
        description: "Supprime une ressource {{Name}}.",
        tags: ['{{Name}}'],
        responses: [
            new OA\Response(response: 204, description: '{{Name}} supprimé'),
            new OA\Response(response: 404, description: '{{Name}} introuvable'),
        ]
    )]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        // 204 No Content : pas de corps de réponse, conformément à HTTP.
        return $response->withStatus(204);
    }
}
PHP,
        ],
    ],
];
