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
    it('muestra el elegido y lista los exámenes con su tipo, asignatura y fecha', () => {
        const onCambiar = vi.fn();
        render(<SelectorExamen valor={2} onCambiar={onCambiar} examenes={EXAMENES} />);
        const boton = screen.getByRole('button', { name: /^Examen:/ });

        expect(boton).toHaveTextContent('Taller de Ingeniería de Software');
        expect(boton).toHaveTextContent('Primer parcial · 20 oct');
        fireEvent.click(boton);
        expect(screen.getByRole('option', { selected: true })).toHaveTextContent(
            'Taller de Ingeniería de Software'
        );
        fireEvent.click(screen.getByRole('option', { name: /^Introducción a la Programación/ }));
        expect(onCambiar).toHaveBeenCalledWith(1);
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('se maneja con el teclado', () => {
        const onCambiar = vi.fn();
        render(<SelectorExamen valor={1} onCambiar={onCambiar} examenes={EXAMENES} />);
        const boton = screen.getByRole('button', { name: /^Examen:/ });

        fireEvent.keyDown(boton, { key: 'ArrowDown' });
        fireEvent.keyDown(boton, { key: 'ArrowDown' });
        fireEvent.keyDown(boton, { key: 'Enter' });
        expect(onCambiar).toHaveBeenCalledWith(2);

        fireEvent.keyDown(boton, { key: 'Enter' });
        fireEvent.keyDown(boton, { key: 'Escape' });
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('con más de seis agrupa por asignatura', () => {
        const muchos = Array.from({ length: 8 }, (_, i) =>
            examen(i + 1, i < 4 ? 'Cálculo I' : 'Álgebra I')
        );
        render(<SelectorExamen valor={1} onCambiar={() => {}} examenes={muchos} />);
        fireEvent.click(screen.getByRole('button', { name: /^Examen:/ }));

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
