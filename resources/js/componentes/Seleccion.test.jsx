import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Seleccion, { AreaTexto } from './Seleccion';

describe('Seleccion y AreaTexto', () => {
    it('la lista lleva su etiqueta, el obligatorio y avisa el cambio', () => {
        const onChange = vi.fn();
        render(
            <Seleccion etiqueta="Tipo" requerido value="a" onChange={onChange}>
                <option value="a">Primer parcial</option>
                <option value="b">Examen final</option>
            </Seleccion>
        );
        const lista = screen.getByRole('combobox', { name: /Tipo/ });

        expect(lista).toHaveAttribute('aria-required', 'true');
        fireEvent.change(lista, { target: { value: 'b' } });
        expect(onChange).toHaveBeenCalledTimes(1);
    });

    it('el área de texto muestra el error y el pie', () => {
        render(
            <AreaTexto
                etiqueta="Motivo"
                requerido
                error="Mínimo 5 caracteres"
                pie="3 / 1000"
                value="abc"
                onChange={() => {}}
            />
        );
        const area = screen.getByRole('textbox', { name: /Motivo/ });

        expect(area).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByText('Mínimo 5 caracteres')).toBeInTheDocument();
        expect(screen.getByText('3 / 1000')).toBeInTheDocument();
    });
});
