<?php

declare(strict_types=1);

namespace App\Repositories\User;

use Doctrine\ORM\EntityManager;

/**
 * Accès aux données de l'Entity UserModel, partagé entre tous les
 * consommateurs de l'application (Controllers de domaine, endpoints
 * REST/RPC...). Isole ces consommateurs des détails de Doctrine.
 *
 * Le Model (UserModel) vit dans ce même dossier/namespace plutôt que
 * dans src/Application/User/ : voir src/Repositories/README.md pour le
 * schéma complet Entity → Repository → EntityManager → base de données
 * et la justification de cet emplacement.
 */
final class UserRepository
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {
    }

    /**
     * Recherche par clé primaire. Retourne null si aucune ligne ne
     * correspond (jamais d'exception) : à l'appelant de décider comment
     * traiter l'absence (404, valeur par défaut...).
     */
    public function find(int $id): ?UserModel
    {
        return $this->entityManager->find(UserModel::class, $id);
    }

    /**
     * Charge toutes les lignes de la table. Suffisant pour un starter ou
     * un petit volume ; à remplacer par une requête DQL paginée
     * ($this->entityManager->createQuery(...)) dès que le volume de
     * données le justifie.
     *
     * @return list<UserModel>
     */
    public function all(): array
    {
        return $this->entityManager->getRepository(UserModel::class)->findBy([], ['name' => 'ASC']);
    }

    public function findByEmail(string $email): ?UserModel
    {
        return $this->entityManager->getRepository(UserModel::class)->findOneBy([
            'email' => strtolower(trim($email)),
        ]);
    }

    /**
     * Crée ou met à jour une ligne. Deux étapes Doctrine distinctes :
     * `persist()` marque l'entité comme "à synchroniser" (aucune requête
     * SQL immédiate), `flush()` déclenche réellement l'INSERT/UPDATE.
     * Appeler flush() à chaque save() est volontairement simple pour ce
     * starter ; regrouper plusieurs opérations dans un seul flush() est
     * possible mais laissé à l'appelant selon son besoin transactionnel.
     */
    public function save(UserModel $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Supprime une ligne. Comme save(), remove() ne fait rien tant que
     * flush() n'a pas été appelé.
     */
    public function remove(UserModel $entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
