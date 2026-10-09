import { act, fireEvent, render, renderHook, screen } from '@testing-library/react';
import { MemoryRouter, useLocation } from 'react-router-dom';
import PropTypes from 'prop-types';
import { describe, expect, it, vi } from 'vitest';
import SelectorExamen from './SelectorExamen';
import usarExamen from './usarExamen';

const examen = (id, asignatura, fecha = '2026-10-12', tipo = 'Primer parcial') => ({
    id,
    asignatura: { id: id * 10, codigo: `20100${id}`, nombre: asignatura },
    tipo: 'PRIMER_PARCIAL',
    tipo_texto: tipo,
    fecha,
});
const EXAMENES = [
    examen(1, 'Introducción a la Programación'),
    examen(2, 'Taller de Ingeniería de Software', '2026-10-20'),
];

describe('SelectorExamen', () => {
    it('lista los exámenes con fecha, tipo y asignatura', () => {
        const onCambiar = vi.fn();
        render(<SelectorExamen valor={2} onCambiar={onCambiar} examenes={EXAMENES} />);
        const lista = screen.getByRole('combobox', { name: /Examen/ });

        expect(lista).toHaveValue('2');
        expect(
            screen.getByRole('option', {
                name: '12 oct · Primer parcial · Introducción a la Programación',
            })
        ).toBeInTheDocument();
        fireEvent.change(lista, { target: { value: '1' } });
        expect(onCambiar).toHaveBeenCalledWith(1);
    });

    it('con más de seis agrupa por asignatura', () => {
        const muchos = Array.from({ length: 8 }, (_, i) =>
            examen(i + 1, i < 4 ? 'Cálculo I' : 'Álgebra I')
        );
        render(<SelectorExamen valor={1} onCambiar={() => {}} examenes={muchos} />);

        expect(screen.getByRole('group', { name: 'Cálculo I' })).toBeInTheDocument();
        expect(screen.getByRole('group', { name: 'Álgebra I' })).toBeInTheDocument();
    });

    it('sin exámenes no dibuja nada', () => {
        const { container } = render(<SelectorExamen onCambiar={() => {}} examenes={[]} />);
        expect(container).toBeEmptyDOMElement();
    });
});

describe('usarExamen', () => {
    const envoltorio = (ruta) => {
        function Envoltorio({ children }) {
            return <MemoryRouter initialEntries={[ruta]}>{children}</MemoryRouter>;
        }
        Envoltorio.propTypes = { children: PropTypes.node };
        return Envoltorio;
    };
    const useConDireccion = () => ({
        examen: usarExamen(EXAMENES),
        busqueda: useLocation().search,
    });

    it('toma el examen de ?examen= y, sin parámetro, el primero', () => {
        const conParametro = renderHook(() => usarExamen(EXAMENES), {
            wrapper: envoltorio('/habilitacion?examen=2'),
        });
        const sinParametro = renderHook(() => usarExamen(EXAMENES), {
            wrapper: envoltorio('/habilitacion'),
        });
        const sinLista = renderHook(() => usarExamen([]), {
            wrapper: envoltorio('/habilitacion?examen=2'),
        });

        expect(conParametro.result.current[0].id).toBe(2);
        expect(sinParametro.result.current[0].id).toBe(1);
        expect(sinLista.result.current[0]).toBeNull();
    });

    it('elegir escribe el parámetro y conserva los demás', () => {
        const { result } = renderHook(useConDireccion, {
            wrapper: envoltorio('/codigos-qr?examen=1&aula=31'),
        });

        act(() => result.current.examen[1](2));
        expect(result.current.examen[0].id).toBe(2);
        expect(result.current.busqueda).toBe('?examen=2&aula=31');
    });
});
