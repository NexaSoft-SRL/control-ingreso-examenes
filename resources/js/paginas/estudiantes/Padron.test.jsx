import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Padron from './Padron';
import { api } from '../../api/cliente';
import descargar from '../../api/descargar';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';
import { normalizar } from '../../utiles/texto';

vi.mock('../../api/cliente');
vi.mock('../../api/descargar', () => ({ default: vi.fn() }));

const SIGLAS = { fcyt: 'FCyT', fce: 'FCE', fhce: 'FHCE', fach: 'FACH' };
const CARRERAS = [
    {
        id: 11,
        codigo: '419701',
        nombre: 'Licenciatura en Fisica',
        regimen: 'Semestral',
        de: 'fcyt',
    },
    {
        id: 12,
        codigo: '134111',
        nombre: 'Licenciatura en Biologia',
        regimen: 'Semestral',
        de: 'fcyt',
    },
    {
        id: 39,
        codigo: '202002',
        nombre: 'Licenciatura en Arquitectura',
        regimen: 'Anual',
        de: 'fach',
    },
];
const ORDEN = [
    'codigo_universitario',
    'documento_identidad',
    'nombres',
    'apellidos',
    'codigo_asignatura',
    'grupo',
];

const ESTUDIANTES = [
    {
        id: 1,
        codigo: '202110007',
        nombre: 'Aguilar Bustamante, Adriana',
        documento: '5000000',
        facultad: 'FCyT',
        carrera_id: 11,
        origen: 'Docente',
        materias: [
            {
                asignatura: { codigo: '2010010', nombre: 'Introduccion a la Programacion' },
                grupo: '1',
                grupo_id: 36,
                docente: 'Salazar Serrudo Carla',
                periodo: '2/2026',
                via: 'Docente',
            },
            {
                asignatura: { codigo: '2008019', nombre: 'Algebra I' },
                grupo: '8',
                grupo_id: 90,
                docente: null,
                periodo: '2/2026',
                via: 'Administración',
            },
        ],
    },
    {
        id: 53,
        codigo: '202510063',
        nombre: 'Pérez Pardo, Daniela',
        documento: '5048724',
        facultad: 'FCyT',
        carrera_id: 12,
        origen: 'Verificado',
        materias: [],
    },
    {
        id: 3921,
        codigo: '202314578',
        nombre: 'Zurita Vargas, Marco',
        documento: '8673040',
        facultad: 'FACH',
        carrera_id: 39,
        origen: 'Verificado',
        materias: [],
    },
];

const CONFLICTOS = [
    {
        id: 1,
        codigo: '202311988',
        tipo: 'Documento distinto',
        estado: 'pendiente',
        guardado: {
            nombre: 'Rodríguez García, Adriana',
            documento: '6592900',
            por: 'Administración · 9 oct 2026',
        },
        nuevo: {
            nombre: 'Rodríguez García, Adriana',
            documento: '6592910',
            por: 'Blanco Coca Leticia · Arquitectura de Computadoras I, grupo 2',
        },
        inscripcion_en_espera: true,
    },
    {
        id: 3,
        codigo: '202312058',
        tipo: 'Nombre distinto',
        estado: 'pendiente',
        guardado: { nombre: 'Torrico Condori, Adriana', documento: '6649120', por: 'Docente' },
        nuevo: {
            nombre: 'Torrico Condori, Adriana A.',
            documento: '6649120',
            por: 'Blanco Coca Leticia · Arquitectura de Computadoras I, grupo 2',
        },
        inscripcion_en_espera: true,
    },
];

const CARGA_OK = {
    message: 'Carga procesada.',
    archivo: 'fcyt.csv',
    resumen: {
        filas: 12,
        nuevos: 5,
        reutilizados: 4,
        ya_inscritos: 1,
        rechazados: 2,
        conflictos: 1,
    },
    rechazos: [
        { fila: 4, motivo: 'El código universitario no es válido.' },
        { fila: 9, motivo: 'El grupo no existe en la oferta.' },
    ],
    conflictos: [{ fila: 7, codigo: '202311988', motivo: 'Documento distinto' }],
};

