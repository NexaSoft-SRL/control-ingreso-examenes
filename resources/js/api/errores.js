// Lectura de los errores de la API (sección 3.1 del contrato).

// El `message` del servidor o el texto por defecto.
export function mensajeDe(error, porDefecto = 'No se pudo completar') {
    const mensaje = error?.response?.data?.message;
    return typeof mensaje === 'string' && mensaje.trim() !== '' ? mensaje : porDefecto;
}

// Validación 422: `{ campo: 'primer mensaje' }`, para la prop `error` de Campo.
export function erroresDe(error) {
    if (error?.response?.status !== 422) return {};
    const errores = error.response.data?.errors ?? {};
    return Object.fromEntries(
        Object.entries(errores).map(([campo, mensajes]) => [
            campo,
            Array.isArray(mensajes) ? mensajes[0] : String(mensajes),
        ])
    );
}

export function estadoDe(error) {
    return error?.response?.status ?? null;
}

// El `codigo` de un 409 (`EXAMEN_CON_INGRESOS`, `SIN_AULAS`…).
export function codigoDe(error) {
    return error?.response?.data?.codigo ?? null;
}
