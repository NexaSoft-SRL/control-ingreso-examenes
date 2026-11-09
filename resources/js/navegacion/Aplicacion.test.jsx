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

// Aquí se prueba la tabla de rutas, no las páginas: cada una se sustituye por
// su título, y así esta prueba no depende de lo que cada página pida a la API.
const { pagina } = vi.hoisted(() => ({
    pagina: (titulo) => () => ({ default: () => <h1>{titulo}</h1> }),
}));
vi.mock('../paginas/admin/Periodo', pagina('Período'));
vi.mock('../paginas/admin/Oferta', pagina('Oferta'));
vi.mock('../paginas/admin/Aulas', pagina('Aulas y mapa'));
vi.mock('../paginas/admin/Docentes', pagina('Docentes'));
vi.mock('../paginas/estudiantes/Padron', pagina('Padrón'));
vi.mock('../paginas/reportes/Reportes', pagina('Reportes'));
vi.mock('../paginas/admin/Usuarios', pagina('Usuarios y roles'));
vi.mock('../paginas/admin/Bitacora', pagina('Bitácora'));
vi.mock('../paginas/admin/Respaldo', pagina('Respaldo'));
vi.mock('../paginas/examenes/Examenes', pagina('Exámenes'));
vi.mock('../paginas/examenes/RegistrarExamen', pagina('Registrar examen'));
vi.mock('../paginas/habilitacion/Habilitacion', pagina('Habilitación'));
vi.mock('../paginas/docente/CodigosQr', pagina('Códigos QR'));
vi.mock('../paginas/docente/EnCurso', pagina('En curso'));
vi.mock('../paginas/monitoreo/Asistencia', pagina('Asistencia del aula'));
vi.mock('../paginas/ingreso/PuntoControl', pagina('Punto de control'));
vi.mock('../paginas/docente/MisGrupos', pagina('Mis grupos'));
vi.mock('../paginas/reportes/ReportesDocente', pagina('Reportes del docente'));

