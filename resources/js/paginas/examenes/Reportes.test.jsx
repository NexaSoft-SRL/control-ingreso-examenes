import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Reportes from './Reportes';
import { api } from '../../api/cliente';
import descargar from '../../api/descargar';
import { errorHttp, renderConSesion, respuestaDiferida, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');
vi.mock('../../api/descargar', () => ({ default: vi.fn() }));

const examen = {
    id: 21,
    fecha: '2026-10-12',
    hora: '08:15',
    tipo: 'Primer parcial',
    asignatura: { id: 3, codigo: '2010010', nombre: 'Introducción a la Programación' },
    inscritos: 251,
    habilitados: 251,
    ingresaron: 201,
    asistencia_porcentaje: 80,
    rendido: true,
};

const detalle = {
    id: 21,
    periodo: '2/2026',
    fecha: '2026-10-12',
    hora: '08:15',
    tipo: 'Primer parcial',
    asignatura: { codigo: '2010010', nombre: 'Introducción a la Programación' },
    rendido: true,
    inscritos: 251,
    ingresaron: 201,
    habilitados: 251,
    ausentes: 50,
    no_habilitados: 9,
    incidencias: 6,
    asistencia_porcentaje: 80,
    muestras: {
        asistencia: [],
        ausentes: [],
        incidencias: [
            {
                hora: '08:20',
                aula: '624',
                tipo: 'Intento rechazado · No habilitado',
                estudiante: 'Apellido Nombre',
            },
        ],
    },
    totales: { asistencia: 201, ausentes: 50, incidencias: 1 },
};

const incidencias = Array.from({ length: 6 }, (_, indice) => ({
    hora: `08:${String(20 + indice).padStart(2, '0')}`,
    aula: '624',
    tipo: indice === 0 ? 'Intento rechazado · No habilitado' : 'Expulsión',
    gravedad: indice === 0 ? 'Media' : 'Grave',
    estudiante: `Apellido Nombre ${indice + 1}`,
    codigo: `20210000${indice + 1}`,
    detalle: `Detalle ${indice + 1}`,
    atencion: indice === 0 ? 'Sin atender' : 'Atendida',
    ocurrida_en: `2026-10-12 08:${String(20 + indice)}:00`,
}));

function respuestaLista(examenes = [examen]) {
    return {
        data: {
            data: examenes,
            meta: { total: examenes.length, pagina: 1, por_pagina: 8, paginas: 1, rendidos: 1 },
            periodos: [{ id: 5, codigo: '2/2026' }],
            asignaturas: [{ id: 3, codigo: '2010010', nombre: 'Introducción a la Programación' }],
        },
    };
}

beforeEach(() => {
    api.get.mockReset();
    descargar.mockReset();
    descargar.mockResolvedValue('asistencia-2026-10-12.pdf');
    api.get.mockImplementation((ruta) => {
        if (ruta === '/reportes') return Promise.resolve(respuestaLista());
        if (ruta === '/reportes/examenes/21') return Promise.resolve({ data: { data: detalle } });
        if (ruta === '/reportes/examenes/21/incidencias') {
            return Promise.resolve({
                data: {
                    data: incidencias,
                    meta: { total: incidencias.length, pagina: 1, por_pagina: 8, paginas: 1 },
                },
            });
        }
        return Promise.reject(errorHttp(404, { message: 'No encontrado.' }));
    });
});

describe('Reportes · HU-23', () => {
    it('lista exámenes con asistencia e incluye el detalle seleccionado desde la URL', async () => {
        renderConSesion(<Reportes />, { ruta: '/reportes?examen=21' });

        expect(await screen.findByText('80%')).toBeInTheDocument();
        expect(
            await screen.findByRole('heading', { name: /Primer parcial · Introducción/ })
        ).toBeVisible();
        expect(screen.getByText('Período 2/2026 · 1 examen · 1 rendido')).toBeInTheDocument();
        expect(screen.getByText('201 de 251')).toBeInTheDocument();
        expect(screen.getByText('50 de 251')).toBeInTheDocument();
        expect(await screen.findByText(/Intento rechazado/)).toBeInTheDocument();
        expect(await screen.findByText('Sin atender')).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/reportes/examenes/21', { params: {} });

        fireEvent.click(await screen.findByRole('button', { name: 'Ver todas · 6' }));
        expect(await screen.findByText('Detalle 6')).toBeInTheDocument();
    });

    it('mantiene la descarga por reporte y muestra confirmación al recibir el archivo', async () => {
        renderConSesion(<Reportes />, { ruta: '/reportes?examen=21' });
        const boton = await screen.findByRole('button', {
            name: 'Descargar Asistencia por examen PDF',
        });
        fireEvent.click(boton);

        await waitFor(() =>
            expect(descargar).toHaveBeenCalledWith('/reportes/examenes/21/descargas/asistencia/pdf')
        );
        expect(
            await screen.findByText('asistencia-2026-10-12.pdf descargado.')
        ).toBeInTheDocument();
    });

    it('mantiene deshabilitados solo los botones cuyas descargas siguen en curso', async () => {
        const descargaPendiente = respuestaDiferida();
        descargar.mockImplementation(() => descargaPendiente.promesa);
        renderConSesion(<Reportes />, { ruta: '/reportes?examen=21' });

        const asistenciaPdf = await screen.findByRole('button', {
            name: 'Descargar Asistencia por examen PDF',
        });
        const asistenciaHoja = screen.getByRole('button', {
            name: 'Descargar Asistencia por examen Hoja',
        });
        const incidenciasPdf = screen.getByRole('button', {
            name: 'Descargar Incidencias del examen PDF',
        });

        fireEvent.click(asistenciaPdf);
        await waitFor(() => expect(descargar).toHaveBeenCalledTimes(1));
        expect(asistenciaPdf).toBeDisabled();
        expect(asistenciaHoja).toBeEnabled();
        expect(incidenciasPdf).toBeEnabled();

        fireEvent.click(asistenciaHoja);
        await waitFor(() => expect(descargar).toHaveBeenCalledTimes(2));
        expect(asistenciaPdf).toBeDisabled();
        expect(asistenciaHoja).toBeDisabled();
        expect(incidenciasPdf).toBeEnabled();

        descargaPendiente.resolver('reporte.xlsx');
        await waitFor(() => expect(asistenciaPdf).toBeEnabled());
        expect(asistenciaHoja).toBeEnabled();
    });

    it('deshabilita las descargas de exámenes futuros', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/reportes') {
                return Promise.resolve(respuestaLista([{ ...examen, rendido: false }]));
            }
            if (ruta === '/reportes/examenes/21') {
                return Promise.resolve({
                    data: { data: { ...detalle, rendido: false } },
                });
            }
            return Promise.reject(errorHttp(404));
        });
        renderConSesion(<Reportes />, { ruta: '/reportes?examen=21' });

        expect((await screen.findAllByText('Sin rendir')).length).toBeGreaterThan(0);
        expect(
            screen.getByRole('button', { name: 'Descargar Asistencia por examen PDF' })
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Descargar Asistencia por examen Hoja' })
        ).toBeDisabled();
        expect(screen.getAllByText('—')).toHaveLength(4);
        expect(
            screen.queryByRole('heading', { name: 'Incidencias del examen' })
        ).not.toBeInTheDocument();
    });

    it('muestra el error explícito del servidor cuando falla una descarga', async () => {
        descargar.mockRejectedValueOnce(
            errorHttp(409, { message: 'El examen todavía no se rindió.' })
        );
        renderConSesion(<Reportes />, { ruta: '/reportes?examen=21' });
        fireEvent.click(
            await screen.findByRole('button', { name: 'Descargar Asistencia por examen PDF' })
        );

        expect(await screen.findByText('El examen todavía no se rindió.')).toBeInTheDocument();
    });

    it('mantiene la descarga de incidencias pero no muestra su tarjeta sin permiso de seguimiento', async () => {
        renderConSesion(<Reportes />, {
            ruta: '/reportes?examen=21',
            usuario: usuarioDePrueba('Docente', { permisos: ['reportes_examenes'] }),
        });

        expect(
            await screen.findByRole('button', {
                name: 'Descargar Incidencias del examen PDF',
            })
        ).toBeEnabled();
        expect(
            screen.queryByRole('heading', { name: 'Incidencias del examen' })
        ).not.toBeInTheDocument();
        expect(api.get).not.toHaveBeenCalledWith(
            '/reportes/examenes/21/incidencias',
            expect.anything()
        );
    });

    it('al cambiar de período vuelve a Todas las asignaturas', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/reportes') {
                return Promise.resolve({
                    data: {
                        ...respuestaLista().data,
                        periodos: [
                            { id: 5, codigo: '2/2026' },
                            { id: 4, codigo: '1/2026' },
                        ],
                    },
                });
            }
            return Promise.reject(errorHttp(404));
        });
        renderConSesion(<Reportes />, { ruta: '/reportes?asignatura=3' });

        fireEvent.click(await screen.findByRole('button', { name: '1/2026' }));
        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith('/reportes', {
                params: { periodo: 4, pagina: 1 },
            })
        );
    });
});
