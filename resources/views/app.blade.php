<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#003870">
    <title>Control de exámenes</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="/iconos/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/iconos/apple-touch-icon.png">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    {{-- React monta aqui. En el servidor de TIS esta vista recibe el index compilado. --}}
    <div id="app"></div>
</body>
</html>
