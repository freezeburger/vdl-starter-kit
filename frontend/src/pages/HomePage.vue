<script setup>
import { onMounted, ref } from 'vue'

/**
 * Démontre l'appel au backend via le proxy dev (/api/... -> Slim, voir
 * vite.config.js) : à supprimer/adapter dès qu'une vraie page consomme
 * l'API. Sert de preuve de fonctionnement du starter frontend + backend.
 */
const health = ref(null)
const error = ref(null)

const products = ref([])

onMounted(async () => {

  const response = await fetch('https://dummyjson.com/products')
  products.value = await response.json()

  try {
    const response = await fetch('/api/health')
    if (!response.ok) {
      throw new Error(`Statut HTTP ${response.status}`)
    }
    health.value = await response.json()
  } catch (cause) {
    error.value = cause.message
  }
})
</script>

<template>
  <v-card>
    <v-card-title>Accueil</v-card-title>
    <v-card-text>
      <p>Starter Vue 3 + Vuetify 3, buildé sous <code>public/app</code>.</p>

      <v-alert v-if="health" type="success" class="mt-4">
        Backend joignable : {{ health.service }} ({{ health.status }})
      </v-alert>
      <v-alert v-else-if="error" type="error" class="mt-4">
        Backend injoignable : {{ error }}
      </v-alert>
      <v-alert v-else type="info" class="mt-4">
        Appel de /api/health en cours...
      </v-alert>
    </v-card-text>
  </v-card>
</template>
