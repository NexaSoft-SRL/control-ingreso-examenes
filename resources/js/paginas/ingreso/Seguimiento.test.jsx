import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
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
});
