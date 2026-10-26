import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import Seguimiento from './Seguimiento';
import { api } from '../../api/cliente';
import { renderConSesion, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        patch: vi.fn(),
    },
}));

const aula = {
    examen_id: 10,
    aula_id: 7,
    aula: 'A-101',
    fecha: '2026-10-16',
    hora: '08:00',
    tipo: 'Primer parcial',
    asignatura: 'Programación',
    puede_asignar: false,
};

const detalle = {
    examen: {
        id: 10,
        fecha: '2026-10-16',
        hora: '08:00',
        duracion_minutos: 90,
        tipo: 'Primer parcial',
        asignatura: 'Programación',
        hora_servidor: '2026-10-16T08:15:00-04:00',
    },
    aula: { id: 7, nombre: 'A-101' },
    cifras: { ingresaron: 3, faltan: 2, asignados: 5, incidencias: 0 },
    estudiantes: [],
    incidencias: [],
    pagina: { actual: 1, ultima: 1, por_pagina: 25, total: 0 },
};

describe('Seguimiento HU-21', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        Object.defineProperty(document, 'hidden', { configurable: true, value: false });
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') {
                return Promise.resolve({ data: { data: [aula] } });
            }

            if (ruta === '/seguimiento/examenes/10/aulas/7') {
                return Promise.resolve({ data: { data: detalle } });
            }

            return Promise.reject(new Error(`Ruta inesperada: ${ruta}`));
        });
    });

    it('muestra el aula autorizada, asistencia y refresco periódico', async () => {
        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        expect(
            await screen.findByRole('heading', { name: 'Seguimiento del aula' })
        ).toBeInTheDocument();
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(
                '/seguimiento/examenes/10/aulas/7',
                expect.objectContaining({
                    params: { estado: 'faltan', buscar: '', pagina: 1 },
                })
            );
        });
        expect(await screen.findByRole('heading', { name: 'Aula A-101' })).toBeInTheDocument();
        expect(screen.getByText('Ingresaron')).toBeInTheDocument();
        expect(screen.getByText('Faltan')).toBeInTheDocument();
        expect(screen.getByText('Asignados')).toBeInTheDocument();
        expect(screen.getByText('Actualización automática cada 15 segundos')).toBeInTheDocument();
    });

    it('abre el aula que llega en la dirección desde «En curso»', async () => {
        const otra = { ...aula, aula_id: 8, aula: 'A-102' };
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') {
                return Promise.resolve({ data: { data: [aula, otra] } });
            }

            if (ruta === '/seguimiento/examenes/10/aulas/8') {
                return Promise.resolve({
                    data: { data: { ...detalle, aula: { id: 8, nombre: 'A-102' } } },
                });
            }

            return Promise.reject(new Error(`Ruta inesperada: ${ruta}`));
        });

        renderConSesion(<Seguimiento />, {
            usuario: usuarioDePrueba('Docente'),
            ruta: '/seguimiento?examen=10&aula=8',
        });

        expect(await screen.findByRole('heading', { name: 'Aula A-102' })).toBeInTheDocument();
        expect(api.get).not.toHaveBeenCalledWith(
            '/seguimiento/examenes/10/aulas/7',
            expect.anything()
        );
    });

    it('conserva el aula asignada de un examen ajeno en el enlace de «En curso»', async () => {
        const examenAsignado = {
            ...aula,
            examen_id: 12,
            aula_id: 19,
            aula: '692A',
        };
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') {
                return Promise.resolve({ data: { data: [aula, examenAsignado] } });
            }

            if (ruta === '/seguimiento/examenes/12/aulas/19') {
                return Promise.resolve({
                    data: {
                        data: {
                            ...detalle,
                            examen: { ...detalle.examen, id: 12 },
                            aula: { id: 19, nombre: '692A' },
                        },
                    },
                });
            }

            return Promise.reject(new Error(`Ruta inesperada: ${ruta}`));
        });

        renderConSesion(<Seguimiento />, {
            usuario: usuarioDePrueba('Docente'),
            ruta: '/seguimiento?examen=12&aula=19',
        });

        expect(await screen.findByRole('heading', { name: 'Aula 692A' })).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith(
            '/seguimiento/examenes/12/aulas/19',
            expect.objectContaining({ params: { estado: 'faltan', buscar: '', pagina: 1 } })
        );
        expect(api.get).not.toHaveBeenCalledWith(
            '/seguimiento/examenes/10/aulas/7',
            expect.anything()
        );
    });

    it('distingue un error del servidor de una pérdida de conexión', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') {
                return Promise.resolve({ data: { data: [aula] } });
            }

            return Promise.reject({ response: { status: 500 } });
        });

        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        expect(
            await screen.findByText(
                'El servidor no pudo actualizar el seguimiento. Inténtalo de nuevo.'
            )
        ).toBeInTheDocument();
        expect(screen.queryByText(/Sin conexión/)).not.toBeInTheDocument();
    });

    it('mantiene el foco en la búsqueda mientras actualiza los resultados', async () => {
        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        const busqueda = await screen.findByRole('searchbox', {
            name: 'Buscar por nombre, código o documento',
        });
        busqueda.focus();
        fireEvent.change(busqueda, { target: { value: 'alvaro alvarez' } });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(
                '/seguimiento/examenes/10/aulas/7',
                expect.objectContaining({
                    params: { estado: 'faltan', buscar: 'alvaro alvarez', pagina: 1 },
                })
            );
        });
        expect(document.activeElement).toBe(busqueda);
    });
});
