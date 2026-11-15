{{-- En-tête officiel commun aux actes et aux reçus (styles .entete dans la page appelante) --}}
<table class="entete">
    <tr>
        <td class="gauche">
            @if ($logo_gauche)<img src="{{ $logo_gauche }}" style="height: 1.45cm" alt="">@endif
            <div class="legende">{{ \App\Support\ReglagesApplication::texte('ecole.universite') }}</div>
        </td>
        <td class="centre">
            <div class="pays">REPOBLIKAN'I MADAGASIKARA</div>
            <div class="devise">Fitiavana - Tanindrazana - Fandrosoana</div>
            <div class="etoiles">************************************</div>
            <div class="universite">{{ mb_strtoupper(\Illuminate\Support\Str::ascii(\App\Support\ReglagesApplication::texte('ecole.universite'))) }}</div>
            <div class="etoiles">************************************</div>
            <div class="ecole">{{ mb_strtoupper(\Illuminate\Support\Str::ascii(\App\Support\ReglagesApplication::texte('ecole.nom'))) }}</div>
            <div class="etoiles">****************************************</div>
        </td>
        <td class="droite">
            @if ($logo_droite)<img src="{{ $logo_droite }}" style="height: 2cm" alt="">@endif
        </td>
    </tr>
</table>
