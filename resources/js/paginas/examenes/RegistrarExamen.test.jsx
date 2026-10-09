import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RegistrarExamen from './RegistrarExamen';
import { api } from '../../api/cliente';
import { olvidarEdificios } from '../../api/usarEdificios';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const INTRO = { id: 20, codigo: '2010010', nombre: 'Introducción a la Programación' };
const TALLER = { id: 53, codigo: '2010024', nombre: 'Taller de Ingeniería de Software' };

// GET /docente/grupos: los grupos que dicta en el período vigente.
const MIS_GRUPOS = [
    { id: 37, codigo: '2', asignatura: INTRO, facultad: 'FCyT', periodo: '2/2026', inscritos: 65 },
    {
        id: 100,
        codigo: '2',
        asignatura: TALLER,
        facultad: 'FCyT',
        periodo: '2/2026',
        inscritos: 46,
    },
    {
        id: 101,
        codigo: '5',
        asignatura: TALLER,
        facultad: 'FCyT',
        periodo: '2/2026',
        inscritos: 30,
    },
];
const TIPOS = [
    { valor: 'PRIMER_PARCIAL', etiqueta: 'Primer parcial' },
    { valor: 'SEGUNDO_PARCIAL', etiqueta: 'Segundo parcial' },
    { valor: 'FINAL', etiqueta: 'Examen final' },
    { valor: 'SEGUNDA_INSTANCIA', etiqueta: 'Segunda instancia' },
];
const PERIODOS = [
    { id: 1, codigo: '2/2026', fecha_inicio: '2026-08-10', fecha_fin: '2026-12-26', ventanas: {} },
];
const PLANTILLAS = [
    { id: 1, texto: 'Documento de identidad a la vista', predefinida: true, propia: false },
    { id: 2, texto: 'Sin celular', predefinida: true, propia: false },
    { id: 7, texto: 'Hoja de fórmulas A4', predefinida: false, propia: true },
];
const DOCENTES = ['Salazar Serrudo Carla', 'Ustariz Vargas Hernan', 'Montaño Quiroga Víctor'];
// Diez grupos de otros docentes: la lista lleva buscador.
const OTROS_INTRO = Array.from({ length: 10 }, (_, i) => ({
    id: 200 + i,
    codigo: String(i + 3),
    docente: i === 9 ? null : DOCENTES[i % 3],
    inscritos: 10 * (i + 1),
    periodo: '2/2026',
}));
const OPCIONES_GRUPOS = {
    20: {
        propios: [
            {
                id: 37,
                codigo: '2',
                docente: 'Blanco Coca Leticia',
                inscritos: 65,
                periodo: '2/2026',
            },
        ],
        otros: OTROS_INTRO,
    },
    53: {
        propios: [
            {
                id: 100,
                codigo: '2',
                docente: 'Blanco Coca Leticia',
                inscritos: 46,
                periodo: '2/2026',
            },
            {
                id: 101,
                codigo: '5',
                docente: 'Blanco Coca Leticia',
                inscritos: 30,
                periodo: '2/2026',
            },
        ],
        otros: [
            {
                id: 1023,
                codigo: '4',
                docente: 'Rodriguez Bilbao Erika',
                inscritos: 44,
                periodo: '2/2026',
            },
        ],
    },
};
const AULAS = [
    {
        id: 408,
        nombre: '004',
        edificio_id: 43,
        edificio: 'Bloque Posgrado',
        piso: null,
        facultad: 'FHCE',
    },
    {
        id: 78,
        nombre: '606',
        edificio_id: 18,
        edificio: 'Departamento de Biología',
        piso: null,
        facultad: 'FCyT',
    },
    {
        id: 31,
        nombre: '691A',
        edificio_id: 2,
        edificio: 'Edificio Académico 2',
        piso: '1° Piso',
        facultad: 'FCyT',
    },
    {
        id: 32,
        nombre: '691B',
        edificio_id: 2,
        edificio: 'Edificio Académico 2',
        piso: '1° Piso',
        facultad: 'FCyT',
    },
    { id: 450, nombre: '003', edificio_id: null, edificio: null, piso: null, facultad: 'FHCE' },
];
const cuadro = (x) => [
    [x, 0],
    [x + 1, 0],
    [x + 1, 1],
    [x, 1],
];
const EDIFICIOS = {
    data: [
        {
            id: 2,
            facultad: 'FCyT',
            nombre: 'Edificio Académico 2',
            poligono: cuadro(0),
            centro: [0.5, 0.5],
            aulas: ['691A', '691B'],
            pisos: [],
        },
        {
            id: 18,
            facultad: 'FCyT',
            nombre: 'Departamento de Biología',
            poligono: cuadro(2),
            centro: [2.5, 0.5],
            aulas: ['606'],
            pisos: [],
        },
        {
            id: 43,
            facultad: 'FHCE',
            nombre: 'Bloque Posgrado',
            poligono: cuadro(4),
            centro: [4.5, 0.5],
            aulas: ['004'],
            pisos: [],
        },
    ],
    meta: { caja: { lon: [0, 5], lat: [0, 1] } },
};
const OPCIONES_AULAS = {
    sugeridas: [32, 31],
    compartidas: { 31: ['Cálculo I · Primer parcial'], 78: ['Análisis Numérico · Primer parcial'] },
};

