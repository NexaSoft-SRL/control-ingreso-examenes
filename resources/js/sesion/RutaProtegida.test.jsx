import { screen } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import RutaProtegida from './RutaProtegida';
import { renderConSesion, usuarioDePrueba } from '../test/apoyo';

function Donde() {
    const { pathname, search } = useLocation();
    return (
        <p>
            Estoy en {pathname}
            {search}
        </p>
    );
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
                path="/control"
                element={
                    <RutaProtegida ruta="/control">
                        <p>Control del docente</p>
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

    it('con contraseña temporal lleva al primer ingreso', () => {
        montar('/examenes', {
            usuario: usuarioDePrueba('Docente', { debe_cambiar_contrasena: true }),
        });
        expect(screen.getByText('Estoy en /primer-ingreso')).toBeInTheDocument();
    });

    it('una vista sin permiso muestra «Sin permiso» y no redirige', () => {
        montar('/examenes', { usuario: usuarioDePrueba('Administrador') });
        expect(screen.queryByText('Pantalla de exámenes')).not.toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
        expect(screen.getByText('Administración académica · Administrador')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Volver' })).toHaveAttribute('href', '/periodo');
    });

    it('quien solo atiende un aula va de la ruta del docente a su gemela, con los parámetros', () => {
        montar('/control?aula=30', { usuario: usuarioDePrueba('Auxiliar') });
        expect(screen.queryByText('Control del docente')).not.toBeInTheDocument();
        expect(screen.getByText('Estoy en /ingreso?aula=30')).toBeInTheDocument();
    });

    it('un rol creado pasa por sus permisos, no por su nombre', () => {
        const cuenta = usuarioDePrueba('Docente', {
            rol: 'Coordinación',
            permisos: ['examenes', 'periodo_oferta'],
        });
        montar('/examenes', { usuario: cuenta });
        expect(screen.getByText('Pantalla de exámenes')).toBeInTheDocument();
    });

    it('sin el permiso indicado muestra «Sin permiso»', () => {
        montar('/bitacora', { usuario: usuarioDePrueba('Docente') });
        expect(screen.queryByText('Pantalla de bitácora')).not.toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
    });

    it('exige el permiso indicado', () => {
        montar('/bitacora', { usuario: usuarioDePrueba('Administrador') });
        expect(screen.getByText('Pantalla de bitácora')).toBeInTheDocument();
    });

    it('una cuenta sin pantallas va a /sin-acceso', () => {
        montar('/bitacora', { usuario: usuarioDePrueba('Docente', { permisos: [] }) });
        expect(screen.getByText('Estoy en /sin-acceso')).toBeInTheDocument();
    });
});
