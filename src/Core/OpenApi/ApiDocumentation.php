<?php

declare(strict_types=1);

namespace App\Core\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Porte les métadonnées racine de la spécification OpenAPI (titre,
 * version, description). Ne contient aucun code : uniquement un attribut
 * PHP 8, scanné comme n'importe quelle classe d'endpoint.
 *
 * Une seule déclaration #[OA\Info(...)] est nécessaire pour tout le
 * document généré ; elle vit ici plutôt que dans un endpoint arbitraire,
 * pour ne pas coupler la génération à l'existence (ou non) d'une route
 * particulière.
 */
#[OA\Info(
    title: 'VDL Starter Kit — API REST',
    version: '1.0.0',
    description: 'Documentation générée automatiquement à partir des attributs '
        . 'OpenAPI (#[OA\\...]) déclarés sur les endpoints de src/Endpoints/Rest.'
)]
final class ApiDocumentation
{
}
