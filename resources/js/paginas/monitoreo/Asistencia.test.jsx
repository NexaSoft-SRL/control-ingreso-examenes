import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Asistencia from './Asistencia';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const AULAS = [
    { aula_id: 32, nombre: '691B' },
    { aula_id: 31, nombre: '691A' },
    { aula_id: 35, nombre: '691E' },
];
const examenDeHoy = (aulas = AULAS) => ({
    examen_id: 1,
    texto: '9 oct · Primer parcial · Introducción a la Programación',
    asignatura: 'Introducción a la Programación',
    tipo: 'PRIMER_PARCIAL',
    tipo_texto: 'Primer parcial',
    fecha: '2026-10-09',
    hora: '15:59',
    abierto: true,
    aulas,
});
const EXAMEN_PROXIMO = {
    examen_id: 3,
    texto: '11 oct · Primer parcial · Taller de Ingeniería de Software',
    asignatura: 'Taller de Ingeniería de Software',
    tipo: 'PRIMER_PARCIAL',
    tipo_texto: 'Primer parcial',
    fecha: '2026-10-11',
    hora: '08:15',
    abierto: false,
    aulas: [{ aula_id: 58, nombre: 'INFLAB' }],
};

// 50 asignados al aula: los 22 primeros ingresaron (el tercero, expulsado).
const estudiantes = () =>
    Array.from({ length: 50 }, (_, i) => ({
        codigo: String(202100001 + i),
        documento: String(7000001 + i),
        nombre: `Apellido${String(i + 1).padStart(2, '0')}, Nombre`,
        grupo: i % 2 === 0 ? '1' : '2',
        ingreso: i < 22 ? `16:0${i % 10}:1${i % 6}` : null,
        expulsado: i === 2,
    }));

// La lista como la devuelve la ruta 72, filtrada y paginada aquí.
function asistencia(todos, params = {}, nombre = '691A', cambios = {}) {
    const lista = params.lista ?? 'faltan';
    const texto = (params.buscar ?? '').toLowerCase();
    const propios = todos.filter((e) => (lista === 'ingresaron' ? e.ingreso : !e.ingreso));
    const filtrados = propios.filter(
        (e) => !texto || `${e.nombre} ${e.codigo} ${e.documento}`.toLowerCase().includes(texto)
    );
    const pagina = params.pagina ?? 1;
    const porPagina = params.por_pagina ?? 25;
    const ingresaron = todos.filter((e) => e.ingreso).length;

    return {
        examen: {
            id: 1,
            asignatura: 'Introducción a la Programación',
            tipo: 'Primer parcial',
            fecha: '2026-10-09',
            hora: '15:59',
        },
        aula: { id: 31, nombre },
        abierto: true,
        hora_servidor: '2026-10-09T16:07:16-04:00',
        cifras: { ingresaron, faltan: todos.length - ingresaron, asignados: todos.length },
        data: filtrados.slice((pagina - 1) * porPagina, pagina * porPagina).map((e) => ({
            codigo: e.codigo,
            nombre: e.nombre,
            grupo: e.grupo,
            ...(lista === 'ingresaron' ? { hora: e.ingreso, expulsado: e.expulsado } : {}),
        })),
        meta: { total: filtrados.length, pagina, por_pagina: porPagina },
        ...cambios,
    };
}

const INCIDENCIA = {
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
};

const RUTA = '/examenes/1/aulas/31/asistencia';
let alumnos;
let reportadas;
let rutas;

const docente = (ruta = '/en-curso/aula?examen=1&aula=31') =>
    renderConSesion(<Asistencia />, { usuario: usuarioDePrueba('Docente'), ruta });
const auxiliar = (ruta = '/mi-aula') =>
    renderConSesion(<Asistencia />, { usuario: usuarioDePrueba('Auxiliar'), ruta });
const consultas = (ruta = RUTA) =>
    api.get.mock.calls.filter(([url]) => url === ruta).map(([, config]) => config.params);
const dato = (etiqueta) => screen.getByText(etiqueta, { selector: 'span.text-xs' }).parentElement;
const filaDe = (nombre) => screen.getByText(nombre).closest('li');
const escrituras = () =>
    ['post', 'put', 'patch', 'delete'].reduce((s, m) => s + api[m].mock.calls.length, 0);

