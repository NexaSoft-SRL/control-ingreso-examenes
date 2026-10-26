<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Aviso de examen · {{ $credencial->examen }}</title>
</head>
<body style="font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; color: #1e293b; margin: 0; padding: 24px; background: #f1f5f9;">
    <div style="max-width: 420px; margin: 0 auto; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 20px;">
        <p style="margin: 0; font-size: 12px; font-weight: 700; color: #1d4ed8;">
            UMSS{{ $credencial->facultad ? ' · '.$credencial->facultad : '' }}
        </p>
        <p style="margin: 4px 0 16px; font-size: 16px; font-weight: 700;">{{ $credencial->examen }}</p>

        <p style="margin: 0; font-size: 14px; font-weight: 700;">{{ $credencial->estudiante }}</p>
        <p style="margin: 2px 0 12px; font-size: 13px; color: #475569;">Código {{ $credencial->codigo }}</p>

        <p style="margin: 0; font-size: 14px;">
            {{ $credencial->fechaTexto }} · {{ $credencial->hora }} · {{ $credencial->duracion }} min
        </p>
        <p style="margin: 4px 0 16px; font-size: 14px;">
            @if ($credencial->aula)
                <strong>Aula {{ $credencial->aula->nombre }}</strong>@if ($credencial->aula->ubicacion()) · {{ $credencial->aula->ubicacion() }}@endif
            @else
                <strong>Sin aula</strong>
            @endif
        </p>

        @if ($aviso->normasMarcadas !== [] || $aviso->normas)
            <p style="margin: 0 0 4px; font-size: 13px; font-weight: 700;">Normas</p>
            @if ($aviso->normasMarcadas !== [])
                <ul style="margin: 0 0 8px; padding-left: 18px; font-size: 14px;">
                    @foreach ($aviso->normasMarcadas as $norma)
                        <li>{{ $norma }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($aviso->normas)
                <p style="margin: 0 0 16px; font-size: 14px; white-space: pre-line;">{{ $aviso->normas }}</p>
            @endif
        @endif

        @if ($aviso->recomendaciones)
            <p style="margin: 0 0 4px; font-size: 13px; font-weight: 700;">Recomendaciones</p>
            <p style="margin: 0; font-size: 14px; white-space: pre-line;">{{ $aviso->recomendaciones }}</p>
        @endif
    </div>
</body>
</html>
