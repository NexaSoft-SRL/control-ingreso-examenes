import { fireEvent, render, screen } from '@testing-library/react';
import PropTypes from 'prop-types';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Seleccion, { AreaTexto } from './Seleccion';

function Ejemplo({ onChange = () => {}, deshabilitada = false }) {
    const [valor, setValor] = useState('a');
    return (
        <Seleccion
            etiqueta="Tipo"
            value={valor}
            disabled={deshabilitada}
            onChange={(e) => {
                setValor(e.target.value);
                onChange(e);
            }}
        >
            <option value="a">Primer parcial</option>
            <option value="b">Examen final</option>
            <option value="c" disabled>
                Mesa cerrada
            </option>
            <optgroup label="Otros">
                <option value="d">Segunda instancia</option>
            </optgroup>
        </Seleccion>
    );
}

Ejemplo.propTypes = { onChange: PropTypes.func, deshabilitada: PropTypes.bool };

describe('Seleccion y AreaTexto', () => {
    it('es una lista propia: muestra la elegida y abre sus opciones con grupos', () => {
        render(<Ejemplo />);
        const lista = screen.getByRole('combobox', { name: 'Tipo' });

        expect(document.querySelector('select')).toBeNull();
        expect(lista).toHaveTextContent('Primer parcial');
        expect(lista).toHaveAttribute('aria-expanded', 'false');

        fireEvent.click(lista);

        expect(lista).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getAllByRole('option').map((o) => o.textContent)).toEqual([
            'Primer parcial',
            'Examen final',
            'Mesa cerrada',
            'Segunda instancia',
        ]);
        expect(screen.getByRole('group', { name: 'Otros' })).toBeInTheDocument();
        expect(screen.getByRole('option', { name: 'Primer parcial' })).toHaveAttribute(
            'aria-selected',
            'true'
        );
    });

    it('elegir una opción avisa el valor y cierra la lista', () => {
        const onChange = vi.fn();
        render(<Ejemplo onChange={onChange} />);

        fireEvent.click(screen.getByRole('combobox', { name: 'Tipo' }));
        fireEvent.click(screen.getByRole('option', { name: 'Examen final' }));

        expect(onChange).toHaveBeenCalledWith({ target: { value: 'b', name: undefined } });
        expect(screen.getByRole('combobox', { name: 'Tipo' })).toHaveTextContent('Examen final');
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('una opción deshabilitada no se puede elegir', () => {
        const onChange = vi.fn();
        render(<Ejemplo onChange={onChange} />);

        fireEvent.click(screen.getByRole('combobox', { name: 'Tipo' }));
        const cerrada = screen.getByRole('option', { name: 'Mesa cerrada' });
        fireEvent.click(cerrada);

        expect(cerrada).toHaveAttribute('aria-disabled', 'true');
        expect(onChange).not.toHaveBeenCalled();
    });

    it('se maneja con el teclado: flechas, Enter, una letra y Escape', () => {
        const onChange = vi.fn();
        render(<Ejemplo onChange={onChange} />);
        const lista = screen.getByRole('combobox', { name: 'Tipo' });

        fireEvent.keyDown(lista, { key: 'ArrowDown' });
        expect(lista).toHaveAttribute('aria-expanded', 'true');
        fireEvent.keyDown(lista, { key: 'ArrowDown' });
        fireEvent.keyDown(lista, { key: 'Enter' });
        expect(onChange).toHaveBeenLastCalledWith({ target: { value: 'b', name: undefined } });

        fireEvent.keyDown(lista, { key: 'ArrowDown' });
        fireEvent.keyDown(lista, { key: 's' });
        fireEvent.keyDown(lista, { key: 'Enter' });
        expect(onChange).toHaveBeenLastCalledWith({ target: { value: 'd', name: undefined } });

        fireEvent.keyDown(lista, { key: 'ArrowDown' });
        fireEvent.keyDown(lista, { key: 'Escape' });
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('deshabilitada no abre', () => {
        render(<Ejemplo deshabilitada />);
        const lista = screen.getByRole('combobox', { name: 'Tipo' });

        expect(lista).toBeDisabled();
        fireEvent.click(lista);
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

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
