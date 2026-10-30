<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { font-size: 17px; margin: 0 0 4px; }
        .meta { color: #475569; margin-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        .total { margin-top: 10px; text-align: right; }
    </style>
</head>
<body>
    <h1>Bitácora del examen</h1>
    <div class="meta">
        {{ $metadatos['examen'] }} · Fecha del examen: {{ $metadatos['fecha'] }} · Emitido: {{ $metadatos['emitido'] }}
    </div>
    <table>
        <thead>
            <tr>
                @foreach ($encabezados as $encabezado)
                    <th>{{ $encabezado }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    @foreach ($fila as $celda)
                        <td>{{ $celda }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="total">Total: {{ $metadatos['total'] }} evento(s)</p>
</body>
</html>
