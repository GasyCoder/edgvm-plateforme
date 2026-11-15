---
name: edgvm-interface-nuxt
description: Créer ou modifier l'interface Nuxt 4 de la plateforme EDGVM (frontend/) : pages, layouts, composants shadcn-vue, composables d'appel à l'API Laravel, protection des pages par rôle, style Tailwind v4 aux couleurs du logo. À utiliser aussi pour créer le projet frontend/ s'il n'existe pas encore.
---

# Interface Nuxt (frontend/)

Lire d'abord `AGENTS.md` (rôles, conventions d'interface).

## Création du projet (déjà faite, pour mémoire)

1. Créer le projet : `npm create nuxt@latest frontend`, avec le modèle minimal, TypeScript et npm.
2. Régler `nuxt.config.ts` :
   - `ssr: false` ;
   - `app.head` avec `lang: 'fr'` ;
   - `runtimeConfig.public.apiBase` (local : `http://localhost:8000`).
3. Tailwind CSS v4 :
   - installer le plugin Vite `@tailwindcss/vite` dans `vite.plugins` ;
   - créer `app/assets/css/main.css` avec `@import "tailwindcss";` et les tokens de couleur.
4. shadcn-vue :
   - ajouter le module `shadcn-nuxt` (`npx nuxi module add shadcn-nuxt`) ;
   - lancer `npx shadcn-vue@latest init` ;
   - les composants vont dans `app/components/ui/`.
5. Autres dépendances prévues : `@lucide/vue` (icônes) et `vue-sonner` (toasts). Demander avant d'en ajouter d'autres.

## Arborescence (Nuxt 4, dossier `app/`)

```
app/
  pages/                  connexion.vue, index.vue (tableau de bord selon le rôle), doctorants/, inscriptions/, paiements/, actes/, notifications/, mon-espace/
  layouts/                default.vue (barre latérale + menu mobile en bas), connexion.vue
  components/ui/          composants shadcn-vue (ne pas les réécrire, les composer)
  components/             composants métier : DoctorantCarte.vue, ResteAPayer.vue, BadgeNiveau.vue...
  composables/            useApi.ts, useAuth.ts, useMontant.ts
  middleware/             auth.global.ts, role.ts
  utils/                  formatage (dates, montants)
```

## Appels à l'API

- Un seul point d'entrée : `useApi()`, qui encapsule `$fetch.create`.
  - Options : `baseURL: apiBase`, `credentials: 'include'`, `headers: { Accept: 'application/json' }`.
  - Envoyer l'en-tête `X-XSRF-TOKEN`, lu dans le cookie `XSRF-TOKEN`.
- Avant le login : `await api('/sanctum/csrf-cookie')`.
- Erreurs :
  - **422** : afficher les messages de validation à côté des champs ;
  - **401** : rediriger vers `/connexion` ;
  - **403** : toast « Accès refusé » ;
  - **autre** : toast d'erreur générique.
- Listes paginées : l'API renvoie `{ data, meta }`. Garder les filtres dans l'URL (`useRoute().query`) pour que le retour arrière fonctionne.

## Rôles côté interface

- `useAuth()` expose `utilisateur`, `estSuperAdmin`, `estDoctorant` et `estMembreEad`.
- Une page réservée déclare `definePageMeta({ middleware: 'role', roles: ['super_admin'] })`.
- Le masquage côté interface n'est qu'un confort : **la sécurité est dans les policies Laravel.**
- Les menus par rôle :
  - **doctorant** : Mon espace, Mes inscriptions, Mes paiements, Mes attestations, Notifications.
  - **membre_ead** : Mes doctorants, Suivi, Notifications.
  - **super_admin** : Tableau de bord, Doctorants, Inscriptions, Paiements, Actes, Notifications, Équipes d'accueil, Comptes, Réglages.

## Style

- Utiliser les tokens Tailwind tirés du logo :
  - `brand-*` (vert forêt, `brand-800 = #14563F`, `brand-600 = #267058`) ;
  - `leaf-*` (`leaf-400 = #55B66F`) ;
  - `sun-*` (`sun-300 = #F6D94C`).
- Police unique : Open Sans, hébergée dans le projet.
- Sobre et « humain » :
  - pas de dégradés, de motifs, de sur-titres décoratifs ni d'effets ;
  - textes courts en français ;
  - aucun tiret cadratin ou demi-cadratin (— –) dans les textes.
- Mobile d'abord :
  - menu principal en bas de l'écran sous `xl` ;
  - bouton hamburger toujours visible sur mobile ;
  - pas de défilement horizontal de page ;
  - les tableaux deviennent des cartes sous `md`.
- Toasts `vue-sonner` en **bas à droite**, au-dessus du menu du bas sur mobile.
- Squelettes de chargement :
  - ils ont la forme de la page attendue (liste, fiche, formulaire) ;
  - ils s'affichent seulement si la réponse tarde (environ 180 ms).
- Montants : `1 250 000 Ar` (`Intl.NumberFormat('fr-FR')`). Dates : `15 novembre 2026`.

## Vérification

- `npx nuxi typecheck`.
- Ouvrir la page dans un navigateur, sur mobile (390 px) et sur bureau.
- Contrôler qu'il n'y a pas d'erreur dans la console.
- Tester chaque rôle concerné avec un compte du seeder.
