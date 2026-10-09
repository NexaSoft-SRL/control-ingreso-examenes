import { renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import usarEdificios, { olvidarEdificios } from './usarEdificios';
import { api } from './cliente';
import { errorHttp, simularApi } from '../test/apoyo';

vi.mock('./cliente');

const CUERPO = {
    data: [
        { id: 3, facultad: 'FCyT', nombre: 'Edificio Académico 2', poligono: [], aulas: ['624'] },
    ],
    meta: { caja: { lon: [-66.15, -66.14], lat: [-17.4, -17.39] } },
};

describe('usarEdificios', () => {
    beforeEach(() => olvidarEdificios());

    it('pide los edificios una sola vez para toda la sesión', async () => {
        simularApi(api, { 'GET /edificios': CUERPO });

        const primero = renderHook(() => usarEdificios());
        expect(primero.result.current.cargando).toBe(true);
        await waitFor(() => expect(primero.result.current.edificios).toHaveLength(1));
        expect(primero.result.current.caja).toEqual(CUERPO.meta.caja);

        const segundo = renderHook(() => usarEdificios());
        expect(segundo.result.current.edificios).toHaveLength(1);
        expect(api.get).toHaveBeenCalledTimes(1);
    });

    it('expone el error y vuelve a pedir en el siguiente montaje', async () => {
        simularApi(api, { 'GET /edificios': errorHttp(500) });
        const fallido = renderHook(() => usarEdificios());
        await waitFor(() => expect(fallido.result.current.error).toBeTruthy());

        simularApi(api, { 'GET /edificios': CUERPO });
        const nuevo = renderHook(() => usarEdificios());
        await waitFor(() => expect(nuevo.result.current.edificios).toHaveLength(1));
    });
});
