<x-mail::message>
{{-- Salutation d'ouverture --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
# Bonjour,
@endif

{{-- Texte --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Bouton --}}
@isset($actionText)
<x-mail::button :url="$actionUrl" :color="$level === 'error' ? 'error' : 'primary'">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Suite --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Signature --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Cordialement,<br>
{{ \App\Support\ReglagesApplication::texte('app.nom') }}
@endif

{{-- Lien de secours --}}
@isset($actionText)
<x-slot:subcopy>
Si le bouton « {{ $actionText }} » ne fonctionne pas, copiez cette adresse dans votre navigateur :
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
