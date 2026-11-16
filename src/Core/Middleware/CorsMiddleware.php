<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Ajoute les en-têtes CORS nécessaires pour qu'un front séparé (Vue, ou
 * tout autre client cross-origin) puisse consommer les endpoints REST/RPC.
 *
 * Les origines autorisées se configurent via la variable d'environnement
 * CORS_ALLOWED_ORIGINS (liste séparée par des virgules), ou "*" par défaut
 * en développement. Intercepte directement les requêtes OPTIONS de
 * préflight sans les faire passer par le routage, pour éviter un faux 404
 * sur une route qui ne déclare pas explicitement la méthode OPTIONS.
 *
 * Positionnement dans le bootstrap : ajouté après addRoutingMiddleware()
 * (donc exécuté avant lui), pour intercepter les préflights avant qu'ils
 * n'atteignent la résolution de route — convention standard de Slim pour
 * le CORS.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedOrigins
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly array $allowedOrigins = ['*'],
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        if ($request->getMethod() === 'OPTIONS') {
            $response = $this->responseFactory->createResponse(204);

            return $this->withCorsHeaders($response, $origin);
        }

        $response = $handler->handle($request);

        return $this->withCorsHeaders($response, $origin);
    }

    private function withCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        $allowedOrigin = $this->resolveAllowedOrigin($origin);

        if ($allowedOrigin === null) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Request-Id')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }

    private function resolveAllowedOrigin(string $origin): ?string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        if ($origin !== '' && in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        return null;
    }
}
