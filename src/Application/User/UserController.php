<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Core\Attributes\Route;
use App\Repositories\User\UserModel;
use App\Repositories\User\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * Contrôleur du domaine User.
 * Orchestre la requête : lit via UserRepository (src/Repositories/User/,
 * généré par `composer make:repository -- User`), puis restitue la
 * vue via Twig. Le Model (UserModel) vit à côté du Repository, pas ici :
 * ce Controller ne fait qu'en consommer des instances, et pourrait tout
 * aussi bien injecter plusieurs Repositories si son orchestration en
 * avait besoin (le mécanisme d'injection résout chaque paramètre typé du
 * constructeur indépendamment — voir src/Application/README.md).
 *
 * Chaque méthode publique annotée #[Route] devient une route indépendante,
 * auto-enregistrée par le bootstrap (contrairement aux endpoints REST/RPC,
 * un Controller de domaine est naturellement multi-actions : index, show,
 * store, update, destroy...).
 */
final class UserController
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly Twig $view,
    ) {
    }

    #[Route('GET', '/user')]
    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $query = $request->getQueryParams();
        $selected = isset($query['id']) ? $this->repository->find((int) $query['id']) : null;

        return $this->view->render($response, '@User/user.html.twig', [
            'items' => $this->repository->all(),
            'selected' => $selected,
            'errors' => [],
        ]);
    }

    #[Route('POST', '/user')]
    public function save(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $user = $id !== false && $id !== null ? $this->repository->find($id) : new UserModel();

        if ($user === null) {
            return $response->withStatus(404);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $errors = $this->validate($name, $email, $user);

        if ($errors !== []) {
            $user->setName($name);
            $user->setEmail($email);

            return $this->view->render($response->withStatus(422), '@User/user.html.twig', [
                'items' => $this->repository->all(),
                'selected' => $user,
                'errors' => $errors,
            ]);
        }

        $user->setName($name);
        $user->setEmail($email);
        $this->repository->save($user);

        return $response
            ->withStatus(303)
            ->withHeader('Location', '/user?id=' . $user->getId());
    }

    /**
     * @return list<string>
     */
    private function validate(string $name, string $email, UserModel $user): array
    {
        $errors = [];
        if ($name === '') {
            $errors[] = 'Le nom est obligatoire.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'adresse e-mail est invalide.";
        }

        $existing = $email !== '' ? $this->repository->findByEmail($email) : null;
        if ($existing !== null && $existing->getId() !== $user->getId()) {
            $errors[] = 'Cette adresse e-mail est déjà utilisée.';
        }

        return $errors;
    }
}