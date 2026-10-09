import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MisGrupos from './MisGrupos';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

const ORDEN = ['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos'];

function grupo(id, codigo, asignatura, inscritos = 0, cambios = {}) {
    return {
        id,
        codigo,
        asignatura: { id: id * 10, codigo: `20100${id}`, nombre: asignatura },
        nivel: 'Semestre 1',
        facultad: 'FCyT',
        periodo: '2/2026',
        horarios: [
            { dia: 'LU', hora: '06:45-08:15', aula: '691C' },
            { dia: 'MI', hora: '08:15-09:45', aula: '624' },
        ],
        inscritos,
        con_lista: inscritos > 0,
        ...cambios,
    };
}

function estudiantes(cantidad) {
    return Array.from({ length: cantidad }, (_, i) => ({
        id: i + 1,
        codigo: String(202400001 + i),
        nombre: `Apellido${String(i + 1).padStart(3, '0')}, Nombre`,
        documento: String(5000001 + i),
        origen: i % 2 === 0 ? 'Docente' : 'Administración',
    }));
}

const RESULTADO = {
    message: 'Carga procesada.',
    archivo: 'inscritos_g2.csv',
    resumen: {
        filas: 34,
        nuevos: 9,
        reutilizados: 20,
        ya_inscritos: 2,
        rechazados: 2,
        conflictos: 1,
    },
    rechazos: [
        { fila: 31, motivo: 'Sin documento de identidad.' },
        { fila: 33, motivo: 'El código se repite en el archivo.' },
    ],
    conflictos: [{ fila: 17, codigo: '202400458', motivo: 'Documento distinto al del padrón.' }],
};

// Un servidor mínimo en memoria con el contrato de GET /docente/grupos y de
// las rutas 39 a 41 más la descarga de la lista.
function servidor(grupos, { inscritos = {}, carga = RESULTADO, rutas = {} } = {}) {
    const listas = new Map(Object.entries(inscritos).map(([id, lista]) => [Number(id), lista]));

    const lista = ({ url, params = {} }) => {
        const id = Number(url.split('/')[3]);
        const texto = (params.buscar ?? '').toLowerCase();
        const todos = (listas.get(id) ?? []).filter(
            (e) => !texto || `${e.nombre} ${e.codigo} ${e.documento}`.toLowerCase().includes(texto)
        );
        const pagina = params.pagina ?? 1;
        const porPagina = params.por_pagina ?? 25;
        return {
            data: todos.slice((pagina - 1) * porPagina, pagina * porPagina),
            meta: { total: todos.length, pagina, por_pagina: porPagina },
        };
    };

    const cargar = (pedido) => {
        const respuesta = typeof carga === 'function' ? carga(pedido) : carga;
        if (respuesta instanceof Error) return respuesta;
        const id = Number(pedido.url.split('/')[3]);
        const g = grupos.find((uno) => uno.id === id);
        const sumados = respuesta.resumen.nuevos + respuesta.resumen.reutilizados;
        listas.set(id, [...(listas.get(id) ?? []), ...estudiantes(sumados)]);
        g.inscritos += sumados;
        g.con_lista = g.inscritos > 0;
        return respuesta;
    };

    simularApi(api, {
        'GET /docente/grupos': () => ({
            data: grupos.map((g) => ({ ...g })),
            meta: {
                periodo: '2/2026',
                asignaturas: new Set(grupos.map((g) => g.asignatura.nombre)).size,
                sin_lista: grupos.filter((g) => !g.con_lista).length,
            },
        }),
        'GET /docente/grupos/*/inscritos': lista,
        'GET /docente/grupos/*/inscritos/descarga': () => new Blob(['x']),
        'GET /inscritos/plantilla': () => new Blob(['x']),
        'POST /docente/grupos/*/inscritos/carga': cargar,
        ...rutas,
    });
}

const POCOS = () => [
    grupo(37, '2', 'Introducción a la Programación', 65),
    grupo(66, '1', 'Algoritmos Avanzados', 0, { nivel: 'Semestre 4' }),
];

