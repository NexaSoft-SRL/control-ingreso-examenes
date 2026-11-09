import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import EnCurso from './EnCurso';
import { api } from '../../api/cliente';
import { olvidarEdificios } from '../../api/usarEdificios';
import {
    errorHttp,
    renderConSesion,
    simularApi,
    usuarioDePrueba,
    elegirExamen,
} from '../../test/apoyo';

vi.mock('../../api/cliente');

// Las respuestas, con la forma que devuelve el servidor (rutas 70, 71 y 67).
const EXAMENES = [
    {
        examen_id: 1,
        texto: '9 oct · Primer parcial · Introducción a la Programación',
        asignatura: 'Introducción a la Programación',
        tipo: 'PRIMER_PARCIAL',
        tipo_texto: 'Primer parcial',
        fecha: '2026-10-09',
        hora: '15:59',
        abierto: true,
        aulas: [
            { aula_id: 32, nombre: '691B' },
            { aula_id: 31, nombre: '691A' },
            { aula_id: 35, nombre: '691E' },
        ],
    },
    {
        examen_id: 3,
        texto: '11 oct · Examen final · Taller de Ingeniería de Software',
        asignatura: 'Taller de Ingeniería de Software',
        tipo: 'FINAL',
        tipo_texto: 'Examen final',
        fecha: '2026-10-11',
        hora: '08:15',
        abierto: false,
        aulas: [{ aula_id: 58, nombre: 'INFLAB' }],
    },
];

const aula = (cambios) => ({
    edificio_id: 2,
    encargado: { nombre: 'Blanco Coca Leticia', es_docente: true },
    apoyo: [],
    ingresados: 0,
    asignados: 50,
    incidencias_sin_atender: 0,
    compartida: null,
    usa_codigo: false,
    a_mi_cargo: false,
    ultimo_ingreso: null,
    ...cambios,
});

const AULAS = [
    aula({
        aula_id: 32,
        nombre: '691B',
        apoyo: ['Lucía Peredo Soliz', 'Sergio Vargas Camacho'],
        ingresados: 40,
        asignados: 51,
        de_otra: 2,
        en_otra: 1,
        usa_codigo: true,
        a_mi_cargo: true,
        ultimo_ingreso: '2026-10-09T16:05:12-04:00',
    }),
    aula({
        aula_id: 31,
        nombre: '691A',
        encargado: { nombre: 'Salazar Serrudo Carla', es_docente: true },
        ingresados: 22,
        incidencias_sin_atender: 1,
        compartida: 'Cálculo I · Primer parcial',
        ultimo_ingreso: '2026-10-09T16:04:30-04:00',
    }),
    aula({
        aula_id: 35,
        nombre: '691E',
        edificio_id: 14,
        encargado: { nombre: 'Diego Mamani Quispe', es_docente: false },
        ingresados: 0,
        incidencias_sin_atender: 1,
    }),
];

const tablero = (aulas = AULAS, cambios = {}) => ({
    examen: {
        id: 1,
        asignatura: 'Introducción a la Programación',
        tipo: 'Primer parcial',
        fecha: '2026-10-09',
        hora: '15:59',
    },
    abierto: true,
    hora_servidor: '2026-10-09T16:07:16-04:00',
    ultimo_ingreso:
        aulas
            .map((a) => a.ultimo_ingreso)
            .filter(Boolean)
            .sort()
            .at(-1) ?? null,
    cifras: {
        ingresaron: aulas.reduce((s, a) => s + a.ingresados, 0),
        faltan: 89,
        aulas: aulas.length,
        incidencias_sin_atender: aulas.reduce((s, a) => s + a.incidencias_sin_atender, 0),
    },
    aulas,
    ...cambios,
});

