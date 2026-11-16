# Application (domaines)

Chaque sous-dossier de `src/Application/` est un **domaine** applicatif : Controller et templates y vivent ensemble, plutôt que d'être dispersés par couche technique dans des dossiers globaux `Controllers/`, `Views/`, etc.

Le Model (Entity Doctrine) n'est volontairement pas généré ici : il vit sous `src/Repositories/NOM/`, à côté de son Repository (voir [src/Repositories/README.md](../Repositories/README.md)). Le Model est une préoccupation de la couche d'accès aux données, pas du Controller — ce dernier ne fait qu'en consommer des instances via le(s) Repository(s) qu'il injecte.

Un domaine se génère avec (dans cet ordre : le Repository doit exister avant le Controller qui le consomme) :

```bash
composer make:repository -- Product
composer make:domain -- Product
```

Ce qui produit :

```text
src/Repositories/Product/
  ProductModel.php               (Entity Doctrine)
  ProductRepository.php
src/Application/Product/
  ProductController.php
  templates/product.html.twig
```

## Routage : attribut `#[Route]`

Un Controller de domaine est naturellement **multi-actions** (index, show, store, update, destroy...), contrairement à un endpoint REST/RPC qui ne représente qu'une seule route (ou, en répertoire, une route par classe — voir [src/Endpoints/README.md](../Endpoints/README.md)). Le routage suit donc une convention différente : chaque méthode publique du Controller annotée `#[App\Core\Attributes\Route('METHODE', '/chemin')]` devient une route indépendante.

```php
#[Route('GET', '/product')]
public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    // ...
}
```

Le bootstrap (`app/app.php`) scanne `src/Application/*/*Controller.php`, lit ces attributs par réflexion, et enregistre chaque méthode annotée comme route Slim — sans registre séparé à maintenir.

Les dépendances du constructeur du Controller (Repository(s), vue Twig...) sont résolues automatiquement par un mécanisme de réflexion simple et temporaire, en attendant l'introduction d'un conteneur DI dans une étape ultérieure du starter. Cela n'impose aucune contrainte particulière au code du Controller : il continue de déclarer ses dépendances via son constructeur, normalement.

## Un Controller peut consommer plusieurs Repositories

Rien ne limite un Controller à un seul Repository : chaque paramètre typé du constructeur est résolu indépendamment des autres par le mécanisme d'injection (voir [src/Repositories/README.md](../Repositories/README.md)) :

```php
final class ProductController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly Twig $view,
    ) {
    }
}
```

## Persistance : le Model est l'Entity Doctrine, vivant à côté du Repository

Le Model consommé par ce Controller n'est pas un simple objet de données : c'est directement l'**Entity Doctrine** du domaine, annotée avec les attributs de mapping (`#[ORM\Entity]`, `#[ORM\Table]`, `#[ORM\Column]`...), et générée par `composer make:repository` sous `src/Repositories/NOM/` :

```php
#[ORM\Entity]
#[ORM\Table(name: 'products')]
class ProductModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
}
```

Voir [src/Repositories/README.md](../Repositories/README.md) pour le détail et la justification de cet emplacement.

## Rendu de vue : Twig

Le template généré est un fichier `.html.twig`, rendu par le Controller via l'instance `Slim\Views\Twig` injectée dans son constructeur :

```php
return $this->view->render($response, '@Product/product.html.twig', [
    'items' => $this->repository->all(),
]);
```

Chaque dossier `src/Application/DOMAIN_NAME/templates/` est enregistré par le bootstrap sous un **namespace Twig portant le nom du domaine** (`@Product`, `@Order`...), ce qui évite toute collision de nom de fichier entre domaines et rend explicite l'origine d'un template.

## Pourquoi deux conventions de routage différentes ?

Les endpoints REST/RPC (`src/Endpoints/`) utilisent `RouteAction::PATH` (une classe = une route), tandis que les domaines utilisent `#[Route]` sur des méthodes (une classe = plusieurs routes). Ce choix n'est pas accidentel : il reflète une différence réelle de forme entre les deux natures de composants plutôt qu'un manque de cohérence. Voir [src/Endpoints/README.md](../Endpoints/README.md) pour la convention symétrique.
