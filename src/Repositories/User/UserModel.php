<?php

declare(strict_types=1);

namespace App\Repositories\User;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity Doctrine du domaine User : représente la donnée métier
 * persistée. Le mapping vers la table SQL est déclaré par **attributs
 * PHP 8** (pas d'annotations docblock ni de fichier XML séparé) :
 *
 * - `#[ORM\Entity]`       : cette classe est mappée sur une table.
 * - `#[ORM\Table]`        : nom de la table SQL ('users').
 * - `#[ORM\Id]`           : cette colonne est la clé primaire.
 * - `#[ORM\GeneratedValue]` : la valeur est auto-incrémentée par la BDD.
 * - `#[ORM\Column]`       : une propriété = une colonne (type explicite).
 *
 * Le Model vit désormais à côté du Repository (App\Repositories\User),
 * et non plus dans le dossier du domaine (src/Application/User/) : le
 * Model est une préoccupation de la couche d'accès aux données, pas du
 * Controller — un Controller ne fait qu'en consommer une ou plusieurs
 * instances via le(s) Repository(s) qu'il injecte. Voir
 * src/Repositories/README.md pour le schéma complet Entity → Repository
 * → EntityManager → base de données.
 *
 * Ajouter un champ : ajouter une propriété privée annotée #[ORM\Column]
 * puis son getter (et setter si mutable), et générer une migration
 * (`composer doctrine -- migrations:diff`).
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class UserModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = strtolower(trim($email));
    }

    /**
     * @return array{id: int|null, name: string, email: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
