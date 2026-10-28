import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Respaldo from './Respaldo';
import { api } from '../../api/cliente';
import {
    errorHttp,
    renderConSesion,
    respuestaDiferida,
    simularApi,
    usuarioDePrueba,
} from '../../test/apoyo';

vi.mock('../../api/cliente');

const respaldosEjemplo = [
    {
        id: 1,
        archivo: 'respaldo-20261028-131500.sql',
        tipo: 'Puntual',
        tamano: '5.6 MB',
        tamano_bytes: 5872025,
        estado: 'Correcto',
        fecha: '2026-10-28 13:15',
        created_at: '2026-10-28 13:15:00',
    },
];

const montar = (usuario = usuarioDePrueba('Administrador')) =>
    renderConSesion(<Respaldo />, { usuario, ruta: '/respaldo' });

describe('Respaldo - Cabecera, programación y generación', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        simularApi(api, {
            'GET /respaldos': {
                data: respaldosEjemplo,
                conteos: { todos: 1, programados: 0, puntuales: 1, fallidos: 0 },
                ultimo: '2026-10-28 13:15:00',
            },
        });
    });

    it('sin permiso muestra «Sin permiso» sin consultar nada (criterio 12)', async () => {
        montar(usuarioDePrueba('Docente'));

        expect(
            await screen.findByRole('heading', { level: 1, name: 'Sin permiso' })
        ).toBeInTheDocument();
        expect(api.get).not.toHaveBeenCalled();
    });

    it('con respaldos muestra la programación, el último correcto y el botón «Respaldar ahora» (criterio 1)', async () => {
        montar();

        expect(
            await screen.findByRole('heading', { level: 1, name: 'Respaldo' })
        ).toBeInTheDocument();
        expect(screen.getByText('Diario, 03:00 · último: 28 oct 2026, 13:15')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Respaldar ahora' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { level: 2, name: 'Historial' })).toBeInTheDocument();
    });

    it('sin respaldos muestra «Sin respaldos» y no dice «último» (criterio 1)', async () => {
        simularApi(api, {
            'GET /respaldos': {
                data: [],
                conteos: { todos: 0, programados: 0, puntuales: 0, fallidos: 0 },
                ultimo: null,
            },
        });

        montar();

        expect(
            await screen.findByRole('heading', { level: 1, name: 'Respaldo' })
        ).toBeInTheDocument();
        expect(screen.getAllByText('Sin respaldos').length).toBeGreaterThan(0);
        expect(screen.queryByText(/último/i)).not.toBeInTheDocument();
    });

    it('un intento que falla no cuenta como el último correcto (criterio 5)', async () => {
        simularApi(api, {
            'GET /respaldos': {
                data: [
                    {
                        id: 2,
                        archivo: 'respaldo-20261028-140000.sql',
                        tipo: 'Puntual',
                        estado: 'Fallido',
                        error: 'Error de conexión',
                        fecha: '2026-10-28 14:00',
                        created_at: '2026-10-28 14:00:00',
                    },
                    {
                        id: 1,
                        archivo: 'respaldo-20261028-120000.sql',
                        tipo: 'Puntual',
                        estado: 'Correcto',
                        fecha: '2026-10-28 12:00',
                        created_at: '2026-10-28 12:00:00',
                    },
                ],
                conteos: { todos: 2, programados: 0, puntuales: 2, fallidos: 1 },
                ultimo: '2026-10-28 12:00:00',
            },
        });

        montar();

        expect(
            await screen.findByRole('heading', { level: 1, name: 'Respaldo' })
        ).toBeInTheDocument();
        expect(screen.getByText('Diario, 03:00 · último: 28 oct 2026, 12:00')).toBeInTheDocument();
        expect(
            screen.queryByText('Diario, 03:00 · último: 28 oct 2026, 14:00')
        ).not.toBeInTheDocument();
    });

    it('al pulsar «Respaldar ahora» dice «Respaldando» mientras dura y avisa «Respaldo generado» (criterio 3)', async () => {
        const deferred = respuestaDiferida();
        simularApi(api, {
            'GET /respaldos': {
                data: respaldosEjemplo,
                conteos: { todos: 1, programados: 0, puntuales: 1, fallidos: 0 },
            },
            'POST /respaldos': () => deferred.promesa,
        });

        montar();

        const boton = await screen.findByRole('button', { name: 'Respaldar ahora' });
        fireEvent.click(boton);

        expect(screen.getByRole('button', { name: 'Respaldando' })).toBeDisabled();

        deferred.resolver({
            data: {
                id: 3,
                archivo: 'respaldo-20261028-160000.sql',
                tipo: 'Puntual',
                estado: 'Correcto',
                fecha: '2026-10-28 16:00',
                created_at: '2026-10-28 16:00:00',
            },
        });

        expect(await screen.findByText('Respaldo generado')).toBeInTheDocument();
        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Respaldar ahora' })).not.toBeDisabled();
        });
    });

    it('si la generación falla avisa «No se pudo generar el respaldo» y vuelve a leer el historial (criterio 5)', async () => {
        let consultasGet = 0;
        simularApi(api, {
            'GET /respaldos': () => {
                consultasGet++;
                return {
                    data: respaldosEjemplo,
                    conteos: { todos: 1, programados: 0, puntuales: 1, fallidos: 0 },
                };
            },
            'POST /respaldos': errorHttp(500, { message: 'Falla al volcar datos' }),
        });

        montar();

        const boton = await screen.findByRole('button', { name: 'Respaldar ahora' });
        expect(consultasGet).toBe(1);

        fireEvent.click(boton);

        expect(await screen.findByText('No se pudo generar el respaldo')).toBeInTheDocument();
        await waitFor(() => {
            expect(consultasGet).toBeGreaterThan(1);
        });
    });

    it('si el historial no carga muestra «No se pudo cargar» con «Reintentar» (criterio 12)', async () => {
        simularApi(api, {
            'GET /respaldos': errorHttp(500, { message: 'Error de servidor' }),
        });

        montar();

        expect(await screen.findByText('No se pudo cargar')).toBeInTheDocument();
        const botonReintentar = screen.getByRole('button', { name: 'Reintentar' });
        expect(botonReintentar).toBeInTheDocument();

        // Al pulsar reintentar consulta de nuevo
        simularApi(api, {
            'GET /respaldos': {
                data: respaldosEjemplo,
                conteos: { todos: 1, programados: 0, puntuales: 1, fallidos: 0 },
                ultimo: '2026-10-28 13:15:00',
            },
        });

        fireEvent.click(botonReintentar);

        expect(
            await screen.findByText('Diario, 03:00 · último: 28 oct 2026, 13:15')
        ).toBeInTheDocument();
    });
});

