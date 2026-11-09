<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 24px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0 0 16px; }
        .hoja { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .tarjeta { border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px; display: flex; gap: 12px; page-break-inside: avoid; break-inside: avoid; }
        .tarjeta img { width: 140px; height: 140px; flex-shrink: 0; }
        .facultad { font-size: 11px; font-weight: 700; color: #1d4ed8; }
        .examen { font-size: 13px; font-weight: 700; }
        .nombre { font-size: 13px; font-weight: 700; margin-top: 8px; }
        .dato { font-size: 12px; color: #475569; }
        .codigo { font-family: ui-monospace, monospace; }
        .lugar { font-size: 12px; margin-top: 8px; }
        .vacio { font-size: 14px; color: #475569; }
        @media print { body { margin: 0; } h1 { display: none; } }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    @if ($tarjetas === [])
        <p class="vacio">Sin credenciales</p>
    @endif
    <div class="hoja">
        @foreach ($tarjetas as $tarjeta)
            <div class="tarjeta">
                <img src="{{ $tarjeta['imagen'] }}" alt="Código QR de {{ $tarjeta['estudiante'] }}">
                <div>
                    <div class="facultad">UMSS{{ $tarjeta['facultad'] ? ' · '.$tarjeta['facultad'] : '' }}</div>
                    <div class="examen">{{ $tarjeta['examen'] }}</div>
                    <div class="nombre">{{ $tarjeta['estudiante'] }}</div>
                    <div class="dato codigo">{{ $tarjeta['codigo'] }}</div>
                    <div class="dato">{{ $tarjeta['fecha'] }} · {{ $tarjeta['hora'] }} · {{ $tarjeta['duracion'] }} min</div>
                    <div class="lugar">
                        @if ($tarjeta['aula'])
                            <strong>Aula {{ $tarjeta['aula'] }}</strong>
                            @if ($tarjeta['edificio'] || $tarjeta['piso'])
                                <br>{{ implode(' · ', array_filter([$tarjeta['edificio'], $tarjeta['piso']])) }}
                            @endif
                        @else
                            <strong>Sin aula</strong>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
