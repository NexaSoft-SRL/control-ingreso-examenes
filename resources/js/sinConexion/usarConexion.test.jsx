import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api, fallosDeRedSeguidos, suscribirRed } from '../api/cliente';
import { agregarEvento } from './almacen';
import { sincronizarTodo } from './flujo';
import usarConexion, { VISIBLE_MS } from './usarConexion';

vi.mock('../api/cliente');
vi.mock('./flujo', async (original) => ({
    ...(await original()),
    sincronizarTodo: vi.fn(),
}));

const evento = (uid) => ({ uid, resultado: 'ADMITIDO', medio: 'CODIGO', examen_id: 16 });

let avisarRed;
let enLinea;

// El navegador pierde o recupera la red.
function cambiarRed(valor) {
    enLinea = valor;
    act(() => {
        window.dispatchEvent(new Event(valor ? 'online' : 'offline'));
    });
}

beforeEach(() => {
    vi.useFakeTimers();
    window.localStorage.clear();
    enLinea = true;
    vi.spyOn(window.navigator, 'onLine', 'get').mockImplementation(() => enLinea);
    fallosDeRedSeguidos.mockReturnValue(0);
    suscribirRed.mockImplementation((oyente) => {
        avisarRed = oyente;
        return () => {};
    });
    api.get.mockResolvedValue({ data: {} });
    sincronizarTodo.mockResolvedValue(0);
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('usarConexion', () => {
    it('con red y sin pendientes no muestra nada', () => {
        const { result } = renderHook(() => usarConexion());

        expect(result.current).toMatchObject({ fase: 'conectado', pendientes: 0 });
    });

    it('cuando el navegador pierde la red avisa «sin conexión»', () => {
        const { result } = renderHook(() => usarConexion());

        cambiarRed(false);

        expect(result.current.fase).toBe('sin_conexion');
    });

    it('también avisa cuando fallan seguidas las peticiones sin que el servidor responda', () => {
        const { result } = renderHook(() => usarConexion());

        fallosDeRedSeguidos.mockReturnValue(2);
        act(() => avisarRed(2));

        expect(result.current.fase).toBe('sin_conexion');
    });

    it('un solo fallo no basta: puede ser una consulta lenta', () => {
        const { result } = renderHook(() => usarConexion());

        fallosDeRedSeguidos.mockReturnValue(1);
        act(() => avisarRed(1));

        expect(result.current.fase).toBe('conectado');
    });

    it('cuenta las lecturas guardadas mientras no hay conexión', () => {
        const { result } = renderHook(() => usarConexion());
        cambiarRed(false);

        act(() => {
            agregarEvento(78, evento('a'));
            agregarEvento(78, evento('b'));
            vi.advanceTimersByTime(2500);
        });

        expect(result.current.pendientes).toBe(2);
    });

    it('al volver la red sin nada pendiente dice «sincronizado» y se quita sola', () => {
        const { result } = renderHook(() => usarConexion());
        cambiarRed(false);

        cambiarRed(true);
        expect(result.current.fase).toBe('sincronizado');
        expect(sincronizarTodo).not.toHaveBeenCalled();

        act(() => vi.advanceTimersByTime(VISIBLE_MS + 100));
        expect(result.current.fase).toBe('conectado');
    });

    it('al volver la red envía lo pendiente y cuenta cuántas se enviaron', async () => {
        const { result } = renderHook(() => usarConexion());
        cambiarRed(false);
        act(() => {
            agregarEvento(78, evento('a'));
            agregarEvento(78, evento('b'));
            vi.advanceTimersByTime(2500);
        });
        let terminar;
        sincronizarTodo.mockImplementation(
            () =>
                new Promise((resolver) => {
                    terminar = () => {
                        window.localStorage.clear();
                        resolver(2);
                    };
                })
        );

        cambiarRed(true);
        expect(result.current.fase).toBe('enviando');
        expect(sincronizarTodo).toHaveBeenCalledTimes(1);

        await act(async () => terminar());

        expect(result.current).toMatchObject({ fase: 'sincronizado', enviadas: 2, pendientes: 0 });
    });

    it('si al volver la red no se pudo enviar todo, deja el aviso de lo pendiente', async () => {
        const { result } = renderHook(() => usarConexion());
        cambiarRed(false);
        act(() => {
            agregarEvento(78, evento('a'));
            vi.advanceTimersByTime(2500);
        });
        sincronizarTodo.mockResolvedValue(0);

        await act(async () => cambiarRed(true));

        expect(result.current).toMatchObject({ fase: 'pendiente', pendientes: 1 });
    });

    it('con la conexión caída pregunta al servidor cada tanto si ya volvió', () => {
        renderHook(() => usarConexion());
        cambiarRed(false);
        api.get.mockClear();

        act(() => vi.advanceTimersByTime(5100));

        expect(api.get).toHaveBeenCalledWith('/auth/sesion', { silencioso: true });
    });

    it('al abrir con lecturas guardadas de antes y con red, intenta enviarlas', async () => {
        agregarEvento(78, evento('a'));
        sincronizarTodo.mockImplementation(async () => {
            window.localStorage.clear();
            return 1;
        });

        const { result } = renderHook(() => usarConexion());
        expect(result.current.fase).toBe('pendiente');
        await act(async () => {});

        expect(sincronizarTodo).toHaveBeenCalledTimes(1);
        expect(result.current.fase).toBe('conectado');
    });
});