beforeEach(() => {
    alumnos = estudiantes();
    reportadas = [INCIDENCIA];
    rutas = {
        'GET /en-curso': { data: [examenDeHoy(), EXAMEN_PROXIMO] },
        'GET /examenes/1/aulas/31/asistencia': ({ params }) => asistencia(alumnos, params),
        'GET /examenes/1/aulas/32/asistencia': ({ params }) =>
            asistencia(alumnos.slice(0, 30), params, '691B'),
        'GET /examenes/1/aulas/35/asistencia': ({ params }) =>
            asistencia(alumnos.slice(0, 40), params, '691E'),
        'GET /examenes/*/incidencias': () => ({
            data: reportadas,
            meta: {
                sin_atender: reportadas.filter((i) => !i.atendida).length,
                todas: reportadas.length,
            },
        }),
        'POST /incidencias/*/atencion': () => {
            reportadas = reportadas.map((i) => ({
                ...i,
                atendida: true,
                atendida_por: 'Blanco Coca Leticia',
                atendida_en: '2026-10-09T16:07:40-04:00',
            }));
            return { message: 'Incidencia atendida.' };
        },
        'POST /examenes/1/incidencias': { data: {}, message: 'Incidencia registrada.' },
    };
    simularApi(api, rutas);
});

afterEach(() => {
    vi.useRealTimers();
});

