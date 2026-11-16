import { App } from '@capacitor/app'
import { Browser } from '@capacitor/browser'
import { toast } from 'vue-sonner'

/**
 * Application mobile (Capacitor) : elle affiche la plateforme elle-même, avec ces ajustements.
 * - Les documents (PDF, reçus, carte) s'ouvrent dans le navigateur du téléphone avec un lien signé de quelques minutes.
 * - Les liens vers d'autres sites s'ouvrent aussi dans le navigateur.
 * - Le bouton retour d'Android ferme d'abord une fenêtre ouverte, puis revient à la page précédente.
 */
export default defineNuxtPlugin(() => {
  if (!estAppMobile()) return

  document.documentElement.classList.add('app-mobile')
  const api = useApi()
  const router = useRouter()

  async function ouvrir(adresse: string) {
    try {
      const url = new URL(adresse, window.location.href)
      const signee = url.origin === window.location.origin && url.pathname.startsWith('/telechargements/')
        ? (await api<{ data: { url: string } }>('/api/liens-telechargement', { method: 'POST', body: { chemin: url.pathname + url.search } })).data.url
        : url.href
      await Browser.open({ url: signee })
    }
    catch (erreur) {
      toast.error(messageErreur(erreur, 'Impossible d\'ouvrir ce document.'))
    }
  }

  const aOuvrirDehors = (url: URL) => url.origin === window.location.origin
    ? url.pathname.startsWith('/telechargements/')
    : url.protocol === 'http:' || url.protocol === 'https:'

  document.addEventListener('click', (evenement) => {
    const lien = (evenement.target as Element | null)?.closest?.('a[href]') as HTMLAnchorElement | null
    if (!lien) return
    const url = new URL(lien.href, window.location.href)
    if (!aOuvrirDehors(url)) return
    evenement.preventDefault()
    evenement.stopPropagation()
    ouvrir(url.href)
  }, true)

  const ouvrirFenetre = window.open.bind(window)
  window.open = (adresse?: string | URL, ...reste) => {
    if (adresse && aOuvrirDehors(new URL(adresse, window.location.href))) {
      ouvrir(String(adresse))
      return null
    }
    return ouvrirFenetre(adresse, ...reste)
  }

  App.addListener('backButton', ({ canGoBack }) => {
    const fenetre = document.querySelector('[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"], [role="menu"][data-state="open"]')
    if (fenetre) {
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
      return
    }
    if (canGoBack && router.currentRoute.value.path !== '/') router.back()
    else App.minimizeApp()
  }).catch(() => { /* Plugin natif absent (ancienne version de l'APK) : le retour reste celui d'Android */ })
})
