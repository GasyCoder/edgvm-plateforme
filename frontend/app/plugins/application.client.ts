/**
 * Au démarrage : réglages publics (nom, logos, maintenance, apparence), puis thème et couleur appliqués
 * et tenus à jour (choix de l'administration, choix personnel, préférence du système).
 */
export default defineNuxtPlugin(async () => {
  const { application, charger, appliquer, estSombre } = useApplication()
  await charger()
  appliquer()
  watch([estSombre, () => application.value.apparence], appliquer, { deep: true })

  useHead({
    titleTemplate: titre => titre ? `${titre}, ${application.value.nom}` : `Plateforme ${application.value.nom}`,
    link: [{ rel: 'icon', href: application.value.logos.edgvm }],
  })
})