// Una docente de mucha carga: 30 grupos de 10 asignaturas.
const MUCHOS = () =>
    Array.from({ length: 30 }, (_, i) =>
        grupo(
            100 + i,
            String((i % 3) + 1),
            `Asignatura ${String(Math.floor(i / 3) + 1)}`,
            i % 4 === 3 ? 0 : 40
        )
    );

const archivo = (nombre, contenido = 'a;b;c;d') => new File([contenido], nombre);

function elegirArchivo(file) {
    fireEvent.change(screen.getByLabelText('Archivo de la lista'), { target: { files: [file] } });
}

const pedidos = (metodo, patron) => api[metodo].mock.calls.filter(([url]) => patron.test(url));

beforeEach(() => {
    URL.createObjectURL = vi.fn(() => 'blob:prueba');
    URL.revokeObjectURL = vi.fn();
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
});

describe('Mis grupos · grupos del docente', () => {
    it('muestra cada grupo con asignatura, código, nivel, horarios con aula y estado de la lista', async () => {
        servidor(POCOS(), { inscritos: { 37: estudiantes(65) } });
        renderConSesion(<MisGrupos />);

        const conLista = await screen.findByRole('button', {
            name: /Introducción a la Programación/,
        });
        expect(conLista).toHaveTextContent('Lista cargada · 65');
        expect(conLista).toHaveTextContent('Grupo 2 · 2010037 · Semestre 1');
        expect(conLista).toHaveTextContent('LU 06:45-08:15 (691C) · MI 08:15-09:45 (624)');
        expect(conLista).toHaveTextContent('FCyT');

        const sinLista = screen.getByRole('button', { name: /Algoritmos Avanzados/ });
        expect(sinLista).toHaveTextContent('Sin lista');
        expect(sinLista).toHaveTextContent('Grupo 1 · 2010066 · Semestre 4');

        expect(screen.getByText('Período 2/2026 · 2 grupos')).toBeInTheDocument();
        expect(conLista).toHaveAttribute('aria-pressed', 'true');
    });

    it('sin grupos dice «Sin grupos»', async () => {
        servidor([]);
        renderConSesion(<MisGrupos />);

        expect(await screen.findByText('Sin grupos')).toBeInTheDocument();
        expect(screen.queryByText('Lista de inscritos')).not.toBeInTheDocument();
    });

    it('si la lista de grupos falla ofrece reintentar', async () => {
        let intentos = 0;
        servidor(POCOS(), {
            rutas: {
                'GET /docente/grupos': () => {
                    intentos += 1;
                    return intentos === 1
                        ? errorHttp(500, { message: 'Error' })
                        : { data: POCOS(), meta: { periodo: '2/2026' } };
                },
            },
        });
        renderConSesion(<MisGrupos />);

        fireEvent.click(await screen.findByRole('button', { name: 'Reintentar' }));

        expect(
            await screen.findByRole('button', { name: /Algoritmos Avanzados/ })
        ).toBeInTheDocument();
    });
});

