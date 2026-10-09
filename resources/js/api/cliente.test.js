import { afterEach, describe, expect, it, vi } from 'vitest';
import { api, suscribirSesion, SIN_SESION } from './cliente';

// Un adaptador en lugar de la red: responde lo que la prueba le indique.
function responderCon(estado, datos = {}) {
    api.defaults.adapter = (config) => {
        const respuesta = { status: estado, data: datos, headers: {}, config };
        if (estado >= 200 && estado < 300) return Promise.resolve(respuesta);
        return Promise.reject(
            Object.assign(new Error(`HTTP ${estado}`), { response: respuesta, config })
        );
    };
}

describe('cliente de la API', () => {
    const bajas = [];
    const escuchar = () => {
        const oyente = vi.fn();
        bajas.push(suscribirSesion(oyente));
        return oyente;
    };

    afterEach(() => bajas.splice(0).forEach((baja) => baja()));

    it('apunta a /api y se identifica como petición del cliente', () => {
        expect(api.defaults.baseURL).toBe('/api');
        expect(api.defaults.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(api.defaults.headers.Accept).toBe('application/json');
        expect(api.defaults.withXSRFToken).toBe(true);
    });

    it('con 401 fuera de /auth/ avisa que no hay sesión y rechaza', async () => {
        const oyente = escuchar();
        responderCon(401, { message: 'No hay una sesión activa.' });

        await expect(api.get('/examenes')).rejects.toMatchObject({ response: { status: 401 } });
        expect(oyente).toHaveBeenCalledWith(SIN_SESION);
    });

    it('trata el 419 como sesión vencida', async () => {
        const oyente = escuchar();
        responderCon(419);

        await expect(api.post('/examenes', {})).rejects.toBeTruthy();
        expect(oyente).toHaveBeenCalledWith(SIN_SESION);
    });

    it('no avisa por un 401 de las rutas de acceso', async () => {
        const oyente = escuchar();
        responderCon(401, { message: 'Credenciales incorrectas.' });

        await expect(api.post('/auth/login', {})).rejects.toBeTruthy();
        await expect(api.get('/auth/sesion')).rejects.toBeTruthy();
        expect(oyente).not.toHaveBeenCalled();
    });

    it('un 409 de estado no toca la sesión', async () => {
        const oyente = escuchar();
        responderCon(409, { codigo: 'EXAMEN_CON_INGRESOS' });

        await expect(api.put('/examenes/1', {})).rejects.toBeTruthy();
        expect(oyente).not.toHaveBeenCalled();
    });

    it('una petición silenciosa no emite eventos', async () => {
        const oyente = escuchar();
        responderCon(401);

        await expect(api.get('/bitacora', { silencioso: true })).rejects.toBeTruthy();
        expect(oyente).not.toHaveBeenCalled();
    });

    it('deja pasar las respuestas correctas y permite darse de baja', async () => {
        const oyente = vi.fn();
        const baja = suscribirSesion(oyente);
        responderCon(200, { data: [1] });
        await expect(api.get('/facultades')).resolves.toMatchObject({ data: { data: [1] } });

        baja();
        responderCon(401);
        await expect(api.get('/facultades')).rejects.toBeTruthy();
        expect(oyente).not.toHaveBeenCalled();
    });
});
