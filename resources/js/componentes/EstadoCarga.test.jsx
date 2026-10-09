import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import EstadoCarga from './EstadoCarga';

describe('EstadoCarga', () => {
    it('en la primera carga dibuja un esqueleto, sin texto', () => {
        render(
            <EstadoCarga cargando filas={3}>
                <p>lista</p>
            </EstadoCarga>
        );
        const esqueleto = screen.getByRole('status', { name: 'Cargando' });

        expect(esqueleto).toHaveAttribute('aria-busy', 'true');
        expect(esqueleto.children).toHaveLength(3);
        expect(esqueleto).toHaveTextContent('');
        expect(screen.queryByText('lista')).not.toBeInTheDocument();
    });

    it('con error ofrece reintentar', () => {
        const onReintentar = vi.fn();
        render(
            <EstadoCarga error={new Error('red')} onReintentar={onReintentar}>
                <p>lista</p>
            </EstadoCarga>
        );

        expect(screen.getByRole('alert')).toHaveTextContent('No se pudo cargar');
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        expect(onReintentar).toHaveBeenCalledTimes(1);
        expect(screen.queryByText('lista')).not.toBeInTheDocument();
    });

    it('vacío muestra el texto de la pantalla', () => {
        const { rerender } = render(<EstadoCarga vacio textoVacio="Sin exámenes" />);
        expect(screen.getByText('Sin exámenes')).toBeInTheDocument();

        rerender(<EstadoCarga vacio />);
        expect(screen.getByText('Sin resultados')).toBeInTheDocument();
    });

    it('si no aplica ningún estado dibuja el contenido', () => {
        render(
            <EstadoCarga>
                <p>lista</p>
            </EstadoCarga>
        );
        expect(screen.getByText('lista')).toBeInTheDocument();
    });
});
