<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 25px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 17px; margin-bottom: 8px; }
        .institucion { color: #555; margin-bottom: 12px; }
        .filtro { margin-bottom: 3px; }
        .emitido { margin: 10px 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; overflow-wrap: anywhere; }
        th { background: #eee; font-weight: bold; }
        tr { page-break-inside: avoid; }
        .totales { margin-top: 14px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $tabla->titulo }}</h1>
    <div class="institucion">Universidad Mayor de San Simón · Control de ingreso a exámenes</div>

    @foreach ($tabla->filtros as $filtro)
        <div class="filtro">{{ $filtro }}</div>
    @endforeach

    <div class="emitido">Emitido: {{ $tabla->emitidoEn }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($tabla->encabezados as $encabezado)
                    <th>{{ $encabezado }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($tabla->filas as $fila)
                <tr>
                    @foreach ($fila as $valor)
                        <td>{{ $valor }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($tabla->encabezados) }}">Sin datos para estos filtros.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @foreach ($tabla->totales as $total)
        <div class="totales">{{ $total }}</div>
    @endforeach
</body>
</html>
