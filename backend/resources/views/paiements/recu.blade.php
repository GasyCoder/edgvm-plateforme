<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 1.2cm 1.9cm 1.3cm 1.9cm; }
    body { font-family: 'Helvetica', sans-serif; font-size: 10.5pt; color: #1b1b1b; margin: 0; }
    table { border-collapse: collapse; }
    .entete { width: 100%; border-bottom: 2.4pt solid #1b1b1b; }
    .entete td { vertical-align: middle; padding-bottom: 5pt; }
    .entete .gauche { width: 21%; text-align: center; }
    .entete .centre { text-align: center; }
    .entete .droite { width: 17%; text-align: center; }
    .legende { font-size: 7pt; font-weight: bold; margin-top: 3pt; }
    .pays, .universite { font-weight: bold; font-size: 11pt; }
    .devise { font-style: italic; font-size: 7.5pt; margin-top: 1pt; }
    .etoiles { font-size: 6pt; letter-spacing: 0.5pt; line-height: 1; margin: 1pt 0; }
    .ecole { font-weight: bold; font-size: 8.6pt; }
    h1 { font-family: 'Times-Roman', serif; text-align: center; font-size: 16pt; text-decoration: underline; margin: 26pt 0 4pt; }
    .numero { text-align: center; font-size: 11pt; font-weight: bold; margin-bottom: 18pt; }
    .infos { width: 100%; margin-bottom: 14pt; }
    .infos td { padding: 3pt 0; vertical-align: top; }
    .infos .libelle { width: 32%; color: #555; }
    .lignes { width: 100%; margin-bottom: 8pt; }
    .lignes th { background: #eef6f2; color: #14563f; text-align: left; font-size: 9pt; padding: 6pt 8pt; border-bottom: 1pt solid #14563f; }
    .lignes td { padding: 6pt 8pt; border-bottom: 0.5pt solid #ccc; }
    .montant, .lignes th.montant { text-align: right; white-space: nowrap; }
    .total td { font-weight: bold; font-size: 11.5pt; border-bottom: none; border-top: 1.2pt solid #1b1b1b; }
    .lettres { font-style: italic; margin: 6pt 0 14pt; }
    .reste { padding: 8pt 10pt; background: #f5f6fa; border-left: 3pt solid #14563f; }
    .signature { width: 100%; margin-top: 24pt; }
    .signature td { vertical-align: top; }
    .pied { position: absolute; bottom: 0; left: 0; width: 100%; border-top: 0.6pt solid #999; padding-top: 5pt; font-size: 7.5pt; color: #555; }
    .page { position: relative; height: 26.9cm; page-break-after: always; }
    .page.derniere { page-break-after: auto; }
    .annule { position: absolute; top: 10cm; left: 0; width: 100%; text-align: center; font-size: 72pt; font-weight: bold; color: #c62828; opacity: 0.18; transform: rotate(-28deg); }
</style>
</head>
<body>
@foreach ($recus as $recu)
    @php([$paiement, $en_lettres, $reste] = [$recu['paiement'], $recu['en_lettres'], $recu['reste']])
    <div class="page{{ $loop->last ? ' derniere' : '' }}">
    @include('pdf.entete', ['logo_gauche' => $logo_gauche, 'logo_droite' => $logo_droite])

    <h1>REÇU DE PAIEMENT</h1>
    <div class="numero">N° {{ $paiement->numero_recu }}</div>

    <table class="infos">
        <tr><td class="libelle">Reçu de</td><td><strong>{{ $paiement->doctorant->nomComplet() }}</strong></td></tr>
        <tr><td class="libelle">Matricule</td><td>{{ $paiement->doctorant->matricule }}</td></tr>
        @if ($paiement->doctorant->equipeAccueil)
            <tr><td class="libelle">Équipe d'accueil</td><td>{{ $paiement->doctorant->equipeAccueil->sigle }}, {{ $paiement->doctorant->equipeAccueil->nom }}</td></tr>
        @endif
        <tr><td class="libelle">Date du paiement</td><td>{{ $paiement->date_paiement->locale('fr')->translatedFormat('d F Y') }}</td></tr>
        <tr><td class="libelle">Mode de paiement</td><td>{{ $paiement->mode->libelle() }}@if ($paiement->reference), réf. {{ $paiement->reference }}@endif</td></tr>
        @if ($paiement->justificatif_chemin)
            <tr><td class="libelle">Justificatif</td><td>Reçu de versement enregistré dans le dossier</td></tr>
        @endif
    </table>

    <table class="lignes">
        <thead><tr><th>Objet</th><th>Année</th><th class="montant">Montant</th></tr></thead>
        <tbody>
            @foreach ($paiement->imputations as $imputation)
                <tr>
                    <td>{{ $imputation->frais?->libelle }}</td>
                    <td>{{ $imputation->frais?->anneeUniversitaire?->libelle }}</td>
                    <td class="montant">{{ number_format($imputation->montant, 0, ',', ' ') }} Ar</td>
                </tr>
            @endforeach
            <tr class="total"><td colspan="2">Total versé</td><td class="montant">{{ number_format($paiement->montant, 0, ',', ' ') }} Ar</td></tr>
        </tbody>
    </table>
    @if ($en_lettres)
        <p class="lettres">Arrêté le présent reçu à la somme de {{ $en_lettres }}.</p>
    @endif

    <div class="reste">
        @if ($reste > 0)
            Reste à payer après ce paiement, tous frais confondus : <strong>{{ number_format($reste, 0, ',', ' ') }} Ar</strong>.
        @else
            Après ce paiement, le doctorant est à jour de tous ses frais.
        @endif
    </div>

    <table class="signature">
        <tr>
            <td style="width: 50%">@if ($paiement->saisiPar)Encaissé par : {{ $paiement->saisiPar->name }}@endif</td>
            <td style="text-align: center">
                Fait à {{ $ville }}, le {{ $paiement->created_at->locale('fr')->translatedFormat('d F Y') }}<br>
                Pour l'École Doctorale,
            </td>
        </tr>
    </table>

    <div class="pied">Reçu n° {{ $paiement->numero_recu }}, École Doctorale Génie du Vivant et Modélisation, Université de Mahajanga. À conserver.</div>

    @if ($paiement->estAnnule())
        <div class="annule">ANNULÉ</div>
    @endif
    </div>
@endforeach
</body>
</html>
