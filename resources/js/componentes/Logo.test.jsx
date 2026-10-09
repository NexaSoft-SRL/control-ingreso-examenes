import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Logo from './Logo';

describe('Logo', () => {
    it('es una imagen con nombre y un degradado propio por instancia', () => {
        render(
            <>
                <Logo />
                <Logo className="h-16 w-16" />
            </>
        );
        const [uno, dos] = screen.getAllByRole('img', { name: 'Control de ingreso' });

        expect(uno).toHaveClass('h-9', 'w-9');
        expect(dos).toHaveClass('h-16');
        expect(uno.querySelector('linearGradient').id).not.toBe(
            dos.querySelector('linearGradient').id
        );
    });
});
