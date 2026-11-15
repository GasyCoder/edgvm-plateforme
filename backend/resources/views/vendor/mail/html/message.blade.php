@php($application = \App\Support\ReglagesApplication::public())
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="rtrim((string) config('app.frontend_url', config('app.url')), '/')">
{{ $application['nom'] }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
**{{ $application['ecole'] }}**<br>
{{ $application['universite'] }}

Message envoyé automatiquement par la plateforme {{ $application['nom'] }}. Merci de ne pas y répondre.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
