import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import IncidenciasManuales from './IncidenciasManuales';
import { api } from '../../api/cliente';

vi.mock('../../api/cliente', () => ({
    api: { get: vi.fn(), post: vi.fn() },
}));

const examenA = {
    id: 10,
    tipo: 'parcial',
    asignatura: { nombre: 'Programación' },
    aulas: [{ id: 7, nombre: 'A-101' }],
};

const examenB = {
    id: 11,
    tipo: 'final',
    asignatura: { nombre: 'Redes' },
    aulas: [{ id: 7, nombre: 'A-101' }],
};

describe('BUG-09: incidencias manuales', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.get.mockResolvedValue({ data: { data: [] } });
        api.post.mockResolvedValue({
            data: { data: { incidencia_id: 50 } },
        });
    });

    it('registra una incidencia y vuelve a consultar el historial', async () => {
        let registrada = false;

        api.get.mockImplementation(() =>
            Promise.resolve({
                data: {
                    data: registrada
                        ? [
                              {
                                  incidencia_id: 50,
                                  examen_id: 10,
                                  aula_id: 7,
                                  estudiante_id: null,
                                  tipo: 'INCIDENTE_GENERAL',
                                  detalle: 'Corte temporal de energía.',
                                  operador: 'Docente de prueba',
                                  reportada_en: '2026-10-22 10:00:00',
                                  atendida_en: null,
                              },
                          ]
                        : [],
                },
            })
        );

        api.post.mockImplementation(() => {
            registrada = true;
            return Promise.resolve({ data: { data: { incidencia_id: 50 } } });
        });

        render(<IncidenciasManuales aulaId="7" examenes={[examenA]} />);

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/aulas/7/punto-control/incidencias-manuales', {
                params: { examen_id: 10, limite: 20 },
                signal: expect.any(AbortSignal),
            });
        });

        fireEvent.change(screen.getByRole('textbox', { name: 'Detalle de incidencia manual' }), {
            target: { value: 'Corte temporal de energía.' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Registrar incidencia manual' }));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/aulas/7/punto-control/incidencias-manuales', {
                examen_id: 10,
                tipo: 'INCIDENTE_GENERAL',
                detalle: 'Corte temporal de energía.',
            });
        });

        expect(await screen.findByText('Corte temporal de energía.')).toBeInTheDocument();

        expect(await screen.findByText('Docente de prueba', { exact: false })).toBeInTheDocument();

        expect(api.get.mock.calls.length).toBeGreaterThanOrEqual(2);
    });

    it('no envía detalles demasiado cortos', async () => {
        render(<IncidenciasManuales aulaId="7" examenes={[examenA]} />);

        fireEvent.change(screen.getByRole('textbox', { name: 'Detalle de incidencia manual' }), {
            target: { value: 'abc' },
        });

        expect(screen.getByRole('button', { name: 'Registrar incidencia manual' })).toBeDisabled();

        expect(api.post).not.toHaveBeenCalled();
    });

    it('exige seleccionar el examen cuando dos comparten aula', async () => {
        render(<IncidenciasManuales aulaId="7" examenes={[examenA, examenB]} />);

        const selector = screen.getByRole('combobox', {
            name: 'Examen de incidencia manual',
        });

        expect(selector).toHaveValue('');
        expect(within(selector).getAllByRole('option')).toHaveLength(3);

        fireEvent.change(screen.getByRole('textbox', { name: 'Detalle de incidencia manual' }), {
            target: { value: 'Incidencia observada durante el examen.' },
        });

        expect(screen.getByRole('button', { name: 'Registrar incidencia manual' })).toBeDisabled();

        fireEvent.change(selector, { target: { value: '11' } });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/aulas/7/punto-control/incidencias-manuales', {
                params: { examen_id: 11, limite: 20 },
                signal: expect.any(AbortSignal),
            });
        });

        fireEvent.click(screen.getByRole('button', { name: 'Registrar incidencia manual' }));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith(
                '/aulas/7/punto-control/incidencias-manuales',
                expect.objectContaining({ examen_id: 11 })
            );
        });
    });

    it('ignora un POST tardío tras cambiar de examen', async () => {
        let resolverPost;

        api.post.mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolverPost = resolve;
                })
        );

        render(<IncidenciasManuales aulaId="7" examenes={[examenA, examenB]} />);

        const selector = screen.getByRole('combobox', {
            name: 'Examen de incidencia manual',
        });

        fireEvent.change(selector, { target: { value: '10' } });

        fireEvent.change(
            screen.getByRole('textbox', {
                name: 'Detalle de incidencia manual',
            }),
            {
                target: {
                    value: 'Incidencia correspondiente al examen A.',
                },
            }
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Registrar incidencia manual',
            })
        );

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledTimes(1);
        });

        fireEvent.change(selector, { target: { value: '11' } });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/aulas/7/punto-control/incidencias-manuales', {
                params: { examen_id: 11, limite: 20 },
                signal: expect.any(AbortSignal),
            });
        });

        const lecturasPrevias = api.get.mock.calls.length;

        resolverPost({
            data: { data: { incidencia_id: 90 } },
        });

        await waitFor(() => {
            expect(
                screen.getByRole('button', {
                    name: 'Registrar incidencia manual',
                })
            ).toBeEnabled();
        });

        expect(screen.queryByText('Incidencia manual registrada.')).not.toBeInTheDocument();

        expect(api.get).toHaveBeenCalledTimes(lecturasPrevias);
    });

    it('al cambiar de examen no conserva incidencias del anterior', async () => {
        api.get.mockImplementation((ruta, opciones) =>
            Promise.resolve({
                data: {
                    data: [
                        {
                            incidencia_id: opciones.params.examen_id,
                            tipo: 'INCIDENTE_GENERAL',
                            detalle:
                                opciones.params.examen_id === 10
                                    ? 'Incidencia exclusiva del examen A'
                                    : 'Incidencia exclusiva del examen B',
                            estudiante_id: null,
                            reportada_por: 1,
                            reportada_en: '2026-10-22 10:00:00',
                            atendida_en: null,
                        },
                    ],
                },
            })
        );

        render(<IncidenciasManuales aulaId="7" examenes={[examenA, examenB]} />);

        const selector = screen.getByRole('combobox', {
            name: 'Examen de incidencia manual',
        });

        fireEvent.change(selector, { target: { value: '10' } });

        expect(await screen.findByText('Incidencia exclusiva del examen A')).toBeInTheDocument();

        fireEvent.change(selector, { target: { value: '11' } });

        expect(await screen.findByText('Incidencia exclusiva del examen B')).toBeInTheDocument();

        expect(screen.queryByText('Incidencia exclusiva del examen A')).not.toBeInTheDocument();
    });
});
