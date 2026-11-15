<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 1.2cm 1.9cm 1.3cm 1.9cm; }
    body { font-family: 'Times-Roman', 'Times New Roman', serif; font-size: 12.5pt; color: #111; margin: 0; }
    .page { position: relative; height: 26.9cm; page-break-after: always; }
    .page.derniere { page-break-after: auto; }
    table { border-collapse: collapse; }
    .entete { width: 100%; border-bottom: 2.4pt solid #1b1b1b; }
    .entete td { vertical-align: middle; padding-bottom: 5pt; }
    .entete .gauche { width: 21%; text-align: center; }
    .entete .centre { text-align: center; font-family: 'Helvetica', sans-serif; }
    .entete .droite { width: 17%; text-align: center; }
    .legende { font-family: 'Helvetica', sans-serif; font-size: 7pt; font-weight: bold; margin-top: 3pt; }
    .pays, .universite { font-weight: bold; font-size: 11pt; }
    .devise { font-style: italic; font-size: 7.5pt; margin-top: 1pt; }
    .etoiles { font-size: 6pt; letter-spacing: 0.5pt; line-height: 1; margin: 1pt 0; }
    .ecole { font-weight: bold; font-size: 8.6pt; }
    h1.titre { text-align: center; font-size: 16.5pt; font-weight: bold; text-decoration: underline; text-transform: uppercase; margin: 34pt 0 28pt; line-height: 1.3; }
    .corps { line-height: 1.65; text-align: left; }
    .corps p { margin: 0 0 10pt; }
    .corps h2, .corps h3, .corps h4 { margin: 12pt 0 8pt; font-size: 13pt; }
    .corps ul, .corps ol { margin: 0 0 10pt 18pt; padding: 0; }
    .bloc-signature { width: 100%; margin-top: 26pt; }
    .bloc-signature .vide { width: 50%; }
    .bloc-signature .contenu { text-align: center; }
    .fait { text-align: right; margin-bottom: 6pt; }
    .image-signature { height: 3.3cm; margin: 2pt 0; }
    .espace-signature { height: 3.4cm; }
    .nom-signataire { font-weight: bold; }
    .titre-signataire { font-style: italic; font-size: 11pt; }
    .pied { position: absolute; bottom: 0; left: 0; width: 100%; border-top: 0.6pt solid #999; padding-top: 5pt; font-family: 'Helvetica', sans-serif; font-size: 7.5pt; color: #444; }
    .pied td { vertical-align: middle; }
    .pied .qr img { width: 1.9cm; height: 1.9cm; }
    .compact h1.titre { margin: 18pt 0 14pt; }
    .compact .corps { line-height: 1.38; font-size: 12pt; }
    .compact .corps p { margin: 0 0 5.5pt; }
    .compact .bloc-signature { margin-top: 12pt; }
    .compact .image-signature { height: 2.8cm; }
    .compact .espace-signature { height: 2.8cm; }
    .filigrane { position: absolute; top: 11cm; left: 0; width: 100%; text-align: center; font-family: 'Helvetica', sans-serif; font-size: 72pt; font-weight: bold; color: #c62828; opacity: 0.18; transform: rotate(-28deg); }
    .filigrane.apercu { color: #14563f; }
</style>
</head>
<body>
@foreach ($pages as $page)
    @php($acte = $page['acte'])
    <div class="page{{ $loop->last ? ' derniere' : '' }}{{ $page['compact'] ? ' compact' : '' }}">
        <table class="entete">
            <tr>
                <td class="gauche">
                    @if ($page['logo_gauche'])<img src="{{ $page['logo_gauche'] }}" style="height: 1.45cm" alt="">@endif
                    <div class="legende">Université de Mahajanga</div>
                </td>
                <td class="centre">
                    <div class="pays">REPOBLIKAN'I MADAGASIKARA</div>
                    <div class="devise">Fitiavana - Tanindrazana - Fandrosoana</div>
                    <div class="etoiles">************************************</div>
                    <div class="universite">UNIVERSITE DE MAHAJANGA</div>
                    <div class="etoiles">************************************</div>
                    <div class="ecole">ECOLE DOCTORALE GENIE DU VIVANT ET MODELISATION</div>
                    <div class="etoiles">****************************************</div>
                </td>
                <td class="droite">
                    @if ($page['logo_droite'])<img src="{{ $page['logo_droite'] }}" style="height: 2cm" alt="">@endif
                </td>
            </tr>
        </table>

        <h1 class="titre">{{ $acte->titre }}</h1>

        <div class="corps">{!! $acte->contenu !!}</div>

        <table class="bloc-signature">
            <tr>
                <td class="vide"></td>
                <td class="contenu">
                    <div class="fait">Fait à {{ $acte->signataire['ville'] ?? 'Mahajanga' }}, le {{ $page['date'] }}</div>
                    <div>{{ $acte->signataire['fonction'] ?? '' }}</div>
                    @if ($page['signature'])
                        <img class="image-signature" src="{{ $page['signature'] }}" alt="">
                    @else
                        <div class="espace-signature"></div>
                    @endif
                    @if (! empty($acte->signataire['afficher_nom']))
                        <div class="nom-signataire">{{ $acte->signataire['nom'] ?? '' }}</div>
                        <div class="titre-signataire">{{ $acte->signataire['titre'] ?? '' }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <table class="pied">
            <tr>
                @if ($page['qr'])
                    <td class="qr" style="width: 2.2cm"><img src="{{ $page['qr'] }}" alt=""></td>
                @endif
                <td>
                    <strong>N° {{ $acte->numero }}</strong><br>
                    @if ($apercu)
                        Aperçu du modèle : sans valeur officielle.
                    @else
                        Authenticité vérifiable en scannant le code ou sur {{ $acte->urlVerification() }}
                    @endif
                </td>
            </tr>
        </table>

        @if ($apercu)
            <div class="filigrane apercu">APERÇU</div>
        @elseif ($acte->estAnnule())
            <div class="filigrane">ANNULÉ</div>
        @endif
    </div>
@endforeach
</body>
</html>
