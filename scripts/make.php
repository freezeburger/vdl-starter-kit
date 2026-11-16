<?php

declare(strict_types=1);

/**
 * Point d'entrée unique des commandes `composer make:*`.
 *
 * Ce fichier ne connaît aucun détail de template : il valide les
 * arguments, charge le générateur correspondant dans scripts/generators/
 * et écrit le ou les fichiers décrits par ce générateur. Chaque type de
 * composant (rest, rpc, domain) est défini dans son propre fichier sous
 * scripts/generators/, ce qui permet d'ajouter ou de modifier un
 * générateur sans toucher aux autres.
 *
 * Un générateur retourne un tableau :
 *   ['files' => [
 *       ['path' => 'src/.../{{Name}}Xxx.php', 'template' => '<?php ...'],
 *       ...
 *   ]]
 *
 * Les chemins et templates supportent les jetons {{Name}} (PascalCase,
 * tel que saisi) et {{name}} (minuscules, utilisé pour les routes et les
 * fichiers de gabarit).
 *
 * Usage :
 *   composer make:rest -- Product
 *   composer make:rpc -- Import
 *   composer make:repository -- Product
 *   composer make:domain -- Product
 */

$type = $argv[1] ?? null;
$name = $argv[2] ?? null;

$availableTypes = ['rest', 'rpc', 'domain', 'repository'];

if (!in_array($type, $availableTypes, true) || $name === null) {
    fwrite(STDERR, "Usage: composer make:<rest|rpc|domain|repository> -- <Name>\n");
    exit(1);
}

if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name)) {
    fwrite(STDERR, "Invalid name '{$name}'. Use letters and numbers only.\n");
    exit(1);
}

$root = dirname(__DIR__);
$generatorFile = __DIR__ . '/generators/' . $type . '.php';

if (!is_file($generatorFile)) {
    fwrite(STDERR, "Unknown generator: {$type}\n");
    exit(1);
}

/** @var array{files: list<array{path: string, template: string}>} $definition */
$definition = require $generatorFile;

$tokens = [
    '{{Name}}' => $name,
    '{{name}}' => strtolower($name),
];

/** @var list<array{target: string, content: string}> $resolved */
$resolved = [];

foreach ($definition['files'] as $fileDefinition) {
    $relativePath = strtr($fileDefinition['path'], $tokens);
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (file_exists($target)) {
        fwrite(STDERR, "Component already exists: {$target}\n");
        exit(1);
    }

    $resolved[] = [
        'target' => $target,
        'content' => strtr($fileDefinition['template'], $tokens),
    ];
}

foreach ($resolved as $file) {
    $directory = dirname($file['target']);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create directory: {$directory}");
    }

    file_put_contents($file['target'], $file['content']);
    fwrite(STDOUT, "Created {$file['target']}\n");
}
