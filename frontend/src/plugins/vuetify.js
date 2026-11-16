import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

/**
 * Instance Vuetify unique de l'application. Import complet des composants
 * et directives (pas de plugin Vite dédié au tree-shaking) : plus simple
 * à comprendre pour un starter, quitte à alourdir légèrement le bundle —
 * un compromis assumé, à revoir si la taille du bundle devient un problème
 * réel.
 */
export default createVuetify({
  components,
  directives,
})
