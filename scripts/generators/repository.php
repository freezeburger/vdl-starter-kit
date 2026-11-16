<?php

declare(strict_types=1);

/**
 * Générateur "repository" : crée une Entity Doctrine (le Model) et son
 * Repository partagé sous src/Repositories/{{Name}}/.
 *
 * Le Model est volontairement généré ici plutôt que dans le dossier de
 * domaine (src/Application/DOMAIN_NAME/) : il représente une
 * préoccupation de la couche d'accès aux données (mapping Doctrine), pas
 * du Controller. Le Controller ne fait que consommer une ou plusieurs
 * instances du Model via le(s) Repository(s) qu'il injecte — rien ne
 * l'empêche d'en injecter plusieurs dans son constructeur (voir
 * src/Application/README.md).
 *
 * Le Repository généré cible l'Entity de même nom, dans le même dossier
 * et le même namespace (App\Repositories\{{Name}}), et s'appuie sur
 * l'EntityManager Doctrine partagé (voir app/app.php : buildEntityManager()).
 *
 * Usage : composer make:repository -- Product
 * Produit :
 *   src/Repositories/Product/ProductModel.php
 *   src/Repositories/Product/ProductRepository.php
 *
 * À générer avant `composer make:domain -- Product` si le domaine a
 * besoin de persister des données : le Controller de domaine référence
 * alors ce Repository (et son Model) depuis src/Repositories/.
 */

return [
    'files' => [
        [
            'path' => 'src/Repositories/{{Name}}/{{Name}}Model.php',
            'template' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Repositories\{{Name}};

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity Doctrine du domaine {{Name}} : représente la donnée métier
 * persistée. Le mapping vers la table SQL est déclaré par **attributs
 * PHP 8** (pas d'annotations docblock ni de fichier XML séparé) :
 *
 * - `#[ORM\Entity]`       : cette classe est mappée sur une table.
 * - `#[ORM\Table]`        : nom de la table SQL ('{{name}}s').
 * - `#[ORM\Id]`           : cette colonne est la clé primaire.
 * - `#[ORM\GeneratedValue]` : la valeur est auto-incrémentée par la BDD.
 * - `#[ORM\Column]`       : une propriété = une colonne (type explicite).
 *
 * Aucun accès aux données ici (pas de find/save) : c'est le rôle du
 * Repository (App\Repositories\{{Name}}\{{Name}}Repository), dans le
 * même dossier — voir src/Repositories/README.md pour le schéma complet
 * Entity → Repository → EntityManager → base de données.
 *
 * Ajouter un champ : ajouter une propriété privée annotée #[ORM\Column]
 * puis son getter (et setter si mutable), et générer une migration
 * (`composer doctrine -- migrations:diff`).
 */
#[ORM\Entity]
#[ORM\Table(name: '{{name}}s')]
class {{Name}}Model
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
PHP,
        ],
        [
            'path' => 'src/Repositories/{{Name}}/{{Name}}Repository.php',
            'template' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Repositories\{{Name}};

use Doctrine\ORM\EntityManager;

/**
 * Accès aux données de l'Entity {{Name}}Model, partagé entre tous les
 * consommateurs de l'application (Controllers de domaine, endpoints
 * REST/RPC...). Isole ces consommateurs des détails de Doctrine.
 *
 * Le Model ({{Name}}Model) vit dans ce même dossier/namespace plutôt que
 * dans src/Application/{{Name}}/ : voir src/Repositories/README.md pour
 * le schéma complet Entity → Repository → EntityManager → base de données.
 */
final class {{Name}}Repository
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
    public function find(int $id): ?{{Name}}Model
    {
        return $this->entityManager->find({{Name}}Model::class, $id);
    }

    /**
     * Charge toutes les lignes de la table. Suffisant pour un starter ou
     * un petit volume ; à remplacer par une requête DQL paginée
     * ($this->entityManager->createQuery(...)) dès que le volume de
     * données le justifie.
     *
     * @return list<{{Name}}Model>
     */
    public function all(): array
    {
        return $this->entityManager->getRepository({{Name}}Model::class)->findAll();
    }

    /**
     * Crée ou met à jour une ligne. Deux étapes Doctrine distinctes :
     * `persist()` marque l'entité comme "à synchroniser" (aucune requête
     * SQL immédiate), `flush()` déclenche réellement l'INSERT/UPDATE.
     * Appeler flush() à chaque save() est volontairement simple pour ce
     * starter ; regrouper plusieurs opérations dans un seul flush() est
     * possible mais laissé à l'appelant selon son besoin transactionnel.
     */
    public function save({{Name}}Model $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Supprime une ligne. Comme save(), remove() ne fait rien tant que
     * flush() n'a pas été appelé.
     */
    public function remove({{Name}}Model $entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
PHP,
        ],
    ],
];
