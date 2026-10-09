import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Buscador from './Buscador';

describe('Buscador', () => {
    it('entrega el texto que se escribe', () => {
        const onCambiar = vi.fn();
        render(<Buscador valor="" onCambiar={onCambiar} placeholder="Nombre o código" />);

        fireEvent.change(screen.getByRole('textbox', { name: 'Nombre o código' }), {
            target: { value: 'agu' },
        });
        expect(onCambiar).toHaveBeenCalledWith('agu');
        expect(screen.queryByRole('button', { name: 'Limpiar búsqueda' })).not.toBeInTheDocument();
    });

    it('con texto ofrece limpiar', () => {
        const onCambiar = vi.fn();
        render(<Buscador valor="aguilar" onCambiar={onCambiar} etiqueta="Buscar docente" />);

        fireEvent.click(screen.getByRole('button', { name: 'Limpiar búsqueda' }));
        expect(onCambiar).toHaveBeenCalledWith('');
        expect(screen.getByRole('textbox', { name: 'Buscar docente' })).toHaveValue('aguilar');
    });
});
