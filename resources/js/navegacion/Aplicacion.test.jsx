import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Aplicacion, { PAGINAS, Rutas } from './Aplicacion';
import { VISTAS } from './vistas';
import { ProveedorSesion } from '../sesion/SesionContexto';
import { api } from '../api/cliente';
import {
    CATALOGO_PERMISOS,
    errorHttp,
    simularApi,
    usuarioDePrueba,
    FACULTADES,
} from '../test/apoyo';

vi.mock('../api/cliente');

function montar(ruta, usuario) {
    simularApi(api, {
        'GET /auth/sesion': usuario
            ? { user: usuario }
            : errorHttp(401, { message: 'No hay una sesión activa.' }),
        'GET /facultades': { data: FACULTADES },
        'GET /bitacora': { data: [] },
    });
    return render(
        <MemoryRouter initialEntries={[ruta]}>
            <ProveedorSesion>
                <Rutas />
            </ProveedorSesion>
        </MemoryRouter>
    );
}

const titulo = (nombre) => screen.findByRole('heading', { level: 1, name: nombre });

// El título de cada pantalla, por ruta.
const TITULOS = {
    '/periodo': 'Período académico',
    '/aulas': 'Aulas y mapa',
    '/docentes': 'Docentes',
    '/estudiantes': 'Padrón',
    '/examenes': 'Exámenes',
    '/examenes/nuevo': 'Registrar examen',
    '/habilitacion': 'Habilitación',
    '/mis-grupos': 'Mis grupos',
    '/admin': 'Usuarios y roles',
    '/bitacora': 'Bitácora',
};

describe('Aplicacion', () => {
    beforeEach(() => window.history.replaceState(null, '', '/'));

    it('sin sesión, la raíz lleva al inicio de sesión por correo', async () => {
        montar('/', null);
        expect(await titulo('Control de ingreso')).toBeInTheDocument();
        expect(screen.getByLabelText(/Correo/)).toBeInTheDocument();
    });

    it('sin sesión, una ruta interna lleva al inicio de sesión', async () => {
        montar('/bitacora', null);
        expect(await screen.findByRole('button', { name: 'Iniciar sesión' })).toBeInTheDocument();
    });

    it.each([
        ['Administrador', 'Período académico'],
        ['Docente', 'Exámenes'],
    ])('la raíz lleva al %s a su entrada', async (rol, nombre) => {
        montar('/', usuarioDePrueba(rol));
        expect(await titulo(nombre)).toBeInTheDocument();
    });

    it('el auxiliar no tiene pantallas todavía', async () => {
        montar('/', usuarioDePrueba('Auxiliar'));
        expect(await titulo('Sin pantallas asignadas')).toBeInTheDocument();
        expect(screen.getByText('Mamani Torrez Diego · Auxiliar')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeInTheDocument();
    });

    it('una vista sin permiso muestra «Sin permiso» dentro del armazón', async () => {
        montar('/admin', usuarioDePrueba('Docente'));
        expect(await titulo('Sin permiso')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Volver' })).toHaveAttribute('href', '/examenes');
        expect(screen.queryByRole('heading', { name: 'Usuarios y roles' })).not.toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: 'Cerrar sesión' }).length).toBeGreaterThan(0);
        expect(screen.getAllByText('Docente').length).toBeGreaterThan(0);
    });

    it('un rol creado entra a las vistas de sus permisos y a ninguna otra', async () => {
        const cuenta = usuarioDePrueba('Docente', {
            rol: 'Coordinación',
            permisos: ['examenes', 'periodo_oferta'],
        });
        const { unmount } = montar('/', cuenta);
        expect(await titulo('Período académico')).toBeInTheDocument();
        unmount();

        montar('/habilitacion', cuenta);
        expect(await titulo('Sin permiso')).toBeInTheDocument();
    });

    it('una dirección desconocida lleva a la entrada de la cuenta', async () => {
        montar('/respaldo', usuarioDePrueba('Docente'));
        expect(await titulo('Exámenes')).toBeInTheDocument();
    });

    it('la tabla de rutas tiene una página por vista', () => {
        expect(Object.keys(PAGINAS).sort()).toEqual(VISTAS.map((v) => v.ruta).sort());
        expect(Object.keys(TITULOS).sort()).toEqual(VISTAS.map((v) => v.ruta).sort());
    });

    it('cada ruta tiene su página dentro del armazón', { timeout: 15000 }, async () => {
        for (const [ruta, nombre] of Object.entries(TITULOS)) {
            const { unmount } = montar(
                ruta,
                usuarioDePrueba('Administrador', { permisos: CATALOGO_PERMISOS })
            );
            expect(await titulo(nombre), ruta).toBeInTheDocument();
            expect(screen.getAllByRole('button', { name: 'Cerrar sesión' }).length).toBeGreaterThan(
                0
            );
            unmount();
        }
    });

    it('una cuenta sin permisos ve su nombre y puede cerrar sesión', async () => {
        montar('/', usuarioDePrueba('Docente', { permisos: [] }));
        expect(await titulo('Sin pantallas asignadas')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeInTheDocument();
    });

    it('monta con el enrutador del navegador', async () => {
        simularApi(api, { 'GET /auth/sesion': errorHttp(401) });
        render(<Aplicacion />);
        const formulario = (await screen.findByRole('button', { name: 'Iniciar sesión' })).closest(
            'form'
        );
        expect(within(formulario).getByLabelText(/Contraseña/)).toBeInTheDocument();
    });
});
