import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Oferta, { ordenarNiveles } from './Oferta';
import { api } from '../../api/cliente';
import RutaProtegida from '../../sesion/RutaProtegida';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const VIGENTES = {
    data: [
        { id: 1, codigo: '2/2026', tipo: 'Semestre 2' },
        { id: 2, codigo: '0/2026', tipo: 'Anual' },
    ],
    principal: '2/2026',
};

const PERIODOS = {
    data: [
        { id: 1, codigo: '2/2026', tipo: 'Semestre 2', estado: 'Vigente' },
        { id: 2, codigo: '0/2026', tipo: 'Anual', estado: 'Vigente' },
        { id: 3, codigo: '1/2026', tipo: 'Semestre 1', estado: 'Cerrado' },
    ],
    meta: { total: 3, pagina: 1, por_pagina: 100 },
};

const CARRERAS = {
    fcyt: [
        {
            id: 15,
            codigo: '411702',
            nombre: 'Licenciatura en Ingenieria de Sistemas',
            regimen: 'Semestral',
        },
        {
            id: 2,
            codigo: '134111',
            nombre: 'Licenciatura en Ingenieria Informatica',
            regimen: 'Semestral',
        },
    ],
    fach: [{ id: 36, codigo: '122081', nombre: 'Lic. en Diseño Grafico', regimen: 'Anual' }],
};

const ASIGNATURAS = [
    {
        id: 17,
        codigo: '2008019',
        nombre: 'Algebra I',
        nivel: 'Semestre 1',
        grupos: 17,
        inscritos: 0,
    },
    {
        id: 20,
        codigo: '2010010',
        nombre: 'Introduccion a la Programacion',
        nivel: 'Semestre 1',
        grupos: 11,
        inscritos: 1260,
    },
    { id: 31, codigo: '2010200', nombre: 'Taller de Grado', nivel: null, grupos: 1, inscritos: 1 },
];

const GRUPOS = [
    {
        id: 501,
        codigo: '1',
        docente: 'Blanco Coca Leticia',
        horarios: [
            { dia: 'LU', hora: '08:15-09:45', aula: '691B' },
            { dia: 'MI', hora: '14:15-15:45', aula: null },
        ],
        inscritos: 73,
    },
    { id: 502, codigo: '2', docente: null, horarios: [], inscritos: 0 },
];

const NIVELES = ['Semestre 1', 'Semestre 10', 'Semestre 2'];

const asignaturas = ({ params = {} }) => ({
    data: ASIGNATURAS,
    meta: {
        total: 63,
        pagina: params.pagina ?? 1,
        por_pagina: params.por_pagina ?? 15,
        total_grupos: 1168,
    },
});

const grupos = ({ params = {} }) => ({
    data: GRUPOS,
    meta: { total: 11, pagina: params.pagina ?? 1, por_pagina: params.por_pagina ?? 10 },
});

const rutas = (cambios = {}) => ({
    'GET /periodos/vigentes': VIGENTES,
    'GET /periodos': PERIODOS,
    'GET /oferta/carreras': ({ params }) => ({ data: CARRERAS[params.facultad] ?? [] }),
    'GET /oferta/asignaturas': asignaturas,
    'GET /oferta/asignaturas/*/grupos': grupos,
    'GET /oferta/niveles': { data: NIVELES },
    ...cambios,
});

const montar = (opciones = {}) =>
    renderConSesion(<Oferta />, {
        usuario: usuarioDePrueba('Administrador'),
        ruta: '/oferta',
        ...opciones,
    });

const pedidos = (ruta) =>
    api.get.mock.calls.filter(([url]) => url === ruta).map(([, c]) => c.params);
const ultimo = (ruta = '/oferta/asignaturas') => pedidos(ruta).at(-1);
const fila = (nombre) => screen.findByRole('button', { name: new RegExp(nombre) });

beforeEach(() => {
    simularApi(api, rutas());
});

