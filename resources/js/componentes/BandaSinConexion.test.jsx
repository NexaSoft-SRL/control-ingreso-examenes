import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { SesionContexto } from '../sesion/SesionContexto';
import usarConexion from '../sinConexion/usarConexion';
import BandaSinConexion from './BandaSinConexion';

vi.mock('../sinConexion/usarConexion');

function montar(estado, permisos = []) {
    usarConexion.mockReturnValue({ pendientes: 0, enviadas: 0, ...estado });
    return render(
        <SesionContexto.Provider value={{ puede: (p) => permisos.includes(p) }}>
            <BandaSinConexion />
        </SesionContexto.Provider>
    );
}

beforeEach(() => document.documentElement.style.removeProperty('--banda'));
afterEach(() => vi.restoreAllMocks());

describe('Banda de sin conexión', () => {
    it('con conexión no muestra nada, pero deja lista la región que anuncia los cambios', () => {
        montar({ fase: 'conectado' });

        expect(screen.getByRole('status')).toBeEmptyDOMElement();
        expect(document.documentElement.style.getPropertyValue('--banda')).toBe('');
    });

    it('sin conexión, para quien controla el ingreso dice que las lecturas se guardan en el equipo', () => {
        montar({ fase: 'sin_conexion' }, ['punto_control']);

        const banda = screen.getByRole('status');
        expect(banda).toHaveAttribute('aria-live', 'polite');
        expect(banda).toHaveTextContent('Sin conexión');
        expect(banda).toHaveTextContent('Las lecturas se guardan en este equipo');
        expect(banda).toHaveTextContent('Conéctese pronto para sincronizar');
        expect(banda.querySelector('[data-fase="sin_conexion"]')).toBeInTheDocument();
    });

    it('para el resto dice que lo que haga no se guarda hasta que vuelva la red', () => {
        montar({ fase: 'sin_conexion' }, ['examenes']);

        expect(screen.getByRole('status')).toHaveTextContent(
            'Lo que haga no se guarda hasta que vuelva la red'
        );
        expect(screen.queryByText(/se guardan en este equipo/)).not.toBeInTheDocument();
    });

    it('con lecturas guardadas muestra cuántas esperan', () => {
        montar({ fase: 'sin_conexion', pendientes: 3 });

        expect(screen.getByRole('status')).toHaveTextContent('3 lecturas por enviar');
    });

    it('al volver la red dice que está sincronizando', () => {
        montar({ fase: 'enviando', pendientes: 2 });

        const banda = screen.getByRole('status');
        expect(banda).toHaveTextContent('Conexión recuperada');
        expect(banda).toHaveTextContent('Sincronizando 2 lecturas…');
    });

    it('cuando todo llegó dice cuántas lecturas se enviaron', () => {
        montar({ fase: 'sincronizado', enviadas: 1 });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Conexión recuperada · 1 lectura enviada.'
        );
    });

    it('si quedan lecturas sin enviar con red, pide abrir el punto de control', () => {
        montar({ fase: 'pendiente', pendientes: 1 });

        const banda = screen.getByRole('status');
        expect(banda).toHaveTextContent('1 lectura por enviar');
        expect(banda).toHaveTextContent('Abra el punto de control con conexión');
    });

    it('deja su alto en --banda mientras se ve, y lo quita al ocultarse', () => {
        const { rerender } = montar({ fase: 'sin_conexion' });
        expect(document.documentElement.style.getPropertyValue('--banda')).toMatch(/px$/);

        usarConexion.mockReturnValue({ fase: 'conectado', pendientes: 0, enviadas: 0 });
        rerender(
            <SesionContexto.Provider value={{ puede: () => false }}>
                <BandaSinConexion />
            </SesionContexto.Provider>
        );

        expect(document.documentElement.style.getPropertyValue('--banda')).toBe('');
    });
});
