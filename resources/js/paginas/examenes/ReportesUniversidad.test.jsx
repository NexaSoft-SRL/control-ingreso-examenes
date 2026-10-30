import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ReportesUniversidad from './ReportesUniversidad';
import { api } from '../../api/cliente';
import descargar from '../../api/descargar';
import { renderConSesion } from '../../test/apoyo';

vi.mock('../../api/cliente');
vi.mock('../../api/descargar', () => ({ default: vi.fn() }));

const examen = {
    id: 21,
    examen: 'Primer parcial',
    fecha: '2026-10-12',
};

const datosPorTipo = {
    horarios: { tramos: [{ tramo: '08:00–09:00', cantidad: 12 }], total: 12 },
    registrados: [{ fecha: '2026-10-12', examen: 'Primer parcial', registrados: 12 }],
    intentos: [{ ocurridaEn: '2026-10-12 08:15', estudiante: 'Estudiante de prueba' }],
    estudiantes: [{ codigoUniversitario: '2026001', nombre: 'Estudiante de prueba' }],
    incidencias: [{ hora: '08:20', tipo: 'Incidencia de prueba' }],
    asistencia: [{ codigoUniversitario: '2026001', nombre: 'Asistente de prueba' }],
    ausentes: [{ codigoUniversitario: '2026002', nombre: 'Ausente de prueba' }],
};

beforeEach(() => {
    api.get.mockReset();
    descargar.mockReset();
    descargar.mockResolvedValue('reporte.pdf');

    api.get.mockImplementation((ruta, opciones = {}) => {
        if (ruta === '/reportes/universidad') {
            const pagina = Number(opciones.params?.pagina ?? 1);

            return Promise.resolve({
                data: {
                    data:
                        pagina === 1
                            ? [examen]
                            : [{ id: 22, examen: 'Segundo parcial', fecha: '2026-10-13' }],
                    meta: {
                        total: 26,
                        pagina,
                        por_pagina: 25,
                        paginas: 2,
                    },
                    conteos: {},
                    cifras: {},
                },
            });
        }

        const tipo = ruta.split('/').at(-1);

        if (Object.hasOwn(datosPorTipo, tipo)) {
            return Promise.resolve({
                data: { data: datosPorTipo[tipo], alcance: {} },
            });
        }

        return Promise.reject(new Error(`Ruta no esperada: ${ruta}`));
    });
});

describe('Reportes universitarios', () => {
    it('consulta horarios mediante la API universitaria', async () => {
        renderConSesion(<ReportesUniversidad />, {
            ruta: '/reportes-universidad',
        });

        expect(await screen.findByText('08:00–09:00')).toBeInTheDocument();
        expect(screen.getByText('Total de ingresos: 12')).toBeInTheDocument();

        expect(api.get).toHaveBeenCalledWith('/reportes/universidad/horarios', { params: {} });
    });

    it.each([
        ['registrados', 'Primer parcial'],
        ['intentos', 'Estudiante de prueba'],
        ['estudiantes', '2026001'],
        ['incidencias', 'Incidencia de prueba'],
        ['asistencia', 'Asistente de prueba'],
        ['ausentes', 'Ausente de prueba'],
    ])('consulta el reporte %s', async (tipo, esperado) => {
        renderConSesion(<ReportesUniversidad />, {
            ruta: `/reportes-universidad?tipo=${tipo}&examen=21`,
        });

        expect(await screen.findByText(esperado)).toBeInTheDocument();

        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith(`/reportes/universidad/${tipo}`, {
                params: { examen: '21' },
            })
        );
    });

    it('exige examen para reportes individuales', async () => {
        renderConSesion(<ReportesUniversidad />, {
            ruta: '/reportes-universidad?tipo=asistencia',
        });

        expect(
            await screen.findByText('Selecciona un examen para consultar este reporte.')
        ).toBeInTheDocument();

        expect(
            api.get.mock.calls.some(([ruta]) => ruta === '/reportes/universidad/asistencia')
        ).toBe(false);
    });

    it('descarga PDF con los filtros de la API', async () => {
        renderConSesion(<ReportesUniversidad />, {
            ruta: '/reportes-universidad?tipo=horarios&facultad=fcyt',
        });

        fireEvent.click(await screen.findByRole('button', { name: 'PDF' }));

        await waitFor(() =>
            expect(descargar).toHaveBeenCalledWith('/reportes/universidad/descargas/horarios/pdf', {
                parametros: { facultad: 'fcyt' },
            })
        );
    });

    it('permite consultar páginas posteriores de exámenes', async () => {
        renderConSesion(<ReportesUniversidad />, {
            ruta: '/reportes-universidad',
        });

        expect(await screen.findByText(/Página 1 de 2/)).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Siguiente' }));

        await waitFor(() =>
            expect(api.get).toHaveBeenCalledWith('/reportes/universidad', {
                params: { pagina: 2, por_pagina: 25 },
            })
        );
    });
});
