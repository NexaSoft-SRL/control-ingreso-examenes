import { act, fireEvent, render, renderHook, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Paginacion, { usePaginas } from './Paginacion';

describe('Paginacion', () => {
    it('muestra el tramo, el total y cambia de página', () => {
        const onCambiar = vi.fn();
        render(
            <Paginacion
                total={849}
                pagina={2}
                porPagina={25}
                onCambiar={onCambiar}
                unidad={['docente', 'docentes']}
            />
        );

        expect(screen.getByText('26–50 de 849 docentes')).toBeInTheDocument();
        expect(screen.getByText('2 / 34')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        fireEvent.click(screen.getByRole('button', { name: 'Página anterior' }));
        expect(onCambiar.mock.calls).toEqual([[3], [1]]);
    });

    it('deshabilita los extremos', () => {
        const { rerender } = render(
            <Paginacion total={60} pagina={1} porPagina={25} onCambiar={() => {}} />
        );
        expect(screen.getByRole('button', { name: 'Página anterior' })).toBeDisabled();

        rerender(<Paginacion total={60} pagina={3} porPagina={25} onCambiar={() => {}} />);
        expect(screen.getByRole('button', { name: 'Página siguiente' })).toBeDisabled();
        expect(screen.getByText(/51–60 de 60/)).toBeInTheDocument();
    });

    it('con una sola página muestra solo el total, en singular si es uno', () => {
        render(
            <Paginacion
                total={1}
                pagina={1}
                porPagina={25}
                onCambiar={() => {}}
                unidad={['aula', 'aulas']}
            />
        );

        expect(screen.getByText('1 aula')).toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});

describe('usePaginas', () => {
    const lista = Array.from({ length: 60 }, (_, i) => i + 1);

    it('parte la lista y vuelve a la primera página al cambiar la clave', () => {
        const { result, rerender } = renderHook(({ clave }) => usePaginas(lista, 25, clave), {
            initialProps: { clave: 'a' },
        });
        expect(result.current.visibles).toHaveLength(25);
        expect(result.current.paginacion).toMatchObject({ total: 60, pagina: 1, porPagina: 25 });

        act(() => result.current.paginacion.onCambiar(3));
        expect(result.current.visibles).toEqual([51, 52, 53, 54, 55, 56, 57, 58, 59, 60]);

        rerender({ clave: 'b' });
        expect(result.current.paginacion.pagina).toBe(1);
    });
});