// GET /examenes/{id}, como lo devuelve la API (plan, 9 · B2).
const DETALLE = {
    id: 4,
    asignatura: INTRO,
    tipo: 'SEGUNDO_PARCIAL',
    tipo_texto: 'Segundo parcial',
    fecha: '2026-11-23',
    hora: '14:15',
    duracion: 120,
    grupos: ['2', '3'],
    inscritos: 75,
    aulas: ['691B'],
    estado: 'Falta habilitar',
    accion: { clave: 'habilitar', paso: null },
    propio: true,
    registrado_por: 'Blanco Coca Leticia',
    normas: 'Mochilas al frente.',
    normas_marcadas: [
        { id: 11, plantilla_id: 2, texto: 'Sin celular' },
        { id: 12, plantilla_id: null, texto: 'Solo lápiz' },
    ],
    grupos_detalle: [
        { id: 37, codigo: '2', docente: 'Blanco Coca Leticia', inscritos: 65, propio: true },
        { id: 200, codigo: '3', docente: 'Salazar Serrudo Carla', inscritos: 10, propio: false },
    ],
    aulas_detalle: [
        {
            aula_id: 32,
            nombre: '691B',
            ubicacion: 'Edificio Académico 2 · 1° Piso',
            edificio_id: 2,
        },
    ],
};

// Lo que el servidor devuelve al guardar: el detalle armado con lo enviado.
function guardado(cuerpo, id = 9) {
    const asignatura = [INTRO, TALLER].find((a) => a.id === cuerpo.asignatura_id);
    const opciones = OPCIONES_GRUPOS[cuerpo.asignatura_id];
    const grupos = [...opciones.propios, ...opciones.otros].filter((g) =>
        cuerpo.grupos.includes(g.id)
    );
    const aulas = AULAS.filter((a) => cuerpo.aulas.includes(a.id));
    return {
        id,
        asignatura,
        tipo: cuerpo.tipo,
        tipo_texto: TIPOS.find((t) => t.valor === cuerpo.tipo).etiqueta,
        fecha: cuerpo.fecha,
        hora: cuerpo.hora_inicio,
        duracion: cuerpo.duracion_minutos,
        grupos: grupos.map((g) => g.codigo),
        inscritos: grupos.reduce((s, g) => s + g.inscritos, 0),
        aulas: aulas.map((a) => a.nombre),
        estado: aulas.length ? 'Falta habilitar' : 'Faltan aulas',
        propio: true,
        registrado_por: 'Blanco Coca Leticia',
        normas: cuerpo.normas || null,
        normas_marcadas: [
            ...DETALLE.normas_marcadas.filter((n) =>
                (cuerpo.normas_conservadas ?? []).includes(n.id)
            ),
            ...cuerpo.normas_marcadas.map((plantilla, i) => ({
                id: 50 + i,
                plantilla_id: plantilla,
                texto: PLANTILLAS.find((p) => p.id === plantilla).texto,
            })),
        ],
        grupos_detalle: grupos.map((g) => ({ ...g, propio: opciones.propios.includes(g) })),
        aulas_detalle: aulas.map((a) => ({
            aula_id: a.id,
            nombre: a.nombre,
            edificio_id: a.edificio_id,
        })),
    };
}

