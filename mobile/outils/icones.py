"""
Icônes de l'application à partir du logo EDGVM (emblème sans le texte du haut).

    python3 mobile/outils/icones.py backend/resources/images/actes/logo-edgvm.jpg

Produit les icônes de l'application web installable (frontend/public/icones)
et celles d'Android (mobile/android/app/src/main/res), si le projet Android existe.
"""
import pathlib
import sys

from PIL import Image

racine = pathlib.Path(__file__).resolve().parents[2]
source = Image.open(sys.argv[1] if len(sys.argv) > 1 else racine / 'backend/resources/images/actes/logo-edgvm.jpg').convert('RGB')
embleme = source.crop((8, 58, 292, 300))


def carre(taille: int, marge: float, fond: str | tuple = 'white') -> Image.Image:
    """Emblème centré dans un carré, avec une marge (fraction du côté) tout autour."""
    image = Image.new('RGBA', (taille, taille), fond)
    interieur = round(taille * (1 - 2 * marge))
    copie = embleme.copy()
    copie.thumbnail((interieur, interieur), Image.LANCZOS)
    image.paste(copie, ((taille - copie.width) // 2, (taille - copie.height) // 2))
    return image


def rond(image: Image.Image) -> Image.Image:
    masque = Image.new('L', image.size, 0)
    from PIL import ImageDraw
    ImageDraw.Draw(masque).ellipse((0, 0, image.width - 1, image.height - 1), fill=255)
    sortie = Image.new('RGBA', image.size, (0, 0, 0, 0))
    sortie.paste(image, mask=masque)
    return sortie


web = racine / 'frontend/public/icones'
web.mkdir(parents=True, exist_ok=True)
carre(192, 0.06).convert('RGB').save(web / 'icone-192.png')
carre(512, 0.06).convert('RGB').save(web / 'icone-512.png')
carre(512, 0.16).convert('RGB').save(web / 'icone-masquable-512.png')
carre(180, 0.08).convert('RGB').save(web / 'apple-touch-icon.png')

res = racine / 'mobile/android/app/src/main/res'
if res.exists():
    densites = {'mdpi': 1, 'hdpi': 1.5, 'xhdpi': 2, 'xxhdpi': 3, 'xxxhdpi': 4}
    for nom, facteur in densites.items():
        dossier = res / f'mipmap-{nom}'
        dossier.mkdir(exist_ok=True)
        icone = carre(round(48 * facteur), 0.08)
        icone.convert('RGB').save(dossier / 'ic_launcher.png')
        rond(carre(round(48 * facteur), 0.14)).save(dossier / 'ic_launcher_round.png')
        # Icône adaptative : 108 dp dont seuls les 72 dp du centre sont toujours visibles
        carre(round(108 * facteur), 0.25, (0, 0, 0, 0)).save(dossier / 'ic_launcher_foreground.png')
    # Écran de démarrage : fond blanc, emblème au centre (mêmes dimensions que les fichiers d'origine)
    for splash in res.glob('drawable*/splash.png'):
        largeur, hauteur = Image.open(splash).size
        fond = Image.new('RGB', (largeur, hauteur), 'white')
        copie = embleme.copy()
        cote = round(min(largeur, hauteur) * 0.42)
        copie.thumbnail((cote, cote), Image.LANCZOS)
        fond.paste(copie, ((largeur - copie.width) // 2, (hauteur - copie.height) // 2))
        fond.save(splash)
    print('Icônes et écrans de démarrage Android écrits.')
print('Icônes web écrites dans', web)
