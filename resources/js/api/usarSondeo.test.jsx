import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import usarSondeo from './usarSondeo';
import { api } from './cliente';
import { errorHttp, simularApi } from '../test/apoyo';

vi.mock('./cliente');

function ocultarPestana(oculta) {
    Object.defineProperty(document, 'hidden', { configurable: true, get: () => oculta });
    document.dispatchEvent(new Event('visibilitychange'));
}

describe('usarSondeo', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        Object.defineProperty(document, 'hidden', { configurable: true, get: () => false });
    });

    it('consulta al montar y luego en cada intervalo', async () => {
        simularApi(api, { 'GET /en-curso/1': { data: [], hora_servidor: '08:21' } });
        const { result } = renderHook(() => usarSondeo('/en-curso/1', { intervalo: 10000 }));

        await act(async () => vi.advanceTimersByTimeAsync(0));
        expect(api.get).toHaveBeenCalledTimes(1);
        expect(result.current.datos).toEqual([]);

        await act(async () => vi.advanceTimersByTimeAsync(10000));
        expect(api.get).toHaveBeenCalledTimes(2);
        await act(async () => vi.advanceTimersByTimeAsync(20000));
        expect(api.get).toHaveBeenCalledTimes(4);
    });

    it('se detiene con la pestaña oculta y se reanuda con una carga inmediata', async () => {
        simularApi(api, { 'GET /en-curso/1': { data: [] } });
        renderHook(() => usarSondeo('/en-curso/1', { intervalo: 10000 }));
        await act(async () => vi.advanceTimersByTimeAsync(0));
        expect(api.get).toHaveBeenCalledTimes(1);

        await act(async () => ocultarPestana(true));
        await act(async () => vi.advanceTimersByTimeAsync(60000));
        expect(api.get).toHaveBeenCalledTimes(1);

        await act(async () => ocultarPestana(false));
        await act(async () => vi.advanceTimersByTimeAsync(0));
        expect(api.get).toHaveBeenCalledTimes(2);

        await act(async () => vi.advanceTimersByTimeAsync(10000));
        expect(api.get).toHaveBeenCalledTimes(3);
    });

    it('marca sin conexión tras tres fallos seguidos y lo quita al primer acierto', async () => {
        simularApi(api, { 'GET /en-curso/1': { data: [{ id: 1 }] } });
        const { result } = renderHook(() => usarSondeo('/en-curso/1', { intervalo: 10000 }));
        await act(async () => vi.advanceTimersByTimeAsync(0));

        simularApi(api, { 'GET /en-curso/1': errorHttp(503) });
        await act(async () => vi.advanceTimersByTimeAsync(20000));
        expect(result.current.sinConexion).toBe(false);

        await act(async () => vi.advanceTimersByTimeAsync(10000));
        expect(result.current.sinConexion).toBe(true);
        // Los datos anteriores siguen a la vista.
        expect(result.current.datos).toEqual([{ id: 1 }]);
        expect(result.current.error).toBeNull();

        simularApi(api, { 'GET /en-curso/1': { data: [{ id: 2 }] } });
        await act(async () => vi.advanceTimersByTimeAsync(10000));
        expect(result.current.sinConexion).toBe(false);
        expect(result.current.datos).toEqual([{ id: 2 }]);
    });

    it('deja de sondear al desmontar', async () => {
        simularApi(api, { 'GET /en-curso/1': { data: [] } });
        const { unmount } = renderHook(() => usarSondeo('/en-curso/1', { intervalo: 15000 }));
        await act(async () => vi.advanceTimersByTimeAsync(0));

        unmount();
        await vi.advanceTimersByTimeAsync(60000);
        expect(api.get).toHaveBeenCalledTimes(1);
    });
});
