import { act, renderHook, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import usarConsulta from './usarConsulta';
import { api } from './cliente';
import { errorHttp, respuestaDiferida, simularApi } from '../test/apoyo';

vi.mock('./cliente');

describe('usarConsulta', () => {
    it('entrega datos y meta, y marca la primera carga', async () => {
        const diferida = respuestaDiferida();
        simularApi(api, { 'GET /docentes': () => diferida.promesa });

        const { result } = renderHook(() =>
            usarConsulta('/docentes', { parametros: { pagina: 1, buscar: '' } })
        );
        expect(result.current.cargando).toBe(true);
        expect(result.current.datos).toBeNull();

        await act(async () => diferida.resolver({ data: [{ id: 1 }], meta: { total: 1 } }));

        expect(result.current.cargando).toBe(false);
        expect(result.current.datos).toEqual([{ id: 1 }]);
        expect(result.current.meta).toEqual({ total: 1 });
        // Los parámetros vacíos no viajan.
        expect(api.get).toHaveBeenCalledWith('/docentes', { params: { pagina: 1 } });
    });

    it('sin `data` entrega el cuerpo entero', async () => {
        simularApi(api, { 'GET /periodos/resumen': { hoy: '2026-10-12' } });
        const { result } = renderHook(() => usarConsulta('/periodos/resumen'));

        await waitFor(() => expect(result.current.datos).toEqual({ hoy: '2026-10-12' }));
        expect(result.current.cuerpo).toEqual({ hoy: '2026-10-12' });
    });

    it('con la primera carga fallida expone el error y recargar lo resuelve', async () => {
        simularApi(api, { 'GET /examenes': errorHttp(500) });
        const { result } = renderHook(() => usarConsulta('/examenes'));

        await waitFor(() => expect(result.current.error).toBeTruthy());
        expect(result.current.cargando).toBe(false);

        simularApi(api, { 'GET /examenes': { data: [] } });
        await act(async () => result.current.recargar());

        expect(result.current.error).toBeNull();
        expect(result.current.datos).toEqual([]);
    });

    it('un fallo al recargar conserva los datos y cuenta el fallo', async () => {
        simularApi(api, { 'GET /examenes': { data: [{ id: 1 }] } });
        const { result } = renderHook(() => usarConsulta('/examenes'));
        await waitFor(() => expect(result.current.datos).toHaveLength(1));

        simularApi(api, { 'GET /examenes': errorHttp(500) });
        await act(async () => result.current.recargar());

        expect(result.current.datos).toHaveLength(1);
        expect(result.current.error).toBeNull();
        expect(result.current.fallos).toBe(1);
    });

    it('vuelve a consultar al cambiar los parámetros y conserva la lista mientras tanto', async () => {
        simularApi(api, {
            'GET /docentes': ({ params }) => ({ data: [`página ${params.pagina}`] }),
        });
        const { result, rerender } = renderHook(
            ({ pagina }) => usarConsulta('/docentes', { parametros: { pagina } }),
            {
                initialProps: { pagina: 1 },
            }
        );
        await waitFor(() => expect(result.current.datos).toEqual(['página 1']));

        rerender({ pagina: 2 });
        expect(result.current.cargando).toBe(false);
        expect(result.current.datos).toEqual(['página 1']);
        await waitFor(() => expect(result.current.datos).toEqual(['página 2']));
        expect(api.get).toHaveBeenCalledTimes(2);
    });

    it('no consulta sin ruta ni con activa en falso', () => {
        simularApi(api, {});
        const sinRuta = renderHook(() => usarConsulta(null));
        const inactiva = renderHook(() => usarConsulta('/examenes', { activa: false }));

        expect(api.get).not.toHaveBeenCalled();
        expect(sinRuta.result.current.cargando).toBe(false);
        expect(inactiva.result.current.datos).toBeNull();
    });
});
