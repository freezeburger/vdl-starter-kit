<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\User;

use App\Core\JsonResponse;
use App\Repositories\User\UserModel;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/user : liste les utilisateurs, paginés (page, perPage) ou
 * filtrés par email (?email=...) grâce aux commodités héritées de
 * UserEndpoint/RouteAction.
 */
final class UserGetAction extends UserEndpoint
{
    public const METHODS = ['GET'];

    #[OA\Get(
        path: self::PATH,
        description: 'Liste les utilisateurs, avec filtre par email (?email=...) et pagination (page, perPage).',
        tags: ['User'],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée des utilisateurs'),
        ]
    )]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $filters = $this->filters($request);

        if (isset($filters['email'])) {
            $user = $this->repository->findByEmail($filters['email']);

            return JsonResponse::write($response, [
                'data' => $user !== null ? [$user->toArray()] : [],
                'meta' => ['page' => 1, 'perPage' => 1, 'total' => $user !== null ? 1 : 0],
            ]);
        }

        $page = $this->paginate(
            array_map(static fn (UserModel $user): array => $user->toArray(), $this->repository->all()),
            $request
        );

        return JsonResponse::write($response, $page);
    }
}
