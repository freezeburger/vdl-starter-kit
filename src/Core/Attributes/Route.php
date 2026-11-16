<?php

declare(strict_types=1);

namespace App\Core\Attributes;

use Attribute;

/**
 * Annote une méthode publique d'un Controller de domaine pour en faire
 * une route Slim.
 *
 * Contrairement aux endpoints REST/RPC (une classe = une route via
 * RouteAction::PATH), un Controller de domaine est multi-actions
 * (index, show, store, update, destroy...). Chaque méthode publique
 * portant cet attribut devient une route indépendante, enregistrée par
 * le bootstrap via réflexion.
 *
 * Exemple :
 *   #[Route('GET', '/product')]
 *   public function show(...): ResponseInterface { ... }
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Route
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
    ) {
    }
}
