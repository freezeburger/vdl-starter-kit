# frontend

Frontend Vue 3 + Vuetify 3 + vue-router du starter, buildé vers `../public/app` et servi par le backend Slim.

## Pourquoi ici et pas ailleurs ?

Le backend (`src/`, `app/`, `public/index.php`) et le frontend suivent des cycles de vie et un outillage différents (Composer vs npm) : les séparer en dossiers de premier niveau (`/frontend`, backend à la racine) évite toute ambiguïté sur ce qui appartient à quel écosystème, tout en gardant un seul dépôt.

## Démarrage

```bash
cd frontend
npm install
npm run dev      # http://localhost:5173/app/, proxy /api et /rpc vers le backend
```

Le backend (`composer start`, port 8080 par défaut) doit tourner en parallèle pour que le proxy fonctionne — voir `vite.config.js`.

## Build de production

```bash
npm run build
```

Dépose le résultat directement dans `../public/app` (voir `build.outDir` dans `vite.config.js`) : aucune étape de copie manuelle, le backend Slim (ou tout serveur de fichiers statiques) sert ensuite `/app` sans configuration supplémentaire en local (voir avertissement ci-dessous pour un vrai serveur de production).

## Structure

```text
src/main.js               Point d'entrée : installe Vue, vue-router et Vuetify
src/App.vue                Racine : ne fait que monter le layout par défaut
src/layouts/                Layouts applicatifs (un seul pour l'instant : DefaultLayout)
src/router/index.js        Déclaration explicite des routes (pas de scan automatique de dossier)
src/pages/                  Une page = un composant Vue, une route
src/plugins/vuetify.js      Instance Vuetify unique (import complet des composants/directives)
```

Ajouter une page : créer un composant sous `src/pages/`, l'ajouter à `src/router/index.js`. Volontairement pas de convention de découverte automatique par fichier (contrairement aux endpoints PHP du backend) : le nombre de pages d'un frontend reste faible, une liste explicite est plus lisible qu'un scan.

## Point de vigilance : SPA fallback en production

En développement (`npm run dev`) et via le serveur PHP intégré (`php -S ... -t public`), naviguer directement vers une URL profonde (ex. `/app/about`) fonctionne "gratuitement" grâce au fallback `index.html` de chaque serveur de dev. **Un vrai serveur de production (Nginx, Apache) ne fait pas cela par défaut** : il faudra une règle explicite de type `try_files $uri /app/index.html` sur le préfixe `/app` pour que le rechargement d'une page profonde ne renvoie pas un 404. Non traité pour l'instant, à faire lors de la mise en place du déploiement.

## Authentification

Le cycle d'authentification (Keycloak) n'est pas encore implémenté dans ce squelette — voir `PLAN.md`, point 8. Ce frontend est pour l'instant une coquille minimaliste sans notion de session.
