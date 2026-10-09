import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Habilitacion from './Habilitacion';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const EXAMENES = [
    {
        id: 1,
        asignatura: { id: 5, codigo: '2010010', nombre: 'Introducción a la Programación' },
        tipo: 'primer_parcial',
        tipo_texto: 'Primer parcial',
        fecha: '2026-10-12',
    },
    {
        id: 3,
        asignatura: { id: 9, codigo: '2010020', nombre: 'Base de Datos I' },
        tipo: 'final',
        tipo_texto: 'Examen final',
        fecha: '2026-11-20',
    },
];

const AULAS = [
    { aula_id: 31, nombre: '691A', asignados: 20 },
    { aula_id: 32, nombre: '691B', asignados: 19 },
];
const GRUPOS = [
    { id: 36, codigo: '1', propio: false },
    { id: 37, codigo: '2', propio: true },
];

// 60 inscritos: tres páginas de 25.
const inscritos = () =>
    Array.from({ length: 60 }, (_, i) => ({
        estudiante_id: i + 1,
        codigo: String(202100001 + i),
        nombre: `Apellido${String(i + 1).padStart(2, '0')}, Nombre`,
        documento: String(5000000 + i),
        grupo: i % 2 === 0 ? '1' : '2',
        estado: i === 0 ? 'no' : i < 40 ? 'habilitado' : 'pendiente',
        aula: i > 0 && i < 40 ? AULAS[i % 2].nombre : null,
        motivo: i === 0 ? 'No presentó el proyecto' : null,
    }));

// La lista como la devuelve la API (plan, 9 · B3), filtrada y paginada aquí.
function listado(todos, params = {}, { aulas = AULAS } = {}) {
    const texto = (params.buscar ?? '').toLowerCase();
    const base = todos.filter(
        (e) =>
            (!params.grupo || GRUPOS.find((g) => g.id === params.grupo)?.codigo === e.grupo) &&
            (!params.aula || aulas.find((a) => a.aula_id === params.aula)?.nombre === e.aula) &&
            (!texto || `${e.nombre} ${e.codigo} ${e.documento}`.toLowerCase().includes(texto))
    );
    const cuenta = (lista, estado) => lista.filter((e) => e.estado === estado).length;
    const filtrados = params.condicion ? base.filter((e) => e.estado === params.condicion) : base;
    const pagina = params.pagina ?? 1;
    const porPagina = params.por_pagina ?? 25;

    return {
        data: filtrados.slice((pagina - 1) * porPagina, pagina * porPagina),
        meta: {
            total: filtrados.length,
            pagina,
            por_pagina: porPagina,
            cifras: {
                inscritos: todos.length,
                habilitados: cuenta(todos, 'habilitado'),
                no_habilitados: cuenta(todos, 'no'),
                sin_revisar: cuenta(todos, 'pendiente'),
                sin_aula: todos.filter((e) => e.estado === 'habilitado' && !e.aula).length,
            },
            por_aula: aulas,
            condiciones: {
                todos: base.length,
                habilitado: cuenta(base, 'habilitado'),
                no: cuenta(base, 'no'),
                pendiente: cuenta(base, 'pendiente'),
            },
            grupos: GRUPOS,
        },
    };
}

const RUTA_LISTA = '/examenes/1/habilitaciones';
const montar = (ruta = '/habilitacion?examen=1') =>
    renderConSesion(<Habilitacion />, { usuario: usuarioDePrueba('Docente'), ruta });

// Las peticiones a la lista, con sus parámetros.
const consultas = (ruta = RUTA_LISTA) =>
    api.get.mock.calls.filter(([url]) => url === ruta).map(([, config]) => config.params);