let detalle;

const montar = (ruta = '/examenes/nuevo', usuario = usuarioDePrueba('Docente')) =>
    renderConSesion(<RegistrarExamen />, { usuario, ruta });
const campo = (etiqueta) => screen.findByLabelText(etiqueta);
const cambiar = (elemento, value) => fireEvent.change(elemento, { target: { value } });
const pulsar = (nombre) => fireEvent.click(screen.getByRole('button', { name: nombre }));
const casilla = (nombre) => screen.findByRole('checkbox', { name: nombre });

// Llena el primer paso con datos válidos y continúa a Grupos.
async function llenarExamen({ fecha = '2026-10-20', hora = '08:15', duracion = '90' } = {}) {
    cambiar(await campo(/^Fecha/), fecha);
    cambiar(await campo(/^Hora de inicio/), hora);
    cambiar(await campo(/^Duración/), duracion);
    pulsar(/Continuar/);
    await screen.findByRole('heading', { name: '2. Grupos' });
}

async function irAAulas(datos) {
    await llenarExamen(datos);
    await casilla(/^Grupo 2 · Blanco/);
    pulsar(/Continuar/);
    await screen.findByRole('heading', { name: '3. Aulas' });
    await screen.findByRole('list', { name: 'Aulas' });
}

beforeEach(() => {
    olvidarEdificios();
    detalle = DETALLE;
    simularApi(api, {
        'GET /docente/grupos': { data: MIS_GRUPOS, meta: { periodo: '2/2026', asignaturas: 2 } },
        'GET /examenes/tipos': { data: TIPOS },
        'GET /periodos/vigentes': { data: PERIODOS, principal: '2/2026' },
        'GET /normas/plantillas': { data: PLANTILLAS },
        'GET /examenes/opciones/grupos': ({ params }) => OPCIONES_GRUPOS[params.asignatura_id],
        'GET /examenes/opciones/aulas': OPCIONES_AULAS,
        'GET /aulas': { data: AULAS },
        'GET /edificios': EDIFICIOS,
        'GET /examenes/*': () => ({ data: detalle }),
        'POST /examenes': ({ data }) => ({ data: guardado(data), message: 'Examen registrado.' }),
        'PUT /examenes/*': ({ data }) => ({ data: guardado(data, 4), message: 'Examen guardado.' }),
    });
});

