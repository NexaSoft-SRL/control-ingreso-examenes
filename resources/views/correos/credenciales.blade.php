@component('mail::message')
# Acceso al sistema

Hola {{ $nombre }}:

Estas son las credenciales para el sistema de control de ingreso a exámenes:

- **Correo:** {{ $correo }}
- **Usuario:** {{ $usuario }}
- **Contraseña temporal:** {{ $contrasenaTemporal }}

@component('mail::button', ['url' => $enlace])
Ingresar al sistema
@endcomponent

La contraseña temporal caduca en {{ $horasVigencia }} horas. No compartas estos datos con nadie.

Gracias,
{{ config('app.name') }}
@endcomponent
