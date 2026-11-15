import type { FetchError } from 'ofetch'

function lireCookie(nom: string): string | null {
  const ligne = document.cookie.split('; ').find(morceau => morceau.startsWith(`${nom}=`))
  return ligne ? decodeURIComponent(ligne.slice(nom.length + 1)) : null
}

/**
 * Appels à l'API Laravel (même adresse que l'interface) avec la session Sanctum et le jeton CSRF.
 */
export function useApi() {
  return $fetch.create({
    credentials: 'include',
    headers: { Accept: 'application/json' },
    onRequest({ options }) {
      const jeton = lireCookie('XSRF-TOKEN')
      if (jeton) {
        options.headers.set('X-XSRF-TOKEN', jeton)
      }
    },
    async onResponseError({ request, response }) {
      if (response.status === 401 && !String(request).includes('/api/moi')) {
        useState('utilisateur').value = null
        await navigateTo({ path: '/connexion', query: { session: 'expiree' } })
      }
    },
  })
}

/** Demande le cookie CSRF avant une connexion ou si la session a expiré. */
export async function preparerSession(): Promise<void> {
  await $fetch('/sanctum/csrf-cookie', { credentials: 'include' })
}

/** Premier message d'erreur de chaque champ renvoyé par Laravel (422). */
export function erreursDeValidation(erreur: unknown): Record<string, string> {
  const donnees = (erreur as FetchError)?.data as { errors?: Record<string, string[]> } | undefined
  return Object.fromEntries(Object.entries(donnees?.errors ?? {}).map(([champ, messages]) => [champ, messages[0] ?? '']))
}

/** Message lisible pour une erreur d'API (toast). */
export function messageErreur(erreur: unknown, parDefaut = 'Une erreur est survenue. Réessayez.'): string {
  const reponse = erreur as FetchError
  if (reponse?.statusCode === 403) return 'Accès refusé.'
  if (reponse?.statusCode === 419) return 'Votre session a expiré. Rechargez la page.'
  if (reponse?.statusCode === 429) return 'Trop de tentatives. Patientez une minute.'
  return (reponse?.data as { message?: string } | undefined)?.message || parDefaut
}