// Un servidor mínimo en memoria con el contrato de las rutas del padrón.
function servidor({ estudiantes = ESTUDIANTES, conflictos = CONFLICTOS, rutas = {} } = {}) {
    const pendientes = [...conflictos];
    const pedidos = { cargas: [], resoluciones: [] };

    const lista = ({ params = {} }) => {
        const texto = normalizar(params.buscar ?? '');
        const filtrados = estudiantes.filter(
            (e) =>
                (!params.facultad || e.facultad === SIGLAS[params.facultad]) &&
                (!params.carrera || e.carrera_id === params.carrera) &&
                (!texto ||
                    normalizar(e.nombre).includes(texto) ||
                    e.codigo.includes(texto) ||
                    e.documento.includes(texto))
        );
        const pagina = params.pagina ?? 1;
        const porPagina = params.por_pagina ?? 25;
        const conteos = { todas: estudiantes.length };
        Object.values(SIGLAS).forEach((sigla) => {
            conteos[sigla] = estudiantes.filter((e) => e.facultad === sigla).length;
        });
        return {
            data: filtrados.slice((pagina - 1) * porPagina, pagina * porPagina).map((e) => ({
                id: e.id,
                codigo: e.codigo,
                nombre: e.nombre,
                documento: e.documento,
                facultad: e.facultad,
                carrera: CARRERAS.find((c) => c.id === e.carrera_id)?.nombre ?? null,
                grupos: e.materias.length,
                origen: e.origen,
            })),
            meta: { total: filtrados.length, pagina, por_pagina: porPagina, conteos },
        };
    };

    const fichaDe = ({ url }) => {
        const e = estudiantes.find((uno) => uno.id === Number(url.split('/')[2]));
        if (!e) return errorHttp(404, { message: 'Estudiante no encontrado.' });
        const [apellidos, nombres] = e.nombre.split(', ');
        return {
            data: {
                id: e.id,
                codigo: e.codigo,
                nombre: e.nombre,
                nombres,
                apellidos,
                documento: e.documento,
                correo: `${e.codigo}@est.umss.edu`,
                facultad: e.facultad,
                carrera: CARRERAS.find((c) => c.id === e.carrera_id)?.nombre ?? null,
                origen: e.origen,
                materias: e.materias,
            },
        };
    };

    simularApi(api, {
        'GET /estudiantes/resumen': () => ({
            estudiantes: estudiantes.length,
            inscripciones: 1790,
            cargados_por_docentes: estudiantes.filter((e) => e.origen === 'Docente').length,
            conflictos_pendientes: pendientes.length,
        }),
        'GET /estudiantes/conflictos': ({ params = {} }) => {
            const pagina = params.pagina ?? 1;
            const porPagina = params.por_pagina ?? 5;
            return {
                data: pendientes.slice((pagina - 1) * porPagina, pagina * porPagina),
                meta: {
                    total: pendientes.length,
                    pagina,
                    por_pagina: porPagina,
                    conteos: { pendientes: pendientes.length, resueltos: 0 },
                },
            };
        },
        'GET /estudiantes': lista,
        'GET /estudiantes/*': fichaDe,
        'GET /oferta/carreras': ({ params = {} }) => ({
            data: CARRERAS.filter((c) => !params.facultad || c.de === params.facultad).map(
                ({ de: _de, ...c }) => c
            ),
        }),
        'POST /estudiantes/cargas': (pedido) => {
            pedidos.cargas.push(pedido.data);
            return CARGA_OK;
        },
        'POST /estudiantes/conflictos/*/resolucion': ({ url, data }) => {
            const id = Number(url.split('/')[3]);
            pedidos.resoluciones.push({ id, ...data });
            const lugar = pendientes.findIndex((c) => c.id === id);
            if (lugar < 0) {
                return errorHttp(409, {
                    message: 'El conflicto ya fue resuelto.',
                    codigo: 'CONFLICTO_YA_RESUELTO',
                });
            }
            const [resuelto] = pendientes.splice(lugar, 1);
            return {
                message:
                    data.resolucion === 'USAR_CARGA'
                        ? `${resuelto.codigo} actualizado.`
                        : `${resuelto.codigo} sin cambios.`,
            };
        },
        ...rutas,
    });

    return { pedidos, pendientes };
}

