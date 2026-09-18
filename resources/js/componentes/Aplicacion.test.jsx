import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Aplicacion from './Aplicacion';

describe('Aplicacion', () => {
    it('renderiza la pantalla inicial de usuarios y roles', () => {
        render(<Aplicacion />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Asignaturas y ambientes',
            })
        ).toBeInTheDocument();

        expect(screen.getByText('Sistema Institucional de Verificación')).toBeInTheDocument();

        expect(screen.getByText('UMSS FCyT')).toBeInTheDocument();
    });
});
