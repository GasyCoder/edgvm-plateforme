import { Capacitor } from '@capacitor/core'

/** Vrai dans l'application Android ou iOS (Capacitor), faux dans un navigateur. */
export function estAppMobile(): boolean {
  return import.meta.client && Capacitor.isNativePlatform()
}

/** Vrai quand la plateforme est ouverte depuis l'écran d'accueil (application web installée, iPhone surtout). */
export function estAppInstallee(): boolean {
  if (!import.meta.client) return false
  return window.matchMedia('(display-mode: standalone)').matches || (navigator as Navigator & { standalone?: boolean }).standalone === true
}
