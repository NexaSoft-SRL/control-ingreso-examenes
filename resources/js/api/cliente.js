import axios from 'axios';

// Cliente único de la API. Sesión por cookie: axios toma el X-XSRF-TOKEN de
// la cookie XSRF-TOKEN. Las páginas importan `api` de aquí.
export const api = axios.create({
    baseURL: '/api',
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    withXSRFToken: true,
});

// El estado de la red según las peticiones: cuántas fallaron seguidas sin
// recibir respuesta alguna. Un 401, 422 o 500 es una respuesta: el servidor
// está ahí. Sin respuesta (o 502, 503 y 504 de un intermediario) no hubo
// conexión. Una petición cancelada a propósito no cuenta.
let fallosDeRed = 0;
const oyentesDeRed = new Set();

export const fallosDeRedSeguidos = () => fallosDeRed;

// `oyente(fallosSeguidos)` se llama cada vez que el contador cambia.
export function suscribirRed(oyente) {
    oyentesDeRed.add(oyente);
    return () => oyentesDeRed.delete(oyente);
}

function anotarRed(huboRespuesta) {
    const siguiente = huboRespuesta ? 0 : fallosDeRed + 1;
    if (siguiente === fallosDeRed) return;
    fallosDeRed = siguiente;
    oyentesDeRed.forEach((oyente) => oyente(fallosDeRed));
}

function sinRespuestaDelServidor(error) {
    if (axios.isCancel(error)) return false;
    const estado = error?.response?.status;
    return !error?.response || estado === 502 || estado === 503 || estado === 504;
}

export const SIN_SESION = 'sin_sesion';
export const CAMBIAR_CONTRASENA = 'cambiar_contrasena';

const oyentes = new Set();

// `oyente(evento)` recibe SIN_SESION o CAMBIAR_CONTRASENA. Devuelve la baja.
export function suscribirSesion(oyente) {
    oyentes.add(oyente);
    return () => oyentes.delete(oyente);
}

function emitir(evento) {
    oyentes.forEach((oyente) => oyente(evento));
}

// 401 o 419 fuera de /auth/ → la sesión terminó; 409 con
// `debe_cambiar_contrasena` → falta definir la contraseña propia. Una
// petición con `{ silencioso: true }` no emite nada.
export function interpretarError(error) {
    if (!axios.isCancel(error)) anotarRed(!sinRespuestaDelServidor(error));
    const estado = error?.response?.status;
    const ruta = String(error?.config?.url ?? '');
    const deAcceso = /(^|\/)auth\//.test(ruta);

    if (!error?.config?.silencioso) {
        if ((estado === 401 || estado === 419) && !deAcceso) emitir(SIN_SESION);
        if (estado === 409 && error.response.data?.debe_cambiar_contrasena) {
            emitir(CAMBIAR_CONTRASENA);
        }
    }

    return Promise.reject(error);
}

api.interceptors.response.use((respuesta) => {
    anotarRed(true);
    return respuesta;
}, interpretarError);

export default api;
