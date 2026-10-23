import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import PuntoControl from './PuntoControl';
import { api } from '../../api/cliente';

vi.mock('../../api/cliente', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

const examen = {
    id: 10,
    fecha: '2026-10-15',
    tipo: 'parcial',
    asignatura: 'Programación',
    aulas: [{ id: 7, nombre: 'A-101' }],
};

const IDENTIFICACION = {
    examen_id: 10,
    veredicto: 'ADMITIDO',
    ambiguo: false,
    admisible: true,
};

function montar() {
    return render(
        <MemoryRouter>
            <PuntoControl />
        </MemoryRouter>
    );
}

async function preparar() {
    montar();

    await screen.findByRole('combobox', { name: 'Aula de control' });

    await waitFor(() => {
        expect(screen.getByRole('option', { name: 'A-101' })).toBeInTheDocument();
    });

    fireEvent.change(screen.getByRole('combobox', { name: 'Aula de control' }), {
        target: { value: '7' },
    });

    await waitFor(() => {
        expect(api.get).toHaveBeenCalledWith('/aulas/7/punto-control/ingresos', {
            params: { limite: 5 },
            signal: expect.any(AbortSignal),
        });
    });
}

describe('PuntoControl HU-19', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        api.post.mockImplementation((ruta) => {
            if (ruta.endsWith('/identificar')) {
                return Promise.resolve({ data: { data: IDENTIFICACION } });
            }
            if (ruta.endsWith('/ingresos')) {
                return Promise.resolve({
                    data: { data: { examen_id: 10, veredicto: 'ADMITIDO', admitido: true } },
                });
            }
            return Promise.reject(new Error('Ruta POST inesperada'));
        });

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta.endsWith('/ingresos') || ruta.endsWith('/incidencias')) {
                return Promise.resolve({ data: { data: [] } });
            }

            if (ruta.endsWith('/candidato')) {
                return Promise.resolve({
                    data: {
                        data: {
                            estudiante_id: 5,
                            codigo: '202100001',
                            nombre: 'Estudiante de prueba',
                            inscrito: true,
                            habilitado: true,
                            aula_asignada_id: 7,
                        },
                    },
                });
            }

            return Promise.reject(new Error('Ruta inesperada'));
        });
    });

    it('BUG-04: presenta estudiante, rechazo y detalle de una incidencia', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta.endsWith('/incidencias')) {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                incidencia_id: 14,
                                estudiante_id: 5,
                                codigo: '202100001',
                                nombres: 'Ana',
                                apellidos: 'Rojas',
                                tipo_rechazo: 'NO_HABILITADO',
                                detalle: 'Documentación pendiente.',
                                registrado_en: '2026-10-16 08:15:00',
                            },
                        ],
                    },
                });
            }

            return Promise.resolve({ data: { data: [] } });
        });

        await preparar();

        const seccion = screen.getByRole('region', {
            name: 'Incidencias de ingreso',
        });

        expect(await screen.findByText('Rojas, Ana')).toBeInTheDocument();
        expect(seccion).toHaveTextContent('Documentación pendiente.');
        expect(seccion).toHaveTextContent('Estudiante no habilitado');
        expect(seccion).toHaveTextContent('2026-10-16 08:15:00');
        expect(seccion).toHaveTextContent('202100001');
    });

    it('BUG-04: actualiza incidencias tras rechazar un ingreso', async () => {
        let lecturas = 0;

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta.endsWith('/candidato')) {
                return Promise.resolve({
                    data: {
                        data: {
                            estudiante_id: 5,
                            codigo: '202100001',
                            nombre: 'Estudiante de prueba',
                            inscrito: true,
                            habilitado: false,
                        },
                    },
                });
            }

            if (ruta.endsWith('/incidencias')) {
                lecturas += 1;

                return Promise.resolve({
                    data: {
                        data:
                            lecturas > 1
                                ? [
                                      {
                                          incidencia_id: 15,
                                          estudiante_id: 5,
                                          nombres: 'Ana',
                                          apellidos: 'Rojas',
                                          tipo_rechazo: 'NO_HABILITADO',
                                          detalle: 'No cumple el requisito.',
                                          registrado_en: '2026-10-16 08:20:00',
                                      },
                                  ]
                                : [],
                    },
                });
            }

            return Promise.resolve({ data: { data: [] } });
        });

        api.post.mockImplementation((ruta) =>
            ruta.endsWith('/identificar')
                ? Promise.resolve({ data: { data: IDENTIFICACION } })
                : Promise.reject({
                      response: {
                          status: 409,
                          data: {
                              data: {
                                  veredicto: 'NO_HABILITADO',
                                  admitido: false,
                              },
                          },
                      },
                  })
        );

        await preparar();

        await waitFor(() => {
            expect(lecturas).toBe(1);
        });

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));
        await screen.findByText('Resultado de identificación');

        fireEvent.click(screen.getByRole('button', { name: 'Confirmar ingreso' }));

        expect(await screen.findByText(/Detalle:\s*No cumple el requisito\./)).toBeInTheDocument();
        expect(lecturas).toBe(2);
    });

    it('BUG-04: actualiza incidencias después de YA_INGRESO con HTTP 409', async () => {
        let lecturas = 0;

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta.endsWith('/candidato')) {
                return Promise.resolve({
                    data: {
                        data: {
                            estudiante_id: 5,
                            codigo: '202100001',
                            nombre: 'Estudiante de prueba',
                            inscrito: true,
                            habilitado: true,
                            aula_asignada_id: 7,
                        },
                    },
                });
            }

            if (ruta.endsWith('/incidencias')) {
                lecturas += 1;

                return Promise.resolve({
                    data: {
                        data:
                            lecturas > 1
                                ? [
                                      {
                                          incidencia_id: 16,
                                          estudiante_id: 5,
                                          nombres: 'Ana',
                                          apellidos: 'Rojas',
                                          tipo_rechazo: 'YA_INGRESO',
                                          detalle: 'El estudiante ya tiene un ingreso registrado.',
                                          registrado_en: '2026-10-16 08:30:00',
                                      },
                                  ]
                                : [],
                    },
                });
            }

            return Promise.resolve({ data: { data: [] } });
        });

        api.post.mockImplementation((ruta) =>
            ruta.endsWith('/identificar')
                ? Promise.resolve({ data: { data: IDENTIFICACION } })
                : Promise.reject({
                      response: {
                          status: 409,
                          data: {
                              data: {
                                  veredicto: 'YA_INGRESO',
                                  admitido: false,
                              },
                          },
                      },
                  })
        );

        await preparar();

        await waitFor(() => {
            expect(lecturas).toBe(1);
        });

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));
        await screen.findByText('Resultado de identificación');

        fireEvent.click(screen.getByRole('button', { name: 'Confirmar ingreso' }));

        expect(await screen.findByText('Ingreso registrado anteriormente')).toBeInTheDocument();

        expect(
            await screen.findByText(/Detalle:\s*El estudiante ya tiene un ingreso registrado\./)
        ).toBeInTheDocument();

        expect(lecturas).toBe(2);
        expect(api.post).toHaveBeenCalledTimes(2);
    });

    it('BUG-04: ignora incidencias antiguas al cambiar de aula', async () => {
        let resolverAnterior;

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                ...examen,
                                aulas: [
                                    { id: 7, nombre: 'A-101' },
                                    { id: 8, nombre: 'A-102' },
                                ],
                            },
                        ],
                    },
                });
            }

            if (ruta.endsWith('/incidencias') && ruta.includes('/aulas/7/')) {
                return new Promise((resolver) => {
                    resolverAnterior = resolver;
                });
            }

            if (ruta.endsWith('/incidencias') && ruta.includes('/aulas/8/')) {
                return Promise.resolve({ data: { data: [] } });
            }

            return Promise.resolve({ data: { data: [] } });
        });

        await preparar();

        await waitFor(() => {
            expect(resolverAnterior).toBeTypeOf('function');
        });

        fireEvent.change(screen.getByRole('combobox', { name: 'Aula de control' }), {
            target: { value: '8' },
        });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(
                '/aulas/8/punto-control/incidencias',
                expect.objectContaining({
                    signal: expect.any(AbortSignal),
                })
            );
        });

        resolverAnterior({
            data: {
                data: [
                    {
                        incidencia_id: 99,
                        nombres: 'ESTUDIANTE',
                        apellidos: 'ANTIGUO',
                        tipo_rechazo: 'NO_HABILITADO',
                        detalle: 'No debe aparecer.',
                        registrado_en: '2026-10-16 08:00:00',
                    },
                ],
            },
        });

        await waitFor(() => {
            expect(screen.getByText('No hay incidencias para mostrar.')).toBeInTheDocument();
        });

        expect(screen.queryByText('ANTIGUO, ESTUDIANTE')).not.toBeInTheDocument();
        expect(screen.queryByText(/No debe aparecer/)).not.toBeInTheDocument();
    });

    it('BUG-06: registra una situación excepcional contextual sin admitir', async () => {
        api.post.mockImplementation((ruta) => {
            if (ruta.endsWith('/identificar')) {
                return Promise.resolve({
                    data: {
                        data: {
                            examen_id: 10,
                            veredicto: 'NO_HABILITADO',
                            ambiguo: false,
                            admisible: false,
                            codigo: '202100001',
                            nombre: 'Estudiante de prueba',
                            inscrito: true,
                            habilitado: false,
                            motivo_inhabilitacion: 'Documentación pendiente.',
                        },
                    },
                });
            }

            if (ruta.endsWith('/incidencias-manuales')) {
                return Promise.resolve({
                    data: { data: { incidencia_id: 55 } },
                });
            }

            return Promise.reject(new Error(`POST inesperado: ${ruta}`));
        });

        const getOriginal = api.get.getMockImplementation();

        api.get.mockImplementation((ruta, opciones) => {
            if (ruta.endsWith('/incidencias-manuales')) {
                return Promise.resolve({ data: { data: [] } });
            }

            return getOriginal(ruta, opciones);
        });

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        expect(await screen.findByText('Estudiante no habilitado')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Confirmar ingreso' })).toBeDisabled();

        fireEvent.click(screen.getByRole('button', { name: 'Situación excepcional' }));

        expect(screen.getByRole('combobox', { name: 'Examen de incidencia manual' })).toHaveValue(
            '10'
        );

        expect(screen.getByRole('combobox', { name: 'Tipo de incidencia manual' })).toHaveValue(
            'EXCEPCION_NO_HABILITADO'
        );

        expect(
            screen.getByRole('textbox', { name: 'Código universitario para incidencia' })
        ).toHaveValue('202100001');

        const registrar = screen.getByRole('button', {
            name: 'Registrar incidencia manual',
        });

        expect(registrar).toBeDisabled();

        fireEvent.change(screen.getByRole('textbox', { name: 'Detalle de incidencia manual' }), {
            target: { value: 'Documento original pendiente de revisión.' },
        });

        fireEvent.click(registrar);

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/aulas/7/punto-control/incidencias-manuales', {
                examen_id: 10,
                codigo_universitario: '202100001',
                tipo: 'EXCEPCION_NO_HABILITADO',
                detalle: 'Documento original pendiente de revisión.',
            });
        });

        expect(api.post.mock.calls.some(([ruta]) => ruta.endsWith('/ingresos'))).toBe(false);
    });

    it('BUG-06: no ofrece situación excepcional con examen ambiguo', async () => {
        api.post.mockResolvedValue({
            data: {
                data: {
                    examen_id: null,
                    veredicto: null,
                    ambiguo: true,
                    admisible: false,
                    codigo: null,
                },
            },
        });

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        expect(
            await screen.findByText('Identificación ambigua entre exámenes')
        ).toBeInTheDocument();

        expect(
            screen.queryByRole('button', { name: 'Situación excepcional' })
        ).not.toBeInTheDocument();

        expect(api.post).toHaveBeenCalledTimes(1);
    });

    it('BUG-06: nueva identificación invalida excepción anterior', async () => {
        let consultas = 0;

        api.post.mockImplementation((ruta) => {
            if (ruta.endsWith('/identificar')) {
                consultas += 1;

                return Promise.resolve({
                    data: {
                        data:
                            consultas === 1
                                ? {
                                      examen_id: 10,
                                      veredicto: 'NO_HABILITADO',
                                      ambiguo: false,
                                      admisible: false,
                                      codigo: '202100001',
                                      inscrito: true,
                                      habilitado: false,
                                  }
                                : {
                                      examen_id: null,
                                      veredicto: null,
                                      ambiguo: true,
                                      admisible: false,
                                      codigo: null,
                                  },
                    },
                });
            }

            return Promise.reject(new Error(`POST inesperado: ${ruta}`));
        });

        const getOriginal = api.get.getMockImplementation();

        api.get.mockImplementation((ruta, opciones) => {
            if (ruta.endsWith('/incidencias-manuales')) {
                return Promise.resolve({ data: { data: [] } });
            }

            return getOriginal(ruta, opciones);
        });

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        expect(await screen.findByText('Estudiante no habilitado')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Situación excepcional' }));

        expect(
            await screen.findByRole('combobox', {
                name: 'Tipo de incidencia manual',
            })
        ).toHaveValue('EXCEPCION_NO_HABILITADO');

        expect(
            screen.getByRole('textbox', {
                name: 'Código universitario para incidencia',
            })
        ).toHaveValue('202100001');

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        expect(
            await screen.findByText('Identificación ambigua entre exámenes')
        ).toBeInTheDocument();

        expect(
            screen.queryByRole('button', { name: 'Situación excepcional' })
        ).not.toBeInTheDocument();

        expect(
            screen.getByRole('combobox', {
                name: 'Tipo de incidencia manual',
            })
        ).toHaveValue('INCIDENTE_GENERAL');

        expect(
            screen.getByRole('textbox', {
                name: 'Código universitario para incidencia',
            })
        ).toHaveValue('');

        expect(api.post.mock.calls.some(([ruta]) => ruta.endsWith('/incidencias-manuales'))).toBe(
            false
        );
    });

    it('CA-2: muestra el motivo real del estudiante inhabilitado', async () => {
        api.post.mockResolvedValue({
            data: {
                data: {
                    examen_id: 10,
                    veredicto: 'NO_HABILITADO',
                    ambiguo: false,
                    admisible: false,
                },
            },
        });

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        expect(await screen.findByText('Estudiante no habilitado')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Confirmar ingreso' })).toBeDisabled();
        expect(api.post).toHaveBeenCalledTimes(1);
    });

    it('CA-3: distingue la ausencia de condición registrada', async () => {
        api.post.mockResolvedValue({
            data: {
                data: {
                    examen_id: null,
                    veredicto: null,
                    ambiguo: true,
                    admisible: false,
                },
            },
        });

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        expect(
            await screen.findByText('Identificación ambigua entre exámenes')
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Confirmar ingreso' })).toBeDisabled();
        expect(api.post).toHaveBeenCalledTimes(1);
    });

    it('consulta antes de permitir confirmar el ingreso', async () => {
        await preparar();

        expect(screen.queryByRole('button', { name: 'Confirmar ingreso' })).not.toBeInTheDocument();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        expect(await screen.findByText('Resultado de identificación')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Confirmar ingreso' })).toBeEnabled();
    });

    it('registra solo después de confirmar', async () => {
        api.post.mockImplementation((ruta) =>
            Promise.resolve({
                data: {
                    data: ruta.endsWith('/identificar')
                        ? IDENTIFICACION
                        : { examen_id: 10, veredicto: 'ADMITIDO', admitido: true },
                },
            })
        );

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));
        await screen.findByText('Resultado de identificación');

        expect(api.post).toHaveBeenCalledTimes(1);

        fireEvent.click(screen.getByRole('button', { name: 'Confirmar ingreso' }));

        await screen.findByText('Ingreso registrado correctamente.');

        expect(api.post).toHaveBeenCalledWith(
            '/aulas/7/punto-control/ingresos',
            {
                tipo: 'codigo',
                identificador: '202100001',
            },
            { signal: expect.any(AbortSignal) }
        );
    });

    it('BUG-03: informa horario cerrado sin inventar un ingreso previo', async () => {
        api.post.mockImplementation((ruta) =>
            ruta.endsWith('/identificar')
                ? Promise.resolve({ data: { data: IDENTIFICACION } })
                : Promise.reject({
                      response: {
                          status: 409,
                          data: {
                              message: 'El punto de control está fuera del horario permitido.',
                          },
                      },
                  })
        );

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));
        await screen.findByText('Resultado de identificación');

        fireEvent.click(screen.getByRole('button', { name: 'Confirmar ingreso' }));

        expect(
            await screen.findByText('El punto de control está fuera del horario permitido.')
        ).toBeInTheDocument();

        expect(screen.queryByText('Ingreso registrado anteriormente')).not.toBeInTheDocument();

        expect(api.post).toHaveBeenCalledTimes(2);
    });

    it('BUG-03: conserva YA_INGRESO cuando el backend lo confirma', async () => {
        api.post.mockImplementation((ruta) =>
            ruta.endsWith('/identificar')
                ? Promise.resolve({ data: { data: IDENTIFICACION } })
                : Promise.reject({
                      response: {
                          status: 409,
                          data: {
                              data: {
                                  veredicto: 'YA_INGRESO',
                                  admitido: false,
                              },
                          },
                      },
                  })
        );

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));
        await screen.findByText('Resultado de identificación');

        fireEvent.click(screen.getByRole('button', { name: 'Confirmar ingreso' }));

        expect(await screen.findByText('Ingreso registrado anteriormente')).toBeInTheDocument();

        expect(
            screen.queryByText('El punto de control está fuera del horario permitido.')
        ).not.toBeInTheDocument();

        expect(api.post).toHaveBeenCalledTimes(2);
    });

    it('bloquea la operación cuando el backend devuelve 403', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            return Promise.reject({
                response: { status: 403, data: { message: 'No autorizado' } },
            });
        });

        await preparar();

        expect(
            await screen.findByText('Punto de control bloqueado por falta de asignación.')
        ).toBeInTheDocument();

        expect(screen.getByRole('button', { name: 'Consultar estudiante' })).toBeDisabled();
    });

    it('mantiene el contexto mientras una consulta está pendiente', async () => {
        let responder;

        const pendiente = new Promise((resolver) => {
            responder = resolver;
        });

        api.post.mockImplementation((ruta) => {
            if (ruta.endsWith('/identificar')) return pendiente;
            return Promise.reject(new Error('POST inesperado'));
        });

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                ...examen,
                                aulas: [
                                    { id: 7, nombre: 'A-101' },
                                    { id: 8, nombre: 'A-102' },
                                ],
                            },
                        ],
                    },
                });
            }

            if (ruta.endsWith('/candidato')) return pendiente;

            return Promise.resolve({ data: { data: [] } });
        });

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith(
                '/aulas/7/punto-control/identificar',
                {
                    tipo: 'codigo',
                    identificador: '202100001',
                },
                expect.objectContaining({
                    signal: expect.any(AbortSignal),
                })
            );
        });

        // Durante la consulta no pueden cambiar los datos
        // del contexto asociado a la solicitud.
        const selectorAula = screen.getByRole('combobox', {
            name: 'Aula de control',
        });

        expect(selectorAula).toBeDisabled();
        expect(selectorAula).toHaveValue('7');

        expect(
            screen.getByRole('combobox', {
                name: 'Tipo de identificación',
            })
        ).toBeDisabled();

        expect(
            screen.getByRole('textbox', {
                name: 'Identificador',
            })
        ).toBeDisabled();

        const llamada = api.post.mock.calls.find(([ruta]) => ruta.endsWith('/identificar'));

        responder({
            data: {
                data: {
                    ...IDENTIFICACION,
                    estudiante_id: 5,
                    codigo: '202100001',
                    nombre: 'Estudiante de prueba',
                    habilitado: true,
                },
            },
        });

        await screen.findByText('Resultado de identificación');

        expect(llamada[2].signal).toBeInstanceOf(AbortSignal);
    });

    it('cancela una consulta pendiente al desmontar la pantalla', async () => {
        let responder;

        const pendiente = new Promise((resolver) => {
            responder = resolver;
        });

        api.post.mockImplementation((ruta) => {
            if (ruta.endsWith('/identificar')) return pendiente;
            return Promise.reject(new Error('POST inesperado'));
        });

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta.endsWith('/candidato')) {
                return pendiente;
            }

            return Promise.resolve({ data: { data: [] } });
        });

        const vista = montar();

        await screen.findByRole('option', { name: 'A-101' });

        fireEvent.change(screen.getByRole('combobox', { name: 'Aula de control' }), {
            target: { value: '7' },
        });

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        let señal;

        await waitFor(() => {
            const llamada = api.post.mock.calls.find(([ruta]) => ruta.endsWith('/identificar'));

            expect(llamada).toBeDefined();

            señal = llamada[2].signal;
        });

        expect(señal.aborted).toBe(false);

        vista.unmount();

        expect(señal.aborted).toBe(true);

        responder({
            data: {
                data: {
                    ...IDENTIFICACION,
                    estudiante_id: 5,
                    codigo: '202100001',
                    nombre: 'Estudiante obsoleto',
                    habilitado: true,
                },
            },
        });
    });

    it('sin asignaciones no permite operar ni consultar ingresos', async () => {
        api.get.mockResolvedValue({
            data: { data: [] },
        });

        montar();

        expect(
            await screen.findByText('No tienes exámenes y aulas asignados para operar.')
        ).toBeInTheDocument();

        expect(
            screen.getByRole('button', {
                name: 'Consultar estudiante',
            })
        ).toBeDisabled();

        expect(
            screen.getByRole('button', {
                name: 'Abrir cámara QR',
            })
        ).toBeDisabled();

        expect(api.get).toHaveBeenCalledWith('/punto-control/contexto');
        expect(api.get).toHaveBeenCalledTimes(1);
        expect(api.post.mock.calls.some(([ruta]) => ruta.endsWith('/ingresos'))).toBe(false);
    });

    it('muestra el identificador real del historial Laravel', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta.endsWith('/ingresos')) {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                ingreso_id: 42,
                                estudiante_id: 5,
                                codigo: '202100001',
                                nombres: 'Estudiante',
                                apellidos: 'de prueba',
                                aula_id: 7,
                                registrado_en: '2026-10-15 08:10:00',
                                operador: 'Auxiliar',
                            },
                        ],
                    },
                });
            }

            return Promise.reject(new Error('Ruta inesperada'));
        });

        await preparar();

        expect(await screen.findByText(/de prueba, Estudiante/)).toBeInTheDocument();
        expect(screen.getByText(/Código: 202100001/)).toBeInTheDocument();
        expect(screen.getByText(/Hora: 2026-10-15 08:10:00/)).toBeInTheDocument();

        expect(screen.queryByText(/Ingreso #42/)).not.toBeInTheDocument();
        expect(screen.queryByText(/Estudiante 5/)).not.toBeInTheDocument();
    });

    it('informa el fallo de carga del contexto sin permitir operar', async () => {
        api.get.mockRejectedValue({
            response: {
                status: 403,
                data: { message: 'No autorizado' },
            },
        });

        montar();

        expect(
            await screen.findByText('No tienes asignación para operar en esta aula.')
        ).toBeInTheDocument();

        expect(screen.getByRole('button', { name: 'Consultar estudiante' })).toBeDisabled();

        expect(api.post.mock.calls.some(([ruta]) => ruta.endsWith('/ingresos'))).toBe(false);
    });

    it('ignora un 403 tardío del historial al cambiar de aula', async () => {
        let rechazarHistorialAnterior;

        const historialAnterior = new Promise((resolver, rechazar) => {
            rechazarHistorialAnterior = rechazar;
        });

        const examenConDosAulas = {
            ...examen,
            aulas: [
                { id: 7, nombre: 'A-101' },
                { id: 8, nombre: 'A-102' },
            ],
        };

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({
                    data: { data: [examenConDosAulas] },
                });
            }

            if (ruta === '/aulas/7/punto-control/ingresos') {
                return historialAnterior;
            }

            if (ruta === '/aulas/8/punto-control/ingresos') {
                return Promise.resolve({
                    data: { data: [] },
                });
            }

            return Promise.reject(new Error('Ruta inesperada'));
        });

        montar();

        const selectorAula = await screen.findByRole('combobox', {
            name: 'Aula de control',
        });

        await screen.findByRole('option', { name: 'A-102' });

        fireEvent.change(selectorAula, {
            target: { value: '7' },
        });

        let señalAnterior;

        await waitFor(() => {
            const llamada = api.get.mock.calls.find(
                ([ruta]) => ruta === '/aulas/7/punto-control/ingresos'
            );

            expect(llamada).toBeDefined();
            señalAnterior = llamada[1].signal;
        });

        expect(señalAnterior.aborted).toBe(false);

        fireEvent.change(selectorAula, {
            target: { value: '8' },
        });

        await waitFor(() => {
            expect(señalAnterior.aborted).toBe(true);
        });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(
                '/aulas/8/punto-control/ingresos',
                expect.objectContaining({
                    params: { limite: 5 },
                    signal: expect.any(AbortSignal),
                })
            );
        });

        // Simula un cliente HTTP que entrega un error incluso
        // después de haber recibido la señal de cancelación.
        rechazarHistorialAnterior({
            response: {
                status: 403,
                data: { message: 'Acceso anterior denegado' },
            },
        });

        await waitFor(() => {
            expect(selectorAula).toHaveValue('8');
        });

        // Permite procesar la promesa antigua antes de comprobar el estado.
        await waitFor(() => {
            expect(
                screen.getByRole('button', {
                    name: 'Consultar estudiante',
                })
            ).toBeDisabled();
        });

        expect(
            screen.queryByText('Punto de control bloqueado por falta de asignación.')
        ).not.toBeInTheDocument();

        expect(
            screen.queryByText('No tienes asignación para operar en esta aula.')
        ).not.toBeInTheDocument();

        // El aula 8 debe poder habilitar una consulta nueva.
        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        expect(
            screen.getByRole('button', {
                name: 'Consultar estudiante',
            })
        ).toBeEnabled();
    });
    it('opera una sola aula compartida por varios exámenes sin seleccionar examen', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({
                    data: {
                        data: [
                            examen,
                            {
                                ...examen,
                                id: 11,
                                asignatura: 'Matemáticas',
                                aulas: [
                                    { id: 7, nombre: 'A-101' },
                                    { id: 8, nombre: 'A-102' },
                                ],
                            },
                        ],
                    },
                });
            }

            return Promise.resolve({ data: { data: [] } });
        });

        montar();

        const selectorAula = await screen.findByRole('combobox', {
            name: 'Aula de control',
        });

        await screen.findByRole('option', { name: 'A-102' });

        expect(screen.getAllByRole('option', { name: 'A-101' })).toHaveLength(1);

        expect(screen.queryByText('Examen')).not.toBeInTheDocument();

        fireEvent.change(selectorAula, {
            target: { value: '7' },
        });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(
                '/aulas/7/punto-control/incidencias',
                expect.objectContaining({
                    params: { limite: 20 },
                    signal: expect.any(AbortSignal),
                })
            );

            expect(api.get).toHaveBeenCalledWith(
                '/aulas/7/punto-control/ingresos',
                expect.objectContaining({
                    params: { limite: 5 },
                    signal: expect.any(AbortSignal),
                })
            );
        });

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        expect(await screen.findByText('Resultado de identificación')).toBeInTheDocument();

        expect(api.post).toHaveBeenCalledWith(
            '/aulas/7/punto-control/identificar',
            {
                tipo: 'codigo',
                identificador: '202100001',
            },
            expect.objectContaining({
                signal: expect.any(AbortSignal),
            })
        );
    });

    it('BUG-05: expulsa mediante la API y refleja el estado persistido', async () => {
        let expulsado = false;

        const registro = {
            ingreso_id: 91,
            estudiante_id: 5,
            codigo: '202100001',
            nombres: 'Ana',
            apellidos: 'Rojas',
            registrado_en: '2026-10-22 09:15:00',
        };

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta === '/aulas/7/punto-control/ingresos') {
                return Promise.resolve({
                    data: { data: [{ ...registro, expulsado }] },
                });
            }

            if (ruta.endsWith('/incidencias')) {
                return Promise.resolve({ data: { data: [] } });
            }

            return Promise.reject(new Error('Ruta GET inesperada'));
        });

        api.post.mockImplementation((ruta, datos) => {
            if (ruta === '/aulas/7/punto-control/ingresos/91/expulsion') {
                if (datos.motivo !== 'Incumplimiento de normas') {
                    return Promise.reject(new Error('Motivo inesperado'));
                }

                expulsado = true;

                return Promise.resolve({
                    data: {
                        data: { ingreso_id: 91, expulsado: true },
                    },
                });
            }

            return Promise.reject(new Error('Ruta POST inesperada'));
        });

        await preparar();

        fireEvent.click(
            await screen.findByRole('button', {
                name: 'Expulsar estudiante',
            })
        );

        fireEvent.change(
            screen.getByRole('textbox', {
                name: 'Motivo de expulsión',
            }),
            { target: { value: 'Incumplimiento de normas' } }
        );

        fireEvent.click(
            screen.getByRole('button', {
                name: 'Confirmar expulsión',
            })
        );

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/aulas/7/punto-control/ingresos/91/expulsion', {
                motivo: 'Incumplimiento de normas',
            });
        });

        expect(await screen.findByText('Expulsado')).toBeInTheDocument();

        await waitFor(() => {
            expect(
                screen.queryByRole('button', {
                    name: 'Expulsar estudiante',
                })
            ).not.toBeInTheDocument();
        });
    });

    it('BUG-05: rechaza un motivo corto sin realizar escrituras', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta.endsWith('/ingresos')) {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                ingreso_id: 91,
                                codigo: '202100001',
                                nombres: 'Ana',
                                apellidos: 'Rojas',
                                registrado_en: '2026-10-22 09:15:00',
                                expulsado: false,
                            },
                        ],
                    },
                });
            }

            if (ruta.endsWith('/incidencias')) {
                return Promise.resolve({ data: { data: [] } });
            }

            return Promise.reject(new Error('Ruta GET inesperada'));
        });

        await preparar();

        fireEvent.click(
            await screen.findByRole('button', {
                name: 'Expulsar estudiante',
            })
        );

        fireEvent.change(
            screen.getByRole('textbox', {
                name: 'Motivo de expulsión',
            }),
            { target: { value: 'abc' } }
        );

        expect(
            screen.getByRole('button', {
                name: 'Confirmar expulsión',
            })
        ).toBeDisabled();

        expect(api.post).not.toHaveBeenCalled();
    });
});
