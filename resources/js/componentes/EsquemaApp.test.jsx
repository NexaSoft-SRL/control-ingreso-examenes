import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import EsquemaApp from './EsquemaApp';
import { renderConSesion, usuarioDePrueba } from '../test/apoyo';

function Donde() {
    return <p>Estoy en {useLocation().pathname}</p>;
}

function montar(rol, ruta, cambios) {
    return renderConSesion(
        <Routes>
            <Route path="/login" element={<Donde />} />
            <Route
                path="*"
                element={
                    <EsquemaApp>
                        <Donde />
                    </EsquemaApp>
                }
            />
        </Routes>,
        { usuario: usuarioDePrueba(rol, cambios), ruta }
    );
}

const [lateral, inferior] = [0, 1];
const menus = () => screen.getAllByRole('navigation', { name: 'Principal' });
const enlaces = (menu) =>
    within(menu)
        .getAllByRole('link')
        .map((e) => e.textContent);

describe('EsquemaApp', () => {
    it('muestra el nombre, el rol y cerrar sesión arriba y en el menú lateral', () => {
        montar('Docente', '/examenes');

        expect(screen.getAllByText('Blanco Coca Leticia')).toHaveLength(2);
        expect(screen.getAllByText('Docente')).toHaveLength(2);
        expect(screen.getAllByText('BC')).toHaveLength(2);
        expect(screen.getAllByRole('button', { name: 'Cerrar sesión' })).toHaveLength(2);
        expect(screen.getByRole('banner')).toHaveClass('sticky', 'top-0');
        expect(screen.getByRole('complementary')).toHaveClass('md:sticky', 'md:h-dvh');
        expect(screen.getByText('Estoy en /examenes')).toBeInTheDocument();
    });

    it('el administrador ve sus dos secciones, tres vistas en la barra y el resto en «Más»', () => {
        montar('Administrador', '/periodo');

        expect(enlaces(menus()[lateral])).toEqual([
            'Período',
            'Aulas y mapa',
            'Docentes',
            'Padrón',
            'Usuarios y roles',
            'Bitácora',
        ]);
        expect(within(menus()[lateral]).getByText('Preparación')).toBeInTheDocument();
        expect(within(menus()[lateral]).queryByText('Docencia')).not.toBeInTheDocument();
        expect(within(menus()[lateral]).getByText('Sistema')).toBeInTheDocument();
        expect(enlaces(menus()[inferior])).toEqual(['Período', 'Docentes', 'Padrón']);

        const mas = within(menus()[inferior]).getByRole('button', { name: 'Más' });
        expect(mas).toHaveAttribute('aria-expanded', 'false');
        fireEvent.click(mas);
        const hoja = screen.getByText('Más', { selector: 'p' }).closest('div').parentElement;
        expect(
            within(hoja)
                .getAllByRole('link')
                .map((e) => e.textContent)
        ).toEqual(['Aulas y mapaPreparación', 'Usuarios y rolesSistema', 'BitácoraSistema']);

        fireEvent.click(within(hoja).getByRole('link', { name: /Bitácora/ }));
        expect(screen.getByText('Estoy en /bitacora')).toBeInTheDocument();
        expect(screen.queryByText('Más', { selector: 'p' })).not.toBeInTheDocument();
    });

    it('el docente tiene sus dos vistas en la barra, sin «Más» ni las ocultas', () => {
        montar('Docente', '/examenes');

        expect(enlaces(menus()[lateral])).toEqual(['Exámenes', 'Mis grupos']);
        expect(enlaces(menus()[inferior])).toEqual(['Exámenes', 'Mis grupos']);
        expect(screen.queryByRole('button', { name: 'Más' })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Habilitación' })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Registrar examen' })).not.toBeInTheDocument();
    });

    it('un rol creado ve las vistas de sus permisos y su nombre de rol tal cual', () => {
        montar('Docente', '/periodo', {
            rol: 'Coordinación',
            permisos: ['examenes', 'periodo_oferta'],
        });

        expect(enlaces(menus()[lateral])).toEqual(['Período', 'Exámenes']);
        expect(screen.getAllByText('Coordinación')).toHaveLength(2);
    });

    it('con una sola vista no hay barra inferior', () => {
        montar('Administrador', '/bitacora', { permisos: ['bitacora'] });

        expect(menus()).toHaveLength(1);
        expect(enlaces(menus()[lateral])).toEqual(['Bitácora']);
    });

    it('marca la vista actual y, en una vista oculta, la de la que se viene', () => {
        const { unmount } = montar('Docente', '/examenes/nuevo?examen=1');
        within(menus()[lateral])
            .getAllByRole('link')
            .forEach((enlace) => {
                const actual = enlace.textContent === 'Exámenes';
                expect(enlace.getAttribute('aria-current')).toBe(actual ? 'page' : null);
            });
        unmount();

        montar('Docente', '/habilitacion?examen=2');
        expect(within(menus()[inferior]).getByRole('link', { name: 'Exámenes' })).toHaveAttribute(
            'aria-current',
            'page'
        );
    });

    it('solo ofrece las vistas para las que la cuenta tiene permiso', () => {
        montar('Administrador', '/bitacora', { permisos: ['bitacora', 'usuarios_roles'] });
        expect(enlaces(menus()[lateral])).toEqual(['Usuarios y roles', 'Bitácora']);
    });

    it('lleva el logo y el nombre de la aplicación', () => {
        montar('Docente', '/examenes');
        expect(screen.getAllByRole('img', { name: 'Control de ingreso' })).toHaveLength(2);
        expect(screen.getByText('Control de ingreso')).toBeInTheDocument();
    });

    it('cerrar sesión llama a salir y lleva al acceso', async () => {
        const { sesion } = montar('Docente', '/examenes');

        fireEvent.click(screen.getAllByRole('button', { name: 'Cerrar sesión' })[0]);
        await waitFor(() => expect(screen.getByText('Estoy en /login')).toBeInTheDocument());
        expect(sesion.salir).toHaveBeenCalledTimes(1);
    });
});
