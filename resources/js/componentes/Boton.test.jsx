import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Boton from './Boton';

describe('Boton', () => {
    it('es primario y normal por defecto, y pasa lo demás al botón', () => {
        const alPulsar = vi.fn();
        render(
            <Boton type="submit" onClick={alPulsar}>
                Guardar
            </Boton>
        );
        const boton = screen.getByRole('button', { name: 'Guardar' });

        expect(boton).toHaveClass('bg-primary-600', 'min-h-11');
        expect(boton).toHaveAttribute('type', 'submit');
        fireEvent.click(boton);
        expect(alPulsar).toHaveBeenCalledTimes(1);
    });

    it('aplica variante, tamaño y deshabilitado', () => {
        render(
            <Boton variante="peligro" tamano="chico" className="w-full" disabled>
                Restaurar
            </Boton>
        );
        const boton = screen.getByRole('button', { name: 'Restaurar' });

        expect(boton).toHaveClass('bg-danger-600', 'min-h-9', 'w-full');
        expect(boton).toBeDisabled();
    });
});
