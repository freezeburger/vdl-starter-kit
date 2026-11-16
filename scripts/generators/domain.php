<?php

declare(strict_types=1);

/**
 * Générateur "domain" : crée un domaine applicatif sous
 * src/Application/{{Name}}/. Un domaine regroupe la présentation web
 * d'une fonctionnalité métier : Controller et Template vivent dans le
 * même dossier plutôt que d'être dispersés par couche technique.
 *
 * Le Model (Entity Doctrine) n'est volontairement PAS généré ici : il
 * vit sous src/Repositories/{{Name}}/ (voir composer make:repository),
 * à côté de son Repository. Le Model est une préoccupation de la couche
 * d'accès aux données, pas du Controller — générer ce domaine suppose
 * donc d'avoir déjà généré le Repository correspondant :
 *
 *   composer make:repository -- {{Name}}   (à générer d'abord : Model + Repository)
 *   composer make:domain -- {{Name}}       (puis : Controller + template)
 *
 * Le Controller est multi-actions : chaque méthode publique annotée
 * #[Route('METHODE', '/chemin')] est auto-enregistrée comme route
 * indépendante par le bootstrap (à la différence des endpoints REST/RPC,
 * où c'est la classe entière — via RouteAction::PATH — qui représente
 * une unique route).
 *
 * Rien n'impose qu'un Controller de domaine ne consomme qu'un seul
 * Repository : il suffit de typer un paramètre supplémentaire dans son
 * constructeur (ex. `private readonly CategoryRepository $categories`),
 * résolu comme les autres par le mécanisme d'injection par réflexion
 * (voir src/Application/README.md).
 *
 * Le rendu de vue passe par Twig : le template généré est un fichier
 * `.html.twig`, rendu par le Controller via l'instance Twig injectée.
 *
 * Usage :
 *   composer make:repository -- Product
 *   composer make:domain -- Product
 * Produit :
 *   src/Application/Product/ProductController.php
 *   src/Application/Product/templates/product.html.twig
 */

return [
    'files' => [
        [
            'path' => 'src/Application/{{Name}}/{{Name}}Controller.php',
            'template' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Application\{{Name}};

use App\Core\Attributes\Route;
use App\Repositories\{{Name}}\{{Name}}Repository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * Contrôleur du domaine {{Name}}.
 * Orchestre la requête : lit via {{Name}}Repository (src/Repositories/{{Name}}/,
 * généré par `composer make:repository -- {{Name}}`), puis restitue la
 * vue via Twig. Le Model ({{Name}}Model) vit à côté du Repository, pas
 * ici : ce Controller ne fait qu'en consommer des instances, et pourrait
 * tout aussi bien injecter un second Repository si son orchestration en
 * avait besoin.
 *
 * Chaque méthode publique annotée #[Route] devient une route indépendante,
 * auto-enregistrée par le bootstrap (contrairement aux endpoints REST/RPC,
 * un Controller de domaine est naturellement multi-actions : index, show,
 * store, update, destroy...).
 */
final class {{Name}}Controller
{
    public function __construct(
        private readonly {{Name}}Repository $repository,
        private readonly Twig $view,
    ) {
    }

    #[Route('GET', '/{{name}}')]
    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $this->view->render($response, '@{{Name}}/{{name}}.html.twig', [
            'items' => $this->repository->all(),
        ]);
    }
}
PHP,
        ],
        [
            'path' => 'src/Application/{{Name}}/templates/{{name}}.html.twig',
            'template' => <<<'TWIG'
{# Gabarit de départ du domaine {{Name}}, rendu par {{Name}}Controller::show(). #}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{Name}}</title>
</head>
<body>
    <h1>{{Name}}</h1>
    <ul>
        {% for item in items %}
            <li>{{ item.id }}</li>
        {% endfor %}
    </ul>
</body>
</html>
TWIG,
        ],
    ],
];
