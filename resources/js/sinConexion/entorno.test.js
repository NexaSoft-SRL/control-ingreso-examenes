import { afterEach, describe, expect, it, vi } from 'vitest';
import { almacenamientoDisponible, esNavegacionPrivada, pedirPersistencia } from './entorno';

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('entorno del navegador', () => {
    it('detecta si se puede guardar', () => {
        expect(almacenamientoDisponible()).toBe(true);
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('bloqueado');
        });
        expect(almacenamientoDisponible()).toBe(false);
    });

    it('toma una cuota muy chica por navegación privada', async () => {
        vi.stubGlobal('navigator', {
            storage: { estimate: async () => ({ quota: 100 * 1024 * 1024 }) },
        });
        expect(await esNavegacionPrivada()).toBe(true);
        vi.stubGlobal('navigator', { storage: { estimate: async () => ({ quota: 5e10 }) } });
        expect(await esNavegacionPrivada()).toBe(false);
    });

    it('ante la duda no dice que es privada', async () => {
        vi.stubGlobal('navigator', {});
        expect(await esNavegacionPrivada()).toBe(false);
    });

    it('pide almacenamiento persistente', async () => {
        const persist = vi.fn(async () => true);
        vi.stubGlobal('navigator', { storage: { persisted: async () => false, persist } });
        expect(await pedirPersistencia()).toBe(true);
        expect(persist).toHaveBeenCalled();
        vi.stubGlobal('navigator', {});
        expect(await pedirPersistencia()).toBeNull();
    });
});