const montar = (opciones) => renderConSesion(<Padron />, opciones);
const tabla = () => screen.getByRole('table');
const filasDeTabla = () => within(tabla()).getAllByRole('row').slice(1);
const archivoDe = (nombre, tipo = 'text/csv') => new File(['a,b'], nombre, { type: tipo });

async function listaCargada() {
    await waitFor(() => expect(screen.getByRole('table')).toBeInTheDocument());
}

async function abrirCarga() {
    fireEvent.click(screen.getByRole('button', { name: /Cargar inscripciones/ }));
    return screen.findByRole('dialog', { name: 'Carga de inscripciones' });
}

function elegirArchivo(dialogo, archivo) {
    const campo = dialogo.querySelector('input[type="file"]');
    fireEvent.change(campo, { target: { files: archivo ? [archivo] : [] } });
}

async function abrirConflictos() {
    fireEvent.click(await screen.findByRole('tab', { name: 'Conflictos (2)' }));
    await screen.findByText('Código 202311988');
}

beforeEach(() => {
    descargar.mockReset();
});

describe('Padrón · cifras y lista (criterio 1)', () => {
    it('muestra las cuatro cifras y la lista con sus columnas, de a 25', async () => {
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        expect(screen.getByText('Cargados por docentes').nextSibling).toHaveTextContent('1');
        expect(screen.getByText('Inscripciones').nextSibling).toHaveTextContent('1.790');
        expect(screen.getByText('Conflictos pendientes').nextSibling).toHaveTextContent('2');

        const cabeceras = within(tabla())
            .getAllByRole('columnheader')
            .map((th) => th.textContent);
        expect(cabeceras).toEqual([
            'Código',
            'Estudiante',
            'Documento',
            'Facultad',
            'Carrera',
            'Grupos',
            'Origen',
        ]);

        const [primera] = filasDeTabla();
        expect(primera).toHaveTextContent('202110007');
        expect(primera).toHaveTextContent('Aguilar Bustamante, Adriana');
        expect(primera).toHaveTextContent('5000000');
        expect(primera).toHaveTextContent('FCyT');
        expect(primera).toHaveTextContent('Licenciatura en Fisica');
        expect(primera).toHaveTextContent('Docente');
        expect(filasDeTabla()[1]).toHaveTextContent('Verificado');

        expect(api.get).toHaveBeenCalledWith('/estudiantes', {
            params: { pagina: 1, por_pagina: 25 },
        });
        expect(screen.getByText('3 estudiantes')).toBeInTheDocument();
    });

    it('pide la página siguiente al servidor', async () => {
        const muchos = Array.from({ length: 30 }, (_, i) => ({
            ...ESTUDIANTES[1],
            id: 100 + i,
            codigo: `2025${String(i).padStart(5, '0')}`,
            nombre: `Apellido ${String(i).padStart(2, '0')}, Nombre`,
        }));
        servidor({ estudiantes: muchos });
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        expect(filasDeTabla()).toHaveLength(25);
        expect(screen.getByText('1–25 de 30 estudiantes')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        await waitFor(() => expect(filasDeTabla()).toHaveLength(5));
        expect(api.get).toHaveBeenCalledWith('/estudiantes', {
            params: { pagina: 2, por_pagina: 25 },
        });
    });

    it('la lista de tarjetas del móvil trae los mismos datos (criterio 16)', async () => {
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        const tarjetas = screen.getByRole('list', { name: 'Estudiantes' });
        expect(tarjetas).toHaveClass('sm:hidden');
        expect(tabla()).toHaveClass('hidden', 'sm:table');
        const primera = within(tarjetas).getAllByRole('listitem')[0];
        expect(primera).toHaveTextContent('Aguilar Bustamante, Adriana');
        expect(primera).toHaveTextContent('202110007 · CI 5000000');
        expect(primera).toHaveTextContent('Licenciatura en Fisica · 2 grupos');
    });

    it('si la lista no carga, ofrece reintentar', async () => {
        servidor({ rutas: { 'GET /estudiantes': errorHttp(500, {}) } });
        montar({ usuario: usuarioDePrueba('Administrador') });

        expect(await screen.findByText('No se pudo cargar')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument();
    });
});

describe('Padrón · búsqueda y filtros (criterios 2 a 4)', () => {
    it('busca en el servidor y muestra «Sin resultados» si no hay coincidencias', async () => {
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        fireEvent.change(screen.getByLabelText('Buscar estudiante'), {
            target: { value: 'PEREZ' },
        });
        await waitFor(() => expect(filasDeTabla()).toHaveLength(1));
        expect(filasDeTabla()[0]).toHaveTextContent('Pérez Pardo, Daniela');
        expect(api.get).toHaveBeenCalledWith('/estudiantes', {
            params: { buscar: 'PEREZ', pagina: 1, por_pagina: 25 },
        });

        fireEvent.change(screen.getByLabelText('Buscar estudiante'), {
            target: { value: 'zzzz' },
        });
        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('filtra por facultad con la clave y cada opción muestra su conteo', async () => {
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        const filtro = screen.getByRole('group', { name: 'Filtrar por facultad' });
        expect(within(filtro).getByRole('button', { name: /Todas/ })).toHaveTextContent('3');
        expect(within(filtro).getByRole('button', { name: /FCyT/ })).toHaveTextContent('2');
        expect(within(filtro).getByRole('button', { name: /FACH/ })).toHaveTextContent('1');
        expect(within(filtro).getByRole('button', { name: /FCE/ })).toHaveTextContent('0');

        fireEvent.click(within(filtro).getByRole('button', { name: /FACH/ }));
        await waitFor(() => expect(filasDeTabla()).toHaveLength(1));
        expect(filasDeTabla()[0]).toHaveTextContent('Zurita Vargas, Marco');
        expect(api.get).toHaveBeenCalledWith('/estudiantes', {
            params: { facultad: 'fach', pagina: 1, por_pagina: 25 },
        });
    });

    it('el selector ofrece las carreras de la facultad y se limpia al cambiarla', async () => {
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        const filtro = screen.getByRole('group', { name: 'Filtrar por facultad' });
        const selector = screen.getByLabelText('Filtrar por carrera');
        await waitFor(() => expect(within(selector).getAllByRole('option')).toHaveLength(4));

        fireEvent.click(within(filtro).getByRole('button', { name: /FCyT/ }));
        await waitFor(() =>
            expect(
                within(selector)
                    .getAllByRole('option')
                    .map((o) => o.textContent)
            ).toEqual(['Todas las carreras', 'Licenciatura en Fisica', 'Licenciatura en Biologia'])
        );
        expect(api.get).toHaveBeenCalledWith('/oferta/carreras', { params: { facultad: 'fcyt' } });

        fireEvent.change(selector, { target: { value: '12' } });
        await waitFor(() => expect(filasDeTabla()).toHaveLength(1));
        expect(filasDeTabla()[0]).toHaveTextContent('Pérez Pardo, Daniela');
        expect(api.get).toHaveBeenCalledWith('/estudiantes', {
            params: { facultad: 'fcyt', carrera: 12, pagina: 1, por_pagina: 25 },
        });

        fireEvent.click(within(filtro).getByRole('button', { name: /FACH/ }));
        await waitFor(() => expect(filasDeTabla()[0]).toHaveTextContent('Zurita Vargas, Marco'));
        expect(selector).toHaveValue('');
        expect(api.get).toHaveBeenCalledWith('/estudiantes', {
            params: { facultad: 'fach', pagina: 1, por_pagina: 25 },
        });
    });
});

describe('Padrón · ficha del estudiante (criterio 5)', () => {
    it('abre la ficha con los datos, el origen y las materias con grupo y docente', async () => {
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        fireEvent.click(
            within(tabla()).getByRole('button', { name: 'Aguilar Bustamante, Adriana' })
        );
        const ficha = await screen.findByRole('dialog', { name: 'Aguilar Bustamante, Adriana' });
        const materias = await within(ficha).findByRole('region', { name: 'Materias' });

        expect(api.get).toHaveBeenCalledWith('/estudiantes/1', { params: {} });
        expect(ficha).toHaveTextContent('202110007');
        expect(ficha).toHaveTextContent('5000000');
        expect(ficha).toHaveTextContent('202110007@est.umss.edu');
        expect(ficha).toHaveTextContent('Licenciatura en Fisica');
        expect(within(ficha).getByText('Origen').nextSibling).toHaveTextContent('Docente');

        const [primera, segunda] = within(materias).getAllByRole('listitem');
        expect(primera).toHaveTextContent('Introduccion a la Programacion');
        expect(primera).toHaveTextContent('2010010 · Grupo 1 · 2/2026');
        expect(primera).toHaveTextContent('Salazar Serrudo Carla');
        expect(segunda).toHaveTextContent('Algebra I');
        expect(segunda).toHaveTextContent('Grupo 8');
        expect(segunda).toHaveTextContent('Por designar');

        fireEvent.click(within(ficha).getAllByRole('button', { name: 'Cerrar' })[0]);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('se abre también desde la tarjeta del móvil y dice «Sin materias»', async () => {
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        const tarjetas = screen.getByRole('list', { name: 'Estudiantes' });
        fireEvent.click(within(tarjetas).getByRole('button', { name: /Pérez Pardo, Daniela/ }));

        const ficha = await screen.findByRole('dialog', { name: 'Pérez Pardo, Daniela' });
        expect(await within(ficha).findByText('Sin materias')).toBeInTheDocument();
        expect(within(ficha).getByText('Origen').nextSibling).toHaveTextContent('Verificado');
    });

    it('si la ficha no carga, lo dice dentro del diálogo', async () => {
        servidor({ rutas: { 'GET /estudiantes/*': errorHttp(404, {}) } });
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        fireEvent.click(within(tabla()).getByRole('button', { name: 'Zurita Vargas, Marco' }));
        const ficha = await screen.findByRole('dialog', { name: 'Zurita Vargas, Marco' });
        expect(await within(ficha).findByText('No se pudo cargar')).toBeInTheDocument();
    });
});

describe('Padrón · cargar inscripciones (criterios 6 a 9)', () => {
    it('sin archivo señala «Obligatorio» y no envía nada', async () => {
        const { pedidos } = servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();
        const dialogo = await abrirCarga();

        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cargar' }));

        expect(within(dialogo).getByText('Obligatorio')).toBeInTheDocument();
        expect(pedidos.cargas).toHaveLength(0);
    });

    it('con un archivo que no es .csv ni .xlsx señala «Solo .csv o .xlsx»', async () => {
        const { pedidos } = servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();
        const dialogo = await abrirCarga();

        elegirArchivo(dialogo, archivoDe('foto.png', 'image/png'));
        expect(within(dialogo).getByText('Solo .csv o .xlsx')).toBeInTheDocument();

        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cargar' }));
        expect(pedidos.cargas).toHaveLength(0);
    });

    it('envía la facultad y el archivo, y muestra las seis cifras con rechazos y conflictos por fila', async () => {
        const { pedidos } = servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();
        const dialogo = await abrirCarga();

        const archivo = archivoDe('fcyt.csv');
        fireEvent.change(within(dialogo).getByLabelText(/Facultad/), {
            target: { value: 'fce' },
        });
        elegirArchivo(dialogo, archivo);
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cargar' }));

        const resultado = await screen.findByRole('dialog', { name: 'Resultado de la carga' });
        expect(pedidos.cargas).toHaveLength(1);
        expect(pedidos.cargas[0]).toBeInstanceOf(FormData);
        expect(pedidos.cargas[0].get('facultad')).toBe('fce');
        expect(pedidos.cargas[0].get('archivo')).toBe(archivo);

        expect(resultado).toHaveTextContent('fcyt.csv');
        [
            ['Filas', '12'],
            ['Nuevos', '5'],
            ['Reutilizados', '4'],
            ['Ya inscritos', '1'],
            ['Rechazados', '2'],
            ['En conflicto', '1'],
        ].forEach(([etiqueta, valor]) =>
            expect(within(resultado).getByText(etiqueta).nextSibling).toHaveTextContent(valor)
        );

        const rechazadas = within(resultado).getByRole('region', { name: 'Filas rechazadas' });
        const [una, otra] = within(rechazadas).getAllByRole('listitem');
        expect(una).toHaveTextContent('Fila 4');
        expect(una).toHaveTextContent('El código universitario no es válido');
        expect(otra).toHaveTextContent('Fila 9');
        expect(otra).toHaveTextContent('El grupo no existe en la oferta');

        const enConflicto = within(resultado).getByRole('region', { name: 'Filas en conflicto' });
        expect(within(enConflicto).getByRole('listitem')).toHaveTextContent(
            'Fila 7202311988 · Documento distinto'
        );

        // Las cifras y la lista se vuelven a pedir.
        await waitFor(() =>
            expect(
                api.get.mock.calls.filter(([ruta]) => ruta === '/estudiantes/resumen')
            ).toHaveLength(2)
        );

        fireEvent.click(within(resultado).getByRole('button', { name: 'Ver conflictos' }));
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(screen.getByRole('tab', { name: /Conflictos/ })).toHaveAttribute(
            'aria-selected',
            'true'
        );
    });

    it('propone la facultad del filtro vigente', async () => {
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        fireEvent.click(
            within(screen.getByRole('group', { name: 'Filtrar por facultad' })).getByRole(
                'button',
                { name: /FHCE/ }
            )
        );
        const dialogo = await abrirCarga();
        expect(within(dialogo).getByLabelText(/Facultad/)).toHaveValue('fhce');
    });

    it('archivo rechazado entero: muestra el motivo, lo que falta y el orden esperado', async () => {
        const mensaje = 'Faltan columnas en el archivo.';
        servidor({
            rutas: {
                'POST /estudiantes/cargas': errorHttp(422, {
                    message: mensaje,
                    codigo: 'FALTAN_COLUMNAS',
                    orden_esperado: ORDEN,
                    columnas: ['documento_identidad', 'grupo'],
                    errors: { archivo: [mensaje] },
                }),
            },
        });
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();
        const dialogo = await abrirCarga();

        elegirArchivo(dialogo, archivoDe('lista.csv'));
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cargar' }));

        const alerta = await within(dialogo).findByRole('alert');
        expect(alerta).toHaveTextContent('Faltan columnas en el archivo');
        expect(alerta).toHaveTextContent('Faltan: documento_identidad, grupo');
        expect(
            within(within(alerta).getByRole('list', { name: 'Orden esperado' }))
                .getAllByRole('listitem')
                .map((li) => li.textContent)
        ).toEqual(ORDEN.map((columna, i) => `${i + 1}. ${columna}`));
        // El motivo no se repite bajo el campo.
        expect(within(dialogo).getAllByText('Faltan columnas en el archivo')).toHaveLength(1);
        expect(screen.queryByRole('dialog', { name: 'Resultado de la carga' })).toBeNull();

        // Al elegir otro archivo el rechazo desaparece.
        elegirArchivo(dialogo, archivoDe('otra.csv'));
        expect(within(dialogo).queryByRole('alert')).not.toBeInTheDocument();
    });

    it.each([
        ['ORDEN_DE_COLUMNAS', 'Las columnas del archivo están en otro orden.'],
        ['ARCHIVO_NO_CORRESPONDE', 'El archivo no es una lista de inscritos.'],
        ['ARCHIVO_VACIO', 'El archivo no tiene filas.'],
    ])('archivo rechazado entero (%s): motivo y orden esperado', async (codigo, mensaje) => {
        servidor({
            rutas: {
                'POST /estudiantes/cargas': errorHttp(422, {
                    message: mensaje,
                    codigo,
                    orden_esperado: ORDEN,
                    errors: { archivo: [mensaje] },
                }),
            },
        });
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();
        const dialogo = await abrirCarga();

        elegirArchivo(dialogo, archivoDe('lista.xlsx'));
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cargar' }));

        const alerta = await within(dialogo).findByRole('alert');
        expect(alerta).toHaveTextContent(mensaje.replace(/\.$/, ''));
        expect(alerta).not.toHaveTextContent('Faltan:');
        expect(within(alerta).getByRole('list', { name: 'Orden esperado' }).children).toHaveLength(
            6
        );
    });

    it('el 422 del formulario se pinta en su campo', async () => {
        servidor({
            rutas: {
                'POST /estudiantes/cargas': errorHttp(422, {
                    message: 'El archivo no puede superar los 7 MB. (y 1 error más)',
                    errors: {
                        archivo: ['El archivo no puede superar los 7 MB.'],
                        facultad: ['La facultad no existe.'],
                    },
                }),
            },
        });
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();
        const dialogo = await abrirCarga();

        elegirArchivo(dialogo, archivoDe('lista.csv'));
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cargar' }));

        expect(
            await within(dialogo).findByText('El archivo no puede superar los 7 MB')
        ).toBeInTheDocument();
        expect(within(dialogo).getByText('La facultad no existe')).toBeInTheDocument();
        expect(within(dialogo).queryByRole('alert')).not.toBeInTheDocument();
    });

    it('otro rechazo del servidor se muestra en el diálogo', async () => {
        servidor({
            rutas: {
                'POST /estudiantes/cargas': errorHttp(403, {
                    message: 'No tiene permiso para esta acción.',
                }),
            },
        });
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();
        const dialogo = await abrirCarga();

        elegirArchivo(dialogo, archivoDe('lista.csv'));
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cargar' }));

        expect(await within(dialogo).findByRole('alert')).toHaveTextContent(
            'No tiene permiso para esta acción'
        );
    });

    it('descarga la plantilla de la carga por facultad', async () => {
        descargar.mockResolvedValue('plantilla_inscritos.xlsx');
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();
        const dialogo = await abrirCarga();

        fireEvent.click(within(dialogo).getByRole('button', { name: /Plantilla/ }));

        expect(descargar).toHaveBeenCalledWith('/inscritos/plantilla', {
            parametros: { alcance: 'facultad' },
            nombre: 'plantilla_inscritos.xlsx',
        });
        expect(await screen.findByText('plantilla_inscritos.xlsx descargado')).toBeInTheDocument();
    });
});

describe('Padrón · conflictos (criterios 11 a 13)', () => {
    it('cada conflicto muestra su tipo, lo guardado y lo nuevo con la diferencia resaltada y quién cargó', async () => {
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await abrirConflictos();

        expect(api.get).toHaveBeenCalledWith('/estudiantes/conflictos', {
            params: { pagina: 1, por_pagina: 5 },
        });

        const documento = screen.getByText('Código 202311988').closest('section');
        expect(documento).toHaveTextContent('Documento distinto');
        expect(documento).toHaveTextContent('Inscripción en espera');
        expect([...documento.querySelectorAll('mark')].map((marca) => marca.textContent)).toEqual([
            '6592900',
            '6592910',
        ]);
        expect(documento).toHaveTextContent('Administración · 9 oct 2026');
        expect(documento).toHaveTextContent(
            'Blanco Coca Leticia · Arquitectura de Computadoras I, grupo 2'
        );

        const nombre = screen.getByText('Código 202312058').closest('section');
        expect(nombre).toHaveTextContent('Nombre distinto');
        // Solo se marca lo agregado al nombre.
        expect([...nombre.querySelectorAll('mark')].map((marca) => marca.textContent)).toEqual([
            'A.',
        ]);
        expect(screen.getByText('2 conflictos')).toBeInTheDocument();
    });

    it('sin conflictos lo dice', async () => {
        servidor({ conflictos: [] });
        montar({ usuario: usuarioDePrueba('Administrador') });

        fireEvent.click(await screen.findByRole('tab', { name: 'Conflictos (0)' }));
        expect(await screen.findByText('Sin conflictos')).toBeInTheDocument();
    });

    it('la cifra de conflictos pendientes lleva a la pestaña', async () => {
        servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await listaCargada();

        fireEvent.click(screen.getByRole('button', { name: /Conflictos pendientes/ }));
        expect(await screen.findByText('Código 202311988')).toBeInTheDocument();
        expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('«Mantener padrón» envía MANTENER_PADRON y el conflicto sale de la lista', async () => {
        const { pedidos } = servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await abrirConflictos();

        const tarjeta = screen.getByText('Código 202311988').closest('section');
        fireEvent.click(within(tarjeta).getByRole('button', { name: 'Mantener padrón' }));

        expect(await screen.findByText('202311988 sin cambios')).toBeInTheDocument();
        await waitFor(() => expect(screen.queryByText('Código 202311988')).not.toBeInTheDocument());
        expect(pedidos.resoluciones).toEqual([{ id: 1, resolucion: 'MANTENER_PADRON' }]);
        expect(await screen.findByRole('tab', { name: 'Conflictos (1)' })).toBeInTheDocument();
    });

    it('«Usar carga nueva» envía USAR_CARGA y avisa', async () => {
        const { pedidos } = servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await abrirConflictos();

        const tarjeta = screen.getByText('Código 202312058').closest('section');
        fireEvent.click(within(tarjeta).getByRole('button', { name: 'Usar carga nueva' }));

        expect(await screen.findByText('202312058 actualizado')).toBeInTheDocument();
        await waitFor(() => expect(screen.queryByText('Código 202312058')).not.toBeInTheDocument());
        expect(pedidos.resoluciones).toEqual([{ id: 3, resolucion: 'USAR_CARGA' }]);
    });

    it('si el documento pertenece a otro estudiante, muestra el rechazo y el conflicto sigue', async () => {
        servidor({
            rutas: {
                'POST /estudiantes/conflictos/*/resolucion': errorHttp(409, {
                    message: 'Ese documento pertenece a otro estudiante.',
                    codigo: 'DOCUMENTO_EN_USO',
                }),
            },
        });
        montar({ usuario: usuarioDePrueba('Administrador') });
        await abrirConflictos();

        const tarjeta = screen.getByText('Código 202311988').closest('section');
        fireEvent.click(within(tarjeta).getByRole('button', { name: 'Usar carga nueva' }));

        expect(await within(tarjeta).findByRole('alert')).toHaveTextContent(
            'Ese documento pertenece a otro estudiante'
        );
        expect(screen.getByText('Código 202311988')).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: 'Conflictos (2)' })).toBeInTheDocument();
    });

    it('un conflicto ya resuelto avisa y recarga la lista', async () => {
        const { pendientes } = servidor();
        montar({ usuario: usuarioDePrueba('Administrador') });
        await abrirConflictos();

        // Otra sesión lo resolvió antes.
        pendientes.splice(0, 1);
        const tarjeta = screen.getByText('Código 202311988').closest('section');
        fireEvent.click(within(tarjeta).getByRole('button', { name: 'Mantener padrón' }));

        expect(await screen.findByText('El conflicto ya fue resuelto')).toBeInTheDocument();
        await waitFor(() => expect(screen.queryByText('Código 202311988')).not.toBeInTheDocument());
    });

    it('pagina de a cinco y, si la última página queda vacía, vuelve a la anterior', async () => {
        const seis = Array.from({ length: 6 }, (_, i) => ({
            ...CONFLICTOS[0],
            id: 10 + i,
            codigo: `20231200${i}`,
        }));
        servidor({ conflictos: seis });
        montar({ usuario: usuarioDePrueba('Administrador') });

        fireEvent.click(await screen.findByRole('tab', { name: 'Conflictos (6)' }));
        await screen.findByText('Código 202312000');
        expect(screen.getAllByRole('button', { name: 'Mantener padrón' })).toHaveLength(5);
        expect(screen.getByText('1–5 de 6 conflictos')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        await screen.findByText('Código 202312005');
        fireEvent.click(screen.getByRole('button', { name: 'Mantener padrón' }));

        await screen.findByText('Código 202312000');
        expect(screen.getByText('5 conflictos')).toBeInTheDocument();
    });
});