const incidencias = () => [
    {
        id: 5,
        tipo: 'OTRA',
        tipo_texto: 'Otra',
        aula_id: 31,
        aula: '691A',
        estudiante: null,
        detalle: 'Faltan pupitres en dos filas.',
        hora: '16:03',
        por: 'Salazar Serrudo Carla',
        rol: 'Docente',
        atendida: false,
        atendida_por: null,
        atendida_en: null,
    },
    {
        id: 2,
        tipo: 'EXPULSION',
        tipo_texto: 'Expulsión',
        aula_id: 32,
        aula: '691B',
        estudiante: 'Aguilar Bustamante Adriana · 202110007',
        detalle: 'Sorprendida consultando el teléfono.',
        hora: '16:02',
        por: 'Blanco Coca Leticia',
        rol: 'Docente',
        atendida: true,
        atendida_por: 'Blanco Coca Leticia',
        atendida_en: '2026-10-09T16:04:55-04:00',
    },
    {
        id: 3,
        tipo: 'SUPLANTACION',
        tipo_texto: 'Suplantación',
        aula_id: 35,
        aula: '691E',
        estudiante: 'Joven de chompa azul',
        detalle: 'La foto del documento no coincide.',
        hora: '16:00',
        por: 'Diego Mamani Quispe',
        rol: 'Auxiliar',
        atendida: false,
        atendida_por: null,
        atendida_en: null,
    },
];

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

let lista;
let rutas;

const montar = (ruta = '/en-curso', usuario = usuarioDePrueba('Docente')) =>
    renderConSesion(<EnCurso />, { usuario, ruta });
const tarjetaDe = (nombre) => screen.getByRole('heading', { name: `Aula ${nombre}` }).closest('li');
const dato = (etiqueta) => screen.getByText(etiqueta, { selector: 'span' }).parentElement;
const ordenDeAulas = () =>
    screen.getAllByRole('heading', { level: 2, name: /^Aula / }).map((h) => h.textContent);
const cargado = () => screen.findByRole('heading', { name: 'Aula 691A' });

beforeEach(() => {
    olvidarEdificios();
    lista = incidencias();
    rutas = {
        'GET /en-curso': { data: EXAMENES },
        'GET /examenes/1/en-curso': () => tablero(),
        'GET /examenes/3/en-curso': tablero(
            [aula({ aula_id: 58, nombre: 'INFLAB', asignados: 44, a_mi_cargo: true })],
            {
                examen: {
                    id: 3,
                    asignatura: 'Taller de Ingeniería de Software',
                    tipo: 'Examen final',
                    fecha: '2026-10-11',
                    hora: '08:15',
                },
                abierto: false,
            }
        ),
        'GET /examenes/*/incidencias': () => ({
            data: lista,
            meta: { sin_atender: lista.filter((i) => !i.atendida).length, todas: lista.length },
        }),
        'POST /incidencias/*/atencion': ({ url }) => {
            const id = Number(url.split('/')[2]);
            lista = lista.map((i) =>
                i.id === id
                    ? {
                          ...i,
                          atendida: true,
                          atendida_por: 'Docente De Prueba',
                          atendida_en: '2026-10-09T16:07:20-04:00',
                      }
                    : i
            );
            return { message: 'Incidencia atendida.' };
        },
        'POST /examenes/1/incidencias': { data: {}, message: 'Incidencia registrada.' },
        'GET /edificios': EDIFICIOS,
    };
    simularApi(api, rutas);
});

afterEach(() => {
    vi.useRealTimers();
});

