@component('mail::message')
# Código de acceso

{{ $nombre }}:

Su código para entrar al sistema de control de ingreso a exámenes es:

@component('mail::panel')
**{{ $codigo }}**
@endcomponent

Vale una sola vez y vence en {{ $minutos }} minutos. Si no lo solicitó, no hace falta
hacer nada.

{{ config('app.name') }}
@endcomponent
