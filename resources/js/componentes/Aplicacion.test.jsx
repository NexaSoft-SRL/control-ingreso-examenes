import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import Aplicacion from './Aplicacion';
import { guardarSesion, limpiarSesion } from './sesion.js';

afterEach(() => {
    limpiarSesion();
    window.history.pushState({}, '', '/');
});

describe('Aplicacion', () => {
    it('renderiza la pantalla inicial de usuarios y roles', () => {
        guardarSesion({ id: 1, name: 'Administrador' });
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
        guardarSesion({ id: 1, name: 'Administrador' });
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
        guardarSesion({ id: 1, name: 'Administrador' });
        render(<Aplicacion />);

        expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeInTheDocument();
    });
});
