import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import EnCurso from './EnCurso';
import { api } from '../../api/cliente';
import { olvidarEdificios } from '../../api/usarEdificios';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

// Las respuestas, con la forma que devuelve el servidor (GET /api/en-curso y
// GET /api/en-curso/{examen}).
const EXAMENES = [
    {
        id: 1,
        fecha: '2026-10-09',
        hora: '15:59',
        duracion_minutos: 90,
        tipo: 'PRIMER_PARCIAL',
        tipo_texto: 'Primer parcial',
        asignatura: 'Introducción a la Programación',
        abierto: true,
    },
    {
        id: 3,
        fecha: '2026-10-11',
        hora: '08:15',
        duracion_minutos: 120,
        tipo: 'FINAL',
        tipo_texto: 'Examen final',
        asignatura: 'Taller de Ingeniería de Software',
        abierto: false,
    },
];

const persona = (id, nombre, esDocente) => ({ id, nombre, es_docente: esDocente });

const aula = (cambios) => {
    const base = {
        ubicacion: null,
        edificio_id: 2,
        a_cargo: persona(7, 'Blanco Coca Leticia', true),
        apoyo: [],
        ingresados: 0,
        asignados: 50,
        ultimo_registro: null,
        incidencias_sin_atender: 0,
        compartida: [],
        codigo_acceso: false,
        a_mi_cargo: false,
        puede_operar: false,
        ...cambios,
    };
    return { faltan: Math.max(base.asignados - base.ingresados, 0), ...base, ...cambios };
};

const AULAS = [
    aula({
        aula_id: 32,
        nombre: '691B',
        apoyo: [persona(20, 'Lucía Peredo Soliz', false), persona(21, 'Sergio Vargas', false)],
        ingresados: 40,
        asignados: 51,
        codigo_acceso: true,
        a_mi_cargo: true,
        puede_operar: true,
        ultimo_registro: '2026-10-09T16:05:12-04:00',
    }),
    aula({
        aula_id: 31,
        nombre: '691A',
        a_cargo: persona(8, 'Salazar Serrudo Carla', true),
        ingresados: 19,
        incidencias_sin_atender: 1,
        compartida: ['Cálculo I · Primer parcial'],
        // La docente del examen opera su puerta sin estar a cargo del aula.
        puede_operar: true,
        ultimo_registro: '2026-10-09T16:04:30-04:00',
    }),
    aula({
        aula_id: 35,
        nombre: '691E',
        edificio_id: 14,
        a_cargo: persona(9, 'Mamani Quispe Diego', false),
        incidencias_sin_atender: 1,
    }),
];

// Con la forma de GET /api/examenes/{examen}/incidencias.
const incidencia = (cambios) => ({
    estudiante: null,
    gravedad: 'LEVE',
    evidencia: false,
    corregida: false,
    atendida: false,
    atendida_por: null,
    atendida_en: null,
    ...cambios,
});

const incidencias = () => [
    incidencia({
        id: 5,
        aula_id: 31,
        aula: '691A',
        tipo: 'OTRA',
        tipo_texto: 'Otra',
        detalle: 'Faltan pupitres en dos filas.',
        hora: '16:03',
        por: 'Salazar Serrudo Carla',
        rol: 'Docente',
    }),
    incidencia({
        id: 2,
        aula_id: 32,
        aula: '691B',
        tipo: 'EXPULSION',
        tipo_texto: 'Expulsión',
        gravedad: 'GRAVE',
        detalle: 'Consultaba el teléfono.',
        estudiante: 'Aguilar Bustamante Adriana · 202100123',
        hora: '16:02',
        por: 'Blanco Coca Leticia',
        rol: 'Docente',
        atendida: true,
        atendida_por: 'Blanco Coca Leticia',
        atendida_en: '2026-10-09T16:04:55-04:00',
    }),
    incidencia({
        id: 3,
        aula_id: 35,
        aula: '691E',
        tipo: 'SUPLANTACION',
        tipo_texto: 'Suplantación de identidad',
        gravedad: 'GRAVE',
        detalle: 'La foto del documento no coincide.',
        estudiante: 'Joven de chompa azul',
        hora: '16:00',
        por: 'Mamani Quispe Diego',
        rol: 'Auxiliar',
    }),
];

