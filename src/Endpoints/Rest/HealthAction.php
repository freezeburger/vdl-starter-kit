<?php

declare(strict_types=1);

namespace App\Endpoints\Rest;

use App\Core\JsonResponse;
use App\Core\RouteAction;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Endpoint minimal permettant de vérifier que le service répond — sert
 * ici d'**exemple travaillé** de la convention Action (voir
 * [App\Core\RouteAction](../../Core/RouteAction.php) pour l'anatomie
 * complète d'une requête).
 *
 * Trois éléments à retenir, dans l'ordre où ils apparaissent ci-dessous :
 *
 * 1. `PATH` est relatif ('/health', pas '/api/health') : le préfixe /api
 *    est appliqué centralement par app/app.php, jamais ici.
 * 2. L'attribut `#[OA\Get]` documente la route dans /api/openapi — la
 *    documentation Swagger est donc toujours à jour avec le code, sans
 *    fichier séparé (voir src/Core/OpenApi/README.md).
 * 3. `__invoke()` est la seule méthode possible : une Action représente
 *    une unique opération, contrairement à un Controller de domaine.
 */
final class HealthAction extends RouteAction
{
    public const PATH = '/health';

    #[OA\Get(
        path: self::PATH,
        description: "Vérifie que le service répond, sans dépendance externe (base de données, cache...).",
        tags: ['Health'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service opérationnel',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'service', type: 'string', example: 'VDL Starter'),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        // Volontairement sans dépendance (pas de Repository ni de service
        // externe) : /api/health doit répondre même si la base de données
        // est indisponible, pour servir de sonde d'infrastructure fiable.
        return JsonResponse::write($response, [
            'status' => 'ok',
            'service' => $_ENV['APP_NAME'] ?? 'VDL Starter',
        ]);
    }
}