describe('Oferta', () => {
    it('desde el período llega filtrada a las asignaturas con grupos sin lista', async () => {
        montar({ ruta: '/oferta?sin_lista=1' });
        await waitFor(() => expect(ultimo()).toMatchObject({ sin_lista: 1 }));
        const boton = screen.getByRole('button', { name: 'Con grupos sin lista' });
        expect(boton).toHaveAttribute('aria-pressed', 'true');

        fireEvent.click(boton);
        await waitFor(() => expect(ultimo()).not.toHaveProperty('sin_lista'));
    });

    it('abre en la primera facultad y lista sus asignaturas de a 15', async () => {
        montar();

        const algebra = await fila('Algebra I');
        expect(algebra).toHaveTextContent('2008019 · Semestre 1');
        expect(algebra).toHaveTextContent('17 grupos');
        expect(algebra).toHaveTextContent('0 inscritos');
        expect(await fila('Introduccion a la Programacion')).toHaveTextContent('1.260 inscritos');
        // Sin nivel en el plan solo va el código; el singular concuerda.
        const taller = await fila('Taller de Grado');
        expect(taller).toHaveTextContent('2010200');
        expect(taller).toHaveTextContent('1 grupo1 inscrito');

        expect(pedidos('/oferta/asignaturas')[0]).toEqual({
            facultad: 'fcyt',
            pagina: 1,
            por_pagina: 15,
        });
        expect(pedidos('/oferta/carreras')[0]).toEqual({ facultad: 'fcyt' });
        expect(screen.getByLabelText('Facultad')).toHaveValue('fcyt');
        expect(screen.getByText('1.168 grupos')).toBeInTheDocument();
        expect(screen.getByRole('navigation', { name: 'Paginación' })).toHaveTextContent(
            '1–15 de 63 asignaturas'
        );
    });

    it('rotula el período, la facultad y la carrera; es de solo lectura', async () => {
        montar();
        await fila('Algebra I');

        expect(screen.getByRole('heading', { level: 1, name: 'Oferta' })).toBeInTheDocument();
        expect(screen.getByText('Importado · solo lectura')).toBeInTheDocument();
        expect(await screen.findByText('Períodos 2/2026 y 0/2026 · FCyT')).toBeInTheDocument();

        fireEvent.change(screen.getByLabelText('Carrera'), { target: { value: '15' } });
        expect(
            await screen.findByText(
                'Período 2/2026 · FCyT · Licenciatura en Ingenieria de Sistemas'
            )
        ).toBeInTheDocument();

        expect(api.post).not.toHaveBeenCalled();
        expect(api.put).not.toHaveBeenCalled();
        expect(api.delete).not.toHaveBeenCalled();
    });

    it('una carrera anual se rotula con el período anual', async () => {
        montar();
        await fila('Algebra I');

        fireEvent.change(screen.getByLabelText('Facultad'), { target: { value: 'fach' } });
        const carrera = screen.getByLabelText('Carrera');
        await waitFor(() => expect(within(carrera).getAllByRole('option')).toHaveLength(2));
        fireEvent.change(carrera, { target: { value: '36' } });

        expect(
            await screen.findByText('Período 0/2026 · FACH · Lic. en Diseño Grafico')
        ).toBeInTheDocument();
    });

    it('consulta un período cerrado, también en los grupos', async () => {
        montar();
        await fila('Algebra I');

        const periodo = screen.getByLabelText('Período');
        await waitFor(() => expect(within(periodo).getAllByRole('option')).toHaveLength(4));
        expect(
            within(periodo)
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual(['Vigentes', '2/2026', '0/2026', '1/2026 · Cerrado']);
        expect(pedidos('/periodos')[0]).toEqual({ por_pagina: 100 });

        fireEvent.change(periodo, { target: { value: '3' } });
        await waitFor(() =>
            expect(ultimo()).toEqual({ periodo: '3', facultad: 'fcyt', pagina: 1, por_pagina: 15 })
        );
        expect(await screen.findByText('Período 1/2026 · FCyT')).toBeInTheDocument();

        fireEvent.click(await fila('Algebra I'));
        await screen.findByRole('table');
        expect(pedidos('/oferta/asignaturas/17/grupos')[0]).toEqual({
            periodo: '3',
            facultad: 'fcyt',
            pagina: 1,
            por_pagina: 10,
        });
    });

    it('filtra por carrera y nivel en el servidor', async () => {
        montar();
        await fila('Algebra I');

        const carrera = screen.getByLabelText('Carrera');
        await waitFor(() => expect(within(carrera).getAllByRole('option')).toHaveLength(3));
        expect(within(carrera).getAllByRole('option')[0]).toHaveTextContent('Todas');

        // Los niveles se muestran por orden del plan, no alfabético.
        const nivel = screen.getByLabelText('Nivel');
        expect(
            within(nivel)
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual(['Todos', 'Semestre 1', 'Semestre 2', 'Semestre 10']);

        fireEvent.change(nivel, { target: { value: 'Semestre 2' } });
        await waitFor(() => expect(ultimo()).toMatchObject({ nivel: 'Semestre 2', pagina: 1 }));

        // Cambiar de carrera suelta el nivel: cada plan tiene los suyos.
        fireEvent.change(carrera, { target: { value: '15' } });
        await waitFor(() =>
            expect(ultimo()).toEqual({ facultad: 'fcyt', carrera: '15', pagina: 1, por_pagina: 15 })
        );
    });

    it('cambiar de facultad pide sus carreras y suelta carrera y nivel', async () => {
        montar();
        await fila('Algebra I');

        fireEvent.change(screen.getByLabelText('Carrera'), { target: { value: '15' } });
        await waitFor(() => expect(ultimo()).toMatchObject({ carrera: '15' }));

        fireEvent.change(screen.getByLabelText('Facultad'), { target: { value: 'fach' } });
        await waitFor(() =>
            expect(ultimo()).toEqual({ facultad: 'fach', pagina: 1, por_pagina: 15 })
        );
        expect(pedidos('/oferta/carreras').at(-1)).toEqual({ facultad: 'fach' });
        expect(screen.getByLabelText('Carrera')).toHaveValue('');
    });

    it('busca por asignatura, código o docente', async () => {
        montar();
        await fila('Algebra I');

        fireEvent.change(screen.getByLabelText('Buscar'), { target: { value: ' blanco ' } });
        await waitFor(() => expect(ultimo()).toMatchObject({ buscar: 'blanco', pagina: 1 }));
    });

    it('pagina las asignaturas en el servidor', async () => {
        montar();
        const pie = await screen.findByRole('navigation', { name: 'Paginación' });

        fireEvent.click(within(pie).getByRole('button', { name: 'Página siguiente' }));
        await waitFor(() => expect(ultimo()).toMatchObject({ pagina: 2, por_pagina: 15 }));
    });

    it('al abrir una asignatura pide sus grupos, con docente, horario, aula e inscritos', async () => {
        montar();
        const boton = await fila('Introduccion a la Programacion');
        expect(boton).toHaveAttribute('aria-expanded', 'false');

        fireEvent.click(boton);
        expect(boton).toHaveAttribute('aria-expanded', 'true');

        const tabla = await screen.findByRole('table');
        expect(pedidos('/oferta/asignaturas/20/grupos')[0]).toEqual({
            facultad: 'fcyt',
            pagina: 1,
            por_pagina: 10,
        });
        const filas = within(tabla).getAllByRole('row');
        expect(filas).toHaveLength(3);
        expect(filas[1]).toHaveTextContent('Blanco Coca Leticia');
        expect(filas[1]).toHaveTextContent('LU 08:15-09:45 · 691B');
        expect(filas[1]).toHaveTextContent('MI 14:15-15:45');
        expect(filas[1]).toHaveTextContent('73');
        expect(filas[2]).toHaveTextContent('Por designar');

        // Los grupos tienen su propia paginación, de a diez.
        const pies = screen.getAllByRole('navigation', { name: 'Paginación' });
        expect(pies[0]).toHaveTextContent('1–10 de 11 grupos');
        fireEvent.click(within(pies[0]).getByRole('button', { name: 'Página siguiente' }));
        await waitFor(() =>
            expect(pedidos('/oferta/asignaturas/20/grupos').at(-1)).toMatchObject({ pagina: 2 })
        );

        fireEvent.click(boton);
        expect(screen.queryByRole('table')).toBeNull();
    });

    it('la búsqueda viaja también a los grupos de la asignatura abierta', async () => {
        montar();
        fireEvent.click(await fila('Algebra I'));
        await screen.findByRole('table');

        fireEvent.change(screen.getByLabelText('Buscar'), { target: { value: 'blanco' } });
        await waitFor(() =>
            expect(pedidos('/oferta/asignaturas/17/grupos').at(-1)).toEqual({
                facultad: 'fcyt',
                buscar: 'blanco',
                pagina: 1,
                por_pagina: 10,
            })
        );
    });

    it('con pocos grupos no dibuja su paginación', async () => {
        simularApi(
            api,
            rutas({
                'GET /oferta/asignaturas/*/grupos': {
                    data: GRUPOS,
                    meta: { total: 2, pagina: 1, por_pagina: 10 },
                },
            })
        );
        montar();
        fireEvent.click(await fila('Algebra I'));
        await screen.findByRole('table');

        expect(screen.getAllByRole('navigation', { name: 'Paginación' })).toHaveLength(1);
    });

    it('si los grupos no cargan ofrece reintentar sin perder la lista', async () => {
        simularApi(api, rutas({ 'GET /oferta/asignaturas/*/grupos': errorHttp(500) }));
        montar();
        fireEvent.click(await fila('Algebra I'));

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar');
        expect(screen.getByRole('button', { name: /Taller de Grado/ })).toBeInTheDocument();

        simularApi(api, rutas());
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        expect(await screen.findByRole('table')).toBeInTheDocument();
    });

    it('sin asignaturas dice «Sin resultados»', async () => {
        simularApi(
            api,
            rutas({
                'GET /oferta/asignaturas': {
                    data: [],
                    meta: { total: 0, pagina: 1, por_pagina: 15, total_grupos: 0 },
                },
            })
        );
        montar();

        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
        expect(screen.getByText('0 grupos')).toBeInTheDocument();
    });

    it('muestra el esqueleto mientras carga y espera a las facultades', () => {
        montar({ facultades: [] });

        expect(screen.getByRole('status', { name: 'Cargando' })).toBeInTheDocument();
        expect(pedidos('/oferta/asignaturas')).toHaveLength(0);
    });

    it('si falla la carga ofrece reintentar', async () => {
        simularApi(api, rutas({ 'GET /oferta/asignaturas': errorHttp(500) }));
        montar();

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar');

        simularApi(api, rutas());
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        expect(await fila('Algebra I')).toBeInTheDocument();
    });

    it('sin el permiso «periodo_oferta» la vista no se abre', async () => {
        renderConSesion(
            <RutaProtegida ruta="/oferta">
                <Oferta />
            </RutaProtegida>,
            { usuario: usuarioDePrueba('Docente'), ruta: '/oferta' }
        );

        expect(await screen.findByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
        expect(api.get).not.toHaveBeenCalled();
    });
});

describe('Oferta · correcciones de QA', () => {
    it('el buscador tiene su etiqueta asociada (BUG-10)', async () => {
        montar();
        await fila('Algebra I');

        expect(screen.getByRole('searchbox', { name: 'Buscar' })).toHaveAttribute(
            'placeholder',
            'Asignatura, código o docente'
        );
    });

    it('los grupos desplegados se vuelven a pedir al cambiar período o facultad (N-02)', async () => {
        montar();
        fireEvent.click(await fila('Algebra I'));
        await screen.findByRole('table');

        fireEvent.change(screen.getByLabelText('Período'), { target: { value: '3' } });
        await waitFor(() =>
            expect(pedidos('/oferta/asignaturas/17/grupos').at(-1)).toMatchObject({
                periodo: '3',
                pagina: 1,
            })
        );

        fireEvent.change(screen.getByLabelText('Facultad'), { target: { value: 'fach' } });
        await waitFor(() =>
            expect(pedidos('/oferta/asignaturas/17/grupos').at(-1)).toMatchObject({
                facultad: 'fach',
                periodo: '3',
            })
        );
    });

    it('el nombre del docente no se recorta y «Por designar» se advierte (N-03)', async () => {
        montar();
        fireEvent.click(await fila('Algebra I'));
        const tabla = await screen.findByRole('table');

        const docente = within(tabla).getByText('Blanco Coca Leticia');
        expect(docente.className).not.toMatch(/truncate/);
        expect(within(tabla).getByText('Por designar').className).toMatch(/text-warning/);
    });

    it('el conteo de grupos sigue al filtrado y cada fila lleva el ícono de su área', async () => {
        montar();
        await fila('Algebra I');

        expect(screen.getByText('1.168 grupos')).toBeInTheDocument();
        expect(within(await fila('Algebra I')).getByRole('img')).toBeInTheDocument();
        expect(pedidos('/oferta/niveles')[0]).toEqual({ facultad: 'fcyt' });
    });
});

describe('ordenarNiveles', () => {
    it('pone los años y los semestres en el orden del plan', () => {
        expect(
            ordenarNiveles([
                'Cuarto año',
                'Preparatorio',
                'Primer año',
                'Quinto año',
                'Segundo año',
                'Semestre 1',
                'Semestre 10',
                'Semestre 2',
                'Tercer año',
            ])
        ).toEqual([
            'Preparatorio',
            'Primer año',
            'Segundo año',
            'Tercer año',
            'Cuarto año',
            'Quinto año',
            'Semestre 1',
            'Semestre 2',
            'Semestre 10',
        ]);
    });
});