describe('Mis grupos · muchos grupos', () => {
    it('los agrupa por asignatura y todos quedan en la lista', async () => {
        servidor(MUCHOS());
        renderConSesion(<MisGrupos />);

        const lista = await screen.findByRole('group', { name: 'Grupos' });
        expect(within(lista).getAllByRole('heading', { level: 2 })).toHaveLength(10);
        expect(within(lista).getAllByRole('button')).toHaveLength(30);
        expect(lista.className).toContain('overflow-y-auto');
        expect(
            screen.getByText('Período 2/2026 · 30 grupos · 10 asignaturas · 7 sin lista')
        ).toBeInTheDocument();
    });

    it('busca por asignatura o grupo y avisa si nada coincide', async () => {
        servidor(MUCHOS());
        renderConSesion(<MisGrupos />);
        const lista = await screen.findByRole('group', { name: 'Grupos' });
        const buscador = screen.getByLabelText('Buscar grupo');

        fireEvent.change(buscador, { target: { value: 'asignatura 10' } });
        expect(within(lista).getAllByRole('button')).toHaveLength(3);

        fireEvent.change(buscador, { target: { value: 'grupo 3' } });
        expect(within(lista).getAllByRole('button')).toHaveLength(10);

        fireEvent.change(buscador, { target: { value: 'zoología' } });
        expect(within(lista).queryAllByRole('button')).toHaveLength(0);
        expect(within(lista).getByText('Sin resultados')).toBeInTheDocument();
    });

    it('filtra «Sin lista» con su conteo', async () => {
        servidor(MUCHOS());
        renderConSesion(<MisGrupos />);
        const lista = await screen.findByRole('group', { name: 'Grupos' });

        const filtro = screen.getByRole('button', { name: /^Sin lista/ });
        expect(filtro).toHaveTextContent('Sin lista7');
        expect(screen.getByRole('button', { name: /^Todos/ })).toHaveTextContent('Todos30');

        fireEvent.click(filtro);

        const filas = within(lista).getAllByRole('button');
        expect(filas).toHaveLength(7);
        filas.forEach((fila) => expect(fila).toHaveTextContent('Sin lista'));
    });

    it('al elegir un grupo abre su lista y deja la búsqueda en blanco', async () => {
        const grupos = MUCHOS();
        servidor(grupos, { inscritos: { 100: estudiantes(40), 101: estudiantes(3) } });
        renderConSesion(<MisGrupos />);
        const lista = await screen.findByRole('group', { name: 'Grupos' });
        await screen.findByText('Asignatura 1 · Grupo 1');

        fireEvent.change(screen.getByLabelText('Buscar estudiante'), {
            target: { value: 'Apellido001' },
        });
        fireEvent.click(within(lista).getAllByRole('button')[1]);

        expect(await screen.findByText('Asignatura 1 · Grupo 2')).toBeInTheDocument();
        expect(screen.getByLabelText('Buscar estudiante')).toHaveValue('');
        expect(await screen.findByText('3 inscritos')).toBeInTheDocument();
    });
});

describe('Mis grupos · lista de inscritos', () => {
    it('la pide de a 25 y la muestra con código, nombre, documento y origen', async () => {
        servidor(POCOS(), { inscritos: { 37: estudiantes(65) } });
        renderConSesion(<MisGrupos />);

        const tabla = await screen.findByRole('table');
        expect(
            within(tabla)
                .getAllByRole('columnheader')
                .map((c) => c.textContent)
        ).toEqual(['Código', 'Estudiante', 'Documento', 'Origen']);
        expect(within(tabla).getAllByRole('row')).toHaveLength(26);

        const primera = within(tabla).getAllByRole('row')[1];
        expect(primera).toHaveTextContent('202400001');
        expect(primera).toHaveTextContent('Apellido001, Nombre');
        expect(primera).toHaveTextContent('5000001');
        expect(primera).toHaveTextContent('Docente');
        expect(within(tabla).getAllByRole('row')[2]).toHaveTextContent('Administración');

        expect(pedidos('get', /\/37\/inscritos$/)[0][1].params).toEqual({
            pagina: 1,
            por_pagina: 25,
        });
        expect(screen.getByText('1–25 de 65 inscritos')).toBeInTheDocument();
    });

    it('pasa de página en el servidor', async () => {
        servidor(POCOS(), { inscritos: { 37: estudiantes(65) } });
        renderConSesion(<MisGrupos />);
        await screen.findByRole('table');

        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));

        expect(await screen.findByText('26–50 de 65 inscritos')).toBeInTheDocument();
        expect(within(screen.getByRole('table')).getAllByRole('row')[1]).toHaveTextContent(
            'Apellido026'
        );
        expect(pedidos('get', /\/37\/inscritos$/).at(-1)[1].params.pagina).toBe(2);
    });

    it('busca por nombre, código o documento y dice «Sin resultados»', async () => {
        servidor(POCOS(), { inscritos: { 37: estudiantes(65) } });
        renderConSesion(<MisGrupos />);
        await screen.findByRole('table');
        const buscador = screen.getByLabelText('Buscar estudiante');

        fireEvent.change(buscador, { target: { value: '5000007' } });
        expect(await screen.findByText('1 inscrito')).toBeInTheDocument();
        expect(pedidos('get', /\/37\/inscritos$/).at(-1)[1].params).toEqual({
            buscar: '5000007',
            pagina: 1,
            por_pagina: 25,
        });

        fireEvent.change(buscador, { target: { value: 'nadie' } });
        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('descarga la lista con «Reporte de mis estudiantes»', async () => {
        servidor(POCOS(), { inscritos: { 37: estudiantes(65) } });
        renderConSesion(<MisGrupos />);
        await screen.findByRole('table');

        fireEvent.click(screen.getByRole('button', { name: /Reporte de mis estudiantes/ }));

        expect(await screen.findByText('estudiantes_2010037_g2.xlsx descargado')).toBeVisible();
        const [url, config] = pedidos('get', /descarga$/)[0];
        expect(url).toBe('/docente/grupos/37/inscritos/descarga');
        expect(config.responseType).toBe('blob');
    });

    it('si la descarga responde SIN_LISTA lo avisa', async () => {
        servidor(POCOS(), {
            inscritos: { 37: estudiantes(65) },
            rutas: {
                'GET /docente/grupos/*/inscritos/descarga': errorHttp(409, {
                    message: 'El grupo no tiene lista cargada.',
                    codigo: 'SIN_LISTA',
                }),
            },
        });
        renderConSesion(<MisGrupos />);
        await screen.findByRole('table');

        fireEvent.click(screen.getByRole('button', { name: /Reporte de mis estudiantes/ }));

        expect(await screen.findByText('El grupo no tiene lista cargada.')).toBeVisible();
    });

    it('muestra la negativa si el grupo no es del docente', async () => {
        servidor(POCOS(), {
            rutas: {
                'GET /docente/grupos/*/inscritos': errorHttp(403, {
                    message: 'Este grupo no es tuyo.',
                    alcance: true,
                }),
            },
        });
        renderConSesion(<MisGrupos />);

        expect(await screen.findByRole('alert')).toHaveTextContent('Este grupo no es tuyo.');
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });
});

