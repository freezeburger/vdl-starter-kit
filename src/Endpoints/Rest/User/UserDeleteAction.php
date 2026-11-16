<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\User;

use App\Core\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * DELETE /api/user/{id} : supprime un utilisateur. PATH personnalisé,
 * voir UserPutAction.
 */
final class UserDeleteAction extends UserEndpoint
{
    public const PATH = parent::PATH . '/{id}';
    public const METHODS = ['DELETE'];

    #[OA\Delete(
        path: self::PATH,
        description: 'Supprime un utilisateur.',
        tags: ['User'],
        responses: [
            new OA\Response(response: 204, description: 'Utilisateur supprimé'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ],
    )]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $id = filter_var($this->routeArgument($request, 'id'), FILTER_VALIDATE_INT);
        $user = $id !== false && $id !== null ? $this->repository->find($id) : null;

        if ($user === null) {
            return JsonResponse::write($response, ['error' => 'User not found.'], 404);
        }

        $this->repository->remove($user);

        // 204 No Content : pas de corps de réponse, conformément à HTTP.
        return $response->withStatus(204);
    }
}
