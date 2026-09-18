import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Aplicacion from './Aplicacion';

describe('Aplicacion', () => {
    it('renderiza la pantalla inicial de asignaturas y ambientes', () => {
        render(<Aplicacion />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Asignaturas y ambientes',
            })
        ).toBeInTheDocument();

        // Usamos getAllByText para evitar el conflicto de elementos duplicados
        expect(screen.getAllByText('Sistema Institucional de Verificación')[0]).toBeInTheDocument();
        expect(screen.getByText('UMSS FCyT')).toBeInTheDocument();
    });
});
