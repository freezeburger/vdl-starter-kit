<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\User;

use App\Core\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PUT /api/user/{id} : remplace intégralement un utilisateur (nom et
 * email requis). Exemple de route personnalisée par action : contrairement
 * à UserGetAction/UserPostAction (PATH hérité de UserEndpoint, '/user'),
 * cette action cible un élément précis et redéfinit son propre PATH.
 */
final class UserPutAction extends UserEndpoint
{
    public const PATH = parent::PATH . '/{id}';
    public const METHODS = ['PUT'];

    #[OA\Put(
        path: self::PATH,
        description: 'Remplace intégralement un utilisateur (nom et email).',
        tags: ['User'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur remplacé'),
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
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
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