describe('RegistrarExamen · paso 1, examen', () => {
    it('ofrece solo las asignaturas que dicta y los cuatro tipos', async () => {
        montar();

        const asignatura = await campo(/^Asignatura/);
        expect(
            within(asignatura)
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual([
            'Introducción a la Programación · 2010010',
            'Taller de Ingeniería de Software · 2010024',
        ]);
        expect(
            within(screen.getByLabelText(/^Tipo/))
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual(['Primer parcial', 'Segundo parcial', 'Examen final', 'Segunda instancia']);
        expect(screen.getByRole('heading', { name: 'Registrar examen' })).toBeInTheDocument();
        expect(screen.getByText('Período 2/2026')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Exámenes/ })).toHaveAttribute('href', '/examenes');
        // Sin el paso «Quién atiende».
        const pasos = screen.getAllByRole('list')[0];
        expect(
            within(pasos)
                .getAllByRole('listitem')
                .map((p) => p.textContent)
        ).toEqual(['1Examen', '2Grupos', '3Aulas', '4Listo']);
    });

    it('sin fecha u hora no avanza y señala «Obligatorio»', async () => {
        montar();
        await campo(/^Fecha/);

        pulsar(/Continuar/);

        expect(screen.getAllByText('Obligatorio')).toHaveLength(2);
        expect(screen.getByLabelText(/^Fecha/)).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByLabelText(/^Hora de inicio/)).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByRole('heading', { name: '1. Examen' })).toBeInTheDocument();
    });

    it.each(['10', '481', 'abc', ''])('la duración «%s» no avanza', async (valor) => {
        montar();
        cambiar(await campo(/^Fecha/), '2026-10-20');
        cambiar(await campo(/^Hora de inicio/), '08:15');
        cambiar(await campo(/^Duración/), valor);

        pulsar(/Continuar/);

        expect(screen.getByText('Entre 15 y 480')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: '1. Examen' })).toBeInTheDocument();
    });

    it('la fecha fuera del período de los grupos se señala en el campo', async () => {
        montar();
        cambiar(await campo(/^Fecha/), '2027-01-15');
        cambiar(await campo(/^Hora de inicio/), '08:15');

        pulsar(/Continuar/);

        expect(screen.getByText('Fuera del período 2/2026')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: '1. Examen' })).toBeInTheDocument();

        cambiar(screen.getByLabelText(/^Fecha/), '2026-12-26');
        pulsar(/Continuar/);
        expect(await screen.findByRole('heading', { name: '2. Grupos' })).toBeInTheDocument();
    });

    it('sin asignaturas no hay asistente', async () => {
        api.get.mockImplementation(async (url) => {
            if (url === '/docente/grupos') return { data: { data: [], meta: {} } };
            if (url === '/examenes/tipos') return { data: { data: TIPOS } };
            throw errorHttp(404);
        });
        montar();

        expect(await screen.findByText('Sin asignaturas')).toBeInTheDocument();
    });
});

describe('RegistrarExamen · paso 2, grupos', () => {
    it('trae marcados los propios, suma los de otros docentes y muestra el total', async () => {
        montar();
        await llenarExamen();

        expect(await casilla(/^Grupo 2 · Blanco Coca Leticia/)).toBeChecked();
        expect(screen.getByText('65 inscritos')).toBeInTheDocument();
        expect(screen.getByText(/Otros grupos de la asignatura · 0 de 10/)).toBeInTheDocument();
        expect(await casilla('Grupo 12 · Sin docente')).not.toBeChecked();

        fireEvent.click(await casilla(/^Grupo 3 · Salazar/));
        expect(screen.getByText('75 inscritos')).toBeInTheDocument();
        expect(screen.getByText(/Otros grupos de la asignatura · 1 de 10/)).toBeInTheDocument();

        pulsar('Todos');
        expect(screen.getByText(/Otros grupos de la asignatura · 10 de 10/)).toBeInTheDocument();
        expect(screen.getByText('615 inscritos')).toBeInTheDocument();

        pulsar('Ninguno');
        expect(screen.getByText('65 inscritos')).toBeInTheDocument();
    });

    it('busca por grupo o por docente; «Todos» suma solo lo buscado', async () => {
        montar();
        await llenarExamen();
        await casilla(/^Grupo 2 · Blanco/);

        cambiar(screen.getByLabelText('Buscar grupo'), 'montano');
        expect(screen.getAllByRole('checkbox', { name: /Montaño/ })).toHaveLength(3);
        expect(screen.queryByRole('checkbox', { name: /Salazar/ })).not.toBeInTheDocument();

        pulsar('Todos');
        expect(screen.getByText(/Otros grupos de la asignatura · 3 de 10/)).toBeInTheDocument();

        cambiar(screen.getByLabelText('Buscar grupo'), 'grupo 12');
        expect(screen.getByRole('checkbox', { name: 'Grupo 12 · Sin docente' })).toBeVisible();

        cambiar(screen.getByLabelText('Buscar grupo'), 'zzz');
        expect(screen.getByText('Sin resultados')).toBeInTheDocument();
    });

    it('sin al menos un grupo no se puede continuar', async () => {
        montar();
        await llenarExamen();

        fireEvent.click(await casilla(/^Grupo 2 · Blanco/));

        expect(screen.getByRole('button', { name: /Continuar/ })).toBeDisabled();
        expect(screen.getByText('0 inscritos')).toBeInTheDocument();
    });

    it('al cambiar de asignatura vuelven a valer sus grupos propios', async () => {
        montar();
        cambiar(await campo(/^Asignatura/), '53');
        await llenarExamen();

        expect(await casilla(/^Grupo 2 · Blanco/)).toBeChecked();
        expect(await casilla(/^Grupo 5 · Blanco/)).toBeChecked();
        expect(screen.getByText('76 inscritos')).toBeInTheDocument();
        // Con un solo grupo de otro docente no hay «Todos» ni buscador.
        expect(screen.queryByRole('button', { name: 'Todos' })).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Buscar grupo')).not.toBeInTheDocument();
    });
});