describe('Mis grupos · grupo sin lista', () => {
    async function abrirSinLista(opciones) {
        servidor(POCOS(), { inscritos: { 37: estudiantes(65) }, ...opciones });
        renderConSesion(<MisGrupos />);
        fireEvent.click(await screen.findByRole('button', { name: /Algoritmos Avanzados/ }));
        await screen.findByText('Sin lista cargada');
    }

    it('dice «Sin lista cargada», ofrece la carga con su formato y deshabilita el reporte', async () => {
        await abrirSinLista();

        expect(
            screen.getByText('.xlsx o .csv · código universitario, documento, nombres y apellidos')
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Archivo de la lista')).toHaveAttribute(
            'accept',
            '.csv,.xlsx'
        );
        expect(screen.getByRole('button', { name: /Reporte de mis estudiantes/ })).toBeDisabled();
        expect(screen.queryByRole('button', { name: /Cargar archivo/ })).not.toBeInTheDocument();
        expect(pedidos('get', /\/66\/inscritos$/)).toHaveLength(0);
    });

    it('descarga la plantilla del grupo', async () => {
        await abrirSinLista();

        fireEvent.click(screen.getByRole('button', { name: /Descargar plantilla/ }));

        expect(await screen.findByText('plantilla_inscritos.xlsx descargado')).toBeVisible();
        const [url, config] = pedidos('get', /plantilla$/)[0];
        expect(url).toBe('/inscritos/plantilla');
        expect(config.params).toEqual({ alcance: 'grupo' });
    });

    it('el archivo es obligatorio', async () => {
        await abrirSinLista();

        fireEvent.click(screen.getByRole('button', { name: 'Cargar lista' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('El archivo es obligatorio.');
        expect(api.post).not.toHaveBeenCalled();
    });

    it('solo admite .csv o .xlsx', async () => {
        await abrirSinLista();

        elegirArchivo(archivo('foto.png'));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'El archivo debe ser .csv o .xlsx.'
        );
        fireEvent.click(screen.getByRole('button', { name: 'Cargar lista' }));
        expect(api.post).not.toHaveBeenCalled();
    });

    it('carga el archivo y muestra el resumen con las filas rechazadas y en conflicto', async () => {
        await abrirSinLista();

        elegirArchivo(archivo('inscritos_g2.csv'));
        expect(screen.getByText('inscritos_g2.csv')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Cargar lista' }));

        expect(await screen.findByText('34 filas', { exact: false })).toBeInTheDocument();
        const [url, cuerpo] = api.post.mock.calls[0];
        expect(url).toBe('/docente/grupos/66/inscritos/carga');
        expect(cuerpo).toBeInstanceOf(FormData);
        expect(cuerpo.get('archivo').name).toBe('inscritos_g2.csv');

        expect(screen.getByText('inscritos_g2.csv')).toBeInTheDocument();
        [
            '9 nuevos',
            '20 ya en el padrón',
            '2 ya inscritos',
            '2 rechazados',
            '1 en conflicto',
        ].forEach((cifra) => expect(screen.getByText(cifra)).toBeInTheDocument());
        expect(screen.getByText('Lista cargada')).toBeVisible();

        fireEvent.click(screen.getByRole('button', { name: 'Ver filas: rechazados' }));
        expect(screen.getByText('Fila 31')).toBeInTheDocument();
        expect(screen.getByText('Sin documento de identidad.')).toBeInTheDocument();
        expect(screen.getByText('Fila 33')).toBeInTheDocument();
        expect(screen.getByText('El código se repite en el archivo.')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Ver filas: en conflicto' }));
        expect(screen.getByText('Fila 17 · 202400458')).toBeInTheDocument();
        expect(screen.getByText('Documento distinto al del padrón.')).toBeInTheDocument();
        expect(screen.queryByText('Fila 31')).not.toBeInTheDocument();

        // El grupo pasa a tener lista y se consulta.
        expect(await screen.findByText('1–25 de 29 inscritos')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Algoritmos Avanzados/ })).toHaveTextContent(
            'Lista cargada · 29'
        );
        expect(screen.queryByText('Sin lista cargada')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Reporte de mis estudiantes/ })).toBeEnabled();

        fireEvent.click(screen.getByRole('button', { name: 'Cerrar el resumen de la carga' }));
        expect(screen.queryByText('9 nuevos')).not.toBeInTheDocument();
    });

    it('sin rechazos ni conflictos no ofrece «Ver filas»', async () => {
        await abrirSinLista({
            carga: {
                ...RESULTADO,
                resumen: { ...RESULTADO.resumen, rechazados: 0, conflictos: 0 },
                rechazos: [],
                conflictos: [],
            },
        });

        elegirArchivo(archivo('lista.xlsx'));
        fireEvent.click(screen.getByRole('button', { name: 'Cargar lista' }));

        expect(await screen.findByText('0 rechazados')).toBeInTheDocument();
        expect(screen.getByText('0 en conflicto')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Ver filas/ })).not.toBeInTheDocument();
    });

    it('archivo rechazado entero: dice qué columnas faltan y el orden esperado', async () => {
        await abrirSinLista({
            carga: errorHttp(422, {
                message: 'Faltan columnas: documento_identidad.',
                codigo: 'FALTAN_COLUMNAS',
                orden_esperado: ORDEN,
                columnas: ['documento_identidad'],
                errors: { archivo: ['Faltan columnas: documento_identidad.'] },
            }),
        });

        elegirArchivo(archivo('lista.csv'));
        fireEvent.click(screen.getByRole('button', { name: 'Cargar lista' }));

        const alerta = await screen.findByRole('alert');
        expect(alerta).toHaveTextContent('Faltan columnas');
        expect(alerta).toHaveTextContent('Faltan: Documento de identidad');
        expect(alerta).toHaveTextContent(
            'Orden esperado: Código universitario · Documento de identidad · Nombres · Apellidos'
        );
        expect(screen.getByText('Sin lista cargada')).toBeInTheDocument();
        expect(screen.queryByText('Lista cargada')).not.toBeInTheDocument();
    });

    it('archivo rechazado entero: columnas en otro orden', async () => {
        await abrirSinLista({
            carga: errorHttp(422, {
                message: 'Las columnas están en otro orden. Orden esperado: …',
                codigo: 'ORDEN_DE_COLUMNAS',
                orden_esperado: ORDEN,
                errors: { archivo: ['Las columnas están en otro orden.'] },
            }),
        });

        elegirArchivo(archivo('lista.csv'));
        fireEvent.click(screen.getByRole('button', { name: 'Cargar lista' }));

        const alerta = await screen.findByRole('alert');
        expect(alerta).toHaveTextContent('Columnas en otro orden');
        expect(alerta).toHaveTextContent(
            'Orden esperado: Código universitario · Documento de identidad · Nombres · Apellidos'
        );
        expect(alerta).not.toHaveTextContent('Faltan:');
    });

    it.each([
        ['ARCHIVO_NO_CORRESPONDE', 'El archivo no es una lista de inscritos'],
        ['ARCHIVO_VACIO', 'El archivo no tiene filas'],
    ])('archivo rechazado entero: %s', async (codigo, texto) => {
        await abrirSinLista({
            carga: errorHttp(422, {
                message: `${texto}.`,
                codigo,
                orden_esperado: ORDEN,
                errors: { archivo: [`${texto}.`] },
            }),
        });

        elegirArchivo(archivo('lista.xlsx'));
        fireEvent.click(screen.getByRole('button', { name: 'Cargar lista' }));

        const alerta = await screen.findByRole('alert');
        expect(alerta).toHaveTextContent(texto);
        expect(alerta).toHaveTextContent('Orden esperado: Código universitario');
        expect(screen.queryByText('9 nuevos')).not.toBeInTheDocument();
    });

    it('muestra el 422 del formulario del servidor', async () => {
        await abrirSinLista({
            carga: errorHttp(422, {
                message: 'El archivo no puede superar los 10 MB.',
                errors: { archivo: ['El archivo no puede superar los 10 MB.'] },
            }),
        });

        elegirArchivo(archivo('lista.csv'));
        fireEvent.click(screen.getByRole('button', { name: 'Cargar lista' }));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'El archivo no puede superar los 10 MB.'
        );
    });

    it.each([
        [409, { message: 'El período del grupo ya no está vigente.', codigo: 'PERIODO_CERRADO' }],
        [403, { message: 'Este grupo no es tuyo.', alcance: true }],
    ])('muestra la negativa %i del servidor', async (estado, cuerpo) => {
        await abrirSinLista({ carga: errorHttp(estado, cuerpo) });

        elegirArchivo(archivo('lista.csv'));
        fireEvent.click(screen.getByRole('button', { name: 'Cargar lista' }));

        expect(await screen.findByRole('alert')).toHaveTextContent(cuerpo.message);
    });
});

