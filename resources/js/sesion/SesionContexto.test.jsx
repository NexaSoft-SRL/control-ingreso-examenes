import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ProveedorSesion, usarFacultades, usarSesion } from './SesionContexto';
import { api, suscribirSesion, CAMBIAR_CONTRASENA, SIN_SESION } from '../api/cliente';
import { guardarSesion, sesionGuardada } from '../sinConexion/almacen';
import {
    errorHttp,
    respuestaDiferida,
    simularApi,
    usuarioDePrueba,
    FACULTADES,
} from '../test/apoyo';

vi.mock('../api/cliente');

function Sonda() {
    const { usuario, entrar, salir, puede } = usarSesion();
    const facultades = usarFacultades();
    return (
        <div>
            <p>{usuario ? `${usuario.nombre} · ${usuario.rol}` : 'Sin sesión'}</p>
            <p>{usuario?.debe_cambiar_contrasena ? 'Cambio obligatorio' : 'Contraseña propia'}</p>
            <p>{puede('examenes') ? 'Puede exámenes' : 'No puede exámenes'}</p>
            <p>Facultades: {facultades.length}</p>
            <button
                type="button"
                onClick={() => entrar(' Leticia.Blanco ', 'secreta').catch(() => {})}
            >
                Entrar
            </button>
            <button type="button" onClick={salir}>
                Salir
            </button>
        </div>
    );
}

const montar = () =>
    render(
        <ProveedorSesion>
            <Sonda />
        </ProveedorSesion>
    );

