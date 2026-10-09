import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import SinPermiso from './SinPermiso';
import { renderConSesion, usuarioDePrueba } from '../test/apoyo';

describe('SinPermiso', () => {
    it('muestra el título, la cuenta con su rol y la vuelta a la entrada propia', () => {
        renderConSesion(<SinPermiso />, { usuario: usuarioDePrueba('Docente'), ruta: '/admin' });
        expect(screen.getByRole('heading', { level: 1, name: 'Sin permiso' })).toBeInTheDocument();
        expect(screen.getByText('Blanco Coca Leticia · Docente')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Volver' })).toHaveAttribute('href', '/examenes');
    });

    it('usa el nombre del rol tal cual, también si es un rol creado', () => {
        const cuenta = usuarioDePrueba('Docente', { rol: 'Coordinación', permisos: ['bitacora'] });
        renderConSesion(<SinPermiso />, { usuario: cuenta });
        expect(screen.getByText('Blanco Coca Leticia · Coordinación')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Volver' })).toHaveAttribute('href', '/bitacora');
    });

    it('sin una entrada propia vuelve a la pantalla de cuenta sin pantallas', () => {
        const cuenta = usuarioDePrueba('Docente', { permisos: ['habilitacion'] });
        renderConSesion(<SinPermiso />, { usuario: cuenta });
        expect(screen.getByRole('link', { name: 'Volver' })).toHaveAttribute('href', '/sin-acceso');
    });
});
