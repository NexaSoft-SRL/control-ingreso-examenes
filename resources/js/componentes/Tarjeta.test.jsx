import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Tarjeta from './Tarjeta';

describe('Tarjeta', () => {
    it('lleva título, acciones y contenido con relleno', () => {
        render(
            <Tarjeta
                id="pendientes"
                titulo="Pendientes"
                acciones={<button type="button">Importar</button>}
            >
                <p>contenido</p>
            </Tarjeta>
        );

        expect(screen.getByRole('heading', { level: 2, name: 'Pendientes' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Importar' })).toBeInTheDocument();
        expect(screen.getByText('contenido').parentElement).toHaveClass('p-5');
        expect(document.getElementById('pendientes')).toBeInTheDocument();
    });

    it('sin relleno ni cabecera deja el contenido a ras', () => {
        render(
            <Tarjeta sinRelleno>
                <p>tabla</p>
            </Tarjeta>
        );

        expect(screen.queryByRole('heading')).not.toBeInTheDocument();
        expect(screen.getByText('tabla').parentElement).not.toHaveClass('p-5');
    });
});
