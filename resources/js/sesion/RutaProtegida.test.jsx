import { screen } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import RutaProtegida from './RutaProtegida';
import { renderConSesion, usuarioDePrueba } from '../test/apoyo';

function Donde() {
    return <p>Estoy en {useLocation().pathname}</p>;
}

function montar(ruta, opciones) {
    return renderConSesion(
        <Routes>
            <Route
                path="/examenes"
                element={
                    <RutaProtegida ruta="/examenes">
                        <p>Pantalla de exámenes</p>
                    </RutaProtegida>
                }
            />
            <Route
                path="/admin"
                element={
                    <RutaProtegida ruta="/admin">
                        <p>Pantalla de usuarios</p>
                    </RutaProtegida>
                }
            />
            <Route
                path="/bitacora"
                element={
                    <RutaProtegida permiso="bitacora">
                        <p>Pantalla de bitácora</p>
                    </RutaProtegida>
                }
            />
            <Route path="*" element={<Donde />} />
        </Routes>,
        { ruta, ...opciones }
    );
}

describe('RutaProtegida', () => {
    it('deja pasar a quien tiene la vista', () => {
        montar('/examenes', { usuario: usuarioDePrueba('Docente') });
        expect(screen.getByText('Pantalla de exámenes')).toBeInTheDocument();
    });

    it('sin sesión lleva al acceso', () => {
        montar('/examenes', { usuario: null });
        expect(screen.getByText('Estoy en /login')).toBeInTheDocument();
    });

    it('mientras carga la sesión no decide', () => {
        montar('/examenes', { usuario: null, sesion: { cargando: true } });
        expect(screen.getByRole('status', { name: 'Cargando' })).toBeInTheDocument();
        expect(screen.queryByText('Estoy en /login')).not.toBeInTheDocument();
    });

    it('una vista sin permiso muestra «Sin permiso» y no redirige', () => {
        montar('/admin', { usuario: usuarioDePrueba('Docente') });
        expect(screen.queryByText('Pantalla de usuarios')).not.toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
        expect(screen.getByText('Blanco Coca Leticia · Docente')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Volver' })).toHaveAttribute('href', '/examenes');
    });

    it('decide por permiso y no por el nombre del rol', () => {
        const creado = usuarioDePrueba('Docente', {
            rol: 'Coordinación',
            permisos: ['usuarios_roles'],
        });
        montar('/admin', { usuario: creado });
        expect(screen.getByText('Pantalla de usuarios')).toBeInTheDocument();

        const sinExamenes = usuarioDePrueba('Docente', { permisos: ['mis_grupos'] });
        montar('/examenes', { usuario: sinExamenes });
        expect(screen.queryByText('Pantalla de exámenes')).not.toBeInTheDocument();
    });

    it('exige el permiso indicado', () => {
        montar('/bitacora', { usuario: usuarioDePrueba('Administrador') });
        expect(screen.getByText('Pantalla de bitácora')).toBeInTheDocument();
    });

    it('sin el permiso indicado muestra «Sin permiso»', () => {
        montar('/bitacora', { usuario: usuarioDePrueba('Docente') });
        expect(screen.getByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
    });

    it('una cuenta sin pantallas va a /sin-acceso', () => {
        montar('/bitacora', { usuario: usuarioDePrueba('Auxiliar') });
        expect(screen.getByText('Estoy en /sin-acceso')).toBeInTheDocument();
    });
});
