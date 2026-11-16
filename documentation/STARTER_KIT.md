# Besoin d'origine — Starter Kit Slim/PHP

> Ce document reprend et reformule, sans en changer le sens, l'expression de besoin brute qui a servi de point de départ à ce starter. Il est conservé **à titre de référence historique** : pour l'état réellement construit et son avancement, voir [PLAN.md](PLAN.md).

## Objectif

- Fournir une structure unique, réutilisable par plusieurs projets.
- Constituer une librairie de dépendances internes, partagée entre ces projets.
- Offrir des commodités de développement (génération, conventions, outillage) plutôt que de tout reconstruire à chaque projet.

## Types de projets couverts

- Réalisation d'API REST.
- Réalisation de services HTTP/RPC (ex. traitement batch, proxification/mapping).
- Réalisation d'application Web complète.
- Réalisation de Front-End avec cycle d'authentification, sans logique back-end.

## Structure envisagée

- `/public/index.php` : point d'entrée.
- `/bootstrap` : scripts de bootstrap pour `index.php`.
- `/src/Core` : utilitaires Slim généraux (émetteurs de réponse, middlewares...).
- `/src/Infrastructure` : infrastructure transverse.
- `/src/Application` : utilitaires Slim généraux (applicatif).
- `/src/Rest` : handlers de l'API REST.
- `/src/Application/Domain` : routes, contrôleurs et logique de l'application web (Controller/Service/Model/Repository), avec templating (Twig).
- `/src/RPC` : création simple de contrôleurs pour un usage de type RPC.

## Exemples de commodités attendues

- `composer make:rest`
- `composer make:domain`
- `composer make:rpc`

## Composantes de la librairie

- Stratégie d'authentification (Keycloak).
- Stratégie d'envoi de mail.
- Stratégie d'export de fichiers.
- Stratégie de logging.

## Fonctionnalités attendues du projet

- Usage d'un conteneur d'injection de dépendances (PHP-DI ?).
- Templating (Twig).
- Mise en œuvre simple de Doctrine.
- Des dépendances PHP incontournables (« must have »), en restant simple.
- Définition simplifiée des routes via les Controllers.
- Support optionnel d'un serveur statique.
- Middlewares de route configurables/stratégiques, et déclencheurs RPC.
- API JSON/XML.
- Mapper SOAP/XML.
- Support optionnel de ressources statiques.
- Documentation Swagger auto-générée pour les projets API REST.
- Enregistrement simplifié des middlewares.
- Migrations Doctrine assistées (?).
- Application standardisée : authentification, formulaires, vues maître-détail en VueJS.
