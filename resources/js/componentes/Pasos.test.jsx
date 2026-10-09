import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Pasos from './Pasos';

describe('Pasos', () => {
    it('permite volver a un paso hecho y no saltar adelante', () => {
        const onIr = vi.fn();
        render(<Pasos pasos={['Examen', 'Grupos', 'Aulas', 'Personal']} actual={2} onIr={onIr} />);

        expect(screen.getByRole('button', { name: 'Examen' })).toBeEnabled();
        expect(screen.getByRole('button', { name: /^3\s*Aulas$/ })).toBeDisabled();
        expect(screen.getByRole('button', { name: /^4\s*Personal$/ })).toBeDisabled();

        fireEvent.click(screen.getByRole('button', { name: 'Grupos' }));
        expect(onIr).toHaveBeenCalledWith(1);
    });

    it('destaca el paso actual', () => {
        render(<Pasos pasos={['Examen', 'Grupos']} actual={0} />);
        expect(screen.getByRole('button', { name: /^1\s*Examen$/ })).toHaveClass('bg-primary-600');
    });
});