function montar(ruta, usuario) {
    simularApi(api, {
        'GET /auth/sesion': usuario
            ? { user: usuario }
            : errorHttp(401, { message: 'No hay una sesión activa.' }),
        'GET /facultades': { data: FACULTADES },
        'GET /puerta/aulas': errorHttp(401),
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

// El título de la página de cada ruta.
const TITULOS = {
    '/periodo': 'Período',
    '/oferta': 'Oferta',
    '/aulas': 'Aulas y mapa',
    '/docentes': 'Docentes',
    '/estudiantes': 'Padrón',
    '/examenes': 'Exámenes',
    '/examenes/nuevo': 'Registrar examen',
    '/examenes/detalle': 'Examen',
    '/habilitacion': 'Habilitación',
    '/codigos-qr': 'Códigos QR',
    '/en-curso': 'En curso',
    '/en-curso/aula': 'Asistencia del aula',
    '/control': 'Punto de control',
    '/ingreso': 'Punto de control',
    '/mi-aula': 'Asistencia del aula',
    '/mis-grupos': 'Mis grupos',
    '/mis-reportes': 'Reportes del docente',
    '/reportes': 'Reportes',
    '/admin': 'Usuarios y roles',
    '/bitacora': 'Bitácora',
    '/respaldo': 'Respaldo',
};

describe('Aplicacion', () => {
    beforeEach(() => window.history.replaceState(null, '', '/'));

    it('sin sesión, la raíz lleva al inicio de sesión', async () => {
        montar('/', null);
        expect(await titulo('Control de exámenes')).toBeInTheDocument();
        expect(screen.getByLabelText(/Usuario/)).toBeInTheDocument();
    });

    it('sin sesión, una ruta interna lleva al inicio de sesión', async () => {
        montar('/bitacora', null);
        expect(await screen.findByRole('button', { name: 'Iniciar sesión' })).toBeInTheDocument();
    });

    it.each([
        ['Administrador', 'Período'],
        ['Docente', 'Exámenes'],
        ['Auxiliar', 'Punto de control'],
    ])('la raíz lleva al %s a su entrada', async (rol, nombre) => {
        montar('/', usuarioDePrueba(rol));
        expect(await titulo(nombre)).toBeInTheDocument();
    });

    it('una vista sin permiso muestra «Sin permiso» dentro del armazón', async () => {
        montar('/admin', usuarioDePrueba('Docente'));
        expect(await titulo('Sin permiso')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Volver' })).toHaveAttribute('href', '/examenes');
        expect(screen.queryByRole('heading', { name: 'Usuarios y roles' })).not.toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: 'Cerrar sesión' }).length).toBeGreaterThan(0);
    });

    it('el administrador no abre las pantallas de docencia ni sigue exámenes', async () => {
        for (const ruta of ['/examenes', '/habilitacion', '/en-curso', '/control', '/mis-grupos']) {
            const { unmount } = montar(ruta, usuarioDePrueba('Administrador'));
            expect(await titulo('Sin permiso'), ruta).toBeInTheDocument();
            unmount();
        }
    });

    it('un rol creado entra a las vistas de sus permisos y a ninguna otra', async () => {
        const cuenta = usuarioDePrueba('Docente', {
            rol: 'Coordinación',
            permisos: ['examenes', 'periodo_oferta'],
        });
        const { unmount } = montar('/', cuenta);
        expect(await titulo('Período')).toBeInTheDocument();
        unmount();

        montar('/habilitacion', cuenta);
        expect(await titulo('Sin permiso')).toBeInTheDocument();
    });

    it('quien toma el examen y quien atiende un aula llegan cada uno a su control', async () => {
        const { unmount } = montar('/ingreso?aula=30', usuarioDePrueba('Docente'));
        expect(await titulo('Punto de control')).toBeInTheDocument();
        expect(
            screen
                .getAllByRole('link', { name: /^(Punto de control|Control)$/ })
                .map((e) => e.getAttribute('href'))
        ).toEqual(['/control', '/control']);
        expect(document.title).toBe('Punto de control · Control de exámenes');
        unmount();

        montar('/en-curso/aula?examen=1&aula=30', usuarioDePrueba('Auxiliar'));
        expect(await titulo('Asistencia del aula')).toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: 'Mi aula' })[0]).toHaveAttribute(
            'aria-current',
            'page'
        );
    });

    it('una dirección desconocida lleva a la entrada de la cuenta', async () => {
        montar('/no-existe', usuarioDePrueba('Docente'));
        expect(await titulo('Exámenes')).toBeInTheDocument();
    });

    it('con contraseña temporal todo lleva al primer ingreso', async () => {
        montar('/examenes', usuarioDePrueba('Docente', { debe_cambiar_contrasena: true }));
        expect(await titulo('Contraseña nueva')).toBeInTheDocument();
        expect(screen.getByText('Primer ingreso · cambio obligatorio')).toBeInTheDocument();
    });

    it('la tabla de rutas tiene una página por vista', () => {
        expect(Object.keys(PAGINAS).sort()).toEqual(VISTAS.map((v) => v.ruta).sort());
        expect(Object.keys(TITULOS).sort()).toEqual(VISTAS.map((v) => v.ruta).sort());
    });

    it('cada ruta tiene su página dentro del armazón', { timeout: 15000 }, async () => {
        const cuentas = [
            usuarioDePrueba('Administrador', { permisos: CATALOGO_PERMISOS }),
            usuarioDePrueba('Auxiliar'),
        ];
        const vistas = new Set();
        for (const cuenta of cuentas) {
            for (const v of VISTAS.filter((vista) => cuenta.permisos.includes(vista.permiso))) {
                if (v.sin && cuenta.permisos.includes(v.sin)) continue;
                if (v.con && !cuenta.permisos.includes(v.con)) continue;
                const { unmount } = montar(v.ruta, cuenta);
                expect(await titulo(TITULOS[v.ruta]), v.ruta).toBeInTheDocument();
                expect(
                    screen.getAllByRole('button', { name: 'Cerrar sesión' }).length
                ).toBeGreaterThan(0);
                vistas.add(v.ruta);
                unmount();
            }
        }
        expect(vistas.size).toBe(VISTAS.length);
    });

    it('la ruta del acceso con código es pública', async () => {
        montar('/acceso-aula', null);
        expect(await titulo('Punto de control')).toBeInTheDocument();
        expect(screen.getByLabelText(/Código/)).toBeInTheDocument();
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
