import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import SugerenciaInstalar from './SugerenciaInstalar';
import { escucharInstalacion, olvidarInstalacion } from '../utiles/instalacion';

const agente = navigator.userAgent;

function comoIphone(si) {
    Object.defineProperty(navigator, 'userAgent', {
        configurable: true,
        value: si ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1' : agente,
    });
}

function instalada(si) {
    window.matchMedia = vi.fn((consulta) => ({
        matches: si && consulta === '(display-mode: standalone)',
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
}

// El aviso de Chrome: `prompt()` abre su ventana y `userChoice` dice qué eligió.
function avisoDelNavegador(eleccion = 'accepted') {
    const evento = new Event('beforeinstallprompt', { cancelable: true });
    evento.prompt = vi.fn(async () => {});
    evento.userChoice = Promise.resolve({ outcome: eleccion });
    act(() => {
        window.dispatchEvent(evento);
    });
    return evento;
}

beforeAll(() => escucharInstalacion());

beforeEach(() => {
    window.localStorage.clear();
    instalada(false);
    comoIphone(false);
});

afterEach(() => {
    act(() => olvidarInstalacion());
    comoIphone(false);
});

describe('SugerenciaInstalar', () => {
    it('sin aviso del navegador ni iPhone no se muestra', () => {
        render(<SugerenciaInstalar />);
        expect(screen.queryByText('Instalar la aplicación')).not.toBeInTheDocument();
    });

    it('con el aviso del navegador instala ahí mismo y después se oculta', async () => {
        render(<SugerenciaInstalar />);
        const evento = avisoDelNavegador();
        expect(evento.defaultPrevented).toBe(true);
        expect(screen.getByText('Instalar la aplicación')).toBeInTheDocument();

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: 'Instalar' }));
        });
        expect(evento.prompt).toHaveBeenCalled();
        expect(screen.queryByText('Instalar la aplicación')).not.toBeInTheDocument();
    });

    it('ya instalada no se muestra aunque el navegador avise', () => {
        instalada(true);
        render(<SugerenciaInstalar />);
        avisoDelNavegador();
        expect(screen.queryByText('Instalar la aplicación')).not.toBeInTheDocument();
    });

    it('en iPhone muestra los pasos de Safari', () => {
        comoIphone(true);
        render(<SugerenciaInstalar />);
        fireEvent.click(screen.getByRole('button', { name: 'Instalar' }));

        const pasos = within(screen.getByRole('dialog'));
        expect(pasos.getByText('Compartir')).toBeInTheDocument();
        expect(pasos.getByText('Agregar a inicio')).toBeInTheDocument();
    });

    it('cerrada no vuelve a aparecer en este navegador', () => {
        const { unmount } = render(<SugerenciaInstalar />);
        avisoDelNavegador();
        fireEvent.click(screen.getByRole('button', { name: 'No instalar' }));
        expect(screen.queryByText('Instalar la aplicación')).not.toBeInTheDocument();
        unmount();

        render(<SugerenciaInstalar />);
        expect(screen.queryByText('Instalar la aplicación')).not.toBeInTheDocument();
    });
});
