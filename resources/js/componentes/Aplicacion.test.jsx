import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Aplicacion from './Aplicacion';

describe('Aplicacion', () => {
    it('renderiza la identidad inicial del sistema', () => {
        render(<Aplicacion />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Control de ingreso a exámenes masivos',
            })
        ).toBeInTheDocument();

        expect(screen.getByText('NexaSoft S.R.L. — CPTIS-452026-2026')).toBeInTheDocument();
    });
});