describe('Asistencia (docente, /en-curso/aula)', () => {
    it('muestra el aula pedida con cuántos ingresaron y cuántos faltan', async () => {
        docente();

        expect(await screen.findByRole('heading', { name: 'Aula 691A' })).toBeVisible();
        expect(await screen.findByText('Apellido23, Nombre')).toBeVisible();
        expect(
            screen.getByText('Primer parcial · Introducción a la Programación · 15:59')
        ).toBeVisible();
        expect(dato('Ingresaron')).toHaveTextContent('22');
        expect(dato('Faltan')).toHaveTextContent('28');
        expect(dato('Habilitados')).toHaveTextContent('50');
        expect(screen.getByRole('link', { name: /En curso/ })).toHaveAttribute(
            'href',
            '/en-curso?examen=1'
        );
        expect(consultas()[0]).toEqual({ lista: 'faltan', pagina: 1, por_pagina: 25 });
    });

    it('un aula pedida que no es de la cuenta se avisa en vez de cambiarla en silencio', async () => {
        docente('/en-curso/aula?examen=1&aula=9999');
        expect(await screen.findByText(/Aula pedida no disponible/)).toBeInTheDocument();
    });

    it('da la condición de cada estudiante: sin ingreso, presente o expulsado', async () => {
        docente();
        await screen.findByText('Apellido23, Nombre');
        // Con el ingreso abierto, quien falta todavía no es ausente.
        expect(within(filaDe('Apellido23, Nombre')).getByText('Sin ingreso')).toBeVisible();
        expect(within(filaDe('Apellido23, Nombre')).getByText('202100023')).toBeVisible();

        fireEvent.click(screen.getByRole('button', { name: 'Ingresaron22' }));

        await screen.findByText('Apellido01, Nombre');
        expect(consultas().at(-1)).toMatchObject({ lista: 'ingresaron', pagina: 1 });
        expect(within(filaDe('Apellido01, Nombre')).getByText('Presente')).toBeVisible();
        expect(within(filaDe('Apellido01, Nombre')).getByText('16:00')).toBeVisible();
        expect(within(filaDe('Apellido03, Nombre')).getByText('Expulsado')).toBeVisible();
    });

    it('busca en el servidor y vuelve a la primera página', async () => {
        docente();
        await screen.findByText('Apellido23, Nombre');
        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        await screen.findByText('Apellido48, Nombre');
        expect(consultas().at(-1)).toMatchObject({ pagina: 2 });

        fireEvent.change(screen.getByLabelText('Buscar estudiante'), {
            target: { value: '202100049' },
        });

        await waitFor(() =>
            expect(consultas().at(-1)).toEqual({
                lista: 'faltan',
                buscar: '202100049',
                pagina: 1,
                por_pagina: 25,
            })
        );
        expect(await screen.findByText('Apellido49, Nombre')).toBeVisible();
        await waitFor(() => expect(screen.queryByText('Apellido48, Nombre')).toBeNull());
        expect(dato('Faltan')).toHaveTextContent('28');
    });

    it('busca por nombre, código o documento', async () => {
        docente();
        await screen.findByText('Apellido23, Nombre');

        const campo = screen.getByPlaceholderText('Nombre, código o documento');
        fireEvent.change(campo, { target: { value: '7000049' } });

        await waitFor(() => expect(consultas().at(-1)).toMatchObject({ buscar: '7000049' }));
        expect(await screen.findByText('Apellido49, Nombre')).toBeVisible();
    });

    it('sin coincidencias lo dice', async () => {
        docente();
        await screen.findByText('Apellido23, Nombre');

        fireEvent.change(screen.getByLabelText('Buscar estudiante'), {
            target: { value: 'zzz' },
        });

        expect(await screen.findByText('Sin resultados')).toBeVisible();
    });

    it('se actualiza sin recargar: vuelve a pedir la lista cada 15 s', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        docente();
        await screen.findByText('Apellido23, Nombre');
        expect(consultas()).toHaveLength(1);

        alumnos = alumnos.map((e, i) => (i === 22 ? { ...e, ingreso: '16:08:02' } : e));
        await act(async () => vi.advanceTimersByTimeAsync(15000));

        expect(consultas()).toHaveLength(2);
        expect(screen.queryByText('Apellido23, Nombre')).toBeNull();
        expect(dato('Ingresaron')).toHaveTextContent('23');
        expect(dato('Faltan')).toHaveTextContent('27');
    });

    it('muestra las incidencias del aula y el docente las atiende', async () => {
        docente();
        const tarjeta = (await screen.findByRole('heading', { name: 'Incidencias · 1' })).closest(
            'section'
        );
        expect(within(tarjeta).getByText('Faltan pupitres en dos filas.')).toBeVisible();
        expect(
            api.get.mock.calls.find(([url]) => url === '/examenes/1/incidencias')[1].params
        ).toEqual({ aula: 31 });

        fireEvent.click(within(tarjeta).getByRole('button', { name: 'Atender' }));
        fireEvent.click(within(tarjeta).getByRole('button', { name: 'Confirmar' }));

        expect(await screen.findByText('Incidencia atendida')).toBeVisible();
        expect(api.post).toHaveBeenCalledWith('/incidencias/5/atencion');
        // Atendida sale de «Sin atender»; queda en «Todas».
        await waitFor(() =>
            expect(within(tarjeta).queryByText('Faltan pupitres en dos filas.')).toBeNull()
        );
        fireEvent.click(within(tarjeta).getByRole('button', { name: /^Todas/ }));
        expect(await within(tarjeta).findByText('Atendida')).toBeVisible();
        expect(within(tarjeta).getByText('Blanco Coca Leticia · 9 oct 2026, 16:07')).toBeVisible();
        expect(within(tarjeta).queryByRole('button', { name: 'Atender' })).toBeNull();
    });

    it('es de consulta: recorrer la lista no escribe nada', async () => {
        docente();
        await screen.findByText('Apellido23, Nombre');

        fireEvent.click(screen.getByRole('button', { name: 'Ingresaron22' }));
        await screen.findByText('Apellido01, Nombre');
        fireEvent.click(screen.getByRole('button', { name: 'Faltan28' }));
        await screen.findByText('Apellido23, Nombre');

        expect(escrituras()).toBe(0);
        expect(screen.queryByRole('checkbox')).toBeNull();
    });

    it('pasa a otra aula del mismo examen', async () => {
        docente();
        await screen.findByText('Apellido23, Nombre');
        const aulas = screen.getByRole('group', { name: 'Aula' });
        expect(
            within(aulas)
                .getAllByRole('button')
                .map((b) => b.textContent)
        ).toEqual(['691B', '691A', '691E']);

        fireEvent.click(within(aulas).getByRole('button', { name: '691B' }));

        expect(await screen.findByRole('heading', { name: 'Aula 691B' })).toBeVisible();
        await waitFor(() => expect(dato('Habilitados')).toHaveTextContent('30'));
        expect(screen.getByRole('link', { name: /Controlar/ })).toHaveAttribute(
            'href',
            '/control?aula=32'
        );
    });

    it('si la lista no carga ofrece reintentar', async () => {
        rutas[`GET ${RUTA}`] = errorHttp(500);
        simularApi(api, rutas);
        docente();

        expect(await screen.findByText('No se pudo cargar')).toBeVisible();
        expect(screen.getByRole('button', { name: 'Reintentar' })).toBeVisible();
    });
});

