<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\User;

use App\Core\JsonResponse;
use App\Repositories\User\UserModel;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /api/user : crée un utilisateur.
 */
final class UserPostAction extends UserEndpoint
{
    public const METHODS = ['POST'];

    #[OA\Post(
        path: self::PATH,
        description: 'Crée un utilisateur.',
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
            new OA\Response(response: 201, description: 'Utilisateur créé'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ],
    )]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $user = new UserModel();
        $errors = $this->validate($name, $email, $user);

        if ($errors !== []) {
            return JsonResponse::write($response, ['errors' => $errors], 422);
        }

        $user->setName($name);
        $user->setEmail($email);
        $this->repository->save($user);

        return JsonResponse::write($response, ['user' => $user->toArray()], 201);
    }
}
