{{-- Carte de doctorat : une carte par page (dompdf : tableaux, pas de flex ni de grid) --}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0.3cm; }
    body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #111; font-size: 7.5pt; }
    .saut { page-break-after: always; }
    table { border-collapse: collapse; }
    .cadre { width: 16.4cm; table-layout: fixed; }
    .cadre > tr > td, .cadre > tbody > tr > td { border: 0.6pt solid #444; vertical-align: top; }
    .entete { height: 2.05cm; padding: 0.1cm 0.2cm; }
    .ministere { font-weight: bold; font-size: 6.8pt; text-align: center; line-height: 1.35; vertical-align: top; padding: 0.15cm 0 0 0.1cm; }
    .universite { color: #1d5fa8; font-size: 8pt; margin-top: 0.05cm; }
    .arrete { font-weight: bold; font-size: 7pt; line-height: 1.45; vertical-align: top; padding-top: 0.1cm; }
    .titre { text-align: center; font-size: 11pt; font-weight: bold; letter-spacing: 0.04cm; height: 0.7cm; vertical-align: middle !important; }
    .corps { height: 5.5cm; padding: 0.15cm 0.15cm 0 0.15cm; }
    .photo { width: 2.1cm; height: 2.8cm; border: 0.6pt solid #999; text-align: center; vertical-align: middle; color: #999; font-size: 7pt; }
    .photo img { width: 2.1cm; height: 2.8cm; }
    .identite { width: 5.6cm; }
    .identite td { padding: 0.04cm 0 0.04cm 0.2cm; font-size: 7.8pt; vertical-align: top; line-height: 1.25; }
    .identite .valeur { font-family: Times-Roman, serif; font-size: 9.6pt; font-weight: bold; }
    .numero { font-size: 8.4pt; font-weight: bold; padding-top: 0.2cm; }
    .signature-doctorant { text-align: center; font-size: 7.8pt; padding-top: 0.1cm; }
    .annees { width: 7.7cm; table-layout: fixed; margin-top: 0.1cm; }
    .annees th, .annees td { border: 0.6pt solid #444; text-align: center; }
    .annees th { font-weight: normal; font-size: 7.6pt; padding: 0.06cm 0.05cm; }
    .annees td { height: 0.85cm; font-family: Times-Roman, serif; font-size: 9.6pt; }
    .directeur { text-align: right; padding: 0.15cm 0.25cm 0 0; font-size: 8pt; }
    .directeur .nom { margin-top: 0.55cm; font-weight: bold; }
</style>
</head>
<body>
@foreach ($cartes as $carte)
    @php($d = $carte['doctorant'])
    <div class="{{ $loop->last ? '' : 'saut' }}">
        <table class="cadre">
            <tr>
                <td class="entete" style="width: 8.2cm">
                    <table style="width: 7.8cm">
                        <tr>
                            <td style="width: 2.3cm; vertical-align: top; padding: 0">
                                @if ($logo_gauche)<img src="{{ $logo_gauche }}" style="height: 1.35cm" alt="">@endif
                                <div class="universite">Université de Mahajanga</div>
                            </td>
                            <td class="ministere" style="padding: 0.1cm 0 0 0.1cm">{{ $ministere }}</td>
                        </tr>
                    </table>
                </td>
                <td class="entete" style="width: 8.2cm">
                    <table style="width: 7.8cm">
                        <tr>
                            <td class="arrete" style="padding: 0.05cm 0 0 0">{{ $arrete }}</td>
                            <td style="width: 2.1cm; text-align: right; padding: 0">@if ($logo_droite)<img src="{{ $logo_droite }}" style="height: 1.85cm" alt="">@endif</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="titre">CARTE DE DOCTORAT</td>
            </tr>
            <tr>
                <td class="corps">
                    <table style="width: 7.9cm">
                        <tr>
                            <td class="photo">
                                @if ($carte['photo'])<img src="{{ $carte['photo'] }}" alt="">@else Photo @endif
                            </td>
                            <td style="vertical-align: top; width: 5.7cm">
                                <table class="identite">
                                    <tr><td>Nom : <span class="valeur">{{ mb_strtoupper($d->nom) }}</span></td></tr>
                                    <tr><td>Prénoms : <span class="valeur">{{ $d->prenoms }}</span></td></tr>
                                    <tr><td>Date de naissance : <span class="valeur">{{ $d->date_naissance?->format('d/m/Y') }}</span></td></tr>
                                    <tr><td>Lieu de naissance : <span class="valeur">{{ $d->lieu_naissance }}</span></td></tr>
                                    <tr><td>Adresse : <span class="valeur">{{ $d->adresse }}</span></td></tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="numero">N° d'inscription : {{ $d->matricule }}</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="signature-doctorant">Signature</td>
                        </tr>
                    </table>
                </td>
                <td class="corps">
                    <table class="annees">
                        <tr>
                            <th style="width: 38%">Année Universitaire</th>
                            <th style="width: 30%">Année de thèse</th>
                            <th style="width: 32%">Signature</th>
                        </tr>
                        @foreach ($carte['annees'] as $ligne)
                            <tr>
                                <td>{{ $ligne['annee'] }}</td>
                                <td>{{ $ligne['rang'] }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    </table>
                    <div class="directeur">
                        Le Directeur
                        <div class="nom">{{ $directeur }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
@endforeach
</body>
</html>
