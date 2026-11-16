<?php

declare(strict_types=1);

/**
 * Générateur "rpc" : crée une action RPC/batch, fichier unique,
 * auto-enregistrée par le bootstrap (elle étend App\Core\RouteAction et
 * déclare sa route via PATH). Destinée aux déclenchements de traitements
 * non interactifs (batch, proxification, mapping), pas à des ressources
 * REST ni à des domaines applicatifs.
 *
 * PATH est relatif : le préfixe /rpc (configurable via API_RPC_PREFIX) est
 * appliqué centralement par app/app.php, pas par cette classe.
 *
 * Usage : composer make:rpc -- Import
 * Produit : src/Endpoints/Rpc/ImportAction.php, route /rpc/import
 */

return [
    'files' => [
        [
            'path' => 'src/Endpoints/Rpc/{{Name}}Action.php',
            'template' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Endpoints\Rpc;

use App\Core\JsonResponse;
use App\Core\RouteAction;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Opération RPC/batch {{Name}} (déclenchement de traitement non
 * interactif : import, synchronisation, mapping...).
 *
 * - PATH est relatif ('/{{name}}') : le préfixe /rpc est appliqué
 *   centralement par app/app.php (configurable via API_RPC_PREFIX),
 *   jamais ici.
 * - Contrairement à un endpoint REST, une opération RPC n'est
 *   volontairement pas auto-documentée dans /api/openapi : OpenAPI décrit
 *   des ressources, pas des commandes (voir src/Core/OpenApi/README.md).
 * - __invoke() est la seule méthode possible : une Action représente une
 *   unique opération. Pour une logique métier plus riche, déléguer à un
 *   Repository partagé (src/Repositories/, composer make:repository).
 *
 * Anatomie complète d'une requête Action : voir App\Core\RouteAction.
 */
final class {{Name}}Action extends RouteAction
{
    public const PATH = '/{{name}}';

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return JsonResponse::write($response, [
            'operation' => '{{Name}}',
            'method' => $request->getMethod(),
        ]);
    }
}
PHP,
        ],
    ],
];
