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

    it('muestra «Mi aula · A-101» con sus cifras y la lista de los que faltan', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') return Promise.resolve({ data: { data: [aula] } });
            if (ruta === '/examenes/10/incidencias') {
                return Promise.resolve({ data: { data: [], meta: { sin_atender: 0, todas: 0 } } });
            }
            return Promise.resolve({
                data: {
                    data: {
                        ...detalle,
                        abierto: true,
                        cifras: { ...detalle.cifras, de_otra_aula: 2, en_otra_aula: 1 },
                        estudiantes: [
                            {
                                estudiante_id: 1,
                                codigo: '202510245',
                                nombres: 'Ana',
                                apellidos: 'Rojas Vargas',
                                grupos: ['Grupo 2'],
                                registrado_en: null,
                            },
                        ],
                        pagina: { actual: 1, ultima: 1, por_pagina: 25, total: 1 },
                    },
                },
            });
        });

        renderConSesion(<Seguimiento />, {
            usuario: usuarioDePrueba('Auxiliar'),
            ruta: '/mi-aula',
        });

        expect(await screen.findByRole('heading', { name: 'Mi aula · A-101' })).toBeInTheDocument();
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(
                '/seguimiento/examenes/10/aulas/7',
                expect.objectContaining({
                    params: expect.objectContaining({ estado: 'faltan', pagina: 1 }),
                })
            );
        });
        expect(await screen.findByText('Habilitados')).toBeInTheDocument();
        expect(
            screen.getByText('2 ingresaron aquí desde otra aula · 1 asignado entró por otra aula')
        ).toBeInTheDocument();
        expect(screen.getByText('Rojas Vargas Ana')).toBeInTheDocument();
        expect(screen.getByText(/Grupo 2/)).toBeInTheDocument();
        // Con el ingreso abierto, quien no entró todavía no es ausente.
        expect(screen.getByText('Sin ingreso')).toBeInTheDocument();
        expect(screen.queryByText('Ausente')).not.toBeInTheDocument();
    });

    it('el docente abre el aula pedida y vuelve a «En curso»', async () => {
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
            if (ruta === '/examenes/10/incidencias') {
                return Promise.resolve({ data: { data: [], meta: { sin_atender: 0, todas: 0 } } });
            }
            return Promise.reject(new Error(`Ruta inesperada: ${ruta}`));
        });

        renderConSesion(<Seguimiento />, {
            usuario: usuarioDePrueba('Docente'),
            ruta: '/en-curso/aula?examen=10&aula=8',
        });

        expect(await screen.findByRole('heading', { name: 'Aula A-102' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Volver a En curso/ })).toHaveAttribute(
            'href',
            '/en-curso?examen=10'
        );
        expect(api.get).not.toHaveBeenCalledWith(
            '/seguimiento/examenes/10/aulas/7',
            expect.anything()
        );
    });

    it('busca una sola vez cuando se deja de teclear', async () => {
        renderConSesion(<Seguimiento />, {
            usuario: usuarioDePrueba('Auxiliar'),
            ruta: '/mi-aula',
        });

        const busqueda = await screen.findByRole('textbox', { name: 'Buscar estudiante' });
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
        expect(busquedas).toEqual([undefined, 'camacho']);
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
            '/punto-control?aula=7'
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

        expect(await screen.findByRole('heading', { name: 'Mi aula · A-101' })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Controlar' })).not.toBeInTheDocument();
    });

    it('sin aulas de hoy muestra «Sin aula asignada»', async () => {
        api.get.mockResolvedValue({ data: { data: [] } });

        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        expect(await screen.findByText('Sin aula asignada')).toBeInTheDocument();
        expect(screen.queryByText(/tienes/)).not.toBeInTheDocument();
    });
});
