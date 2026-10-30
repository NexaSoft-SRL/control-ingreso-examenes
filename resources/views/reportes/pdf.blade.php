<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 30px 42px; }
        body { color: #172033; font: 9px DejaVu Sans, sans-serif; }
        h1 { font-size: 17px; margin: 0 0 5px; }
        p { margin: 3px 0; }
        .meta { color: #475569; margin-bottom: 16px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #eff6ff; font-weight: bold; }
        .empty { color: #64748b; padding: 18px 0; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <p>Universidad Mayor de San Simón · Control de ingreso a exámenes</p>
    <div class="meta">
        <p>{{ $reporte['asignatura']['nombre'] }} · {{ $reporte['tipo'] }} · {{ $reporte['fecha'] }} · {{ $reporte['hora'] }}</p>
        <p>Período {{ $reporte['periodo'] }} · Emitido el {{ $emitido }}</p>
    </div>
    @if ($filas === [])
        <p class="empty">Sin datos para estos filtros.</p>
    @else
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
    @endif
</body>
</html>