const ultimaConsulta = (ruta) => consultas(ruta).at(-1);
const lotes = () => api.post.mock.calls.filter(([url]) => url.endsWith('/habilitaciones'));
const casillaDe = (nombre) => screen.getAllByLabelText(`Seleccionar a ${nombre}`)[0];
const casillaPagina = () => screen.getAllByRole('checkbox', { name: 'Seleccionar la página' })[0];
const dato = (etiqueta) => screen.getByText(etiqueta, { selector: 'span' }).parentElement;
const cargada = () => screen.findAllByText('Apellido01, Nombre');

let alumnos;
let rutas;

beforeEach(() => {
    alumnos = inscritos();
    rutas = {
        'GET /examenes': { data: EXAMENES, meta: { hoy: '2026-10-09' } },
        'GET /examenes/1/habilitaciones': ({ params }) => listado(alumnos, params),
        'GET /examenes/3/habilitaciones': ({ params }) =>
            listado(alumnos.slice(0, 3), params, { aulas: [] }),
        'POST /examenes/*/habilitaciones': ({ data }) => {
            const ids = data.todos ? alumnos.map((e) => e.estudiante_id) : data.estudiantes;
            alumnos = alumnos.map((e) =>
                ids.includes(e.estudiante_id)
                    ? data.habilitado
                        ? { ...e, estado: 'habilitado', motivo: null }
                        : { ...e, estado: 'no', motivo: data.motivo, aula: null }
                    : e
            );
            const palabra = data.habilitado ? 'habilitados' : 'inhabilitados';
            return { message: `${ids.length} ${palabra}.`, afectados: ids.length, cifras: {} };
        },
        'POST /examenes/*/reparto': {
            message: '12 estudiantes en 2 aulas.',
            repartidos: 12,
            por_aula: AULAS,
        },
    };
    simularApi(api, rutas);
});

