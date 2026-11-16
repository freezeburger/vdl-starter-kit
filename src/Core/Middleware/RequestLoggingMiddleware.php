<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Journalise chaque requête traitée (méthode, chemin, statut, durée,
 * identifiant de corrélation), quelle que soit son issue.
 *
 * Volontairement générique et unique plutôt que dupliqué par convention
 * (REST/RPC/Domaine) ou par handler d'erreur : le niveau de log est déduit
 * du code de statut de la réponse finale (>=500 → error, >=400 → warning,
 * sinon → info), ce qui couvre uniformément les réponses 200 comme les
 * 404/405/500 déjà formatées en JSON par app/app.php, sans dupliquer de
 * journalisation dans chaque handler d'erreur.
 *
 * Positionnement dans le bootstrap : ajouté en tout dernier (donc middleware
 * le plus englobant de tous), afin de recevoir la réponse finale déjà
 * transformée par le middleware d'erreurs — y compris pour les exceptions
 * non prévues, journalisées avant d'être re-levées pour laisser le
 * middleware d'erreurs produire la réponse JSON habituelle.
 */
final class RequestLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startedAt = microtime(true);

        try {
            $response = $handler->handle($request);
        } catch (Throwable $exception) {
            $this->logger->error('Requête interrompue par une exception non interceptée', [
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'durationMs' => $this->durationInMilliseconds($startedAt),
            ]);

            throw $exception;
        }

        $status = $response->getStatusCode();

        $this->logger->log($this->levelForStatus($status), 'Requête traitée', [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $status,
            'durationMs' => $this->durationInMilliseconds($startedAt),
            'requestId' => $response->getHeaderLine(RequestIdMiddleware::ATTRIBUTE) ?: null,
        ]);

        return $response;
    }

    private function levelForStatus(int $status): string
    {
        return match (true) {
            $status >= 500 => LogLevel::ERROR,
            $status >= 400 => LogLevel::WARNING,
            default => LogLevel::INFO,
        };
    }

    private function durationInMilliseconds(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
