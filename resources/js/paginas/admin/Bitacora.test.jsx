import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Bitacora from './Bitacora';

describe('Bitacora', () => {
    it('muestra el listado de eventos de ejemplo', () => {
        render(<Bitacora onNavigate={vi.fn()} />);

        expect(screen.getByRole('heading', { name: 'Bitácora' })).toBeInTheDocument();
        expect(screen.getByText('Mostrando 10 de 248 eventos')).toBeInTheDocument();
        expect(screen.getByText('Generación de códigos QR')).toBeInTheDocument();
    });

    it('filtra por usuario', () => {
        render(<Bitacora onNavigate={vi.fn()} />);

        fireEvent.change(screen.getByLabelText('Usuario'), {
            target: { value: 'Ing. Patricia Villarroel Siles' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Filtrar' }));

        expect(screen.getByText('Mostrando 2 de 248 eventos')).toBeInTheDocument();
        expect(screen.queryByText('Generación de códigos QR')).not.toBeInTheDocument();
    });

    it('filtra por rango de fechas', () => {
        render(<Bitacora onNavigate={vi.fn()} />);

        fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-11-18' } });
        fireEvent.change(screen.getByLabelText('Hasta'), { target: { value: '2026-11-18' } });
        fireEvent.click(screen.getByRole('button', { name: 'Filtrar' }));

        expect(screen.getByText('Mostrando 3 de 248 eventos')).toBeInTheDocument();
    });

    it('marca Bitácora como seleccionada en el menú', () => {
        render(<Bitacora onNavigate={vi.fn()} />);

        const itemsMenu = screen.getAllByText('Bitácora');
        expect(itemsMenu).toHaveLength(2);
    });
});
