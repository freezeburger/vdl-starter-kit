<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Garantit qu'une requête est identifiable par un identifiant unique
 * (en-tête X-Request-Id), utile pour tracer un appel RPC/batch à travers
 * les journaux applicatifs, même sans middleware de logging dédié.
 *
 * Réutilise l'identifiant fourni par le client (utile pour un appelant qui
 * corrèle déjà ses propres traces) s'il est présent, sinon en génère un.
 * Restitué à la fois en attribut de requête (X-Request-Id, lisible par
 * n'importe quel Controller/Action via $request->getAttribute()) et en
 * en-tête de réponse (visible côté client/appelant).
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'X-Request-Id';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $request->getHeaderLine(self::ATTRIBUTE);
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(16));
        }

        $request = $request->withAttribute(self::ATTRIBUTE, $requestId);
        $response = $handler->handle($request);

        return $response->withHeader(self::ATTRIBUTE, $requestId);
    }
}