describe('RegistrarExamen · paso 3, aulas', () => {
    it('pone primero las sugeridas y avisa las compartidas sin impedir elegirlas', async () => {
        montar();
        await irAAulas();

        const filas = within(screen.getByRole('list', { name: 'Aulas' })).getAllByRole('listitem');
        // Sugeridas, después las de la facultad de la asignatura, después el resto.
        expect(filas.map((f) => f.textContent.slice(0, 4).trim())).toEqual([
            '691A',
            '691B',
            '606D',
            '004B',
            '003S',
        ]);
        expect(filas[0]).toHaveTextContent('Sugerida');
        expect(filas[0]).toHaveTextContent('Compartida · Cálculo I · Primer parcial');
        expect(filas[1]).toHaveTextContent('Sugerida');
        expect(filas[1]).not.toHaveTextContent('Compartida');
        expect(filas[2]).not.toHaveTextContent('Sugerida');
        expect(filas[2]).toHaveTextContent('Compartida · Análisis Numérico · Primer parcial');
        expect(filas[3]).toHaveTextContent('Bloque Posgrado · FHCE');
        expect(filas[4]).toHaveTextContent('Sin edificio · FHCE');
        expect(screen.getByText('5 aulas')).toBeInTheDocument();

        const pedido = api.get.mock.calls.find(([url]) => url === '/examenes/opciones/aulas');
        expect(pedido[1].params).toEqual({
            grupos: [37],
            fecha: '2026-10-20',
            hora_inicio: '08:15',
            duracion_minutos: 90,
        });

        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691A' }));
        expect(screen.getByRole('checkbox', { name: 'Aula 691A' })).toBeChecked();
        expect(
            within(screen.getByRole('list', { name: 'Aulas elegidas' })).getByText('691A')
        ).toBeInTheDocument();
        expect(screen.getByText('1 elegida · 65 inscritos')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Registrar examen' })).toBeEnabled();
    });

    it('filtra por edificio, sugeridas, elegidas y texto', async () => {
        montar();
        await irAAulas();
        const filtro = screen.getByLabelText('Edificio');
        const visibles = () =>
            within(screen.getByRole('list', { name: 'Aulas' }))
                .getAllByRole('checkbox')
                .map((c) => c.getAttribute('aria-label'));

        expect(
            within(filtro)
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual([
            'Todos los edificios (5)',
            'Sugeridas · aulas de los grupos (2)',
            'Elegidas (0)',
            'Departamento de Biología (1)',
            'Edificio Académico 2 (2)',
            'Bloque Posgrado · FHCE (1)',
        ]);

        cambiar(filtro, 'sugeridas');
        expect(visibles()).toEqual(['Aula 691A', 'Aula 691B']);

        cambiar(filtro, '18');
        expect(visibles()).toEqual(['Aula 606']);
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 606' }));

        cambiar(filtro, 'elegidas');
        expect(visibles()).toEqual(['Aula 606']);
        pulsar('Quitar el aula 606');
        expect(screen.getByText('Sin resultados')).toBeInTheDocument();

        cambiar(filtro, '');
        cambiar(screen.getByLabelText('Buscar aula'), 'posgrado');
        expect(visibles()).toEqual(['Aula 004']);
        expect(screen.getByText('1 aula')).toBeInTheDocument();
    });

    it('el mapa resalta los edificios de las aulas elegidas', async () => {
        const { container } = montar();
        await irAAulas();
        const resaltados = () => container.querySelectorAll('svg polygon[fill="#2563eb"]').length;

        expect(screen.getByRole('img', { name: 'Mapa de edificios del campus' })).toBeVisible();
        expect(resaltados()).toBe(0);

        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691A' }));
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        expect(resaltados()).toBe(1);

        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 004' }));
        expect(resaltados()).toBe(2);
    });
});

describe('RegistrarExamen · guardar', () => {
    it('registra de una vez con normas, grupos y aulas y muestra el resumen', async () => {
        montar();
        fireEvent.click(await casilla(/Sin celular/));
        fireEvent.click(await casilla(/Hoja de fórmulas A4/));
        cambiar(screen.getByLabelText('Otras normas'), ' Mochilas al frente ');
        cambiar(screen.getByLabelText(/^Tipo/), 'FINAL');
        await irAAulas({ duracion: '120' });
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));

        pulsar('Registrar examen');

        expect(await screen.findByText('Examen registrado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledTimes(1);
        expect(api.post).toHaveBeenCalledWith('/examenes', {
            asignatura_id: 20,
            tipo: 'FINAL',
            fecha: '2026-10-20',
            hora_inicio: '08:15',
            duracion_minutos: 120,
            normas: 'Mochilas al frente',
            normas_marcadas: [2, 7],
            grupos: [37],
            aulas: [32],
        });

        expect(
            screen.getByRole('heading', {
                name: 'Examen final · Introducción a la Programación',
                level: 1,
            })
        ).toBeInTheDocument();
        expect(
            screen.getByText(/martes 20 de octubre, 08:15 · 120 min · 1 grupo \(2\) · 65 inscritos/)
        ).toBeInTheDocument();
        expect(screen.getByText('1 aula: 691B')).toBeInTheDocument();
        const normas = within(screen.getByRole('list', { name: 'Normas' })).getAllByRole(
            'listitem'
        );
        expect(normas.map((n) => n.textContent)).toEqual(['Sin celular', 'Hoja de fórmulas A4']);
        expect(screen.getByText('Mochilas al frente')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Ver exámenes' })).toHaveAttribute(
            'href',
            '/examenes'
        );
        expect(screen.getByRole('link', { name: /Habilitar estudiantes/ })).toHaveAttribute(
            'href',
            '/habilitacion?examen=9'
        );
    });

    it('sin aulas ni normas también se registra y queda «Faltan aulas»', async () => {
        montar();
        await irAAulas();

        pulsar('Registrar examen');

        expect(await screen.findByText('Examen registrado')).toBeInTheDocument();
        expect(api.post.mock.calls[0][1]).toMatchObject({
            normas: '',
            normas_marcadas: [],
            aulas: [],
        });
        expect(screen.getByText('Faltan aulas')).toBeInTheDocument();
        expect(screen.getByText('Sin aulas')).toBeInTheDocument();
        expect(screen.getByText('Sin normas')).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /Habilitar estudiantes/ })
        ).not.toBeInTheDocument();
    });

    it('tras registrar, volver a un paso guarda sobre el mismo examen', async () => {
        montar();
        await irAAulas();
        pulsar('Registrar examen');
        await screen.findByText('Examen registrado');

        pulsar(/Aulas/);
        await screen.findByRole('list', { name: 'Aulas' });
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691A' }));
        pulsar('Guardar examen');

        expect(await screen.findByText('Examen guardado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledTimes(1);
        expect(api.put).toHaveBeenCalledWith(
            '/examenes/9',
            expect.objectContaining({ aulas: [31], normas_conservadas: [] })
        );
    });

    it('el grupo que ya tiene un examen del mismo tipo vuelve al paso de grupos con el mensaje', async () => {
        api.post.mockRejectedValueOnce(
            errorHttp(422, {
                message: 'El grupo 3 ya tiene un primer parcial.',
                errors: { grupos: ['El grupo 3 ya tiene un primer parcial.'] },
            })
        );
        montar();
        await irAAulas();

        pulsar('Registrar examen');

        expect(await screen.findByRole('heading', { name: '2. Grupos' })).toBeInTheDocument();
        expect(screen.getByRole('alert')).toHaveTextContent(
            'El grupo 3 ya tiene un primer parcial.'
        );

        // Al cambiar los grupos el mensaje se va.
        fireEvent.click(await casilla(/^Grupo 3 · Salazar/));
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('los 422 de los datos vuelven al primer paso, cada uno en su campo', async () => {
        api.post.mockRejectedValueOnce(
            errorHttp(422, {
                message: 'La fecha debe estar dentro del período 2/2026.',
                errors: {
                    fecha: ['La fecha debe estar dentro del período 2/2026.'],
                    duracion_minutos: ['Entre 15 y 480'],
                    normas_marcadas: ['La norma no existe.'],
                    'aulas.0': ['El aula no existe.'],
                },
            })
        );
        montar();
        await irAAulas();

        pulsar('Registrar examen');

        expect(await screen.findByRole('heading', { name: '1. Examen' })).toBeInTheDocument();
        expect(screen.getByText('La fecha debe estar dentro del período 2/2026.')).toBeVisible();
        expect(screen.getByLabelText(/^Fecha/)).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByText('Entre 15 y 480')).toBeInTheDocument();
        expect(screen.getByText('La norma no existe.')).toBeInTheDocument();

        cambiar(screen.getByLabelText(/^Fecha/), '2026-10-21');
        expect(
            screen.queryByText('La fecha debe estar dentro del período 2/2026.')
        ).not.toBeInTheDocument();
    });

    it('el 422 de un aula queda en el paso de aulas', async () => {
        api.post.mockRejectedValueOnce(
            errorHttp(422, {
                message: 'El aula no existe.',
                errors: { 'aulas.0': ['El aula no existe.'] },
            })
        );
        montar();
        await irAAulas();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));

        pulsar('Registrar examen');

        expect(await screen.findByRole('alert')).toHaveTextContent('El aula no existe.');
        expect(screen.getByRole('heading', { name: '3. Aulas' })).toBeInTheDocument();
    });
});

