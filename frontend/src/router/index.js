import { createRouter, createWebHistory } from 'vue-router'

/**
 * Routeur de l'application. Utilise l'historique HTML5 (createWebHistory)
 * avec pour base /app, cohérent avec vite.config.js (base: '/app/') et le
 * point de montage du build sous public/app côté backend.
 *
 * Ajouter une page : créer un composant sous src/pages/, puis déclarer sa
 * route ici. Volontairement pas de découverte automatique par dossier
 * (contrairement aux endpoints PHP) : le nombre de pages d'un frontend
 * reste faible, une liste explicite est plus lisible qu'un scan de
 * fichiers.
 */
const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('../pages/HomePage.vue'),
  },
  {
    path: '/about',
    name: 'about',
    component: () => import('../pages/AboutPage.vue'),
  },
    {
    path: '/lorient',
    name: 'lorient',
    component: () => import('../pages/LorientPage.vue'),
  },
]

export default createRouter({
  history: createWebHistory('/app/'),
  routes,
})
