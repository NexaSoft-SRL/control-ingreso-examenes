import { vi } from 'vitest';

// Sustituto del cliente en las pruebas: `vi.mock('../../api/cliente')` lo
// toma solo. Las respuestas se ponen con `simularApi` de test/apoyo.jsx.
export const api = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
};

export const SIN_SESION = 'sin_sesion';
export const suscribirSesion = vi.fn(() => () => {});
export const interpretarError = (error) => Promise.reject(error);

export default api;
