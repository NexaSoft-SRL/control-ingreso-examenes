import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import SelectorAsignatura from './SelectorAsignatura';

const ASIGNATURAS = [
    {
        id: 1,
        codigo: '2010010',
        nombre: 'Introducción a la Programación',
        grupos: ['2'],
        inscritos: 65,
    },
    {
        id: 2,
        codigo: '2010024',
        nombre: 'Taller de Ingeniería de Software',
        grupos: ['1', '5'],
        inscritos: 76,
    },
    {
        id: 3,
        codigo: '2010204',
        nombre: 'Interacción Humano Computador',
        grupos: ['1'],
        inscritos: 40,
    },
];

const montar = (props = {}) => {
    const onCambiar = vi.fn();
    render(
        <SelectorAsignatura
            etiqueta="Asignatura"
            requerido
            asignaturas={ASIGNATURAS}
            valor={1}
            onCambiar={onCambiar}
            {...props}
        />
    );
    return onCambiar;
};
const boton = () => screen.getByRole('button', { name: /^Asignatura/ });

describe('SelectorAsignatura', () => {
    it('muestra la elegida con su código, grupos e inscritos', () => {
        montar();

        expect(boton()).toHaveTextContent('Introducción a la Programación');
        expect(boton()).toHaveTextContent('2010010 · Grupo 2 · 65 inscritos');
        expect(boton()).toHaveAttribute('aria-expanded', 'false');
    });

    it('se abre, marca la elegida y cambia con un clic', () => {
        const onCambiar = montar();
        fireEvent.click(boton());

        const lista = screen.getByRole('listbox', { name: /Asignatura/ });
        expect(within(lista).getAllByRole('option')).toHaveLength(3);
        expect(within(lista).getByRole('option', { selected: true })).toHaveTextContent(
            'Introducción a la Programación'
        );
        expect(within(lista).getByText('2010024 · Grupos 1, 5 · 76 inscritos')).toBeInTheDocument();

        fireEvent.click(within(lista).getByRole('option', { name: /Taller/ }));
        expect(onCambiar).toHaveBeenCalledWith(2);
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('se maneja con el teclado', () => {
        const onCambiar = montar();
        fireEvent.keyDown(boton(), { key: 'ArrowDown' });
        expect(screen.getByRole('listbox')).toBeInTheDocument();

        fireEvent.keyDown(boton(), { key: 'ArrowDown' });
        fireEvent.keyDown(boton(), { key: 'Enter' });
        expect(onCambiar).toHaveBeenCalledWith(2);

        fireEvent.keyDown(boton(), { key: 'Enter' });
        fireEvent.keyDown(boton(), { key: 'i' });
        fireEvent.keyDown(boton(), { key: 'Escape' });
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('bloqueado no se abre', () => {
        montar({ disabled: true });
        fireEvent.click(boton());

        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });
});
