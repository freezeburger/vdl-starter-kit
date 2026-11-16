<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\User;

use App\Core\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PATCH /api/user/{id} : met à jour partiellement un utilisateur (nom
 * et/ou email, seuls les champs fournis sont modifiés). PATH personnalisé,
 * voir UserPutAction.
 */
final class UserPatchAction extends UserEndpoint
{
    public const PATH = parent::PATH . '/{id}';
    public const METHODS = ['PATCH'];

    #[OA\Patch(
        path: self::PATH,
        description: 'Met à jour partiellement un utilisateur (nom et/ou email).',
        tags: ['User'],
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur mis à jour'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
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

        $data = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $name = array_key_exists('name', $data) ? trim((string) $data['name']) : $user->getName();
        $email = array_key_exists('email', $data) ? strtolower(trim((string) $data['email'])) : $user->getEmail();
        $errors = $this->validate($name, $email, $user);

        if ($errors !== []) {
            return JsonResponse::write($response, ['errors' => $errors], 422);
        }

        $user->setName($name);
        $user->setEmail($email);
        $this->repository->save($user);

        return JsonResponse::write($response, ['user' => $user->toArray()]);
    }
}