let lista;
let rutas;

const tablero = (aulas = AULAS, cambios = {}) => ({
    data: {
        examen: EXAMENES[0],
        hora_servidor: '2026-10-09T16:07:16-04:00',
        ultimo_registro:
            aulas
                .map((a) => a.ultimo_registro)
                .filter(Boolean)
                .sort()
                .at(-1) ?? null,
        cifras: {
            ingresaron: aulas.reduce((s, a) => s + a.ingresados, 0),
            faltan: aulas.reduce((s, a) => s + a.faltan, 0),
            aulas: aulas.length,
            incidencias_sin_atender: lista.filter((i) => !i.atendida).length,
        },
        aulas,
        es_docente: true,
        ...cambios,
    },
});

const cuadro = (x, y) => [
    [x, y],
    [x + 0.001, y],
    [x + 0.001, y + 0.001],
    [x, y + 0.001],
];

const EDIFICIOS = {
    data: [
        {
            id: 2,
            facultad: 'FCyT',
            nombre: 'Edificio Académico 2',
            poligono: cuadro(-66.145, -17.394),
            centro: [-66.1445, -17.3935],
            aulas: ['691A', '691B'],
            pisos: [],
        },
        {
            id: 14,
            facultad: 'FCyT',
            nombre: 'Bloque 617',
            poligono: cuadro(-66.147, -17.392),
            centro: [-66.1465, -17.3915],
            aulas: ['691E'],
            pisos: [],
        },
    ],
    meta: { caja: { lon: [-66.15, -66.14], lat: [-17.4, -17.39] } },
};

const montar = (ruta = '/en-curso', usuario = usuarioDePrueba('Docente')) =>
    renderConSesion(<EnCurso />, { usuario, ruta });
const tarjetaDe = (nombre) => screen.getByRole('heading', { name: `Aula ${nombre}` }).closest('li');
const dato = (etiqueta) => screen.getByText(etiqueta, { selector: 'span' }).parentElement;
const ordenDeAulas = () =>
    screen.getAllByRole('heading', { level: 3, name: /^Aula / }).map((h) => h.textContent);
const cargado = () => screen.findByRole('heading', { name: 'Aula 691A' });
const tarjetaIncidencias = async () =>
    (await screen.findByRole('heading', { name: 'Incidencias · 3' })).closest('section');
const pedidosDel = (url) => api.get.mock.calls.filter(([u]) => u === url).length;

beforeEach(() => {
    olvidarEdificios();
    lista = incidencias();
    rutas = {
        'GET /en-curso': { data: EXAMENES },
        'GET /en-curso/1': () => tablero(),
        'GET /en-curso/3': () => ({
            data: {
                ...tablero([aula({ aula_id: 58, nombre: 'INFLAB', asignados: 44 })]).data,
                examen: EXAMENES[1],
                ultimo_registro: null,
            },
        }),
        'GET /examenes/1/incidencias': () => ({
            data: lista,
            meta: { sin_atender: lista.filter((i) => !i.atendida).length, todas: lista.length },
        }),
        'GET /examenes/3/incidencias': { data: [], meta: { sin_atender: 0, todas: 0 } },
        'POST /incidencias/*/atencion': ({ url }) => {
            const id = Number(url.split('/')[2]);
            lista = lista.map((i) =>
                i.id === id
                    ? {
                          ...i,
                          atendida: true,
                          atendida_por: 'Blanco Coca Leticia',
                          atendida_en: '2026-10-09T16:07:20-04:00',
                      }
                    : i
            );
            return { message: 'Incidencia atendida.' };
        },
        'GET /edificios': EDIFICIOS,
    };
    simularApi(api, rutas);
});

afterEach(() => {
    vi.useRealTimers();
});

