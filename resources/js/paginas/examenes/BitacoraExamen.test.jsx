import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import BitacoraExamen from './BitacoraExamen';
import { api } from '../../api/cliente';
import descargar from '../../api/descargar';
import { errorHttp, renderConSesion, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');
vi.mock('../../api/descargar', () => ({ default: vi.fn() }));

const examen = {
    id: 21,
    tipo: 'Primer parcial',
    asignatura: 'Cálculo I',
    fecha: '2026-10-12',
    hora: '08:15',
};

const eventos = [
    {
        id: 'ingreso-1',
        ocurrida_en: '2026-10-12 08:15:00',
        categoria: 'INGRESO',
        suceso: 'Ingreso autorizado',
        estudiante: 'Álvarez José',
        codigo: '202100001',
        aula_id: 7,
        aula: '624',
        detalle: 'Identidad verificada correctamente',
        actor: 'Docente',
    },
];

beforeEach(() => {
    api.get.mockReset();
    descargar.mockReset();
    descargar.mockResolvedValue('bitácora 2026-10-12.xlsx');
    api.get.mockImplementation((ruta) => {
        if (ruta === '/reportes/bitacora/examenes') {
            return Promise.resolve({ data: { data: [examen] } });
        }
        if (ruta === '/reportes/examenes/21/bitacora') {
            return Promise.resolve({
                data: {
                    data: eventos,
                    examen,
                    aulas: [{ id: 7, nombre: '624' }],
                    categorias: [{ valor: 'INGRESO', etiqueta: 'Ingresos', total: 1 }],
                    meta: { total: 1, total_examen: 1, pagina: 1, por_pagina: 100, paginas: 1 },
                },
            });
        }
        return Promise.reject(errorHttp(404, { message: 'No encontrado.' }));
    });
});

describe('Bitácora del examen · HU-29', () => {
    it('muestra la cronología y solo ofrece categorías con resultados', async () => {
        renderConSesion(<BitacoraExamen />, {
            ruta: '/bitacora-examen?examen=21',
            usuario: usuarioDePrueba('Docente'),
        });

        expect(await screen.findByRole('heading', { name: 'Ingreso autorizado' })).toBeVisible();
        expect(screen.getByText(/Álvarez José/)).toBeInTheDocument();
        expect(screen.getByText('Actor: Docente')).toBeInTheDocument();
        expect(screen.getByRole('option', { name: 'Ingresos (1)' })).toBeInTheDocument();
        expect(screen.queryByRole('option', { name: 'Rechazos' })).not.toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/reportes/examenes/21/bitacora', {
            params: { pagina: 1 },
        });
    });

    it('permite seleccionar el examen, filtrar sucesos y descargar sin reenviar filtros de pantalla', async () => {
        renderConSesion(<BitacoraExamen />, {
            ruta: '/bitacora-examen',
            usuario: usuarioDePrueba('Administrador'),
        });

        fireEvent.change(await screen.findByRole('combobox', { name: 'Seleccionar examen' }), {
            target: { value: '21' },
        });
        expect(await screen.findByRole('heading', { name: 'Ingreso autorizado' })).toBeVisible();
        fireEvent.change(screen.getByLabelText('Suceso'), { target: { value: 'INGRESO' } });

        await waitFor(() => {
            expect(
                api.get.mock.calls.some(
                    ([ruta, opciones]) =>
                        ruta === '/reportes/examenes/21/bitacora' &&
                        opciones?.params?.categoria === 'INGRESO'
                )
            ).toBe(true);
        });

        fireEvent.click(screen.getByRole('button', { name: 'XLSX' }));
        await waitFor(() => {
            expect(descargar).toHaveBeenCalledWith('/reportes/examenes/21/bitacora/descargas/xlsx');
        });
        expect(await screen.findByText('bitácora 2026-10-12.xlsx descargada.')).toBeInTheDocument();
    });

    it('deshabilita las descargas cuando el examen no tiene eventos', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/reportes/bitacora/examenes') {
                return Promise.resolve({ data: { data: [examen] } });
            }
            if (ruta === '/reportes/examenes/21/bitacora') {
                return Promise.resolve({
                    data: {
                        data: [],
                        examen,
                        aulas: [],
                        categorias: [],
                        meta: {
                            total: 0,
                            total_examen: 0,
                            pagina: 1,
                            por_pagina: 100,
                            paginas: 0,
                        },
                    },
                });
            }
            return Promise.reject(errorHttp(404));
        });

        renderConSesion(<BitacoraExamen />, {
            ruta: '/bitacora-examen?examen=21',
            usuario: usuarioDePrueba('Docente'),
        });

        expect(
            await screen.findByText('Este examen todavía no tiene eventos.')
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'PDF' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'XLSX' })).toBeDisabled();
    });
});
