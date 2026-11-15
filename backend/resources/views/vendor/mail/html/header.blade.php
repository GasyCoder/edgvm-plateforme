@props(['url'])
@php
    $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
    $application = \App\Support\ReglagesApplication::public();
@endphp
{{-- En-tête : logo et nom de l'École à gauche, logo de l'Université à droite --}}
<tr>
<td class="header">
<table class="header-inner" align="center" width="680" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header-ecole" valign="middle">
<a href="{{ $url }}" style="text-decoration: none;">
<table cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td valign="middle" style="padding-right: 12px;"><img src="{{ $base.$application['logos']['edgvm'] }}" class="logo" width="52" height="52" alt="{{ $application['nom'] }}"></td>
<td valign="middle">
<span class="header-nom">{{ $application['nom'] }}</span><br>
<span class="header-ecole-nom">{{ $application['ecole'] }}</span>
</td>
</tr>
</table>
</a>
</td>
<td class="header-universite" align="right" valign="middle">
<img src="{{ $base.$application['logos']['universite'] }}" class="logo-universite" height="40" alt="{{ $application['universite'] }}">
</td>
</tr>
</table>
</td>
</tr>