describe('EnCurso (HU-22)', () => {
    it('mientras carga muestra el esqueleto', () => {
        montar();
        expect(screen.getByRole('status', { name: 'Cargando' })).toBeInTheDocument();
    });

    it('sin exámenes de hoy ni próximos lo dice', async () => {
        rutas['GET /en-curso'] = { data: [] };
        simularApi(api, rutas);
        montar();

        expect(await screen.findByText('Sin exámenes')).toBeVisible();
        expect(pedidosDel('/en-curso/1')).toBe(0);
    });

    it('si el examen no carga ofrece reintentar', async () => {
        rutas['GET /en-curso/1'] = errorHttp(500);
        simularApi(api, rutas);
        montar();

        expect(await screen.findByText('No se pudo cargar')).toBeVisible();
        rutas['GET /en-curso/1'] = () => tablero();
        simularApi(api, rutas);
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));

        expect(await cargado()).toBeVisible();
    });

    it('muestra cada aula con sus ingresados, sus faltantes y quién la atiende', async () => {
        montar();
        await cargado();

        expect(screen.getByText('Primer parcial · Introducción a la Programación')).toBeVisible();
        expect(dato('Ingresaron')).toHaveTextContent('59');
        expect(dato('Faltan')).toHaveTextContent('92');
        expect(dato('Aulas')).toHaveTextContent('3');
        expect(dato('Incidencias sin atender')).toHaveTextContent('2');

        const propia = within(tarjetaDe('691B'));
        expect(propia.getByText('40 de 51 · Faltan 11')).toBeVisible();
        expect(propia.getByText('Blanco Coca Leticia')).toBeVisible();
        expect(propia.getByText('· Docente')).toBeVisible();
        expect(propia.getByText('Lucía Peredo Soliz, Sergio Vargas')).toBeVisible();
        expect(propia.getByText('Código de acceso')).toBeVisible();
        expect(propia.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '40');

        expect(within(tarjetaDe('691A')).getByText('19 de 50 · Faltan 31')).toBeVisible();

        const auxiliar = within(tarjetaDe('691E'));
        expect(auxiliar.getByText('Mamani Quispe Diego')).toBeVisible();
        expect(auxiliar.getByText('· Auxiliar a cargo')).toBeVisible();
        expect(auxiliar.queryByText('Sin ingresos')).toBeNull();
        expect(auxiliar.getByText('1 incidencia sin atender')).toBeVisible();
    });

    it('dice quiénes entraron por otra aula', async () => {
        rutas['GET /en-curso/1'] = () =>
            tablero([aula({ aula_id: 31, nombre: '691A', ingresados: 3, de_otra: 2, en_otra: 1 })]);
        simularApi(api, rutas);
        montar();
        await cargado();

        expect(
            within(tarjetaDe('691A')).getByText(
                '3 de 50 · Faltan 47 · +2 de otra aula · 1 entró en otra'
            )
        ).toBeVisible();
    });

    it('muestra el ritmo de llegada y el panel de quién controla al docente', async () => {
        rutas['GET /en-curso/1'] = () =>
            tablero(AULAS, {
                llegadas: [
                    { hora: '15:55', ingresos: 3 },
                    { hora: '16:00', ingresos: 5 },
                ],
            });
        rutas['GET /examenes/1/control'] = {
            data: [
                {
                    aula_id: 31,
                    nombre: '691A',
                    usa_codigo: true,
                    personal: [],
                    accesos: [
                        {
                            id: 4,
                            nombre: 'Rocha Ana',
                            estado: 'pendiente',
                            aula_id: 31,
                            aula: '691A',
                            pedido_en: '2026-10-09T16:01:00-04:00',
                        },
                    ],
                },
            ],
            meta: { pendientes: 1, minutos_en_linea: 2 },
        };
        simularApi(api, rutas);
        montar();
        await cargado();

        expect(await screen.findByText('Llegadas · 8')).toBeVisible();
        expect(await screen.findByText('Piden controlar con código · 1')).toBeVisible();
    });

    it('un aula sin responsable lo dice', async () => {
        rutas['GET /en-curso/1'] = () =>
            tablero([aula({ aula_id: 31, nombre: '691A', a_cargo: null })]);
        simularApi(api, rutas);
        montar();
        await cargado();

        expect(within(tarjetaDe('691A')).getByText('Sin responsable')).toBeVisible();
    });

    it('indica que el ingreso está abierto y la hora del último registro', async () => {
        montar();
        await cargado();

        expect(screen.getByText('Ingreso abierto · Último registro 16:05')).toBeVisible();
        expect(within(tarjetaDe('691B')).getByText('Último registro 16:05')).toBeVisible();
        expect(within(tarjetaDe('691A')).getByText('Último registro 16:04')).toBeVisible();
        expect(within(tarjetaDe('691E')).queryByText(/Último registro/)).toBeNull();
    });

    it('sin ingresos todavía dice «Sin registros»', async () => {
        rutas['GET /en-curso/1'] = () =>
            tablero(AULAS.map((a) => ({ ...a, ingresados: 0, ultimo_registro: null })));
        simularApi(api, rutas);
        montar();
        await cargado();

        expect(screen.getByText('Ingreso abierto · Sin registros')).toBeVisible();
    });

    it('señala el aula compartida con otro examen a la misma hora', async () => {
        montar();
        await cargado();

        expect(
            within(tarjetaDe('691A')).getByText('Compartida · Cálculo I · Primer parcial')
        ).toBeVisible();
        expect(within(tarjetaDe('691B')).queryByText(/Compartida/)).toBeNull();
    });

    it('se actualiza sola cada 10 s sin recargar la página', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        let ingresados = 19;
        rutas['GET /en-curso/1'] = () =>
            tablero(
                AULAS.map((a) =>
                    a.aula_id === 31 ? { ...a, ingresados, faltan: 50 - ingresados } : a
                ),
                { ultimo_registro: `2026-10-09T16:0${ingresados === 19 ? 5 : 7}:48-04:00` }
            );
        simularApi(api, rutas);
        montar();
        await cargado();
        expect(within(tarjetaDe('691A')).getByText('19 de 50 · Faltan 31')).toBeVisible();

        ingresados = 30;
        await act(async () => vi.advanceTimersByTimeAsync(10000));

        expect(within(tarjetaDe('691A')).getByText('30 de 50 · Faltan 20')).toBeVisible();
        expect(screen.getByText('Ingreso abierto · Último registro 16:07')).toBeVisible();
        expect(pedidosDel('/en-curso/1')).toBe(2);
    });

    it('tras tres fallos seguidos indica «Sin conexión» y conserva los datos', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        montar();
        await cargado();

        rutas['GET /en-curso/1'] = errorHttp(500);
        simularApi(api, rutas);
        await act(async () => vi.advanceTimersByTimeAsync(20000));
        expect(screen.queryByText('Sin conexión')).toBeNull();

        await act(async () => vi.advanceTimersByTimeAsync(10000));
        expect(screen.getByText('Sin conexión')).toBeVisible();
        expect(screen.getByRole('heading', { name: 'Aula 691A' })).toBeVisible();
    });

    it('deja a la vista las incidencias sin atender y permite ver todas', async () => {
        montar();
        await cargado();
        const tarjeta = await tarjetaIncidencias();

        expect(within(tarjeta).getByRole('button', { name: 'Sin atender2' })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        expect(within(tarjeta).getByText('Faltan pupitres en dos filas.')).toBeVisible();
        expect(within(tarjeta).getByText('La foto del documento no coincide.')).toBeVisible();
        expect(within(tarjeta).getByText('Joven de chompa azul')).toBeVisible();
        expect(within(tarjeta).getByText('Suplantación de identidad')).toBeVisible();
        expect(within(tarjeta).getAllByText('Grave').length).toBeGreaterThan(0);
        expect(within(tarjeta).getByText('Aula 691E · 16:00')).toBeVisible();
        expect(within(tarjeta).getByText('Mamani Quispe Diego · Auxiliar')).toBeVisible();
        expect(within(tarjeta).queryByText('Consultaba el teléfono.')).toBeNull();

        fireEvent.click(within(tarjeta).getByRole('button', { name: /Todas/ }));

        const atendida = within(tarjeta).getByText('Consultaba el teléfono.').closest('li');
        expect(within(atendida).getByText(/Atendida/)).toBeVisible();
        expect(within(atendida).queryByRole('button', { name: 'Atender' })).toBeNull();
    });

    it('el docente atiende una incidencia con confirmación y no se deshace', async () => {
        montar();
        await cargado();
        const tarjeta = await tarjetaIncidencias();

        const fila = within(tarjeta).getByText('Faltan pupitres en dos filas.').closest('li');
        fireEvent.click(within(fila).getByRole('button', { name: 'Atender' }));
        fireEvent.click(within(fila).getByRole('button', { name: 'Confirmar' }));

        expect(await screen.findByText('Incidencia atendida')).toBeVisible();
        expect(api.post).toHaveBeenCalledWith('/incidencias/5/atencion');
        await waitFor(() =>
            expect(within(tarjeta).queryByText('Faltan pupitres en dos filas.')).toBeNull()
        );
    });

    it('quien no es docente del examen ve las incidencias sin atenderlas', async () => {
        rutas['GET /en-curso/1'] = () => tablero(AULAS, { es_docente: false });
        simularApi(api, rutas);
        montar();
        await cargado();
        const tarjeta = await tarjetaIncidencias();

        expect(within(tarjeta).queryByRole('button', { name: 'Atender' })).toBeNull();
        expect(within(tarjeta).getAllByText('Sin atender').length).toBeGreaterThan(0);
    });

    it('reporta una incidencia en cualquiera de las aulas del tablero', async () => {
        rutas['POST /examenes/1/incidencias'] = { message: 'Incidencia registrada.' };
        simularApi(api, rutas);
        montar();
        await cargado();

        fireEvent.click(screen.getByRole('button', { name: 'Incidencia' }));
        const dialogo = screen.getByRole('dialog', { name: 'Incidencia' });
        expect(within(dialogo).getByText('16:07 · Blanco Coca Leticia')).toBeVisible();

        const opciones = within(within(dialogo).getByRole('combobox', { name: /Aula/ }))
            .getAllByRole('option')
            .map((o) => o.textContent);
        expect(opciones).toEqual(['691B', '691A', '691E']);

        fireEvent.change(within(dialogo).getByRole('combobox', { name: /Aula/ }), {
            target: { value: '31' },
        });
        fireEvent.change(within(dialogo).getByRole('combobox', { name: /Tipo/ }), {
            target: { value: 'MATERIAL_NO_PERMITIDO' },
        });
        fireEvent.change(within(dialogo).getByRole('textbox', { name: 'Estudiante' }), {
            target: { value: '202100123' },
        });
        fireEvent.change(within(dialogo).getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Apuntes bajo la mesa.' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Registrar' }));

        expect(await screen.findByText('Incidencia registrada')).toBeVisible();
        expect(api.post).toHaveBeenCalledWith('/examenes/1/incidencias', {
            aula_id: 31,
            tipo: 'MATERIAL_NO_PERMITIDO',
            estudiante: '202100123',
            detalle: 'Apuntes bajo la mesa.',
        });
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('muestra el error del servidor en el campo del estudiante', async () => {
        rutas['POST /examenes/1/incidencias'] = errorHttp(422, {
            message: 'No inscrito en el examen.',
            errors: { estudiante: ['No inscrito en el examen.'] },
        });
        simularApi(api, rutas);
        montar();
        await cargado();

        fireEvent.click(screen.getByRole('button', { name: 'Incidencia' }));
        const dialogo = screen.getByRole('dialog', { name: 'Incidencia' });
        fireEvent.change(within(dialogo).getByRole('combobox', { name: /Tipo/ }), {
            target: { value: 'OTRA' },
        });
        fireEvent.change(within(dialogo).getByRole('textbox', { name: 'Estudiante' }), {
            target: { value: '99999' },
        });
        fireEvent.change(within(dialogo).getByRole('textbox', { name: /Detalle/ }), {
            target: { value: 'Llegó sin documento.' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Registrar' }));

        expect(await within(dialogo).findByText('No inscrito en el examen.')).toBeVisible();
    });

    it('fuera del día del examen no ofrece reportar', async () => {
        montar('/en-curso?examen=3');
        await screen.findByRole('heading', { name: 'Aula INFLAB' });
        expect(screen.queryByRole('button', { name: 'Incidencia' })).toBeNull();
    });

    it('muestra el mensaje del servidor si la incidencia ya estaba atendida', async () => {
        rutas['POST /incidencias/*/atencion'] = errorHttp(409, {
            message: 'La incidencia ya estaba atendida.',
        });
        simularApi(api, rutas);
        montar();
        await cargado();
        const tarjeta = await tarjetaIncidencias();

        fireEvent.click(within(tarjeta).getAllByRole('button', { name: 'Atender' })[0]);
        fireEvent.click(within(tarjeta).getByRole('button', { name: 'Confirmar' }));

        expect(await screen.findByText('La incidencia ya estaba atendida.')).toBeVisible();
    });

    it('filtra las aulas con su cantidad y avisa si ninguna queda', async () => {
        montar();
        await cargado();
        const filtros = screen.getByRole('group', { name: 'Aulas' });
        expect(within(filtros).getByRole('button', { name: 'Todas3' })).toBeVisible();
        expect(within(filtros).getByRole('button', { name: 'Con incidencias2' })).toBeVisible();
        expect(within(filtros).getByRole('button', { name: 'Con pendientes3' })).toBeVisible();
        expect(within(filtros).getByRole('button', { name: 'Sin ingresos1' })).toBeVisible();
        expect(within(filtros).getByRole('button', { name: 'A mi cargo1' })).toBeVisible();

        fireEvent.click(within(filtros).getByRole('button', { name: /A mi cargo/ }));
        expect(ordenDeAulas()).toEqual(['Aula 691B']);

        fireEvent.click(within(filtros).getByRole('button', { name: /Sin ingresos/ }));
        expect(ordenDeAulas()).toEqual(['Aula 691E']);

        fireEvent.click(within(filtros).getByRole('button', { name: /Con incidencias/ }));
        expect(ordenDeAulas()).toEqual(['Aula 691E', 'Aula 691A']);
    });

    it('sin aulas que cumplan el filtro dice «Sin resultados»', async () => {
        rutas['GET /en-curso/1'] = () => tablero(AULAS.map((a) => ({ ...a, a_mi_cargo: false })));
        simularApi(api, rutas);
        montar();
        await cargado();

        fireEvent.click(screen.getByRole('button', { name: /A mi cargo/ }));
        expect(screen.getByText('Sin resultados')).toBeVisible();
    });

    it('ordena las aulas por menor avance, más pendientes o nombre', async () => {
        montar();
        await cargado();

        expect(ordenDeAulas()).toEqual(['Aula 691E', 'Aula 691A', 'Aula 691B']);

        fireEvent.change(screen.getByLabelText('Orden'), { target: { value: 'aula' } });
        expect(ordenDeAulas()).toEqual(['Aula 691A', 'Aula 691B', 'Aula 691E']);

        fireEvent.change(screen.getByLabelText('Orden'), { target: { value: 'faltan' } });
        expect(ordenDeAulas()).toEqual(['Aula 691E', 'Aula 691A', 'Aula 691B']);
    });

    it('marca en el mapa los edificios de las aulas del examen', async () => {
        const { container } = montar();
        await cargado();

        await waitFor(() =>
            expect(container.querySelectorAll('svg[role=img] polygon')).toHaveLength(2)
        );
        const rotulos = [...container.querySelectorAll('svg[role=img] text')].map(
            (t) => t.textContent
        );
        expect(rotulos).toEqual(expect.arrayContaining(['Aula 691B, 691A', 'Aula 691E']));
    });

    it('lleva a la lista y a la puerta de cada aula', async () => {
        montar();
        await cargado();

        expect(screen.getByRole('link', { name: 'Lista del aula 691A' })).toHaveAttribute(
            'href',
            '/en-curso/aula?examen=1&aula=31'
        );
        expect(screen.getByRole('link', { name: 'Controlar el aula 691A' })).toHaveAttribute(
            'href',
            '/punto-control?aula=31'
        );
    });

    it('ofrece controlar cada aula cuya puerta opera la cuenta, esté o no a su cargo', async () => {
        montar();
        await cargado();

        expect(screen.getByRole('link', { name: 'Controlar el aula 691B' })).toHaveAttribute(
            'href',
            '/punto-control?aula=32'
        );
        expect(screen.getByRole('link', { name: 'Controlar el aula 691A' })).toHaveAttribute(
            'href',
            '/punto-control?aula=31'
        );
        expect(screen.queryByRole('link', { name: 'Controlar el aula 691E' })).toBeNull();
    });

    it('sin los permisos de la lista o de la puerta no ofrece sus enlaces', async () => {
        montar('/en-curso', usuarioDePrueba('Docente', { permisos: ['examenes'] }));
        await cargado();

        expect(screen.queryByRole('link', { name: /Lista del aula/ })).toBeNull();
        expect(screen.queryByRole('link', { name: /Controlar/ })).toBeNull();
    });

    it('con el examen sin abrir dice su fecha y no ofrece controlar', async () => {
        montar('/en-curso?examen=3');
        await screen.findByRole('heading', { name: 'Aula INFLAB' });

        expect(screen.getByText('11 oct, 08:15')).toBeVisible();
        expect(screen.queryByText(/Último registro|Sin registros/)).toBeNull();
        expect(screen.queryByRole('link', { name: /Controlar/ })).toBeNull();
        expect(screen.queryByText('Sin ingresos')).toBeNull();
        expect(screen.queryByRole('group', { name: 'Aulas' })).toBeNull();
        expect(screen.queryByRole('heading', { name: /Incidencias/ })).toBeNull();
    });

    it('cambia de examen con el selector', async () => {
        montar();
        await cargado();

        fireEvent.change(screen.getByRole('combobox', { name: /Examen/ }), {
            target: { value: '3' },
        });

        expect(await screen.findByRole('heading', { name: 'Aula INFLAB' })).toBeVisible();
        expect(screen.getByText('Examen final · Taller de Ingeniería de Software')).toBeVisible();
    });

    it('con más de seis aulas pasa a la rejilla compacta', async () => {
        const muchas = Array.from({ length: 8 }, (_, i) =>
            aula({
                aula_id: 100 + i,
                nombre: `62${i}`,
                ingresados: i * 5,
                compartida: i === 7 ? ['Cálculo I · Primer parcial'] : [],
                incidencias_sin_atender: i === 0 ? 2 : 0,
                ultimo_registro: i === 1 ? '2026-10-09T16:06:40-04:00' : null,
            })
        );
        rutas['GET /en-curso/1'] = () => tablero(muchas);
        simularApi(api, rutas);
        montar();
        await screen.findByRole('heading', { name: 'Aula 620' });

        expect(within(tarjetaDe('620')).getByText('0 de 50')).toBeVisible();
        expect(within(tarjetaDe('620')).getByText(/2 incidencias/)).toBeVisible();
        expect(
            within(tarjetaDe('627')).getByText(/Faltan 15 · Docente · Compartida/)
        ).toBeVisible();
        expect(
            within(tarjetaDe('621')).getByText(/Faltan 45 · Último 16:06 · Docente/)
        ).toBeVisible();
        expect(ordenDeAulas()[0]).toBe('Aula 620');
    });
});
