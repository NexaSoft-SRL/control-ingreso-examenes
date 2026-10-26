import { describe, expect, it } from 'vitest';
import { renderHook } from '@testing-library/react';
import usarTituloPestana from './usarTituloPestana';

describe('usarTituloPestana', () => {
    it('pone la pantalla antes del sistema', () => {
        const { rerender } = renderHook(({ titulo }) => usarTituloPestana(titulo), {
            initialProps: { titulo: 'Período' },
        });
        expect(document.title).toBe('Período · Control de ingreso a exámenes');

        rerender({ titulo: undefined });
        expect(document.title).toBe('Control de ingreso a exámenes');
    });
});
