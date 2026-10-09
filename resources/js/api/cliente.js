import axios from 'axios';

// Cliente único de la API. Sesión por cookie: axios toma el X-XSRF-TOKEN de
// la cookie XSRF-TOKEN. Las páginas importan `api` de aquí.
export const api = axios.create({
    baseURL: '/api',
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    withXSRFToken: true,
});

export const SIN_SESION = 'sin_sesion';

const oyentes = new Set();

// `oyente(evento)` recibe SIN_SESION. Devuelve la baja.
export function suscribirSesion(oyente) {
    oyentes.add(oyente);
    return () => oyentes.delete(oyente);
}

function emitir(evento) {
    oyentes.forEach((oyente) => oyente(evento));
}

// 401 o 419 fuera de /auth/ → la sesión terminó. Una petición con
// `{ silencioso: true }` no emite nada.
export function interpretarError(error) {
    const estado = error?.response?.status;
    const ruta = String(error?.config?.url ?? '');
    const deAcceso = /(^|\/)auth\//.test(ruta);

    if (!error?.config?.silencioso) {
        if ((estado === 401 || estado === 419) && !deAcceso) emitir(SIN_SESION);
    }

    return Promise.reject(error);
}

api.interceptors.response.use((respuesta) => respuesta, interpretarError);

export default api;