describe('RegistrarExamen · edición', () => {
    it('abre el examen en el paso pedido con sus datos', async () => {
        montar('/examenes/nuevo?examen=4&paso=2');

        expect(
            await screen.findByRole('heading', {
                name: 'Segundo parcial · Introducción a la Programación',
            })
        ).toBeInTheDocument();
        expect(await screen.findByRole('heading', { name: '3. Aulas' })).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/examenes/4', expect.anything());
        expect(await screen.findByRole('checkbox', { name: 'Aula 691B' })).toBeChecked();
        expect(screen.getByText('1 elegida · 75 inscritos')).toBeInTheDocument();
        // Las compartidas no cuentan el propio examen.
        await waitFor(() =>
            expect(
                api.get.mock.calls.find(([url]) => url === '/examenes/opciones/aulas')[1].params
            ).toEqual({
                grupos: [37, 200],
                fecha: '2026-11-23',
                hora_inicio: '14:15',
                duracion_minutos: 120,
                examen_id: 4,
            })
        );
    });

    it('vuelve a mostrar lo marcado y lo escrito, y guarda los cambios', async () => {
        montar('/examenes/nuevo?examen=4');

        expect(await campo(/^Fecha/)).toHaveValue('2026-11-23');
        expect(screen.getByLabelText(/^Hora de inicio/)).toHaveValue('14:15');
        expect(screen.getByLabelText(/^Duración/)).toHaveValue('120');
        expect(screen.getByLabelText(/^Tipo/)).toHaveValue('SEGUNDO_PARCIAL');
        expect(screen.getByLabelText('Otras normas')).toHaveValue('Mochilas al frente.');
        expect(await casilla(/Sin celular/)).toBeChecked();
        expect(await casilla(/Documento de identidad/)).not.toBeChecked();
        expect(await casilla(/Solo lápiz/)).toBeChecked();

        fireEvent.click(await casilla(/Documento de identidad/));
        fireEvent.click(await casilla(/Solo lápiz/));
        cambiar(screen.getByLabelText(/^Duración/), '100');
        pulsar(/Continuar/);
        expect(await casilla(/^Grupo 3 · Salazar/)).toBeChecked();
        expect(screen.getByText('75 inscritos')).toBeInTheDocument();
        pulsar(/Continuar/);
        await screen.findByRole('list', { name: 'Aulas' });
        pulsar('Guardar examen');

        expect(await screen.findByText('Examen guardado')).toBeInTheDocument();
        expect(api.put).toHaveBeenCalledWith('/examenes/4', {
            asignatura_id: 20,
            tipo: 'SEGUNDO_PARCIAL',
            fecha: '2026-11-23',
            hora_inicio: '14:15',
            duracion_minutos: 100,
            normas: 'Mochilas al frente.',
            normas_marcadas: [2, 1],
            normas_conservadas: [],
            grupos: [37, 200],
            aulas: [32],
        });
        expect(api.post).not.toHaveBeenCalled();
    });

    it('con ingresos registrados muestra el rechazo del servidor', async () => {
        api.put.mockRejectedValueOnce(
            errorHttp(409, {
                message:
                    'El examen ya tiene ingresos registrados: solo se pueden cambiar las normas.',
                codigo: 'EXAMEN_CON_INGRESOS',
            })
        );
        montar('/examenes/nuevo?examen=4&paso=2');
        fireEvent.click(await screen.findByRole('checkbox', { name: 'Aula 691A' }));

        pulsar('Guardar examen');

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'El examen ya tiene ingresos registrados: solo se pueden cambiar las normas.'
        );
        expect(screen.getByRole('heading', { name: '3. Aulas' })).toBeInTheDocument();
    });

    it('al docente de un grupo incluido le muestra el examen sin poder modificarlo', async () => {
        detalle = { ...DETALLE, propio: false, registrado_por: 'Rodriguez Bilbao Erika' };
        montar('/examenes/nuevo?examen=4&paso=2');

        expect(await screen.findByText('Registrado por Rodriguez Bilbao Erika')).toBeVisible();
        expect(screen.getByText('1 aula: 691B')).toBeInTheDocument();
        expect(screen.getByText('Mochilas al frente.')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Guardar|Continuar/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
    });

    it.each([
        [403, 'Este examen no es tuyo.', 'Este examen no es tuyo'],
        [404, 'Examen no encontrado.', 'Examen no encontrado'],
    ])('un %i al abrirlo muestra la negativa', async (estado, message, texto) => {
        api.get.mockImplementation(async (url) => {
            if (url === '/examenes/4') throw errorHttp(estado, { message, alcance: true });
            if (url === '/docente/grupos') return { data: { data: MIS_GRUPOS, meta: {} } };
            if (url === '/examenes/tipos') return { data: { data: TIPOS } };
            throw errorHttp(404);
        });
        montar('/examenes/nuevo?examen=4');

        expect(await screen.findByRole('alert')).toHaveTextContent(texto);
        expect(screen.queryByRole('button', { name: /Continuar/ })).not.toBeInTheDocument();
    });

    it('si los catálogos no cargan ofrece reintentar', async () => {
        api.get.mockRejectedValueOnce(errorHttp(500));
        montar();

        fireEvent.click(await screen.findByRole('button', { name: 'Reintentar' }));
        expect(await campo(/^Fecha/)).toBeInTheDocument();
    });
});
