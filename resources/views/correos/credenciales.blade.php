@component('mail::message')
# Su acceso al sistema

{{ $nombre }}:

Estas son sus credenciales para el sistema de control de exámenes:

- **Usuario:** {{ $usuario }}
- **Contraseña temporal:** {{ $contrasenaTemporal }}

@component('mail::button', ['url' => $enlace])
Ingresar al sistema
@endcomponent

La contraseña temporal caduca en {{ $horasVigencia }} horas. En su primer ingreso el sistema
le pedirá definir una propia. No comparta estos datos con nadie.

Gracias,
{{ config('app.name') }}
@endcomponent
