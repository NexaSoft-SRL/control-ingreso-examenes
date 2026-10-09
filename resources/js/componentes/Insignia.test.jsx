import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Insignia from './Insignia';

describe('Insignia', () => {
    it('es neutra por defecto y toma el tono indicado', () => {
        render(
            <>
                <Insignia>Sin fechas</Insignia>
                <Insignia tono="exito" punto>
                    Listo
                </Insignia>
            </>
        );

        expect(screen.getByText('Sin fechas')).toHaveClass('bg-slate-200');
        expect(screen.getByText('Listo')).toHaveClass('bg-success-100');
        expect(screen.getByText('Listo').querySelector('span')).toHaveClass('rounded-full');
    });
});
