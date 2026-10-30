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

    it('la pestaña dice la pantalla, con el mismo título del menú', () => {
        montar('Docente', '/examenes/nuevo');
        expect(document.title).toBe('Registrar examen · Control de ingreso a exámenes');
    });

    it('el administrador ve las secciones permitidas y tres vistas en la barra', () => {
        montar('Administrador', '/periodo');

        expect(enlaces(menus()[lateral])).toEqual([
            'Período',
            'Oferta',
            'Aulas y mapa',
            'Docentes',
            'Padrón',
            'Bitácora de exámenes',
            'Usuarios y roles',
            'Bitácora',
            'Respaldo',
        ]);
        expect(within(menus()[lateral]).getByText('Preparación')).toBeInTheDocument();
        expect(within(menus()[lateral]).getByText('Docencia')).toBeInTheDocument();
        expect(within(menus()[lateral]).getByText('Sistema')).toBeInTheDocument();
        expect(enlaces(menus()[inferior])).toEqual(['Período', 'Oferta', 'Docentes', 'Padrón']);

        const mas = within(menus()[inferior]).getByRole('button', { name: 'Más' });
        expect(mas).toHaveAttribute('aria-expanded', 'false');
        fireEvent.click(mas);
        const hoja = screen.getByText('Más', { selector: 'p' }).closest('div').parentElement;
        expect(
            within(hoja)
                .getAllByRole('link')
                .map((e) => e.textContent)
        ).toEqual([
            'Aulas y mapaPreparación',
            'Bitácora de exámenesDocencia',
            'Usuarios y rolesSistema',
            'BitácoraSistema',
            'RespaldoSistema',
        ]);

        const enlaceBitacora = within(hoja)
            .getAllByRole('link')
            .find((enlace) => enlace.getAttribute('href') === '/bitacora');
        expect(enlaceBitacora).toBeInTheDocument();
        fireEvent.click(enlaceBitacora);
        expect(screen.getByText('Estoy en /bitacora')).toBeInTheDocument();
        expect(screen.queryByText('Más', { selector: 'p' })).not.toBeInTheDocument();
    });

    it('el docente ve Reportes en la barra y las vistas restantes en «Más»', () => {
        montar('Docente', '/examenes');

        expect(enlaces(menus()[lateral])).toEqual([
            'Exámenes',
            'Reportes',
            'Bitácora de exámenes',
            'Mis grupos',
            'En curso',
            'Punto de control',
            'Mi aula',
        ]);
        expect(enlaces(menus()[inferior])).toEqual([
            'Exámenes',
            'Reportes',
            'En curso',
            'Punto de control',
        ]);
        fireEvent.click(within(menus()[inferior]).getByRole('button', { name: 'Más' }));
        const hoja = screen.getByText('Más', { selector: 'p' }).closest('div').parentElement;
        expect(
            within(hoja)
                .getAllByRole('link')
                .map((enlace) => enlace.textContent)
        ).toEqual(['Bitácora de exámenesDocencia', 'Mi aulaExámenes', 'Mis gruposDocencia']);
        expect(screen.queryByRole('link', { name: 'Habilitación' })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Registrar examen' })).not.toBeInTheDocument();
    });

    it('un rol creado ve las vistas de sus permisos y su nombre de rol tal cual', () => {
        montar('Docente', '/periodo', {
            rol: 'Coordinación',
            permisos: ['examenes', 'periodo_oferta'],
        });

        expect(enlaces(menus()[lateral])).toEqual(['Período', 'Oferta', 'Exámenes', 'En curso']);
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
