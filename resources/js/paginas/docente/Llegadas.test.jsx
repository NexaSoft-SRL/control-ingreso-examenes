import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Llegadas from './Llegadas';

const LLEGADAS = [
    { hora: '07:45', ingresos: 3 },
    { hora: '07:50', ingresos: 12 },
    { hora: '07:55', ingresos: 0 },
    { hora: '08:00', ingresos: 5 },
];

describe('Llegadas', () => {
    it('sin ingresos no se muestra', () => {
        const { container } = render(<Llegadas llegadas={[]} inicio="08:00" />);
        expect(container).toBeEmptyDOMElement();
    });

    it('da el total, marca el inicio y deja la tabla para lectores de pantalla', () => {
        render(<Llegadas llegadas={LLEGADAS} inicio="08:00" />);

        expect(screen.getByRole('heading', { name: 'Llegadas · 20' })).toBeInTheDocument();
        expect(screen.getByText('Inicio 08:00')).toBeInTheDocument();
        const tabla = screen.getByRole('table', { name: 'Llegadas por tramo de cinco minutos' });
        expect(within(tabla).getAllByRole('row')).toHaveLength(5);
        expect(within(tabla).getByText('07:50').closest('tr')).toHaveTextContent('12');
    });

    it('al pasar sobre un tramo dice su intervalo y cuántos entraron', () => {
        const { container } = render(<Llegadas llegadas={LLEGADAS} inicio="08:00" />);
        const tramos = container.querySelectorAll('.flex-1.items-end');

        fireEvent.mouseEnter(tramos[1]);

        expect(screen.getByText('07:50–07:55 · 12')).toBeInTheDocument();
    });
});
