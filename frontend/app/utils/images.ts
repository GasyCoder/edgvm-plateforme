/**
 * Allège une photo avant l'envoi (téléphone : souvent 4 à 8 Mo) : 2000 px au plus, JPEG de bonne qualité.
 * Un PDF ou une image déjà légère est renvoyé tel quel ; en cas d'échec, le fichier d'origine est gardé.
 */
export async function alleger(fichier: File, cote = 2000, qualite = 0.82): Promise<File> {
  if (!fichier.type.startsWith('image/') || fichier.type === 'image/gif' || fichier.size < 700 * 1024) return fichier
  try {
    const image = await createImageBitmap(fichier)
    const echelle = Math.min(1, cote / Math.max(image.width, image.height))
    const toile = document.createElement('canvas')
    toile.width = Math.round(image.width * echelle)
    toile.height = Math.round(image.height * echelle)
    toile.getContext('2d')!.drawImage(image, 0, 0, toile.width, toile.height)
    image.close()
    const blob = await new Promise<Blob | null>(resolve => toile.toBlob(resolve, 'image/jpeg', qualite))
    if (!blob || blob.size >= fichier.size) return fichier
    return new File([blob], fichier.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() })
  }
  catch {
    return fichier
  }
}