describe('Respaldo - Filtros, listado, paginación y responsividad (Tarea 2)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('las fichas muestran sus conteos y al pulsar una filtra en el servidor (criterio 2)', async () => {
        simularApi(api, {
            'GET /respaldos': (pedido) => {
                const f = pedido.params?.filtro;
                if (f === 'programados') {
                    return {
                        data: [
                            {
                                id: 5,
                                archivo: 'respaldo-20261027-030000.sql',
                                tipo: 'Programado',
                                tamano_bytes: 4000000,
                                estado: 'Correcto',
                                fecha: '2026-10-27 03:00',
                                created_at: '2026-10-27 03:00:00',
                            },
                        ],
                        conteos: { todos: 3, programados: 1, puntuales: 1, fallidos: 1 },
                        meta: { total: 1, pagina: 1, por_pagina: 15 },
                    };
                }
                return {
                    data: respaldosEjemplo,
                    conteos: { todos: 3, programados: 1, puntuales: 1, fallidos: 1 },
                    meta: { total: 3, pagina: 1, por_pagina: 15 },
                };
            },
        });

        montar();

        // Verifica que las 4 fichas tengan sus conteos
        expect(await screen.findByRole('button', { name: /Todos\s*3/ })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Programados\s*1/ })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Puntuales\s*1/ })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Fallidos\s*1/ })).toBeInTheDocument();

        // Pulsar «Programados» envía el filtro al servidor
        fireEvent.click(screen.getByRole('button', { name: /Programados/ }));

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/respaldos', {
                params: { filtro: 'programados', pagina: 1, por_pagina: 15 },
            });
        });

        expect((await screen.findAllByText('27 oct 2026, 03:00')).length).toBeGreaterThan(0);
    });

    it('un filtro sin coincidencias muestra «Sin resultados» (criterio 2)', async () => {
        simularApi(api, {
            'GET /respaldos': (pedido) => {
                if (pedido.params?.filtro === 'fallidos') {
                    return {
                        data: [],
                        conteos: { todos: 2, programados: 1, puntuales: 1, fallidos: 0 },
                        meta: { total: 0, pagina: 1, por_pagina: 15 },
                    };
                }
                return {
                    data: respaldosEjemplo,
                    conteos: { todos: 2, programados: 1, puntuales: 1, fallidos: 0 },
                    meta: { total: 2, pagina: 1, por_pagina: 15 },
                };
            },
        });

        montar();

        const fichaFallidos = await screen.findByRole('button', { name: /Fallidos/ });
        fireEvent.click(fichaFallidos);

        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
    });

    it('un filtro inexistente se rechaza con «El filtro no existe.» (criterio 2)', async () => {
        simularApi(api, {
            'GET /respaldos': (pedido) => {
                if (pedido.params?.filtro === 'inexistente') {
                    throw errorHttp(422, { message: 'El filtro no existe.' });
                }
                return {
                    data: respaldosEjemplo,
                    conteos: { todos: 1, programados: 0, puntuales: 1, fallidos: 0 },
                    meta: { total: 1, pagina: 1, por_pagina: 15 },
                };
            },
        });

        montar();

        // Espera a que cargue
        expect(
            await screen.findByRole('heading', { level: 2, name: 'Historial' })
        ).toBeInTheDocument();

        // Simulamos un rechazo de filtro
        simularApi(api, {
            'GET /respaldos': () => {
                throw errorHttp(422, { message: 'El filtro no existe.' });
            },
        });

        const ficha = screen.getByRole('button', { name: /Programados/ });
        fireEvent.click(ficha);

        expect(await screen.findByText('El filtro no existe.')).toBeInTheDocument();
    });

    it('al generar un respaldo puntual mientras se está en un filtro que lo ocultaría, cambia a «Todos» y lo muestra primero (criterio 3)', async () => {
        let elementos = [
            {
                id: 5,
                archivo: 'respaldo-20261027-030000.sql',
                tipo: 'Programado',
                tamano_bytes: 4000000,
                estado: 'Correcto',
                fecha: '2026-10-27 03:00',
                created_at: '2026-10-27 03:00:00',
            },
        ];

        const nuevoPuntual = {
            id: 99,
            archivo: 'respaldo-20261028-170000.sql',
            tipo: 'Puntual',
            tamano_bytes: 5000000,
            estado: 'Correcto',
            fecha: '2026-10-28 17:00',
            created_at: '2026-10-28 17:00:00',
        };

        simularApi(api, {
            'GET /respaldos': (pedido) => {
                const f = pedido.params?.filtro;
                const filtrados =
                    f === 'programados'
                        ? elementos.filter((e) => e.tipo === 'Programado')
                        : elementos;
                return {
                    data: filtrados,
                    conteos: {
                        todos: elementos.length,
                        programados: elementos.filter((e) => e.tipo === 'Programado').length,
                        puntuales: elementos.filter((e) => e.tipo === 'Puntual').length,
                        fallidos: elementos.filter((e) => e.estado === 'Fallido').length,
                    },
                    meta: { total: filtrados.length, pagina: 1, por_pagina: 15 },
                };
            },
            'POST /respaldos': () => {
                elementos = [nuevoPuntual, ...elementos];
                return { data: nuevoPuntual };
            },
        });

        montar();

        // Cambiamos al filtro Programados
        const fichaProgramados = await screen.findByRole('button', { name: /Programados/ });
        fireEvent.click(fichaProgramados);

        expect((await screen.findAllByText('27 oct 2026, 03:00')).length).toBeGreaterThan(0);

        // Pulsamos Respaldar ahora
        const botonRespaldar = screen.getByRole('button', { name: 'Respaldar ahora' });
        fireEvent.click(botonRespaldar);

        expect(await screen.findByText('Respaldo generado')).toBeInTheDocument();

        // El nuevo respaldo puntual debe mostrarse de inmediato
        expect((await screen.findAllByText('28 oct 2026, 17:00')).length).toBeGreaterThan(0);
    });

    it('un respaldo fallido muestra tamaño «—» y no tiene acciones de descarga ni restaurar (criterios 5, 6 y 10)', async () => {
        simularApi(api, {
            'GET /respaldos': {
                data: [
                    {
                        id: 10,
                        archivo: 'respaldo-20261028-100000.sql',
                        tipo: 'Puntual',
                        tamano_bytes: 0,
                        estado: 'Fallido',
                        error: 'Error de escritura en disco',
                        fecha: '2026-10-28 10:00',
                        created_at: '2026-10-28 10:00:00',
                    },
                ],
                conteos: { todos: 1, programados: 0, puntuales: 1, fallidos: 1 },
                meta: { total: 1, pagina: 1, por_pagina: 15 },
            },
        });

        montar();

        expect((await screen.findAllByText('28 oct 2026, 10:00')).length).toBeGreaterThan(0);
        expect(screen.getAllByText('Fallido').length).toBeGreaterThan(0);
        // Debe mostrar tamaño «—»
        expect(screen.getAllByText('—').length).toBeGreaterThan(0);
        // No debe tener botones de Descargar ni Restaurar para esta fila
        expect(screen.queryByRole('button', { name: 'Descargar' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Restaurar' })).not.toBeInTheDocument();
    });

    it('pagina en el servidor cuando hay más de 15 respaldos (criterios 1 y 2)', async () => {
        const crearLista = (inicio, cantidad) =>
            Array.from({ length: cantidad }, (_, i) => ({
                id: inicio + i,
                archivo: `respaldo-20261028-${String(inicio + i).padStart(6, '0')}.sql`,
                tipo: 'Puntual',
                tamano_bytes: 1048576,
                estado: 'Correcto',
                fecha: `2026-10-28 12:${String(i).padStart(2, '0')}`,
                created_at: `2026-10-28 12:${String(i).padStart(2, '0')}:00`,
            }));

        simularApi(api, {
            'GET /respaldos': (pedido) => {
                const pag = pedido.params?.pagina ?? 1;
                return {
                    data: pag === 1 ? crearLista(1, 15) : crearLista(16, 5),
                    conteos: { todos: 20, programados: 0, puntuales: 20, fallidos: 0 },
                    meta: { total: 20, pagina: pag, por_pagina: 15 },
                };
            },
        });

        montar();

        expect(await screen.findByText('1–15 de 20 respaldos')).toBeInTheDocument();

        // Pulsar página siguiente
        const botonSiguiente = screen.getByRole('button', { name: 'Página siguiente' });
        fireEvent.click(botonSiguiente);

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/respaldos', {
                params: { pagina: 2, por_pagina: 15 },
            });
        });

        expect(await screen.findByText('16–20 de 20 respaldos')).toBeInTheDocument();
    });
});
