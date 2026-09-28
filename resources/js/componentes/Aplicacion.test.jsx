import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import Aplicacion from './Aplicacion';
import { guardarSesion, limpiarSesion } from './sesion.js';

afterEach(() => {
    limpiarSesion();
    window.history.pushState({}, '', '/');
});

const TODOS_LOS_PERMISOS = [
    'padron_estudiantes',
    'asignaturas_ambientes',
    'usuarios_roles',
    'bitacora',
];

function iniciarSesion(permisos = TODOS_LOS_PERMISOS) {
    guardarSesion({ id: 1, name: 'Administrador', permisos });
}

describe('Aplicacion', () => {
    it('renderiza la pantalla inicial de usuarios y roles', () => {
        iniciarSesion();
        render(<Aplicacion />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Usuarios y roles',
            })
        ).toBeInTheDocument();

        expect(screen.getByText('Sistema Institucional de Verificación')).toBeInTheDocument();

        expect(screen.getByText('UMSS FCyT')).toBeInTheDocument();
    });

    it('sin sesión iniciada manda al login', () => {
        window.history.pushState({}, '', '/admin/bitacora');
        render(<Aplicacion />);

        expect(
            screen.getByRole('heading', { name: 'Control de ingreso a exámenes' })
        ).toBeInTheDocument();
        expect(window.location.pathname).toBe('/login');
    });

    it('el padrón reúne el registro de estudiantes y la carga masiva', () => {
        iniciarSesion();
        window.history.pushState({}, '', '/admin/padron');
        render(<Aplicacion />);

        expect(screen.getByText('Gestión del padrón estudiantil')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Carga masiva' }));

        expect(window.location.pathname).toBe('/admin/padron/carga-masiva');
        expect(
            screen.getByText('Carga masiva de estudiantes desde un archivo')
        ).toBeInTheDocument();
    });

    it('muestra la opción de cerrar sesión en las pantallas de administración', () => {
        iniciarSesion();
        render(<Aplicacion />);

        expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeInTheDocument();
    });

    it('el menú solo ofrece las secciones que el rol puede abrir', () => {
        iniciarSesion(['padron_estudiantes']);
        window.history.pushState({}, '', '/admin/padron');
        render(<Aplicacion />);

        expect(screen.getByRole('button', { name: 'Padrón' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Usuarios y roles' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Bitácora' })).toBeNull();
    });

    it('entra por la primera pantalla habilitada para el rol', () => {
        iniciarSesion(['bitacora']);
        render(<Aplicacion />);

        expect(window.location.pathname).toBe('/admin/bitacora');
    });

    it('al rol sin ninguna sección habilitada se lo dice y le ofrece salir', () => {
        guardarSesion({ id: 2, name: 'Docente', rol: 'Docente', permisos: ['habilitacion'] });
        render(<Aplicacion />);

        expect(window.location.pathname).toBe('/sin-permiso');
        expect(screen.getByRole('alert')).toHaveTextContent(
            'Tu rol (Docente) todavía no tiene ninguna sección habilitada.'
        );
        expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeInTheDocument();
    });

    it('la negativa por falta de permiso se explica en pantalla', () => {
        iniciarSesion(['bitacora']);
        window.history.pushState({}, '', '/sin-permiso');
        render(<Aplicacion />);

        expect(screen.getByRole('heading', { name: 'Sección no habilitada' })).toBeInTheDocument();
        expect(screen.getByRole('alert')).toHaveTextContent(
            'Tu rol no tiene acceso a esta sección.'
        );
    });
});
