import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Bitacora from './Bitacora';
import { api } from '../../api/cliente';
import RutaProtegida from '../../sesion/RutaProtegida';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const FILAS = [
    {
        id: 991,
        usuario: 'Blanco Coca Leticia',
        rol: 'Docente',
        operacion: 'habilitacion.inhabilitar',
        operacion_texto: 'Inhabilitó al estudiante',
        entidad: 'Habilitación #19 · Introducción a la Programación',
        fecha: '2026-10-11T16:40:00-04:00',
    },
    {
        id: 990,
        usuario: 'Administración académica',
        rol: 'Administrador',
        operacion: 'sesion.iniciar',
        operacion_texto: 'Inició sesión',
        entidad: null,
        fecha: '2026-10-10T09:14:00-04:00',
    },
    {
        id: 989,
        usuario: 'Sistema',
        rol: null,
        operacion: 'respaldo.generar',
        operacion_texto: 'Generó el respaldo',
        entidad: 'respaldo-20261010-030000.sql',
        fecha: '2026-10-10T03:00:00-04:00',
    },
    {
        id: 988,
        usuario: 'Mamani Torrez Diego',
        rol: 'Auxiliar',
        operacion: 'puerta.abrir',
        operacion_texto: 'puerta.abrir',
        entidad: 'Aula 691E',
        fecha: '2026-10-09T08:02:00-04:00',
    },
];

const OPERACIONES = [
    { clave: 'respaldo.generar', texto: 'Generó el respaldo' },
    { clave: 'habilitacion.inhabilitar', texto: 'Inhabilitó al estudiante' },
    { clave: 'sesion.iniciar', texto: 'Inició sesión' },
    { clave: 'puerta.abrir', texto: 'puerta.abrir' },
];

const ROLES = {
    data: [
        { id: 1, nombre: 'Administrador', es_sistema: true },
        { id: 2, nombre: 'Docente', es_sistema: true },
        { id: 3, nombre: 'Auxiliar', es_sistema: true },
        { id: 9, nombre: 'Coordinación', es_sistema: false },
    ],
};

const pagina = (filas = FILAS, total = filas.length, cambios = {}) => ({
    data: filas,
    meta: {
        total,
        pagina: 1,
        por_pagina: 25,
        operaciones: OPERACIONES,
        fecha_minima: '2026-08-03',
        ...cambios,
    },
});

const montar = (usuario = usuarioDePrueba('Administrador')) =>
    renderConSesion(<Bitacora />, { usuario, ruta: '/bitacora' });

const consultas = () =>
    api.get.mock.calls.filter(([url]) => url === '/bitacora').map(([, c]) => c.params);
const ultima = () => consultas().at(-1);

beforeEach(() => {
    simularApi(api, { 'GET /bitacora': pagina(), 'GET /roles': ROLES });
});

