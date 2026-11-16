<?php

declare(strict_types=1);

use OpenApi\Builder;

/**
 * Construit la spécification OpenAPI de l'API REST, à partir des attributs
 * PHP 8 (#[OpenApi\Attributes\...]) déclarés sur les classes de
 * src/Endpoints/Rest et sur src/Core/OpenApi/ApiDocumentation.php (qui
 * porte les métadonnées racine : titre, version).
 *
 * Toujours à jour par construction : aucune spécification statique à
 * maintenir en parallèle du code, contrairement à un fichier YAML/JSON
 * écrit à la main qui se désynchronise avec le temps.
 *
 * @param list<string> $sources répertoires/fichiers scannés pour les
 *   attributs OpenAPI (src/Endpoints/Rest + src/Core/OpenApi)
 * @param string $restPrefix préfixe REST (ex. '/api'), appliqué à chaque
 *   route découverte : chaque endpoint déclare un chemin relatif dans son
 *   attribut #[OA\Get(path: self::PATH)] (comme RouteAction::PATH), ce
 *   builder préfixe le document final de la même façon que
 *   registerActions() préfixe les routes réellement enregistrées.
 *
 * @return array<string, mixed>
 */
function buildOpenApiSpec(array $sources, string $restPrefix): array
{
    $result = (new Builder())
        ->addSource($sources)
        ->build();

    $spec = $result->toArray();

    if (isset($spec['paths']) && is_array($spec['paths'])) {
        $prefixedPaths = [];
        foreach ($spec['paths'] as $path => $operations) {
            $prefixedPaths[$restPrefix . $path] = $operations;
        }
        $spec['paths'] = $prefixedPaths;
    }

    return $spec;
}

/**
 * Construit la page HTML Swagger UI, qui charge la spécification JSON
 * depuis $specUrl. Les assets (CSS/JS) de swagger-ui-dist sont chargés
 * depuis un CDN plutôt que packagés en local : évite d'ajouter une chaîne
 * d'outillage front (npm, bundler) à ce starter pour une simple page de
 * documentation, sans dépendance à un serveur de fichiers statiques
 * supplémentaire.
 */
function buildSwaggerUiHtml(string $specUrl): string
{
    $specUrlJson = json_encode($specUrl, JSON_THROW_ON_ERROR);

    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>VDL Starter Kit — Documentation API</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function () {
            window.ui = SwaggerUIBundle({
                url: {$specUrlJson},
                dom_id: '#swagger-ui',
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
            });
        };
    </script>
</body>
</html>
HTML;
}
