<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Vérification d'un acte, EDGVM</title>
    <style>
        :root { --vert: #14563f; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; background: #f5f6fa; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; color: #364a63; }
        main { width: 100%; max-width: 480px; background: #fff; border: 1px solid #dbdfea; border-radius: 6px; padding: 32px 28px; text-align: center; }
        .etat { display: inline-block; padding: 6px 14px; border-radius: 999px; font-weight: 700; margin: 18px 0; }
        .valide { background: #effaf1; color: #378a49; }
        .annule { background: #fdeeed; color: #c42b2b; }
        h1 { font-size: 20px; margin: 4px 0 0; color: var(--vert); }
        dl { text-align: left; margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 8px 14px; font-size: 15px; }
        dt { color: #8094ae; }
        dd { margin: 0; font-weight: 600; }
        p.note { font-size: 13px; color: #8094ae; margin-top: 22px; }
    </style>
</head>
<body>
<main>
    <h1>École Doctorale Génie du Vivant et Modélisation</h1>
    <div>Université de Mahajanga</div>
    @if (! $acte)
        <div class="etat annule">Aucun acte ne correspond à ce code</div>
        <p class="note">Le document présenté n'a pas été délivré par la plateforme de l'École, ou le code a été mal recopié.</p>
    @else
    @if ($acte->estAnnule())
        <div class="etat annule">Acte annulé le {{ $acte->annule_le->locale('fr')->translatedFormat('d F Y') }}</div>
    @else
        <div class="etat valide">Acte authentique et valide</div>
    @endif
    <dl>
        <dt>Document</dt><dd>{{ $acte->modele->nom }}</dd>
        <dt>Numéro</dt><dd>{{ $acte->numero }}</dd>
        <dt>Délivré à</dt><dd>{{ $acte->doctorant->nomComplet() }}</dd>
        <dt>Le</dt><dd>{{ $acte->delivre_le->locale('fr')->translatedFormat('d F Y') }}</dd>
    </dl>
    <p class="note">Comparez ces informations avec le document présenté. En cas de doute, contactez la scolarité de l'École doctorale.</p>
    @endif
</main>
</body>
</html>
