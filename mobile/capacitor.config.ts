import type { CapacitorConfig } from '@capacitor/cli'

/**
 * L'application affiche la plateforme en ligne : chaque mise à jour de la plateforme arrive
 * dans l'application sans réinstaller l'APK. Seul un changement natif demande une nouvelle version.
 *
 * Pour essayer contre un serveur local : ADRESSE_PLATEFORME=http://192.168.1.20:8001 npx cap sync android
 */
const adresse = process.env.ADRESSE_PLATEFORME ?? 'https://plateforme.edgvm.mahajanga-univ.mg'

const config: CapacitorConfig = {
  appId: 'mg.univmahajanga.edgvm',
  appName: 'EDGVM',
  webDir: 'www',
  appendUserAgent: 'EDGVM-App',
  server: {
    url: adresse,
    cleartext: adresse.startsWith('http:'),
    errorPath: 'hors-ligne.html',
  },
  android: {
    allowMixedContent: false,
  },
  plugins: {
    SystemBars: {
      insetsHandling: 'css',
      initialViewportFitValueHint: 'cover',
    },
  },
}

export default config
