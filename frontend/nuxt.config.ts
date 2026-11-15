import tailwindcss from '@tailwindcss/vite'

/** Adresse locale de l'API Laravel (API_LOCALE=http://localhost:8001 si le port 8000 est pris). */
const apiLocale = (globalThis as { process?: { env: Record<string, string | undefined> } }).process?.env.API_LOCALE ?? 'http://localhost:8000'

// Plateforme privée : pas de rendu serveur, l'interface est servie en fichiers statiques.
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  ssr: false,
  devtools: { enabled: false },
  modules: ['shadcn-nuxt'],
  css: ['~/assets/css/main.css'],
  vite: {
    plugins: [tailwindcss()],
  },
  shadcn: {
    prefix: '',
    componentDir: './app/components/ui',
  },
  app: {
    head: {
      htmlAttrs: { lang: 'fr' },
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'theme-color', content: '#14563f' },
        { name: 'robots', content: 'noindex,nofollow' },
      ],
      link: [
        { rel: 'icon', href: '/favicon.ico' },
        { rel: 'preload', href: '/fonts/open-sans-latin.woff2', as: 'font', type: 'font/woff2', crossorigin: '' },
      ],
    },
  },
  // En local, l'API Laravel (port 8000) est servie sous la même adresse que l'interface : cookies de session simples.
  nitro: {
    devProxy: {
      '/api': { target: `${apiLocale}/api`, changeOrigin: false },
      '/sanctum': { target: `${apiLocale}/sanctum`, changeOrigin: false },
      '/telechargements': { target: `${apiLocale}/telechargements`, changeOrigin: false },
      '/verifier': { target: `${apiLocale}/verifier`, changeOrigin: false },
      '/marque': { target: `${apiLocale}/marque`, changeOrigin: false },
    },
  },
})
