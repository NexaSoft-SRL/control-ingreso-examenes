import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAviso } from './Aviso';

function Prueba() {
    const [aviso, avisar] = useAviso(3200);
    return (
        <>
            <button type="button" onClick={() => avisar('Reporte descargado')}>
                Bien
            </button>
            <button type="button" onClick={() => avisar('No se pudo guardar', 'error')}>
                Mal
            </button>
            {aviso}
        </>
    );
}

describe('useAviso', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });
    afterEach(() => {
        vi.useRealTimers();
    });

    it('muestra el aviso y lo cierra solo', () => {
        render(<Prueba />);
        expect(screen.queryByRole('status')).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Bien' }));
        expect(screen.getByRole('status')).toHaveTextContent('Reporte descargado');

        act(() => vi.advanceTimersByTime(3200));
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });

    it('distingue el tono de error', () => {
        render(<Prueba />);
        fireEvent.click(screen.getByRole('button', { name: 'Mal' }));

        expect(screen.getByText('No se pudo guardar').parentElement).toHaveClass('bg-danger-600');
    });
});
