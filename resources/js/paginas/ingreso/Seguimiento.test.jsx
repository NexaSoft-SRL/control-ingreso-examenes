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

    it('muestra las incidencias con su tipo legible, quién reportó y quién atendió', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') {
                return Promise.resolve({ data: { data: [aula] } });
            }

            return Promise.resolve({
                data: {
                    data: {
                        ...detalle,
                        cifras: { ...detalle.cifras, incidencias: 3 },
                        incidencias: [
                            {
                                id: 1,
                                tipo: 'EXCEPCION_CAMBIO_AULA',
                                detalle: 'Entró por la 691A',
                                reportada_en: '2026-10-16 08:41:10',
                                estudiante: null,
                                reportante: 'Blanco Coca Leticia',
                                rol_reportante: 'Docente',
                                atendida_en: '2026-10-16 08:45:02',
                                atendida_por: 'Salazar Serrudo Carla',
                            },
                            {
                                id: 2,
                                tipo: 'INCIDENTE_GENERAL',
                                detalle: 'Corte de luz',
                                reportada_en: '2026-10-16 08:50:00',
                                estudiante: null,
                                reportante: 'Mamani Torrez Diego',
                                rol_reportante: 'Auxiliar',
                                atendida_en: null,
                                atendida_por: null,
                            },
                            {
                                id: 3,
                                tipo: 'IDENTIFICACION_DUDOSA',
                                detalle: 'Foto distinta',
                                reportada_en: '2026-10-16 08:52:00',
                                estudiante: null,
                                reportante: 'Mamani Torrez Diego',
                                rol_reportante: 'Auxiliar',
                                atendida_en: null,
                                atendida_por: null,
                            },
                        ],
                    },
                },
            });
        });

        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        expect(await screen.findByText('Excepción: cambio de aula')).toBeInTheDocument();
        expect(screen.getByText('Incidente general')).toBeInTheDocument();
        expect(screen.getByText('Identificación dudosa')).toBeInTheDocument();
        expect(
            screen.getByText('Reportó Blanco Coca Leticia · Docente · 08:41')
        ).toBeInTheDocument();
        expect(screen.getByText('Atendida por Salazar Serrudo Carla · 08:45')).toBeInTheDocument();
        expect(screen.getAllByText('Sin atender')).toHaveLength(2);
        expect(screen.queryByRole('button', { name: 'Atender' })).not.toBeInTheDocument();
    });

    it('avisa «Incidencia atendida» y el aviso se cierra solo', async () => {
        const incidencia = {
            id: 5,
            tipo: 'CONDUCTA',
            detalle: 'Habla en voz alta',
            reportada_en: '2026-10-16 08:41:10',
            estudiante: null,
            reportante: 'Mamani Torrez Diego',
            rol_reportante: 'Auxiliar',
            atendida_en: null,
            atendida_por: null,
        };
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') {
                return Promise.resolve({ data: { data: [aula] } });
            }

            return Promise.resolve({
                data: {
                    data: {
                        ...detalle,
                        puede_atender: true,
                        cifras: { ...detalle.cifras, incidencias: 1 },
                        incidencias: [incidencia],
                    },
                },
            });
        });
        api.patch.mockResolvedValue({ data: { message: 'Incidencia atendida.' } });

        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        fireEvent.click(await screen.findByRole('button', { name: 'Atender' }));
        expect(await screen.findByText('Incidencia atendida')).toBeInTheDocument();
        expect(api.patch).toHaveBeenCalledWith('/seguimiento/incidencias/5/atendida');

        await waitFor(
            () => expect(screen.queryByText('Incidencia atendida')).not.toBeInTheDocument(),
            { timeout: 4000 }
        );
    });

    it('ofrece «Incidencia» y «Controlar» a quien opera la puerta del aula', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') {
                return Promise.resolve({ data: { data: [aula] } });
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
            target: { value: 'DOCUMENTO_OBSERVADO' },
        });
        fireEvent.change(within(dialogo).getByLabelText('Código universitario'), {
            target: { value: '202510245' },
        });
        fireEvent.change(within(dialogo).getByLabelText(/Detalle/), {
            target: { value: 'Carnet vencido' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Registrar' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/aulas/7/punto-control/incidencias-manuales', {
                examen_id: 10,
                tipo: 'DOCUMENTO_OBSERVADO',
                detalle: 'Carnet vencido',
                codigo_universitario: '202510245',
            })
        );
        expect(await screen.findByText('Incidencia registrada')).toBeInTheDocument();
        expect(screen.queryByRole('dialog', { name: 'Incidencia' })).not.toBeInTheDocument();
    });

    it('sin la puerta del aula no ofrece «Incidencia» ni «Controlar»', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/seguimiento/aulas') {
                return Promise.resolve({ data: { data: [aula] } });
            }

            return Promise.resolve({
                data: { data: { ...detalle, puede_operar: false, abierto: true } },
            });
        });

        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        expect(await screen.findByRole('heading', { name: 'Aula A-101' })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Controlar' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Incidencia' })).not.toBeInTheDocument();
    });

    it('sin aulas de hoy muestra «Sin aula asignada»', async () => {
        api.get.mockResolvedValue({ data: { data: [] } });

        renderConSesion(<Seguimiento />, { usuario: usuarioDePrueba('Auxiliar') });

        expect(await screen.findByText('Sin aula asignada')).toBeInTheDocument();
        expect(screen.queryByText(/tienes/)).not.toBeInTheDocument();
    });
});
