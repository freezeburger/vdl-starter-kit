<script setup>
/**
 * Layout applicatif unique : barre de navigation Vuetify + zone de contenu
 * qui affiche la page active du routeur. À dupliquer/adapter si un jour
 * plusieurs layouts sont nécessaires (ex. layout "auth" sans navigation).
 */
import { RouterView } from 'vue-router'
</script>

<template>
  <v-app>
    <v-app-bar>
      <v-app-bar-title>VDL Starter Kit</v-app-bar-title>

      <v-btn :to="{ name: 'home' }" text="Accueil" />
      <v-btn :to="{ name: 'about' }" text="À propos" />
    </v-app-bar>

    <v-main>
      <v-container>
        <RouterView />
      </v-container>
    </v-main>
  </v-app>
</template>
