import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import usarPaginaServidor from './usarPaginaServidor';

describe('usarPaginaServidor', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('arma los parámetros con página, tamaño y solo los filtros con valor', () => {
        const { result } = renderHook(() =>
            usarPaginaServidor({ porPagina: 20, filtros: { facultad: null, sin_cuenta: false } })
        );
        expect(result.current.parametros).toEqual({ pagina: 1, por_pagina: 20 });

        act(() => result.current.ponerFiltro('facultad', 'fcyt'));
        expect(result.current.parametros).toEqual({ facultad: 'fcyt', pagina: 1, por_pagina: 20 });
    });

    it('vuelve a la página 1 al filtrar', () => {
        const { result } = renderHook(() => usarPaginaServidor());

        act(() => result.current.irA(4));
        expect(result.current.parametros.pagina).toBe(4);

        act(() => result.current.ponerFiltros({ grupo: 501, aula: 31 }));
        expect(result.current.parametros).toEqual({
            grupo: 501,
            aula: 31,
            pagina: 1,
            por_pagina: 25,
        });
    });

    it('la búsqueda espera 300 ms tras la última tecla y vuelve a la página 1', () => {
        const { result } = renderHook(() => usarPaginaServidor());
        act(() => result.current.irA(3));

        act(() => result.current.ponerBuscar('agu'));
        act(() => vi.advanceTimersByTime(200));
        act(() => result.current.ponerBuscar('aguilar'));
        act(() => vi.advanceTimersByTime(200));
        expect(result.current.buscar).toBe('aguilar');
        expect(result.current.parametros.buscar).toBeUndefined();
        expect(result.current.parametros.pagina).toBe(3);

        act(() => vi.advanceTimersByTime(100));
        expect(result.current.parametros).toEqual({ buscar: 'aguilar', pagina: 1, por_pagina: 25 });
        expect(result.current.filtrosVigentes).toEqual({ buscar: 'aguilar' });
    });

    it('da las propiedades de Paginacion a partir de meta', () => {
        const { result } = renderHook(() => usarPaginaServidor({ porPagina: 25 }));
        const props = result.current.paginacion({ total: 849, pagina: 2, por_pagina: 25 });

        expect(props).toMatchObject({ total: 849, pagina: 2, porPagina: 25 });
        act(() => props.onCambiar(3));
        expect(result.current.pagina).toBe(3);
        expect(result.current.paginacion(null)).toMatchObject({
            total: 0,
            pagina: 3,
            porPagina: 25,
        });
    });
});
