<?php

declare(strict_types=1);

namespace App\Endpoints\Rest\User;

use App\Core\RouteAction;
use App\Repositories\User\UserModel;
use App\Repositories\User\UserRepository;

/**
 * Base commune des actions REST de la ressource User.
 *
 * Ne représente aucune route par elle-même (classe abstraite, ignorée
 * par registerActions()) : elle fixe le PATH par défaut de la ressource,
 * regroupe par héritage les commodités de filtrage et de pagination de
 * App\Core\RouteAction (filters(), paginate(), routeArgument()), et
 * déclare ici le UserRepository dont toutes les actions User ont besoin
 * pour persister leurs données.
 *
 * L'usage d'un Repository reste optionnel au niveau du générateur
 * `composer make:rest` (voir scripts/generators/rest.php) : cette classe
 * illustre le cas où une ressource concrète choisit d'en ajouter un.
 */
abstract class UserEndpoint extends RouteAction
{
    public const PATH = '/user';

    public function __construct(
        protected readonly UserRepository $repository,
    ) {
    }

    /**
     * Validation partagée par les actions qui créent ou modifient un User.
     *
     * @return list<string>
     */
    protected function validate(string $name, string $email, UserModel $user): array
    {
        $errors = [];
        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email is invalid.';
        }

        $existing = $email !== '' ? $this->repository->findByEmail($email) : null;
        if ($existing !== null && $existing->getId() !== $user->getId()) {
            $errors[] = 'Email is already in use.';
        }

        return $errors;
    }
}