describe('Bitacora', () => {
    it('lista las operaciones con el texto que entrega el servidor', async () => {
        montar();

        const filas = within(await screen.findByRole('list')).getAllByRole('listitem');
        expect(filas).toHaveLength(4);
        expect(filas[0]).toHaveTextContent('Blanco Coca Leticia · Inhabilitó al estudiante');
        expect(filas[0]).toHaveTextContent('Habilitación #19 · Introducción a la Programación');
        expect(filas[0]).toHaveTextContent('Docente · 11 oct 2026, 16:40');
        expect(consultas()[0]).toEqual({ pagina: 1, por_pagina: 25 });
        expect(screen.getByRole('heading', { level: 1, name: 'Bitácora' })).toBeInTheDocument();
        expect(screen.getByText('Solo lectura')).toBeInTheDocument();
        expect(screen.getByRole('navigation', { name: 'Paginación' })).toHaveTextContent(
            '4 operaciones'
        );
    });

    it('una operación sin registro ni rol se muestra sin esos datos', async () => {
        montar();

        const filas = within(await screen.findByRole('list')).getAllByRole('listitem');
        expect(filas[1]).toHaveTextContent('Administración académica · Inició sesión');
        expect(filas[1].querySelectorAll('p')).toHaveLength(2);
        expect(filas[2]).toHaveTextContent('Sistema · Generó el respaldo');
        expect(filas[2]).toHaveTextContent(
            /^Sistema · Generó el respaldo.*\.sql10 oct 2026, 03:00$/
        );
        // Una clave que el servidor no rotula llega con la clave como texto.
        expect(filas[3]).toHaveTextContent('Mamani Torrez Diego · puerta.abrir');
    });

    it('es de solo lectura: no ofrece ninguna acción sobre las filas', async () => {
        montar();
        const lista = await screen.findByRole('list');

        expect(within(lista).queryByRole('button')).toBeNull();
        expect(within(lista).queryByRole('link')).toBeNull();
        expect(api.post).not.toHaveBeenCalled();
        expect(api.put).not.toHaveBeenCalled();
        expect(api.delete).not.toHaveBeenCalled();
    });

    it('las operaciones del filtro salen de la respuesta, con su texto', async () => {
        montar();
        await screen.findByRole('list');

        const opciones = within(screen.getByLabelText('Operación')).getAllByRole('option');
        expect(opciones.map((o) => o.textContent)).toEqual([
            'Todas',
            'Generó el respaldo',
            'Inhabilitó al estudiante',
            'Inició sesión',
            'puerta.abrir',
        ]);

        fireEvent.change(screen.getByLabelText('Operación'), {
            target: { value: 'sesion.iniciar' },
        });
        await waitFor(() =>
            expect(ultima()).toEqual({ operacion: 'sesion.iniciar', pagina: 1, por_pagina: 25 })
        );
    });

    it('filtra por rol, también por los roles creados', async () => {
        montar();
        await screen.findByRole('list');

        const rol = screen.getByLabelText('Rol');
        await waitFor(() =>
            expect(within(rol).getByRole('option', { name: 'Coordinación' })).toBeInTheDocument()
        );
        expect(within(rol).getAllByRole('option')).toHaveLength(5);

        fireEvent.change(rol, { target: { value: 'Coordinación' } });
        await waitFor(() => expect(ultima()).toMatchObject({ rol: 'Coordinación', pagina: 1 }));
    });

    it('sin permiso sobre los roles ofrece los tres de inicio y no los pide', async () => {
        montar(usuarioDePrueba('Docente', { rol: 'Auditoría', permisos: ['bitacora'] }));
        await screen.findByRole('list');

        const opciones = within(screen.getByLabelText('Rol')).getAllByRole('option');
        expect(opciones.map((o) => o.textContent)).toEqual([
            'Todos',
            'Administrador',
            'Docente',
            'Auxiliar',
        ]);
        expect(api.get.mock.calls.some(([url]) => url === '/roles')).toBe(false);
    });

    it('filtra por rango de fechas, acotado entre el primer asiento y hoy', async () => {
        montar();
        await screen.findByRole('list');

        const desde = screen.getByLabelText('Desde');
        const hasta = screen.getByLabelText('Hasta');
        expect(desde).toHaveAttribute('min', '2026-08-03');
        expect(hasta).toHaveAttribute('max', expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/));

        fireEvent.change(desde, { target: { value: '2026-10-01' } });
        fireEvent.change(hasta, { target: { value: '2026-10-12' } });

        await waitFor(() =>
            expect(ultima()).toEqual({
                desde: '2026-10-01',
                hasta: '2026-10-12',
                pagina: 1,
                por_pagina: 25,
            })
        );
        expect(hasta).toHaveAttribute('min', '2026-10-01');
        expect(desde).toHaveAttribute('max', '2026-10-12');
    });

    it('busca por usuario o registro', async () => {
        montar();
        await screen.findByRole('list');

        fireEvent.change(screen.getByLabelText('Buscar'), { target: { value: ' leticia ' } });
        await waitFor(() => expect(ultima()).toMatchObject({ buscar: 'leticia', pagina: 1 }));
    });

    it('pagina en el servidor y un filtro vuelve a la primera página', async () => {
        simularApi(api, {
            'GET /bitacora': ({ params }) =>
                pagina(FILAS, 1503, { pagina: params.pagina, por_pagina: params.por_pagina }),
            'GET /roles': ROLES,
        });
        montar();

        const pie = await screen.findByRole('navigation', { name: 'Paginación' });
        expect(pie).toHaveTextContent('1–25 de 1.503 operaciones');

        fireEvent.click(within(pie).getByRole('button', { name: /siguiente/i }));
        await waitFor(() => expect(ultima()).toMatchObject({ pagina: 2 }));
        await waitFor(() => expect(pie).toHaveTextContent('26–50 de 1.503 operaciones'));

        fireEvent.change(screen.getByLabelText('Rol'), { target: { value: 'Docente' } });
        await waitFor(() => expect(ultima()).toMatchObject({ rol: 'Docente', pagina: 1 }));
    });

    it('sin asientos dice «Sin resultados»', async () => {
        simularApi(api, { 'GET /bitacora': pagina([]), 'GET /roles': ROLES });
        montar();

        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
        expect(screen.queryByRole('navigation', { name: 'Paginación' })).toBeNull();
    });

    it('muestra el esqueleto mientras carga', () => {
        montar();
        expect(screen.getByRole('status', { name: 'Cargando' })).toBeInTheDocument();
    });

    it('si falla la carga ofrece reintentar', async () => {
        simularApi(api, { 'GET /bitacora': errorHttp(500), 'GET /roles': ROLES });
        montar();

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar');

        simularApi(api, { 'GET /bitacora': pagina(), 'GET /roles': ROLES });
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        expect(await screen.findByRole('list')).toBeInTheDocument();
    });

    it('marca el filtro que el servidor rechaza y conserva las opciones', async () => {
        const mensaje = 'La fecha final no puede ser anterior a la inicial.';
        simularApi(api, {
            'GET /bitacora': ({ params }) =>
                params.hasta
                    ? errorHttp(422, { message: mensaje, errors: { hasta: [mensaje] } })
                    : pagina(),
            'GET /roles': ROLES,
        });
        montar();
        await screen.findByRole('list');

        fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-10-09' } });
        fireEvent.change(screen.getByLabelText('Hasta'), { target: { value: '2026-10-01' } });

        expect(await screen.findByRole('alert')).toHaveTextContent('Filtros no válidos');
        expect(screen.getByText(mensaje)).toBeInTheDocument();
        expect(screen.getByLabelText(/^Hasta/)).toHaveAttribute('aria-invalid', 'true');
        expect(within(screen.getByLabelText('Operación')).getAllByRole('option')).toHaveLength(5);

        fireEvent.change(screen.getByLabelText(/^Hasta/), { target: { value: '' } });
        expect(await screen.findByRole('list')).toBeInTheDocument();
    });

    it('sin el permiso «bitacora» la vista no se abre', async () => {
        renderConSesion(
            <RutaProtegida ruta="/bitacora">
                <Bitacora />
            </RutaProtegida>,
            { usuario: usuarioDePrueba('Docente'), ruta: '/bitacora' }
        );

        expect(await screen.findByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
        expect(api.get).not.toHaveBeenCalled();
    });
});
