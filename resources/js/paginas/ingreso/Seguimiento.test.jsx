import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
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

const llamadasA = (ruta) => api.get.mock.calls.filter(([destino]) => destino === ruta);

describe('Seguimiento HU-21', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

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

            if (ruta === '/examenes/10/incidencias') {
                return Promise.resolve({ data: { data: [], meta: { sin_atender: 0, todas: 0 } } });
            }

            return Promise.reject(new Error(`Ruta inesperada: ${ruta}`));
        });
    });

    it('muestra el aula autorizada, asistencia y refresco periódico', async () => {
        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        expect(await screen.findByRole('heading', { name: 'Mi aula' })).toBeInTheDocument();
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
        expect(screen.queryByText(/Actualización automática/)).not.toBeInTheDocument();
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

        expect(await screen.findByText('No se pudo actualizar la lista')).toBeInTheDocument();
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

    it('busca una sola vez cuando se deja de teclear', async () => {
        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        const busqueda = await screen.findByRole('searchbox', {
            name: 'Buscar por nombre, código o documento',
        });
        await waitFor(() => expect(llamadasA('/seguimiento/examenes/10/aulas/7')).toHaveLength(1));

        let texto = '';
        for (const letra of 'camacho') {
            texto += letra;
            fireEvent.change(busqueda, { target: { value: texto } });
        }

        await waitFor(() => expect(llamadasA('/seguimiento/examenes/10/aulas/7')).toHaveLength(2));
        await new Promise((listo) => setTimeout(listo, 400));
        const busquedas = llamadasA('/seguimiento/examenes/10/aulas/7').map(
            ([, opciones]) => opciones.params.buscar
        );
        expect(busquedas).toEqual(['', 'camacho']);
    });

    it('lista las incidencias del aula sin atender y el docente las atiende', async () => {
        const incidencia = {
            id: 5,
            tipo: 'SUPLANTACION',
            tipo_texto: 'Suplantación de identidad',
            gravedad: 'GRAVE',
            aula: 'A-101',
            estudiante: 'Rojas Vargas Ana · 202510245',
            detalle: 'La foto no coincide',
            hora: '08:10',
            por: 'Mamani Torrez Diego',
            rol: 'Auxiliar',
            atendida: false,
        };
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') return Promise.resolve({ data: { data: [aula] } });
            if (ruta === '/examenes/10/incidencias') {
                return Promise.resolve({
                    data: {
                        data: [incidencia, { ...incidencia, id: 6, atendida: true }],
                        meta: { sin_atender: 1, todas: 2 },
                    },
                });
            }
            return Promise.resolve({ data: { data: { ...detalle, puede_atender: true } } });
        });
        api.post.mockResolvedValue({ data: { message: 'Incidencia atendida.' } });

        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Docente') });

        expect(await screen.findByText('Incidencias · 2')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/examenes/10/incidencias', {
            params: { aula: 7 },
        });
        expect(screen.getAllByText('La foto no coincide')).toHaveLength(1);
        expect(screen.getByText('Mamani Torrez Diego · Auxiliar')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Atender' }));
        fireEvent.click(screen.getByRole('button', { name: 'Confirmar' }));
        await waitFor(() => expect(api.post).toHaveBeenCalledWith('/incidencias/5/atencion'));
        expect(await screen.findByText('Incidencia atendida')).toBeInTheDocument();
    });

    it('reporta una incidencia en su aula y ofrece «Controlar» a quien opera la puerta', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') return Promise.resolve({ data: { data: [aula] } });
            if (ruta === '/examenes/10/incidencias') {
                return Promise.resolve({ data: { data: [], meta: { sin_atender: 0, todas: 0 } } });
            }
            return Promise.resolve({
                data: { data: { ...detalle, puede_operar: true, abierto: true } },
            });
        });
        api.post.mockResolvedValue({ data: { message: 'Incidencia registrada.' } });

        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        expect(await screen.findByRole('link', { name: 'Controlar' })).toHaveAttribute(
            'href',
            '/punto-control?examen=10&aula=7'
        );

        fireEvent.click(screen.getByRole('button', { name: 'Incidencia' }));
        const dialogo = screen.getByRole('dialog', { name: 'Incidencia' });
        expect(
            within(dialogo).getByText('Aula A-101 · 08:15 · Mamani Torrez Diego')
        ).toBeInTheDocument();
        fireEvent.change(within(dialogo).getByLabelText(/Tipo/), {
            target: { value: 'MATERIAL_NO_PERMITIDO' },
        });
        fireEvent.change(within(dialogo).getByLabelText('Estudiante'), {
            target: { value: '202510245' },
        });
        fireEvent.change(within(dialogo).getByLabelText(/Detalle/), {
            target: { value: 'Apuntes en la mesa' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Registrar' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/examenes/10/incidencias', {
                aula_id: 7,
                tipo: 'MATERIAL_NO_PERMITIDO',
                estudiante: '202510245',
                detalle: 'Apuntes en la mesa',
            })
        );
        expect(await screen.findByText('Incidencia registrada')).toBeInTheDocument();
    });

    it('sin la puerta del aula no ofrece «Controlar»', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') return Promise.resolve({ data: { data: [aula] } });
            if (ruta === '/examenes/10/incidencias') {
                return Promise.resolve({ data: { data: [], meta: { sin_atender: 0, todas: 0 } } });
            }
            return Promise.resolve({
                data: { data: { ...detalle, puede_operar: false, abierto: true } },
            });
        });

        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        expect(await screen.findByRole('heading', { name: 'Aula A-101' })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Controlar' })).not.toBeInTheDocument();
    });

    it('sin aulas de hoy muestra «Sin aula asignada»', async () => {
        api.get.mockResolvedValue({ data: { data: [] } });

        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        expect(await screen.findByText('Sin aula asignada')).toBeInTheDocument();
        expect(screen.queryByText(/tienes/)).not.toBeInTheDocument();
    });
});
