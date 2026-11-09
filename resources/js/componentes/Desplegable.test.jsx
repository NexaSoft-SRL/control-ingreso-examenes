import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { Building2, LayoutGrid } from 'lucide-react';
import Desplegable from './Desplegable';

const OPCIONES = [
    { valor: '', texto: 'Todos', icono: LayoutGrid, cuenta: 3 },
    { valor: '2', texto: 'Académico 2', icono: Building2, cuenta: 2, grupo: 'Edificios' },
    { valor: '18', texto: 'Biología', icono: Building2, cuenta: 1, grupo: 'Edificios' },
];

describe('Desplegable', () => {
    it('muestra la elegida y su cuenta; abre la lista agrupada y elige con clic', () => {
        const onCambiar = vi.fn();
        render(
            <Desplegable etiqueta="Edificio" valor="" onCambiar={onCambiar} opciones={OPCIONES} />
        );
        const boton = screen.getByRole('button', { name: 'Edificio: Todos' });
        expect(boton).toHaveTextContent('Todos3');
        expect(boton).toHaveAttribute('aria-expanded', 'false');

        fireEvent.click(boton);
        const lista = screen.getByRole('listbox', { name: 'Edificio' });
        expect(within(lista).getByRole('option', { name: /Todos/ })).toHaveAttribute(
            'aria-selected',
            'true'
        );
        expect(
            within(screen.getByRole('group', { name: 'Edificios' }))
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual(['Académico 22', 'Biología1']);

        fireEvent.click(within(lista).getByRole('option', { name: /Biología/ }));
        expect(onCambiar).toHaveBeenCalledWith('18');
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('con el teclado salta por letra y elige con Enter', () => {
        const onCambiar = vi.fn();
        render(
            <Desplegable etiqueta="Edificio" valor="" onCambiar={onCambiar} opciones={OPCIONES} />
        );
        const boton = screen.getByRole('button', { name: 'Edificio: Todos' });

        fireEvent.keyDown(boton, { key: 'Enter' });
        fireEvent.keyDown(boton, { key: 'b' });
        fireEvent.keyDown(boton, { key: 'Enter' });
        expect(onCambiar).toHaveBeenCalledWith('18');
    });
});