describe('Asistencia (auxiliar, /mi-aula)', () => {
    beforeEach(() => {
        rutas['GET /en-curso'] = { data: [examenDeHoy([{ aula_id: 35, nombre: '691E' }])] };
        simularApi(api, rutas);
    });

    it('abre el aula que atiende, sin volver ni selector', async () => {
        auxiliar();

        expect(await screen.findByRole('heading', { name: 'Mi aula · 691E' })).toBeVisible();
        await waitFor(() => expect(dato('Habilitados')).toHaveTextContent('40'));
        expect(screen.queryByRole('link', { name: /En curso/ })).toBeNull();
        expect(screen.queryByRole('group', { name: 'Aula' })).toBeNull();
        expect(screen.getByRole('link', { name: /Controlar/ })).toHaveAttribute(
            'href',
            '/ingreso?aula=35'
        );
    });

    it('ve las incidencias de su aula pero no las atiende', async () => {
        auxiliar();

        const tarjeta = (await screen.findByRole('heading', { name: 'Incidencias · 1' })).closest(
            'section'
        );
        expect(
            api.get.mock.calls.find(([url]) => url === '/examenes/1/incidencias')[1].params
        ).toEqual({ aula: 35 });
        expect(within(tarjeta).getAllByText('Sin atender')).not.toHaveLength(0);
        expect(within(tarjeta).queryByRole('button', { name: 'Atender' })).toBeNull();
    });

    it('reporta una incidencia de su aula', async () => {
        auxiliar();
        await screen.findByRole('heading', { name: 'Incidencias · 1' });

        fireEvent.click(screen.getByRole('button', { name: 'Incidencia' }));
        const dialogo = screen.getByRole('dialog');
        expect(within(dialogo).getByText('Aula 691E · 16:07 · Mamani Torrez Diego')).toBeVisible();
        fireEvent.change(within(dialogo).getByLabelText(/Tipo/), {
            target: { value: 'MATERIAL_NO_PERMITIDO' },
        });
        fireEvent.change(within(dialogo).getByLabelText(/Detalle/), {
            target: { value: 'Estudiante con apuntes' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Registrar' }));

        expect(await screen.findByText('Incidencia registrada')).toBeVisible();
        expect(api.post).toHaveBeenCalledWith(
            '/examenes/1/incidencias',
            expect.objectContaining({ aula_id: 35, detalle: 'Estudiante con apuntes' })
        );
    });

    it('sin un examen abierto en sus aulas lo dice', async () => {
        rutas['GET /en-curso'] = { data: [EXAMEN_PROXIMO] };
        simularApi(api, rutas);
        auxiliar();

        expect(await screen.findByText('Ningún examen abierto en tus aulas')).toBeVisible();
        expect(screen.getByRole('heading', { name: 'Lista del aula' })).toBeVisible();
    });

    it('con varias aulas abiertas deja elegir cuál seguir; las de otro día no aparecen', async () => {
        rutas['GET /en-curso'] = {
            data: [
                examenDeHoy([
                    { aula_id: 35, nombre: '691E' },
                    { aula_id: 31, nombre: '691A' },
                ]),
                { ...EXAMEN_PROXIMO, aulas: [{ aula_id: 58, nombre: 'INFLAB' }] },
            ],
        };
        simularApi(api, rutas);
        auxiliar();
        await screen.findByRole('heading', { name: 'Mi aula · 691E' });

        expect(screen.queryByRole('button', { name: /INFLAB/ })).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: '691A' }));

        expect(await screen.findByRole('heading', { name: 'Mi aula · 691A' })).toBeVisible();
    });
});