describe('ProveedorSesion', () => {
    it('muestra la pantalla de carga mientras responde la sesión', async () => {
        const diferida = respuestaDiferida();
        simularApi(api, {
            'GET /auth/sesion': () => diferida.promesa,
            'GET /facultades': { data: [] },
        });
        montar();

        expect(screen.getByRole('status', { name: 'Cargando' })).toHaveAttribute(
            'aria-busy',
            'true'
        );
        expect(screen.queryByText('Sin sesión')).not.toBeInTheDocument();

        await act(async () => diferida.resolver({ user: usuarioDePrueba('Docente') }));
        expect(screen.getByText('Blanco Coca Leticia · Docente')).toBeInTheDocument();
    });

    it('toma la cuenta de GET /auth/sesion y carga las facultades una vez', async () => {
        simularApi(api, {
            'GET /auth/sesion': { user: usuarioDePrueba('Docente') },
            'GET /facultades': { data: FACULTADES },
        });
        montar();

        expect(await screen.findByText('Facultades: 4')).toBeInTheDocument();
        expect(screen.getByText('Puede exámenes')).toBeInTheDocument();
        expect(api.get.mock.calls.filter(([ruta]) => ruta === '/facultades')).toHaveLength(1);
    });

    it('con 401 queda sin sesión y no pide nada más', async () => {
        simularApi(api, {
            'GET /auth/sesion': errorHttp(401, { message: 'No hay una sesión activa.' }),
        });
        montar();

        expect(await screen.findByText('Sin sesión')).toBeInTheDocument();
        expect(screen.getByText('No puede exámenes')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledTimes(1);
    });

    it('entrar envía el usuario en minúsculas y guarda la cuenta; salir la borra', async () => {
        simularApi(api, {
            'GET /auth/sesion': errorHttp(401),
            'POST /auth/login': {
                message: 'Autenticación correcta.',
                user: usuarioDePrueba('Auxiliar'),
            },
            'POST /auth/logout': { message: 'Sesión cerrada.' },
            'GET /facultades': { data: [] },
        });
        montar();
        await screen.findByText('Sin sesión');

        fireEvent.click(screen.getByRole('button', { name: 'Entrar' }));
        expect(await screen.findByText('Mamani Torrez Diego · Auxiliar')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/auth/login', {
            usuario: 'leticia.blanco',
            password: 'secreta',
        });

        fireEvent.click(screen.getByRole('button', { name: 'Salir' }));
        expect(await screen.findByText('Sin sesión')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/auth/logout');
    });

    it('no guarda nada en el navegador de quien no controla el ingreso', async () => {
        const guardar = vi.spyOn(Storage.prototype, 'setItem');
        simularApi(api, {
            'GET /auth/sesion': { user: usuarioDePrueba('Administrador') },
            'GET /facultades': { data: [] },
        });
        montar();
        await screen.findByText('Administración académica · Administrador');

        expect(guardar).not.toHaveBeenCalled();
    });

    describe('sin internet', () => {
        beforeEach(() => window.localStorage.clear());

        const SIN_INTERNET = new Error('Network Error');

        it('de quien controla el ingreso guarda la cuenta, para abrir la puerta si se recarga sin internet', async () => {
            simularApi(api, {
                'GET /auth/sesion': { user: usuarioDePrueba('Auxiliar') },
                'GET /facultades': { data: [] },
            });
            const primera = montar();
            await screen.findByText(/Auxiliar/);
            primera.unmount();

            simularApi(api, {
                'GET /auth/sesion': SIN_INTERNET,
                'GET /facultades': SIN_INTERNET,
            });
            montar();

            expect(await screen.findByText(/Auxiliar/)).toBeInTheDocument();
            expect(screen.queryByText('Sin sesión')).not.toBeInTheDocument();
        });

        it('si el servidor responde que no hay sesión, no vale la cuenta guardada', async () => {
            guardarSesion(usuarioDePrueba('Auxiliar'));
            simularApi(api, { 'GET /auth/sesion': errorHttp(401, { message: 'No hay sesión.' }) });
            montar();

            expect(await screen.findByText('Sin sesión')).toBeInTheDocument();
            expect(sesionGuardada()).toBeNull();
        });

        it('sin cuenta guardada y sin internet no hay sesión', async () => {
            simularApi(api, { 'GET /auth/sesion': SIN_INTERNET });
            montar();

            expect(await screen.findByText('Sin sesión')).toBeInTheDocument();
        });

        it('al salir se borra la cuenta guardada', async () => {
            simularApi(api, {
                'GET /auth/sesion': { user: usuarioDePrueba('Auxiliar') },
                'GET /facultades': { data: [] },
                'POST /auth/logout': { message: 'Sesión cerrada.' },
            });
            montar();
            await screen.findByText(/Auxiliar/);
            expect(sesionGuardada()).not.toBeNull();

            fireEvent.click(screen.getByRole('button', { name: 'Salir' }));
            await screen.findByText('Sin sesión');

            expect(sesionGuardada()).toBeNull();
        });

        it('si el cliente avisa que ya no hay sesión, se borra la cuenta guardada', async () => {
            simularApi(api, {
                'GET /auth/sesion': { user: usuarioDePrueba('Auxiliar') },
                'GET /facultades': { data: [] },
            });
            montar();
            await screen.findByText(/Auxiliar/);

            act(() => suscribirSesion.mock.calls[0][0](SIN_SESION));

            expect(await screen.findByText('Sin sesión')).toBeInTheDocument();
            expect(sesionGuardada()).toBeNull();
        });
    });

    it('reacciona a los eventos del cliente', async () => {
        simularApi(api, {
            'GET /auth/sesion': { user: usuarioDePrueba() },
            'GET /facultades': { data: [] },
        });
        montar();
        await screen.findByText('Blanco Coca Leticia · Docente');
        const oyente = suscribirSesion.mock.calls.at(-1)[0];

        act(() => oyente(CAMBIAR_CONTRASENA));
        expect(screen.getByText('Cambio obligatorio')).toBeInTheDocument();

        act(() => oyente(SIN_SESION));
        await waitFor(() => expect(screen.getByText('Sin sesión')).toBeInTheDocument());
    });

    it('con contraseña temporal no pide las facultades', async () => {
        simularApi(api, {
            'GET /auth/sesion': {
                user: usuarioDePrueba('Docente', { debe_cambiar_contrasena: true }),
            },
        });
        montar();

        expect(await screen.findByText('Cambio obligatorio')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledTimes(1);
    });
});
