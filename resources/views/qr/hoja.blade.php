<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; margin: 24px; color: #1e293b; background: #ffffff; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 16px; }
        h1 { font-size: 18px; margin: 0; }
        button { font: inherit; font-size: 14px; padding: 8px 16px; border: 0; border-radius: 8px; background: #1d4ed8; color: #ffffff; cursor: pointer; }
        .hoja { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .tarjeta { border: 1px solid #94a3b8; border-radius: 10px; padding: 12px; display: flex; gap: 12px; break-inside: avoid; page-break-inside: avoid; }
        .tarjeta img { width: 130px; height: 130px; flex-shrink: 0; }
        .datos { min-width: 0; }
        .facultad { font-size: 11px; font-weight: 700; color: #1d4ed8; text-transform: uppercase; letter-spacing: .03em; }
        .examen { font-size: 13px; font-weight: 700; }
        .nombre { font-size: 13px; font-weight: 700; margin-top: 8px; overflow-wrap: anywhere; }
        .dato { font-size: 12px; color: #334155; }
        .lugar { font-size: 12px; margin-top: 8px; }
        .vacio { font-size: 14px; color: #475569; }
        @media (max-width: 640px) { body { margin: 16px; } .hoja { grid-template-columns: 1fr; } }
        @media print {
            @page { margin: 12mm; }
            body { margin: 0; }
            header { display: none; }
            .hoja { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
    <header>
        <h1>{{ $titulo }}</h1>
        @if ($tarjetas !== [])
            <button type="button" onclick="window.print()">Imprimir</button>
        @endif
    </header>
    @if ($tarjetas === [])
        <p class="vacio">Sin credenciales</p>
    @endif
    <main class="hoja">
        @foreach ($tarjetas as $tarjeta)
            <section class="tarjeta">
                <img src="{{ $tarjeta['imagen'] }}" alt="Código QR de {{ $tarjeta['estudiante'] }}">
                <div class="datos">
                    <div class="facultad">UMSS{{ $tarjeta['facultad'] ? ' · '.$tarjeta['facultad'] : '' }}</div>
                    <div class="examen">{{ $tarjeta['examen'] }}</div>
                    <div class="nombre">{{ $tarjeta['estudiante'] }}</div>
                    <div class="dato">Código {{ $tarjeta['codigo'] }}</div>
                    <div class="dato">{{ $tarjeta['fecha'] }} · {{ $tarjeta['hora'] }} · {{ $tarjeta['duracion'] }} min</div>
                    <div class="lugar">
                        @if ($tarjeta['aula'])
                            <strong>Aula {{ $tarjeta['aula'] }}</strong>
                            @if ($tarjeta['ubicacion'])
                                <br>{{ $tarjeta['ubicacion'] }}
                            @endif
                        @else
                            <strong>Sin aula</strong>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach
    </main>
</body>
</html>
