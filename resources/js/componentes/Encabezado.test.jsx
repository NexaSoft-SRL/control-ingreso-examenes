import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import Encabezado from './Encabezado';

const montar = (ui) => render(<MemoryRouter>{ui}</MemoryRouter>);

describe('Encabezado', () => {
    it('muestra título, subtítulo y la acción', () => {
        montar(
            <Encabezado titulo="Exámenes" subtitulo="Período 2/2026">
                <button type="button">Registrar examen</button>
            </Encabezado>
        );

        expect(screen.getByRole('heading', { level: 1, name: 'Exámenes' })).toBeInTheDocument();
        expect(screen.getByText('Período 2/2026')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Registrar examen' })).toBeInTheDocument();
        expect(screen.queryByText('Sin conexión')).not.toBeInTheDocument();
    });

    it('enlaza a la pantalla de la que se viene, con sus parámetros', () => {
        montar(
            <Encabezado
                titulo="Habilitación"
                volver={{ a: '/examenes?examen=3', texto: 'Exámenes' }}
            />
        );
        expect(screen.getByRole('link', { name: 'Exámenes' })).toHaveAttribute(
            'href',
            '/examenes?examen=3'
        );
    });

    it('marca la falta de conexión del sondeo', () => {
        montar(<Encabezado titulo="En curso" sinConexion />);
        expect(screen.getByText('Sin conexión')).toBeInTheDocument();
    });
});
