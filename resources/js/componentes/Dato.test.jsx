import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Dato from './Dato';

describe('Dato', () => {
    it('muestra la cifra con su etiqueta', () => {
        render(<Dato etiqueta="Habilitados" valor={255} tono="exito" />);

        expect(screen.getByText('Habilitados')).toBeInTheDocument();
        expect(screen.getByText('255').parentElement).toHaveClass('bg-success-50');
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('con onClick es un botón', () => {
        const alPulsar = vi.fn();
        render(<Dato etiqueta="Sin cuenta" valor="636" onClick={alPulsar} />);

        fireEvent.click(screen.getByRole('button', { name: /Sin cuenta/ }));
        expect(alPulsar).toHaveBeenCalledTimes(1);
    });
});