describe('EnCurso', () => {
    it('muestra cada aula con sus ingresados, sus faltantes y quién la atiende', async () => {
        montar();
        await cargado();

        expect(screen.getByText('Primer parcial · Introducción a la Programación')).toBeVisible();
        expect(dato('Ingresaron')).toHaveTextContent('62');
        expect(dato('Faltan')).toHaveTextContent('89');
        expect(dato('Aulas')).toHaveTextContent('3');
        expect(dato('Incidencias sin atender')).toHaveTextContent('2');

        const propia = within(tarjetaDe('691B'));
        expect(
            propia.getByText('40 de 51 · Faltan 11 · +2 de otra aula · 1 entró en otra')
        ).toBeVisible();
        expect(propia.getByText('Blanco Coca Leticia')).toBeVisible();
        expect(propia.getByText('Lucía Peredo Soliz, Sergio Vargas Camacho')).toBeVisible();
        expect(propia.getByText('Código de acceso')).toBeVisible();
        expect(propia.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '40');

        const auxiliar = within(tarjetaDe('691E'));
        expect(auxiliar.getByText('Diego Mamani Quispe')).toBeVisible();
        expect(auxiliar.getByText('· Auxiliar a cargo')).toBeVisible();
        expect(auxiliar.getByText('Sin ingresos')).toBeVisible();
        expect(auxiliar.getByText('1 incidencia sin atender')).toBeVisible();
    });

    it('indica la hora del último registro y que el ingreso está abierto', async () => {
        montar();
        await cargado();

        // La del último ingreso, no la de la lectura (16:07).
        expect(screen.getByText('Ingreso abierto · Último registro 16:05')).toBeVisible();
        expect(within(tarjetaDe('691B')).getByText('Último registro 16:05')).toBeVisible();
        expect(
            within(tarjetaDe('691B')).getByText(/\+2 de otra aula · 1 entró en otra/)
        ).toBeVisible();
        expect(within(tarjetaDe('691A')).getByText('Último registro 16:04')).toBeVisible();
        expect(within(tarjetaDe('691E')).queryByText(/Último registro/)).toBeNull();
    });

    it('sin ingresos todavía lo dice en lugar de la hora', async () => {
        rutas['GET /examenes/1/en-curso'] = tablero(
            AULAS.map((a) => ({ ...a, ingresados: 0, ultimo_ingreso: null }))
        );
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

    it('se actualiza sin recargar: vuelve a pedir el tablero cada 10 s', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        let ingresados = 22;
        rutas['GET /examenes/1/en-curso'] = () =>
            tablero(
                AULAS.map((a) => (a.aula_id === 31 ? { ...a, ingresados } : a)),
                {
                    hora_servidor: `2026-10-09T16:0${ingresados === 22 ? 7 : 8}:00-04:00`,
                    ultimo_ingreso: `2026-10-09T16:0${ingresados === 22 ? 5 : 7}:48-04:00`,
                }
            );
        simularApi(api, rutas);
        montar();
        await cargado();
        expect(within(tarjetaDe('691A')).getByText('22 de 50 · Faltan 28')).toBeVisible();

        ingresados = 30;
        await act(async () => vi.advanceTimersByTimeAsync(10000));

        expect(within(tarjetaDe('691A')).getByText('30 de 50 · Faltan 20')).toBeVisible();
        expect(screen.getByText('Ingreso abierto · Último registro 16:07')).toBeVisible();
        expect(api.get.mock.calls.filter(([url]) => url === '/examenes/1/en-curso')).toHaveLength(
            2
        );
    });

    it('avisa cuando el sondeo falla tres veces seguidas y conserva los datos', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        montar();
        await cargado();

        rutas['GET /examenes/1/en-curso'] = errorHttp(500);
        simularApi(api, rutas);
        await act(async () => vi.advanceTimersByTimeAsync(30000));

        expect(screen.getByText('Sin conexión')).toBeVisible();
        expect(screen.getByRole('heading', { name: 'Aula 691A' })).toBeVisible();
    });

    it('deja a la vista las incidencias sin atender y permite ver todas', async () => {
        montar();
        await cargado();

        const tarjeta = (await screen.findByRole('heading', { name: 'Incidencias · 3' })).closest(
            'section'
        );
        expect(within(tarjeta).getByText('Faltan pupitres en dos filas.')).toBeVisible();
        expect(within(tarjeta).getByText('La foto del documento no coincide.')).toBeVisible();
        expect(within(tarjeta).queryByText('Sorprendida consultando el teléfono.')).toBeNull();
        expect(within(tarjeta).getByText(/Aula 691E · 16:00/)).toBeVisible();

        fireEvent.click(
            within(within(tarjeta).getByRole('group', { name: 'Incidencias' })).getByRole(
                'button',
                {
                    name: /Todas/,
                }
            )
        );

        const atendida = within(tarjeta)
            .getByText('Sorprendida consultando el teléfono.')
            .closest('li');
        expect(within(atendida).getByText('Atendida')).toBeVisible();
        // Quién la atendió y cuándo.
        expect(within(atendida).getByText('Blanco Coca Leticia · 9 oct 2026, 16:04')).toBeVisible();
    });

    it('filtra las incidencias por gravedad', async () => {
        montar();
        await cargado();
        const tarjeta = (await screen.findByRole('heading', { name: 'Incidencias · 3' })).closest(
            'section'
        );
        const gravedad = within(within(tarjeta).getByRole('group', { name: 'Gravedad' }));

        expect(gravedad.getByRole('button', { name: /Muy grave/ })).toHaveTextContent('Muy grave1');
        fireEvent.click(gravedad.getByRole('button', { name: /Muy grave/ }));

        expect(within(tarjeta).getByText('La foto del documento no coincide.')).toBeVisible();
        expect(within(tarjeta).queryByText('Faltan pupitres en dos filas.')).toBeNull();
        expect(within(tarjeta).getAllByText('Muy grave').length).toBeGreaterThan(0);

        fireEvent.click(gravedad.getByRole('button', { name: /Leve/ }));
        expect(within(tarjeta).getByText('Faltan pupitres en dos filas.')).toBeVisible();
        expect(within(tarjeta).queryByText('La foto del documento no coincide.')).toBeNull();
    });

    it('marca una incidencia como atendida y no ofrece deshacerlo', async () => {
        montar();
        await cargado();
        const tarjeta = (await screen.findByRole('heading', { name: 'Incidencias · 3' })).closest(
            'section'
        );

        fireEvent.click(within(tarjeta).getAllByRole('button', { name: 'Atender' })[0]);
        fireEvent.click(within(tarjeta).getByRole('button', { name: 'Confirmar' }));

        expect(await screen.findByText('Incidencia atendida')).toBeVisible();
        expect(api.post).toHaveBeenCalledWith('/incidencias/5/atencion');
        await waitFor(() =>
            expect(within(tarjeta).queryByText('Faltan pupitres en dos filas.')).toBeNull()
        );
        expect(within(tarjeta).getAllByRole('button', { name: 'Atender' })).toHaveLength(1);

        fireEvent.click(
            within(within(tarjeta).getByRole('group', { name: 'Incidencias' })).getByRole(
                'button',
                {
                    name: /Todas/,
                }
            )
        );
        const atendida = within(tarjeta).getByText('Faltan pupitres en dos filas.').closest('li');
        expect(within(atendida).getByText('Atendida')).toBeVisible();
        expect(within(atendida).getByText('Docente De Prueba · 9 oct 2026, 16:07')).toBeVisible();
        expect(within(atendida).queryByRole('button', { name: 'Atender' })).toBeNull();
    });

    it('muestra el mensaje del servidor si la incidencia ya estaba atendida', async () => {
        rutas['POST /incidencias/*/atencion'] = errorHttp(409, {
            message: 'La incidencia ya fue atendida.',
            codigo: 'YA_ATENDIDA',
        });
        simularApi(api, rutas);
        montar();
        await cargado();
        await screen.findByRole('heading', { name: 'Incidencias · 3' });

        fireEvent.click(screen.getAllByRole('button', { name: 'Atender' })[0]);
        fireEvent.click(screen.getByRole('button', { name: 'Confirmar' }));

        expect(await screen.findByText('La incidencia ya fue atendida.')).toBeVisible();
    });

    it('reporta una incidencia en un aula del examen', async () => {
        montar();
        await cargado();

        fireEvent.click(screen.getByRole('button', { name: 'Incidencia' }));
        const dialogo = screen.getByRole('dialog');
        fireEvent.change(within(dialogo).getByLabelText(/Aula/), { target: { value: '35' } });
        fireEvent.change(within(dialogo).getByLabelText(/Tipo/), {
            target: { value: 'INTENTO_NO_AUTORIZADO' },
        });
        fireEvent.change(within(dialogo).getByLabelText(/Detalle/), {
            target: { value: 'Corte de luz en el aula' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Registrar' }));

        expect(await screen.findByText('Incidencia registrada')).toBeVisible();
        expect(api.post).toHaveBeenCalledWith('/examenes/1/incidencias', {
            aula_id: 35,
            tipo: 'INTENTO_NO_AUTORIZADO',
            estudiante: null,
            detalle: 'Corte de luz en el aula',
        });
    });

    it('filtra las aulas', async () => {
        montar();
        await cargado();
        const filtros = screen.getByRole('group', { name: 'Aulas' });
        expect(within(filtros).getByRole('button', { name: 'Con incidencias2' })).toBeVisible();

        fireEvent.click(within(filtros).getByRole('button', { name: /A mi cargo/ }));
        expect(ordenDeAulas()).toEqual(['Aula 691B']);

        fireEvent.click(within(filtros).getByRole('button', { name: /Sin ingresos/ }));
        expect(ordenDeAulas()).toEqual(['Aula 691E']);

        fireEvent.click(within(filtros).getByRole('button', { name: /Con incidencias/ }));
        expect(ordenDeAulas()).toEqual(['Aula 691E', 'Aula 691A']);
    });

    it('ordena las aulas por avance, por pendientes o por nombre', async () => {
        montar();
        await cargado();

        expect(ordenDeAulas()).toEqual(['Aula 691E', 'Aula 691A', 'Aula 691B']);

        fireEvent.change(screen.getByLabelText('Orden'), { target: { value: 'aula' } });
        expect(ordenDeAulas()).toEqual(['Aula 691A', 'Aula 691B', 'Aula 691E']);

        fireEvent.change(screen.getByLabelText('Orden'), { target: { value: 'faltan' } });
        expect(ordenDeAulas()).toEqual(['Aula 691E', 'Aula 691A', 'Aula 691B']);
    });

    it('dibuja en el mapa los edificios de las aulas del examen', async () => {
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

    it('lleva a la lista y al punto de control de cada aula', async () => {
        montar();
        await cargado();

        expect(screen.getByRole('link', { name: 'Lista del aula 691A' })).toHaveAttribute(
            'href',
            '/en-curso/aula?examen=1&aula=31'
        );
        expect(screen.getByRole('link', { name: 'Controlar el aula 691A' })).toHaveAttribute(
            'href',
            '/control?aula=31'
        );
    });

    it('un examen sin abrir no está en curso: ni en el selector ni pedido por dirección', async () => {
        montar('/en-curso?examen=3');
        await cargado();

        expect(screen.queryByRole('heading', { name: 'Aula INFLAB' })).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: /^Examen:/ }));
        expect(
            screen.queryByRole('option', {
                name: /^Taller de Ingeniería de Software/,
            })
        ).toBeNull();
    });

    it('el docente finaliza el examen y deja de estar en curso', async () => {
        let finalizado = false;
        rutas['POST /examenes/1/finalizar'] = () => {
            finalizado = true;
            return { message: 'Examen finalizado.', finalizado_en: '2026-10-09 17:40:00' };
        };
        rutas['GET /en-curso'] = () => ({
            data: EXAMENES.map((e) => ({ ...e, abierto: e.examen_id === 1 && !finalizado })),
        });
        simularApi(api, rutas);
        montar();
        await cargado();

        fireEvent.click(screen.getByRole('button', { name: /Finalizar examen/ }));
        const dialogo = screen.getByRole('dialog', { name: 'Finalizar examen' });
        expect(dialogo).toHaveTextContent('Ingresaron');
        expect(api.post).not.toHaveBeenCalled();
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Finalizar' }));

        expect(await screen.findByText('Ningún examen en curso')).toBeVisible();
        expect(api.post).toHaveBeenCalledWith('/examenes/1/finalizar');
        expect(screen.getByText('Examen finalizado')).toBeInTheDocument();
    });

    it('pasada la hora prevista avisa que sigue abierto hasta finalizarlo', async () => {
        rutas['GET /examenes/1/en-curso'] = () => tablero(AULAS, { fin: '17:29', excedido: true });
        simularApi(api, rutas);
        montar();
        await cargado();

        expect(screen.getByRole('status')).toHaveTextContent(
            'Pasó la hora prevista de término (17:29). La puerta sigue abierta hasta que finalice el examen.'
        );
    });

    it('sin el permiso de exámenes no ofrece finalizar', async () => {
        montar('/en-curso', usuarioDePrueba('Docente', { permisos: ['seguimiento_vivo'] }));
        await cargado();

        expect(screen.queryByRole('button', { name: /Finalizar examen/ })).toBeNull();
    });

    it('sin un examen abierto dice que no hay ninguno en curso', async () => {
        rutas['GET /en-curso'] = { data: EXAMENES.map((e) => ({ ...e, abierto: false })) };
        simularApi(api, rutas);
        montar();

        expect(await screen.findByText('Ningún examen en curso')).toBeVisible();
    });

    it('cambia de examen con el selector', async () => {
        rutas['GET /en-curso'] = { data: EXAMENES.map((e) => ({ ...e, abierto: true })) };
        simularApi(api, rutas);
        montar();
        await cargado();

        elegirExamen(/^Taller de Ingeniería de Software/);

        expect(await screen.findByRole('heading', { name: 'Aula INFLAB' })).toBeVisible();
        expect(screen.getByText('Examen final · Taller de Ingeniería de Software')).toBeVisible();
    });

    it('toma el tipo de examen de su campo, no del texto', async () => {
        rutas['GET /en-curso'] = {
            data: EXAMENES.map((e) => ({ ...e, texto: 'Otro texto', abierto: true })),
        };
        rutas['GET /examenes/1/en-curso'] = errorHttp(403, {
            message: 'Este examen no está a su cargo.',
        });
        simularApi(api, rutas);
        montar();
        await screen.findByRole('link', { name: 'Aula 691A' });

        expect(screen.getByText('Primer parcial · Introducción a la Programación')).toBeVisible();
        fireEvent.click(screen.getByRole('button', { name: /^Examen:/ }));
        expect(
            screen.getByRole('option', {
                name: /^Taller de Ingeniería de Software/,
            })
        ).toBeInTheDocument();
    });

    it('con muchas aulas pasa a la rejilla compacta', async () => {
        const muchas = Array.from({ length: 8 }, (_, i) =>
            aula({
                aula_id: 100 + i,
                nombre: `62${i}`,
                ingresados: i * 5,
                compartida: i === 7 ? 'Cálculo I · Primer parcial' : null,
                incidencias_sin_atender: i === 0 ? 2 : 0,
                ultimo_ingreso: i === 1 ? '2026-10-09T16:06:40-04:00' : null,
            })
        );
        rutas['GET /examenes/1/en-curso'] = tablero(muchas);
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

    it('sin exámenes de hoy ni próximos lo dice', async () => {
        rutas['GET /en-curso'] = { data: [] };
        simularApi(api, rutas);
        montar();

        expect(await screen.findByText('Ningún examen en curso')).toBeVisible();
    });

    it('si el tablero no carga ofrece reintentar', async () => {
        rutas['GET /examenes/1/en-curso'] = errorHttp(500);
        simularApi(api, rutas);
        montar();

        expect(await screen.findByText('No se pudo cargar')).toBeVisible();
        rutas['GET /examenes/1/en-curso'] = tablero();
        simularApi(api, rutas);
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));

        expect(await cargado()).toBeVisible();
    });

    it('en un examen ajeno solo enlaza las aulas a su cargo', async () => {
        rutas['GET /examenes/1/en-curso'] = errorHttp(403, {
            message: 'Este examen no está a su cargo.',
            alcance: true,
        });
        simularApi(api, rutas);
        montar();

        expect(await screen.findByRole('link', { name: 'Aula 691A' })).toHaveAttribute(
            'href',
            '/en-curso/aula?examen=1&aula=31'
        );
        expect(api.get.mock.calls.some(([url]) => url.endsWith('/incidencias'))).toBe(false);
    });
});