describe('Habilitacion', () => {
    it('lista a los inscritos del examen de a 25, con sus seis columnas', async () => {
        montar();
        await cargada();

        expect(ultimaConsulta()).toEqual({ pagina: 1, por_pagina: 25 });
        const tabla = screen.getByRole('table');
        expect(
            within(tabla)
                .getAllByRole('columnheader')
                .map((th) => th.textContent)
                .filter(Boolean)
        ).toEqual(['Código', 'Estudiante', 'Grupo', 'Condición', 'Aula', 'Motivo']);
        expect(within(tabla).getAllByRole('row')).toHaveLength(26);

        const fila = within(tabla).getByText('Apellido01, Nombre').closest('tr');
        expect(within(fila).getByText('202100001')).toBeInTheDocument();
        expect(within(fila).getByText('No habilitado')).toBeInTheDocument();
        expect(within(fila).getByText('No presentó el proyecto')).toBeInTheDocument();
        expect(within(tabla).getByText('Apellido02, Nombre').closest('tr')).toHaveTextContent(
            '691B'
        );
        expect(screen.getByText('1–25 de 60 estudiantes')).toBeInTheDocument();
        expect(screen.getByText('Primer parcial · Introducción a la Programación')).toBeVisible();
        expect(screen.getByRole('link', { name: 'Exámenes' })).toHaveAttribute('href', '/examenes');
        expect(screen.queryByText(/códigos QR/i)).not.toBeInTheDocument();
    });

    it('muestra las cifras del examen y la distribución por aula', async () => {
        alumnos[5].aula = null;
        montar();
        await cargada();

        expect(dato('Inscritos · 2 grupos')).toHaveTextContent('60');
        expect(dato('Habilitados')).toHaveTextContent('39');
        expect(dato('No habilitados')).toHaveTextContent('1');
        expect(dato('Sin revisar')).toHaveTextContent('20');
        expect(screen.getByText('Distribución por aula · 2')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Aula 691A, 20 estudiantes' })).toBeVisible();
        expect(screen.getByRole('button', { name: 'Aula 691B, 19 estudiantes' })).toBeVisible();
        expect(screen.getByText('1 sin aula')).toBeInTheDocument();
    });

    it('pasa de página en el servidor', async () => {
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));

        expect(await screen.findAllByText('Apellido26, Nombre')).not.toHaveLength(0);
        expect(ultimaConsulta()).toEqual({ pagina: 2, por_pagina: 25 });
        expect(screen.getByText('26–50 de 60 estudiantes')).toBeInTheDocument();
    });

    it('al elegir otro examen carga su lista y limpia la selección y los filtros', async () => {
        montar();
        await cargada();
        fireEvent.click(screen.getByRole('button', { name: /^No habilitados/ }));
        await waitFor(() => expect(ultimaConsulta().condicion).toBe('no'));
        fireEvent.click(casillaDe('Apellido01, Nombre'));
        expect(screen.getAllByText('1 seleccionado')).not.toHaveLength(0);

        fireEvent.change(screen.getByRole('combobox', { name: /Examen/ }), {
            target: { value: '3' },
        });

        expect(await screen.findByText('Examen final · Base de Datos I')).toBeInTheDocument();
        await waitFor(() =>
            expect(ultimaConsulta('/examenes/3/habilitaciones')).toEqual({
                pagina: 1,
                por_pagina: 25,
            })
        );
        expect(await screen.findByText('3 estudiantes')).toBeInTheDocument();
        expect(screen.queryByText('1 seleccionado')).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/Motivo de la inhabilitación/)).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^Todos/ })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
    });

    it('busca por nombre, código o documento en el servidor', async () => {
        montar();
        await cargada();
        const buscador = screen.getByLabelText('Buscar estudiante');
        expect(buscador).toHaveAttribute('placeholder', 'Nombre, código o documento');

        fireEvent.change(buscador, { target: { value: ' 202100007 ' } });

        await waitFor(() =>
            expect(ultimaConsulta()).toEqual({ buscar: '202100007', pagina: 1, por_pagina: 25 })
        );
        expect(await screen.findByText('1 estudiante')).toBeInTheDocument();
        expect(screen.queryByText('Apellido01, Nombre')).not.toBeInTheDocument();

        fireEvent.change(buscador, { target: { value: 'zzz' } });

        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('sin inscritos muestra «Sin estudiantes»', async () => {
        alumnos = [];
        montar();

        expect(await screen.findByText('Sin estudiantes')).toBeInTheDocument();
        expect(screen.queryByText('Sin resultados')).not.toBeInTheDocument();
    });

    it('filtra por condición y cada opción lleva su conteo', async () => {
        montar();
        await cargada();

        const opcion = (nombre) => screen.getByRole('button', { name: nombre });
        expect(opcion(/^Todos/)).toHaveTextContent('Todos60');
        expect(opcion(/^Habilitados/)).toHaveTextContent('Habilitados39');
        expect(opcion(/^No habilitados/)).toHaveTextContent('No habilitados1');
        expect(opcion(/^Sin revisar/)).toHaveTextContent('Sin revisar20');

        fireEvent.click(opcion(/^Sin revisar/));

        await waitFor(() =>
            expect(ultimaConsulta()).toEqual({ condicion: 'pendiente', pagina: 1, por_pagina: 25 })
        );
        expect(await screen.findByText('20 estudiantes')).toBeInTheDocument();
        expect(opcion(/^Sin revisar/)).toHaveAttribute('aria-pressed', 'true');
        // Los conteos siguen siendo los de lo filtrado por grupo, aula y texto.
        expect(opcion(/^Todos/)).toHaveTextContent('Todos60');
    });

    it('filtra por grupo y por aula, en los selectores y pulsando el aula', async () => {
        montar();
        await cargada();

        const grupo = screen.getByRole('combobox', { name: 'Grupo' });
        expect(within(grupo).getByRole('option', { name: 'Grupo 2 · Grupo propio' })).toBeVisible();
        expect(within(grupo).getByRole('option', { name: 'Grupo 1' })).toBeVisible();

        fireEvent.change(grupo, { target: { value: '37' } });
        await waitFor(() =>
            expect(ultimaConsulta()).toEqual({ grupo: 37, pagina: 1, por_pagina: 25 })
        );
        expect(await screen.findByText('1–25 de 30 estudiantes')).toBeInTheDocument();

        const ficha = screen.getByRole('button', { name: /^Aula 691A/ });
        fireEvent.click(ficha);
        await waitFor(() =>
            expect(ultimaConsulta()).toEqual({ grupo: 37, aula: 31, pagina: 1, por_pagina: 25 })
        );
        expect(ficha).toHaveAttribute('aria-pressed', 'true');
        const aula = screen.getByRole('combobox', { name: 'Aula' });
        expect(aula).toHaveValue('31');

        fireEvent.click(ficha);
        await waitFor(() => expect(ultimaConsulta().aula).toBeUndefined());
        expect(ficha).toHaveAttribute('aria-pressed', 'false');

        fireEvent.change(aula, { target: { value: '32' } });
        await waitFor(() => expect(ultimaConsulta().aula).toBe(32));
        expect(screen.getByRole('button', { name: /^Aula 691B/ })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
    });

    it('la selección de uno y de la página sobrevive al cambio de página', async () => {
        montar();
        await cargada();
        expect(screen.queryByLabelText(/Motivo de la inhabilitación/)).not.toBeInTheDocument();

        fireEvent.click(casillaDe('Apellido03, Nombre'));
        expect(screen.getAllByText('1 seleccionado')).not.toHaveLength(0);
        expect(screen.getByLabelText(/Motivo de la inhabilitación/)).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        await screen.findAllByText('Apellido26, Nombre');
        fireEvent.click(casillaPagina());
        expect(screen.getAllByText('26 seleccionados')).not.toHaveLength(0);

        fireEvent.click(screen.getByRole('button', { name: 'Página anterior' }));
        await cargada();
        expect(casillaDe('Apellido03, Nombre')).toBeChecked();
        expect(casillaDe('Apellido04, Nombre')).not.toBeChecked();
        expect(casillaPagina()).not.toBeChecked();

        fireEvent.click(screen.getAllByRole('button', { name: 'Habilitar' })[0]);
        await waitFor(() => expect(lotes()).toHaveLength(1));
        const [, cuerpo] = lotes()[0];
        expect(cuerpo.habilitado).toBe(true);
        expect(cuerpo.todos).toBeUndefined();
        expect([...cuerpo.estudiantes].sort((a, b) => a - b)).toEqual([
            3,
            ...Array.from({ length: 25 }, (_, i) => 26 + i),
        ]);
    });

    it('«Seleccionar los N» envía todos con los filtros, sin descargar la lista', async () => {
        montar();
        await cargada();
        fireEvent.click(screen.getByRole('button', { name: /^Sin revisar/ }));
        await screen.findByText('20 estudiantes');
        fireEvent.change(screen.getByRole('combobox', { name: 'Grupo' }), {
            target: { value: '37' },
        });
        await screen.findByText('10 estudiantes');
        fireEvent.click(screen.getByRole('button', { name: /^Todos/ }));
        await screen.findByText('1–25 de 30 estudiantes');

        expect(screen.queryByRole('button', { name: /Seleccionar los/ })).not.toBeInTheDocument();
        fireEvent.click(casillaPagina());
        expect(screen.getAllByText('25 seleccionados')).not.toHaveLength(0);

        const pedidas = consultas().length;
        fireEvent.click(screen.getAllByRole('button', { name: 'Seleccionar los 30' })[0]);

        expect(screen.getAllByText('30 seleccionados')).not.toHaveLength(0);
        expect(screen.queryByRole('button', { name: /Seleccionar los/ })).not.toBeInTheDocument();
        expect(consultas()).toHaveLength(pedidas);

        fireEvent.click(screen.getAllByRole('button', { name: 'Habilitar' })[0]);

        await waitFor(() => expect(lotes()).toHaveLength(1));
        expect(lotes()[0]).toEqual([
            RUTA_LISTA,
            {
                habilitado: true,
                todos: true,
                filtros: { grupo: 37, aula: null, condicion: null, buscar: '' },
            },
        ]);
    });

    it('«Quitar» vacía la selección', async () => {
        montar();
        await cargada();
        fireEvent.click(casillaPagina());
        fireEvent.click(screen.getAllByRole('button', { name: 'Seleccionar los 60' })[0]);
        expect(screen.getAllByText('60 seleccionados')).not.toHaveLength(0);

        fireEvent.click(screen.getAllByRole('button', { name: 'Quitar' })[0]);

        expect(screen.queryByText(/seleccionado/)).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/Motivo de la inhabilitación/)).not.toBeInTheDocument();
        expect(casillaPagina()).not.toBeChecked();
        expect(casillaDe('Apellido01, Nombre')).not.toBeChecked();
    });

    it('habilita la selección, avisa cuántos fueron y actualiza la lista y las cifras', async () => {
        montar();
        await cargada();
        fireEvent.click(casillaDe('Apellido01, Nombre'));

        fireEvent.click(screen.getAllByRole('button', { name: 'Habilitar' })[0]);

        expect(await screen.findByRole('status')).toHaveTextContent('1 habilitado');
        expect(lotes()[0]).toEqual([RUTA_LISTA, { habilitado: true, estudiantes: [1] }]);
        await waitFor(() => expect(dato('No habilitados')).toHaveTextContent('0'));
        expect(dato('Habilitados')).toHaveTextContent('40');
        expect(screen.queryByText('No presentó el proyecto')).not.toBeInTheDocument();
        expect(screen.queryByText('1 seleccionado')).not.toBeInTheDocument();
    });

    it('inhabilita con motivo: queda a la vista y sin aula', async () => {
        montar();
        await cargada();
        fireEvent.click(casillaDe('Apellido02, Nombre'));
        fireEvent.click(casillaDe('Apellido03, Nombre'));
        fireEvent.change(screen.getByLabelText(/Motivo de la inhabilitación/), {
            target: { value: '  Sin prácticas aprobadas ' },
        });

        fireEvent.click(screen.getAllByRole('button', { name: 'Inhabilitar' })[0]);

        expect(await screen.findByRole('status')).toHaveTextContent('2 inhabilitados');
        expect(lotes()[0]).toEqual([
            RUTA_LISTA,
            { habilitado: false, motivo: 'Sin prácticas aprobadas', estudiantes: [2, 3] },
        ]);
        const fila = await waitFor(() => {
            const tr = within(screen.getByRole('table'))
                .getByText('Apellido02, Nombre')
                .closest('tr');
            expect(tr).toHaveTextContent('Sin prácticas aprobadas');
            return tr;
        });
        expect(fila).toHaveTextContent('No habilitado');
        expect(fila).not.toHaveTextContent('691');
        expect(dato('No habilitados')).toHaveTextContent('3');
        expect(screen.queryByLabelText(/Motivo de la inhabilitación/)).not.toBeInTheDocument();
    });

    it('con un motivo de menos de cinco caracteres no cambia a nadie', async () => {
        montar();
        await cargada();
        fireEvent.click(casillaDe('Apellido02, Nombre'));
        const motivo = screen.getByLabelText(/Motivo de la inhabilitación/);
        expect(motivo).toHaveAttribute('maxlength', '1000');
        expect(screen.getByText('0/1000')).toBeInTheDocument();

        fireEvent.click(screen.getAllByRole('button', { name: 'Inhabilitar' })[0]);
        expect(screen.getByText('Mínimo 5 caracteres')).toBeInTheDocument();
        expect(motivo).toHaveAttribute('aria-invalid', 'true');

        fireEvent.change(motivo, { target: { value: ' abc   ' } });
        expect(screen.getByText('7/1000')).toBeInTheDocument();
        fireEvent.click(screen.getAllByRole('button', { name: 'Inhabilitar' })[0]);

        expect(screen.getByText('Mínimo 5 caracteres')).toBeInTheDocument();
        expect(lotes()).toHaveLength(0);
        expect(screen.getAllByText('1 seleccionado')).not.toHaveLength(0);

        fireEvent.change(motivo, { target: { value: 'abcde' } });
        expect(screen.queryByText('Mínimo 5 caracteres')).not.toBeInTheDocument();
        expect(screen.getByText('5/1000')).toBeInTheDocument();
    });

    it('señala el campo si el servidor rechaza el motivo', async () => {
        rutas['POST /examenes/*/habilitaciones'] = errorHttp(422, {
            message: 'Máximo 1000 caracteres',
            errors: { motivo: ['Máximo 1000 caracteres'] },
        });
        simularApi(api, rutas);
        montar();
        await cargada();
        fireEvent.click(casillaDe('Apellido02, Nombre'));
        fireEvent.change(screen.getByLabelText(/Motivo de la inhabilitación/), {
            target: { value: 'Motivo cualquiera' },
        });

        fireEvent.click(screen.getAllByRole('button', { name: 'Inhabilitar' })[0]);

        expect(await screen.findByText('Máximo 1000 caracteres')).toBeInTheDocument();
        expect(screen.getAllByText('1 seleccionado')).not.toHaveLength(0);
    });

    it('rechaza inhabilitar a quien ya ingresó y conserva la selección', async () => {
        rutas['POST /examenes/*/habilitaciones'] = errorHttp(409, {
            message:
                'Un estudiante de la selección ya ingresó al examen y no se puede inhabilitar.',
            codigo: 'ESTUDIANTE_CON_INGRESO',
        });
        simularApi(api, rutas);
        montar();
        await cargada();
        fireEvent.click(casillaDe('Apellido02, Nombre'));
        fireEvent.change(screen.getByLabelText(/Motivo de la inhabilitación/), {
            target: { value: 'Sin prácticas aprobadas' },
        });

        fireEvent.click(screen.getAllByRole('button', { name: 'Inhabilitar' })[0]);

        expect(await screen.findByRole('status')).toHaveTextContent(
            'Un estudiante de la selección ya ingresó al examen y no se puede inhabilitar'
        );
        expect(screen.getAllByText('1 seleccionado')).not.toHaveLength(0);
        expect(screen.getByLabelText(/Motivo de la inhabilitación/)).toHaveValue(
            'Sin prácticas aprobadas'
        );
        const fila = within(screen.getByRole('table'))
            .getByText('Apellido02, Nombre')
            .closest('tr');
        expect(within(fila).getByText('Habilitado')).toBeInTheDocument();
    });

    it('«Repartir» avisa cuántos estudiantes repartió en cuántas aulas y recarga', async () => {
        montar();
        await cargada();
        const pedidas = consultas().length;

        fireEvent.click(screen.getByRole('button', { name: 'Repartir' }));

        expect(await screen.findByRole('status')).toHaveTextContent('12 estudiantes en 2 aulas');
        expect(api.post).toHaveBeenCalledWith('/examenes/1/reparto');
        await waitFor(() => expect(consultas().length).toBe(pedidas + 1));
    });

    it('sin nadie por repartir avisa «Sin estudiantes por repartir»', async () => {
        rutas['POST /examenes/*/reparto'] = {
            message: 'Sin estudiantes por repartir.',
            repartidos: 0,
            por_aula: AULAS,
        };
        simularApi(api, rutas);
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Repartir' }));

        const aviso = await screen.findByRole('status');
        expect(aviso.textContent).toBe('Sin estudiantes por repartir');
    });

    it('sin aulas muestra «Sin aulas» y no permite el reparto', async () => {
        montar('/habilitacion?examen=3');

        expect(await screen.findByText('Sin aulas')).toBeInTheDocument();
        expect(screen.getByText('Distribución por aula')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Repartir' })).toBeDisabled();
        expect(screen.getByRole('combobox', { name: 'Aula' })).toBeDisabled();
    });

    it('avisa «Sin aulas» si el servidor rechaza el reparto', async () => {
        rutas['POST /examenes/*/reparto'] = errorHttp(409, {
            message: 'El examen no tiene aulas: no hay dónde repartir.',
            codigo: 'SIN_AULAS',
        });
        simularApi(api, rutas);
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Repartir' }));

        expect(await screen.findByRole('status')).toHaveTextContent('Sin aulas');
    });

    it('muestra la negativa del servidor si el examen no es del docente', async () => {
        const ajeno = errorHttp(403, { message: 'Este examen no es tuyo.', alcance: true });
        rutas['GET /examenes/1/habilitaciones'] = ajeno;
        simularApi(api, rutas);
        montar();

        expect(await screen.findByRole('alert')).toHaveTextContent('Este examen no es tuyo');
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Repartir' })).not.toBeInTheDocument();
    });

    it('avisa la negativa si el servidor rechaza un lote por alcance', async () => {
        rutas['POST /examenes/*/habilitaciones'] = errorHttp(403, {
            message: 'Este examen no es tuyo.',
            alcance: true,
        });
        simularApi(api, rutas);
        montar();
        await cargada();
        fireEvent.click(casillaDe('Apellido02, Nombre'));

        fireEvent.click(screen.getAllByRole('button', { name: 'Habilitar' })[0]);

        expect(await screen.findByRole('status')).toHaveTextContent('Este examen no es tuyo');
    });

    it('en móvil la lista es de tarjetas y el panel del motivo queda fijo al pie', async () => {
        montar();
        await cargada();

        const tabla = screen.getByRole('table');
        expect(tabla.parentElement).toHaveClass('hidden', 'md:block', 'overflow-x-auto');
        const tarjetas = screen.getByText('Seleccionar la página').closest('div');
        expect(tarjetas).toHaveClass('md:hidden');
        const tarjeta = within(tarjetas).getByText('Apellido02, Nombre').closest('li');
        expect(tarjeta).toHaveTextContent('202100002 · Grupo 2 · Aula 691B');
        expect(within(tarjeta).getByText('Habilitado')).toBeInTheDocument();
        expect(within(tarjetas).getByText('No presentó el proyecto')).toBeInTheDocument();

        fireEvent.click(within(tarjeta).getByRole('checkbox'));

        const panel = screen.getByTestId('panel-seleccion');
        expect(panel).toHaveClass('max-md:fixed', 'max-md:inset-x-0');
        expect(within(panel).getByLabelText(/Motivo de la inhabilitación/)).toBeInTheDocument();
        expect(within(panel).getByRole('button', { name: 'Inhabilitar' })).toBeInTheDocument();
        expect(within(panel).getByRole('button', { name: 'Habilitar' })).toBeInTheDocument();
        expect(within(tarjetas).getByText('Seleccionar la página')).toBeInTheDocument();
    });

    it('sin exámenes muestra «Sin exámenes»', async () => {
        rutas['GET /examenes'] = { data: [], meta: {} };
        simularApi(api, rutas);
        montar('/habilitacion');

        expect(await screen.findByText('Sin exámenes')).toBeInTheDocument();
        expect(consultas()).toHaveLength(0);
    });

    it('si no carga la lista ofrece reintentar', async () => {
        let fallar = true;
        rutas['GET /examenes/1/habilitaciones'] = ({ params }) =>
            fallar ? errorHttp(500) : listado(alumnos, params);
        simularApi(api, rutas);
        montar();

        expect(await screen.findByText('No se pudo cargar')).toBeInTheDocument();
        fallar = false;
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));

        expect(await cargada()).not.toHaveLength(0);
    });
});
