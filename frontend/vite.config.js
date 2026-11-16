import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

/**
 * Base publique du frontend : les assets buildés sont servis sous /app,
 * cohérent avec la sortie de build (voir build.outDir ci-dessous) qui
 * dépose le résultat directement dans public/app du backend Slim.
 */
export default defineConfig({
  base: '/app/',
  plugins: [vue()],
  server: {
    // En développement, le serveur Vite proxifie les appels API/RPC vers
    // le backend Slim (composer start, port 8080 par défaut) : le
    // frontend peut appeler /api/... et /rpc/... sans se soucier de CORS
    // ni de l'URL réelle du backend.
    proxy: {
      '/api': 'http://localhost:8080',
      '/rpc': 'http://localhost:8080',
    },
  },
  build: {
    // Le build est déposé directement dans public/app du backend : en
    // production, Slim (ou un simple serveur de fichiers statiques) sert
    // le frontend buildé sans étape de copie supplémentaire.
    outDir: '../public/app',
    emptyOutDir: true,
  },
})
