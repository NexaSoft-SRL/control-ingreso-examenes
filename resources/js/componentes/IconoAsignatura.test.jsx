import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import IconoAsignatura from './IconoAsignatura';

describe('IconoAsignatura', () => {
    it('nombra el área y usa el color de la asignatura', () => {
        render(<IconoAsignatura nombre="Cálculo I" color="#0e7490" />);
        const icono = screen.getByRole('img', { name: 'Matemáticas' });
        expect(icono).toHaveStyle({ color: '#0e7490' });
    });

    it('sin color usa el del área', () => {
        render(<IconoAsignatura nombre="Redes de Computadoras" tamano="xs" />);
        expect(screen.getByRole('img', { name: 'Redes' })).toHaveStyle({ color: '#0284c7' });
    });
});