describe('Mis grupos · grupo con lista', () => {
    it('«Cargar archivo» abre la zona de carga y «Cancelar» la cierra', async () => {
        servidor(POCOS(), { inscritos: { 37: estudiantes(65) } });
        renderConSesion(<MisGrupos />);
        await screen.findByRole('table');
        expect(screen.queryByLabelText('Archivo de la lista')).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Cargar archivo/ }));
        expect(screen.getByLabelText('Archivo de la lista')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Cancelar' }));
        expect(screen.queryByLabelText('Archivo de la lista')).not.toBeInTheDocument();
    });

    it('una carga nueva suma a la lista y el resumen queda con su grupo', async () => {
        servidor(POCOS(), { inscritos: { 37: estudiantes(65) } });
        renderConSesion(<MisGrupos />);
        await screen.findByRole('table');

        fireEvent.click(screen.getByRole('button', { name: /Cargar archivo/ }));
        elegirArchivo(archivo('inscritos_g2.csv'));
        fireEvent.click(screen.getByRole('button', { name: 'Cargar lista' }));

        expect(await screen.findByText('9 nuevos')).toBeInTheDocument();
        expect(await screen.findByText('1–25 de 94 inscritos')).toBeInTheDocument();
        await waitFor(() =>
            expect(
                screen.getByRole('button', { name: /Introducción a la Programación/ })
            ).toHaveTextContent('Lista cargada · 94')
        );

        fireEvent.click(screen.getByRole('button', { name: /Algoritmos Avanzados/ }));
        await screen.findByText('Sin lista cargada');
        expect(screen.queryByText('9 nuevos')).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Introducción a la Programación/ }));
        expect(await screen.findByText('9 nuevos')).toBeInTheDocument();
    });
});
