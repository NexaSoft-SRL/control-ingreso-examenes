import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Aplicacion from './Aplicacion';

describe('Aplicacion', () => {
    it('renderiza la página inicial de usuarios y roles', () => {
        render(<Aplicacion />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Usuarios y roles',
            })
        ).toBeInTheDocument();
    });
});
