<x-mail::message>
# Hola, {{ $nombre }}

Recibimos una solicitud para restablecer la contraseña de tu cuenta de Control de ingreso.

<x-mail::button :url="$enlace">
Restablecer contraseña
</x-mail::button>

El enlace vence en {{ $minutosVigencia }} minutos y solo puede utilizarse una vez. Si no solicitaste este cambio, puedes ignorar este mensaje.

Saludos,<br>
Control de ingreso
</x-mail::message>
