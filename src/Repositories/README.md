# Repositories (Model + accès aux données)

`src/Repositories/NOM/` regroupe, pour une même Entity, son **Model** (`NOMModel.php`, l'Entity Doctrine) et son **Repository** (`NOMRepository.php`, l'accès aux données), dans le même dossier et le même namespace (`App\Repositories\NOM`) — séparés des dossiers de domaine (`src/Application/DOMAIN_NAME/`).

## Pourquoi le Model vit ici, et pas dans le dossier de domaine ?

Le Model représente une donnée persistée (mapping Doctrine `#[ORM\Entity]`, `#[ORM\Column]`...) : c'est une préoccupation de la couche d'accès aux données, pas du Controller. Un Controller de domaine (ou un endpoint REST/RPC) ne fait **que consommer** des instances du Model via le Repository qu'il injecte — il ne devrait pas être responsable de la définition de cette donnée. Garder le Model dans le dossier de domaine couplait artificiellement une préoccupation de persistance à une préoccupation de présentation/orchestration ; il vit donc à côté de son Repository, dont il est indissociable.

Un Repository représente par ailleurs un accès aux données potentiellement utile à **plusieurs endroits** de l'application (un Controller de domaine, mais aussi un endpoint REST ou RPC), ce qui justifie aussi qu'il ne soit pas enfermé dans un seul dossier de domaine.

## Doctrine en un schéma

```text
Controller de domaine        Endpoint REST/RPC
(src/Application/)           (src/Endpoints/)
        │                          │
        └───────────┬──────────────┘
                     ▼
       NOMRepository (src/Repositories/NOM/)
       find() / all() / save() / remove()
                     │
                     ▼
       EntityManager Doctrine (partagé, app/database.php)
                     │
                     ▼
       NOMModel = Entity (src/Repositories/NOM/)
       mapping par attributs #[ORM\Entity], #[ORM\Column]...
                     │
                     ▼
              Base de données (MySQL/MariaDB)
```

Le Repository est le **seul point de passage** vers Doctrine : ni un Controller ni un endpoint ne manipule l'`EntityManager` directement, ce qui garde les détails de persistance (requêtes, `persist()`/`flush()`) à un seul endroit, réutilisable partout.

## Un Controller (ou un endpoint) peut consommer plusieurs Repositories

Rien n'impose qu'un Controller ou un endpoint ne consomme qu'un seul Repository. Le mécanisme d'injection par réflexion (`app/app.php`, `resolveDependency()`) résout chaque paramètre typé du constructeur indépendamment des autres :

```php
public function __construct(
    private readonly ProductRepository $products,
    private readonly CategoryRepository $categories,
    private readonly Twig $view,
) {
}
```

Il suffit d'ajouter un paramètre de constructeur supplémentaire, typé vers le Repository voulu — aucune configuration séparée à maintenir.

## Génération

```bash
composer make:repository -- Product
```

Produit `src/Repositories/Product/ProductModel.php` (Entity Doctrine) et `src/Repositories/Product/ProductRepository.php`, dans le même dossier/namespace (`App\Repositories\Product`).

À générer **avant** `composer make:domain -- Product` si le domaine doit persister des données : le Controller généré référence alors ce Repository (et son Model) depuis `src/Repositories/Product/`.

## Usage

Le Repository généré s'appuie sur l'`EntityManager` Doctrine partagé, construit par le bootstrap (`app/app.php`) **uniquement si au moins un Repository existe** dans l'application (voir `app/database.php`, fonction `repositoriesExist()`) — un profil sans persistance (RPC pur, proxy...) n'a donc pas besoin de configurer les variables `DB_*` du `.env`. L'`EntityManager` est injecté automatiquement dans le constructeur de chaque Repository, que ce soit depuis un Controller de domaine ou un endpoint :

```php
// Depuis un Controller de domaine (src/Application/Product/ProductController.php)
public function __construct(
    private readonly \App\Repositories\Product\ProductRepository $repository,
    private readonly \Slim\Views\Twig $view,
) {
}
```

```php
// Depuis un endpoint REST (src/Endpoints/Rest/Product/ProductEndpoint.php ou une action concrète)
public function __construct(
    private readonly \App\Repositories\Product\ProductRepository $repository,
) {
}
```

## Les quatre méthodes générées, en clair

| Méthode | Ce qu'elle fait | Requête SQL immédiate ? |
|---|---|---|
| `find(int $id)` | cherche par clé primaire, retourne `null` si absent | oui (SELECT) |
| `all()` | charge toutes les lignes de la table | oui (SELECT) |
| `save(Entity $e)` | `persist()` (marque à synchroniser) puis `flush()` (déclenche l'INSERT/UPDATE) | oui, au `flush()` |
| `remove(Entity $e)` | `remove()` (marque à supprimer) puis `flush()` (déclenche le DELETE) | oui, au `flush()` |

Point important pour l'équipe : `persist()`/`remove()` seuls ne font **rien** en base tant que `flush()` n'a pas été appelé — c'est ce qui permet à Doctrine de regrouper plusieurs changements dans une seule transaction si besoin (non fait par défaut ici, chaque `save()`/`remove()` flush immédiatement pour rester simple).

Le Repository est à étendre selon les besoins réels du domaine (méthodes de recherche spécifiques, requêtes DQL, pagination...).
