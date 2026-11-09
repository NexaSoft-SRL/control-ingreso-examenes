@component('mail::message')
# Restablecimiento de contraseña

{{ $nombre }}:

Recibimos una solicitud para restablecer la contraseña de su cuenta en el sistema de
control de exámenes.

@component('mail::button', ['url' => $enlace])
Definir una contraseña nueva
@endcomponent

El enlace sirve una sola vez y vence en {{ $minutosVigencia }} minutos. Si no lo
solicitó, no hace falta hacer nada: su contraseña sigue igual.

Gracias,
{{ config('app.name') }}
@endcomponent
