import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RegistrarExamen from './RegistrarExamen';
import { api } from '../../api/cliente';
import { olvidarEdificios } from '../../api/usarEdificios';
import {
    errorHttp,
    listaDe,
    renderConSesion,
    respuestaDiferida,
    simularApi,
    usuarioDePrueba,
} from '../../test/apoyo';

vi.mock('../../api/cliente');

// Las fechas de estas pruebas son de octubre de 2026: el reloj queda fijo
// antes de ellas, porque el formulario no deja registrar en el pasado.
beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-12T07:00:00'));
});
afterEach(() => {
    vi.useRealTimers();
});

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
    { valor: 'MESA', etiqueta: 'Examen de mesa' },
];
const PERIODOS = [
    { id: 1, codigo: '2/2026', fecha_inicio: '2026-08-10', fecha_fin: '2026-12-26', ventanas: {} },
];
const PLANTILLAS = [
    { id: 1, texto: 'C.I. a la vista', predefinida: true, propia: false },
    { id: 2, texto: 'Sin celular', predefinida: true, propia: false },
    { id: 7, texto: 'Hoja de fórmulas A4', predefinida: false, propia: true },
];
const DOCENTES = ['Salazar Serrudo Carla', 'Ustariz Vargas Hernan', 'Montaño Quiroga Víctor'];
// Diez grupos de otros docentes: la lista lleva buscador.
// Cuenta de cada docente; el tercero no tiene cuenta activa.
const CUENTAS = [61, 62, null];
const OTROS_INTRO = Array.from({ length: 10 }, (_, i) => ({
    id: 200 + i,
    codigo: String(i + 3),
    docente: i === 9 ? null : DOCENTES[i % 3],
    docente_usuario_id: i === 9 ? null : CUENTAS[i % 3],
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
                docente_usuario_id: 7,
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
                docente_usuario_id: 7,
                inscritos: 46,
                periodo: '2/2026',
            },
            {
                id: 101,
                codigo: '5',
                docente: 'Blanco Coca Leticia',
                docente_usuario_id: 7,
                inscritos: 30,
                periodo: '2/2026',
            },
        ],
        otros: [
            {
                id: 1023,
                codigo: '4',
                docente: 'Rodriguez Bilbao Erika',
                docente_usuario_id: 63,
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
    {
        id: 33,
        nombre: '691C',
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
            aulas: ['691A', '691B', '691C'],
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
    clases: {
        32: [
            {
                asignatura: 'Introducción a la Programación',
                grupo: '2',
                docente: 'Blanco Coca Leticia',
                inicio: '08:15',
                fin: '09:45',
                propio: true,
                auxiliatura: false,
            },
        ],
        33: [
            {
                asignatura: 'Física I',
                grupo: '4',
                docente: 'Rojas Paz Ana',
                inicio: '09:00',
                fin: '10:30',
                propio: false,
                auxiliatura: true,
            },
        ],
    },
    examenes: {
        31: [
            {
                examen: 'Cálculo I · Primer parcial',
                docente: 'Mendez Paz Luis',
                inicio: '08:15',
                fin: '09:45',
                mismo_grupo: false,
            },
        ],
        78: [
            {
                examen: 'Análisis Numérico · Primer parcial',
                docente: null,
                inicio: '07:00',
                fin: '08:30',
                mismo_grupo: true,
            },
        ],
    },
    compartidas: { 31: ['Cálculo I · Primer parcial'], 78: ['Análisis Numérico · Primer parcial'] },
};

// GET /auxiliares: los de la cuenta; con `buscar`, cualquiera.
const AUXILIARES = [
    { id: 9, nombre: 'Rojas Ledezma Andrea', usuario: 'andrea.rojas', mio: true },
    { id: 12, nombre: 'Vargas Arce Sergio', usuario: 'sergio.vargas', mio: true },
];
const AJENOS = [{ id: 30, nombre: 'Mamani Torrez Diego', usuario: 'diego.mamani', mio: false }];
const PERSONAS = Object.fromEntries(
    [
        ...AUXILIARES,
        ...AJENOS,
        { id: 7, nombre: 'Blanco Coca Leticia' },
        { id: 61, nombre: 'Salazar Serrudo Carla' },
        { id: 62, nombre: 'Ustariz Vargas Hernan' },
    ].map((p) => [p.id, p.nombre])
);

// GET /examenes/{id}, como lo devuelve la API (ruta 44).
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
    estado: 'Falta habilitar estudiantes',
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
            encargado: { id: 12, nombre: 'Vargas Arce Sergio', es_docente: false },
            apoyo: [{ id: 9, nombre: 'Rojas Ledezma Andrea' }],
            usa_codigo: true,
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
    const aulas = cuerpo.aulas.map((a) => ({
        ...AULAS.find((x) => x.id === a.aula_id),
        encargado_id: a.encargado_id,
        apoyo: a.apoyo,
    }));
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
        estado: aulas.length ? 'Falta habilitar estudiantes' : 'Faltan aulas',
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
            ubicacion: [a.edificio, a.piso].filter(Boolean).join(' · ') || null,
            edificio_id: a.edificio_id,
            encargado: {
                id: a.encargado_id,
                nombre: PERSONAS[a.encargado_id] ?? `Cuenta ${a.encargado_id}`,
                es_docente: [7, 61, 62].includes(a.encargado_id),
            },
            apoyo: a.apoyo.map((id) => ({ id, nombre: PERSONAS[id] })),
            usa_codigo: false,
        })),
    };
}

let detalle;
// GET /examenes/opciones/choques: los estudiantes con otro examen a esa hora.
let choques;
// GET /examenes/opciones/ocupados: los grupos que ya tienen un examen de ese tipo.
let ocupados;

// Un examen nuevo empieza sin asignatura: salvo que la prueba diga lo contrario,
// `montar` elige Introducción a la Programación (la que antes venía elegida).
let generacion = 0;
const montarVacio = (ruta = '/examenes/nuevo', usuario = usuarioDePrueba('Docente')) => {
    generacion += 1;
    return renderConSesion(<RegistrarExamen />, { usuario, ruta });
};
let eleccionInicial = Promise.resolve();
const montar = (ruta = '/examenes/nuevo', usuario = usuarioDePrueba('Docente')) => {
    const resultado = montarVacio(ruta, usuario);
    const mia = generacion;
    const esNuevo = !/[?&](examen|copiar)=/.test(ruta);
    // Si la prueba termina o monta otra pantalla antes, la elección se abandona.
    const vigente = () => mia === generacion;
    eleccionInicial = esNuevo
        ? (async () => {
              await screen.findByLabelText(/^Fecha/);
              const elegir = screen.queryByRole('button', { name: /Elegir asignatura/ });
              if (!elegir || !vigente()) return;
              fireEvent.click(elegir);
              const opcion = await screen.findByRole('option', {
                  name: /Introducción a la Programación/,
              });
              if (vigente()) fireEvent.click(opcion);
          })().catch(() => {})
        : Promise.resolve();
    return resultado;
};
const campo = async (etiqueta) => {
    await eleccionInicial;
    return screen.findByLabelText(etiqueta);
};
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

// Los grupos de otros docentes empiezan plegados: se abren para marcarlos.
async function abrirColaboracion() {
    const boton = await screen.findByRole('button', { name: /Para colaborar, añade grupos/ });
    if (boton.getAttribute('aria-expanded') === 'false') fireEvent.click(boton);
}

async function irAAulas(datos) {
    await llenarExamen(datos);
    await casilla(/^Grupo 2 · Blanco/);
    pulsar(/Continuar/);
    await screen.findByRole('heading', { name: '3. Aulas' });
    await screen.findByRole('list', { name: 'Aulas' });
}

// Sin un aula no se continúa: si no hay ninguna elegida, se elige la 691B.
function elegirAulaSiFalta() {
    const continuar = screen.getByRole('button', { name: /Continuar: Equipo/ });
    if (continuar.disabled) fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
}

async function irAQuienAtiende(datos) {
    await irAAulas(datos);
    elegirAulaSiFalta();
    pulsar(/Continuar/);
    await screen.findByRole('heading', { name: '4. Equipo' });
}

// Del paso de aulas al de quién atiende, y a guardar.
async function guardar(nombre) {
    elegirAulaSiFalta();
    pulsar(/Continuar/);
    await screen.findByRole('heading', { name: '4. Equipo' });
    pulsar(nombre);
}

let codigo;
let auxiliares;
// Los exámenes del docente (GET /examenes), para copiar las normas.
let anteriores;

beforeEach(() => {
    olvidarEdificios();
    codigo = '482913';
    auxiliares = AUXILIARES;
    detalle = DETALLE;
    choques = { total: 0, estudiantes: [] };
    ocupados = [];
    anteriores = [];
    simularApi(api, {
        'GET /examenes': () => ({ data: anteriores }),
        'GET /docente/grupos': { data: MIS_GRUPOS, meta: { periodo: '2/2026', asignaturas: 2 } },
        'GET /examenes/tipos': { data: TIPOS },
        'GET /periodos/vigentes': { data: PERIODOS, principal: '2/2026' },
        'GET /normas/plantillas': { data: PLANTILLAS },
        'GET /examenes/opciones/grupos': ({ params }) => OPCIONES_GRUPOS[params.asignatura_id],
        'GET /examenes/opciones/aulas': OPCIONES_AULAS,
        'GET /examenes/opciones/choques': () => choques,
        'GET /examenes/opciones/ocupados': () => ({ ocupados }),
        'GET /examenes/*/habilitaciones': () => ({
            data: [],
            meta: { total: 0, cifras: { inscritos: 65, habilitados: 0 } },
        }),
        'GET /examenes/*/codigos-qr': () => ({
            data: [],
            meta: { total: 0, cifras: { habilitados: 0, emitidos: 0 } },
        }),
        'GET /aulas': { data: AULAS },
        'GET /edificios': EDIFICIOS,
        'GET /examenes/*': () => ({ data: detalle }),
        'GET /auxiliares': ({ params }) => ({
            data: params?.buscar
                ? [...auxiliares, ...AJENOS].filter((a) =>
                      a.nombre.toLowerCase().includes(params.buscar.toLowerCase())
                  )
                : auxiliares,
        }),
        'DELETE /auxiliares/*': ({ url }) => {
            auxiliares = auxiliares.filter((a) => !url.endsWith(`/${a.id}`));
            return '';
        },
        'POST /auxiliares': ({ data }) => ({
            data: { id: 40, nombre: data.nombre, usuario: 'nuevo.auxiliar', mio: true },
            message: 'Auxiliar agregado.',
        }),
        'GET /examenes/*/aulas/*/codigo': () => ({
            data: {
                codigo,
                generado_en: codigo ? '2026-11-20T10:00:00-04:00' : null,
                encargado: 'Vargas Arce Sergio',
                examen: '2026-11-23 14:15',
            },
        }),
        'POST /examenes/*/aulas/*/codigo': () => {
            const anterior = codigo;
            codigo = '771204';
            return {
                data: {
                    codigo,
                    generado_en: '2026-11-21T09:00:00-04:00',
                    encargado: 'Vargas Arce Sergio',
                    examen: '2026-11-23 14:15',
                },
                message: anterior ? 'Código regenerado.' : 'Código generado.',
            };
        },
        'POST /examenes': ({ data }) => ({ data: guardado(data), message: 'Examen registrado.' }),
        'PUT /examenes/*': ({ data }) => ({ data: guardado(data, 4), message: 'Examen guardado.' }),
    });
});

describe('RegistrarExamen · normas del examen anterior', () => {
    const anterior = (id, asignatura, fecha, cambios = {}) => ({
        id,
        asignatura,
        tipo_texto: 'Primer parcial',
        fecha,
        hora: '08:15',
        propio: true,
        ...cambios,
    });

    it('un examen nuevo trae las normas del último de su asignatura', async () => {
        anteriores = [
            anterior(30, INTRO, '2026-10-01'),
            anterior(31, INTRO, '2026-10-10'),
            anterior(32, TALLER, '2026-10-20'),
            anterior(33, INTRO, '2026-10-25', { propio: false }),
        ];
        detalle = {
            ...DETALLE,
            normas_marcadas: [
                { id: 5, plantilla_id: 2, texto: 'Sin celular' },
                { id: 6, plantilla_id: null, texto: 'Sin gorra' },
            ],
            normas: 'Traer calculadora básica',
        };
        montar();

        expect(await casilla('Sin celular')).toBeChecked();
        expect(await casilla('C.I. a la vista')).not.toBeChecked();
        expect(screen.getByLabelText('Otras normas')).toHaveValue(
            'Sin gorra\nTraer calculadora básica'
        );
        expect(
            screen.getByText(/^Copiadas de: Primer parcial · Introducción a la Programación/)
        ).toBeInTheDocument();
        await waitFor(() => expect(api.get).toHaveBeenCalledWith('/examenes/31'));
        expect(api.get).not.toHaveBeenCalledWith('/examenes/33');
    });

    it('abierto desde el calendario, trae la fecha y la hora pedidas', async () => {
        montar('/examenes/nuevo?aula=5&fecha=2026-10-20&hora=09:30');

        expect(await campo(/^Fecha/)).toHaveValue('2026-10-20');
        expect(screen.getByLabelText(/^Hora de inicio/)).toHaveValue('09:30');
    });

    it('otro examen a la misma hora parte del tipo, la fecha, la hora y la duración del anterior, con otra asignatura', async () => {
        montarVacio('/examenes/nuevo?copiar=4');

        expect(await campo(/^Fecha/)).toHaveValue('2026-11-23');
        expect(screen.getByLabelText(/^Hora de inicio/)).toHaveValue('14:15');
        expect(screen.getByLabelText(/^Duración/)).toHaveValue('120');
        // La asignatura se elige a mano: viene sin elegir.
        expect(screen.getByRole('button', { name: /Elegir asignatura/ })).toBeVisible();
        expect(screen.queryByRole('button', { name: /^Quitar (Taller|Introducción)/ })).toBeNull();
        expect(screen.getByLabelText(/^Tipo/)).toHaveValue('SEGUNDO_PARCIAL');
    });

    it('si el examen de partida ya pasó, solo se aprovecha lo demás', async () => {
        detalle = { ...DETALLE, fecha: '2026-10-01' };
        montar('/examenes/nuevo?copiar=4');

        expect(await campo(/^Fecha/)).not.toHaveValue('2026-10-01');
        expect(screen.getByLabelText(/^Hora de inicio/)).not.toHaveValue('14:15');
    });

    it('una fecha pasada o una hora mal escrita de la dirección se ignoran', async () => {
        montar('/examenes/nuevo?fecha=2026-10-01&hora=25:99');

        expect(await campo(/^Fecha/)).not.toHaveValue('2026-10-01');
        expect(screen.getByLabelText(/^Hora de inicio/)).not.toHaveValue('25:99');
    });

    it('sin exámenes anteriores empieza sin normas marcadas', async () => {
        montar();

        expect(await casilla('Sin celular')).not.toBeChecked();
        expect(screen.queryByText(/^Copiadas de/)).not.toBeInTheDocument();
    });
});

describe('RegistrarExamen · paso 1, examen', () => {
    it('ofrece solo las asignaturas que dicta y los cinco tipos', async () => {
        montarVacio();

        // Sin nada elegido, el desplegable ofrece todas las que dicta.
        fireEvent.click(await screen.findByRole('button', { name: /Elegir asignatura/ }));
        const opciones = within(screen.getByRole('listbox')).getAllByRole('option');
        expect(opciones.map((o) => o.textContent)).toEqual([
            expect.stringMatching(
                /^Introducción a la Programación.*2010010 · Grupo 2 · 65 inscritos/
            ),
            expect.stringMatching(/^Taller de Ingeniería de Software.*2010024/),
        ]);
        fireEvent.click(opciones[0]);
        // La elegida queda como tarjeta fija y ya no se ofrece.
        const elegidas = screen.getByRole('list', { name: /^Asignaturas/ });
        expect(elegidas).toHaveTextContent(/Introducción a la Programación.*2010010 · Grupo 2/);
        fireEvent.click(screen.getByRole('button', { name: /Agregar otra asignatura/ }));
        expect(
            within(screen.getByRole('listbox'))
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual([expect.stringMatching(/^Taller de Ingeniería de Software.*2010024/)]);
        fireEvent.keyDown(screen.getByRole('button', { name: /Agregar otra asignatura/ }), {
            key: 'Escape',
        });
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
        expect(
            within(listaDe(screen.getByLabelText(/^Tipo/)))
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual([
            'Primer parcial',
            'Segundo parcial',
            'Examen final',
            'Segunda instancia',
            'Examen de mesa',
        ]);
        expect(screen.getByRole('heading', { name: 'Registrar examen' })).toBeInTheDocument();
        expect(screen.getByText('Período 2/2026')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Exámenes/ })).toHaveAttribute('href', '/examenes');
        const pasos = screen.getAllByRole('list')[0];
        expect(
            within(pasos)
                .getAllByRole('listitem')
                .map((p) => p.textContent)
        ).toEqual(['Examen', 'Grupos', 'Aulas', 'Equipo', 'Listo', 'Habilitar', 'Códigos QR']);
    });

    it('un examen nuevo empieza con la fecha de hoy y la primera hora de la mañana', async () => {
        montar();

        expect(await campo(/^Fecha/)).toHaveValue('2026-10-12');
        expect(screen.getByLabelText(/^Hora de inicio/)).toHaveValue('08:15');
    });

    it('la duración solo admite números', async () => {
        montar();
        cambiar(await campo(/^Duración/), '9a0 min');

        expect(screen.getByLabelText(/^Duración/)).toHaveValue('90');
    });

    it('sin grupos elegidos dice qué falta en vez de solo bloquear «Continuar»', async () => {
        montar();
        await campo(/^Fecha/);
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '2. Grupos' });
        const grupos = screen.getAllByRole('checkbox');
        grupos.filter((c) => c.checked).forEach((c) => fireEvent.click(c));

        // Lo dice la cabecera de la asignatura y también junto al botón.
        expect(screen.getAllByText('Elija al menos un grupo')).toHaveLength(2);
        expect(screen.getByRole('button', { name: /Continuar: Aulas/ })).toBeDisabled();
    });

    it('en Grupos avisa de los estudiantes que ya tienen otro examen a esa hora', async () => {
        choques = {
            total: 2,
            estudiantes: [
                {
                    nombre: 'Aguilar Rojas Cristian',
                    codigo: '202100123',
                    examenes: [
                        { examen: 'Física I · Primer parcial', inicio: '08:15', fin: '09:45' },
                    ],
                },
            ],
        };
        montar();
        await llenarExamen({ fecha: '2026-10-20', hora: '08:15', duracion: '90' });

        expect(
            await screen.findByText('2 estudiantes tienen otro examen a esa hora')
        ).toBeInTheDocument();
        expect(
            screen.getByText('Podrá inhabilitarlos con motivo en Habilitación')
        ).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith(
            '/examenes/opciones/choques',
            expect.objectContaining({
                params: expect.objectContaining({
                    fecha: '2026-10-20',
                    hora_inicio: '08:15',
                    duracion_minutos: 90,
                }),
            })
        );
        // Es un aviso: no impide continuar.
        expect(screen.getByRole('button', { name: /Continuar: Aulas/ })).toBeEnabled();
    });

    it('sin estudiantes con otro examen, Grupos no muestra el aviso', async () => {
        montar();
        await llenarExamen();

        await vi.waitFor(() =>
            expect(api.get).toHaveBeenCalledWith('/examenes/opciones/choques', expect.anything())
        );
        expect(screen.queryByText(/tienen? otro examen a esa hora/)).not.toBeInTheDocument();
    });

    it('sin fecha u hora no avanza y señala «Obligatorio»', async () => {
        montar();
        cambiar(await campo(/^Fecha/), '');
        cambiar(screen.getByLabelText(/^Hora de inicio/), '');

        pulsar(/Continuar/);

        expect(screen.getAllByText('Obligatorio')).toHaveLength(2);
        expect(screen.getByLabelText(/^Fecha/)).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByLabelText(/^Hora de inicio/)).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByRole('heading', { name: '1. Examen' })).toBeInTheDocument();
    });

    it('no deja una fecha pasada ni una hora fuera del horario de clases', async () => {
        montar();
        cambiar(await campo(/^Fecha/), '2026-10-01');
        cambiar(await campo(/^Hora de inicio/), '03:00');

        pulsar(/Continuar/);

        expect(screen.getByText('La fecha ya pasó')).toBeInTheDocument();
        expect(screen.getByText('Entre 06:45 y 21:45')).toBeInTheDocument();
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

    it('un examen que terminaría después del horario de clases se señala en la duración', async () => {
        montar();
        cambiar(await campo(/^Fecha/), '2026-10-20');
        cambiar(await campo(/^Hora de inicio/), '21:00');
        cambiar(await campo(/^Duración/), '180');

        pulsar(/Continuar/);

        expect(screen.getByText('Debe terminar a las 21:45 como máximo')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: '1. Examen' })).toBeInTheDocument();
    });

    it('no deja registrar un examen en domingo', async () => {
        montar();
        cambiar(await campo(/^Fecha/), '2026-10-18');
        cambiar(await campo(/^Hora de inicio/), '08:15');

        pulsar(/Continuar/);

        expect(screen.getByText('No hay exámenes los domingos')).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: '1. Examen' })).toBeInTheDocument();

        cambiar(screen.getByLabelText(/^Fecha/), '2026-10-19');
        pulsar(/Continuar/);
        expect(await screen.findByRole('heading', { name: '2. Grupos' })).toBeInTheDocument();
    });

    it('muestra la ventana del calendario del tipo y no ofrece la que ya cerró', async () => {
        PERIODOS[0].ventanas = {
            primeros_parciales: ['2026-09-21', '2026-10-03'],
            segundos_parciales: ['2026-10-19', '2026-10-31'],
        };
        try {
            montar();
            const tipo = await campo(/^Tipo/);
            expect(tipo).toHaveValue('SEGUNDO_PARCIAL');
            expect(
                within(listaDe(tipo)).getByRole('option', {
                    name: 'Primer parcial · calendario cerrado',
                })
            ).toHaveAttribute('aria-disabled', 'true');
            expect(screen.getByText('Calendario: 19 oct – 31 oct')).toBeInTheDocument();

            cambiar(await campo(/^Fecha/), '2026-11-05');
            cambiar(await campo(/^Hora de inicio/), '08:15');
            pulsar(/Continuar/);
            expect(
                screen.getByText('Fuera del calendario: segundo parcial del 19 oct al 31 oct')
            ).toBeInTheDocument();
        } finally {
            PERIODOS[0].ventanas = {};
        }
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
        await abrirColaboracion();

        expect(await casilla(/^Grupo 2 · Blanco Coca Leticia/)).toBeChecked();
        expect(screen.getByText('65 inscritos')).toBeInTheDocument();
        expect(
            screen.getByText(/Para colaborar, añade grupos de otros docentes · 0 de 10/)
        ).toBeInTheDocument();
        expect(await casilla('Grupo 12 · Sin docente')).not.toBeChecked();

        fireEvent.click(await casilla(/^Grupo 3 · Salazar/));
        expect(screen.getByText('75 inscritos')).toBeInTheDocument();
        expect(
            screen.getByText(/Para colaborar, añade grupos de otros docentes · 1 de 10/)
        ).toBeInTheDocument();

        pulsar('Todos');
        expect(
            screen.getByText(/Para colaborar, añade grupos de otros docentes · 10 de 10/)
        ).toBeInTheDocument();
        expect(screen.getByText('615 inscritos')).toBeInTheDocument();

        pulsar('Ninguno');
        expect(screen.getByText('65 inscritos')).toBeInTheDocument();
    });

    it('busca por grupo o por docente; «Todos» suma solo lo buscado', async () => {
        montar();
        await llenarExamen();
        await abrirColaboracion();
        await casilla(/^Grupo 2 · Blanco/);

        cambiar(screen.getByLabelText('Buscar grupo'), 'montano');
        expect(screen.getAllByRole('checkbox', { name: /Montaño/ })).toHaveLength(3);
        expect(screen.queryByRole('checkbox', { name: /Salazar/ })).not.toBeInTheDocument();

        pulsar('Todos');
        expect(
            screen.getByText(/Para colaborar, añade grupos de otros docentes · 3 de 10/)
        ).toBeInTheDocument();

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

    it('al elegir otra asignatura valen sus grupos propios', async () => {
        montarVacio();
        fireEvent.click(await screen.findByRole('button', { name: /Elegir asignatura/ }));
        fireEvent.click(screen.getByRole('option', { name: /Taller de Ingeniería de Software/ }));
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
    it('EV-03: muestra la coincidencia exacta en cada aula afectada', async () => {
        montar();
        await irAAulas();

        const aulaExamen = screen.getByRole('checkbox', { name: 'Aula 691A' }).closest('li');
        const aulaClase = screen.getByRole('checkbox', { name: 'Aula 691C' }).closest('li');

        expect(aulaExamen).toHaveTextContent('Coincidencia 08:15–09:45 (90 min)');
        expect(aulaClase).toHaveTextContent('Coincidencia 09:00–09:45 (45 min)');
    });

    it('EV-03: informa mientras se comprueba la disponibilidad', async () => {
        const pendiente = respuestaDiferida();
        const obtener = api.get.getMockImplementation();

        api.get.mockImplementation((url, opciones) =>
            url === '/examenes/opciones/aulas' ? pendiente.promesa : obtener(url, opciones)
        );

        montar();
        await irAAulas();

        expect(screen.getByText('Comprobando disponibilidad de aulas')).toBeInTheDocument();

        await act(async () => {
            pendiente.resolver({ data: OPCIONES_AULAS });
        });

        await waitFor(() =>
            expect(
                screen.queryByText('Comprobando disponibilidad de aulas')
            ).not.toBeInTheDocument()
        );
    });

    it('EV-03: informa si falla la comprobación y permite reintentar', async () => {
        const obtener = api.get.getMockImplementation();

        api.get.mockImplementation((url, opciones) =>
            url === '/examenes/opciones/aulas'
                ? Promise.reject(errorHttp(500))
                : obtener(url, opciones)
        );

        montar();
        await irAAulas();

        expect(
            await screen.findByText('No se pudo comprobar la disponibilidad de aulas')
        ).toBeInTheDocument();

        expect(
            screen.getByRole('button', {
                name: 'Reintentar disponibilidad',
            })
        ).toBeEnabled();
    });

    it('EV-03: muestra el docente y horario del examen coincidente', async () => {
        montar();
        await irAAulas();

        const aula = screen.getByRole('checkbox', { name: 'Aula 691A' }).closest('li');

        expect(aula).toHaveTextContent('Cálculo I · Primer parcial');
        expect(aula).toHaveTextContent('Mendez Paz Luis');
        expect(aula).toHaveTextContent('08:15');
        expect(aula).toHaveTextContent('09:45');
    });

    it('dice cuántas aulas hay libres a esa hora y sugiere las que se piden', async () => {
        montar();
        await irAAulas();

        // De las cuatro de la facultad, solo la 691B está libre a esa hora.
        expect(screen.getByText('1 libre de 4 a esa hora')).toBeVisible();
        fireEvent.click(screen.getByRole('button', { name: 'Sugerirme aulas' }));

        expect(screen.getByRole('list', { name: 'Aulas elegidas' })).toHaveTextContent('691B');
        expect(screen.getByRole('checkbox', { name: 'Aula 691B' })).toBeChecked();
        expect(screen.getByRole('button', { name: /Continuar: Equipo/ })).toBeEnabled();
    });

    it('la cantidad de aulas a sugerir no pasa de las libres', async () => {
        montar();
        await irAAulas();

        expect(screen.getByRole('button', { name: 'Más aulas' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Menos aulas' })).toBeDisabled();
        expect(screen.getByLabelText('Cantidad de aulas')).toHaveTextContent('1');
    });

    it('EV-03: informa de una clase ajena coincidente', async () => {
        montar();
        await irAAulas();

        const aula = screen.getByRole('checkbox', { name: 'Aula 691C' }).closest('li');

        expect(aula).not.toBeNull();
        expect(aula).toHaveTextContent('Ocupada 09:00–10:30 · Física I');
        // El docente y el grupo quedan en el detalle, sin alargar la fila.
        expect(within(aula).getByTitle(/Rojas Paz Ana/)).toBeInTheDocument();
        expect(aula).toHaveTextContent('09:00');
        expect(aula).toHaveTextContent('10:30');
    });

    it('pone primero las aulas de los grupos y avisa las compartidas sin impedir elegirlas', async () => {
        montar();
        await irAAulas();

        const filas = within(screen.getByRole('list', { name: 'Aulas' })).getAllByRole('listitem');
        // Las libres a esa hora primero; después las compartidas y ocupadas. Dentro
        // de cada una, las de los grupos y las de la facultad de la asignatura; las
        // de otras facultades no se ofrecen.
        expect(filas.map((f) => f.textContent.slice(0, 4).trim())).toEqual([
            '691B',
            '691A',
            '606D',
            '691C',
        ]);
        // Sin rótulos ni explicaciones: solo el aviso de otro examen a esa hora.
        expect(filas[1]).toHaveTextContent(
            '691AEdificio Académico 2 · 1° PisoCompartida · Cálculo I · Primer parcial'
        );
        expect(filas[0]).toHaveTextContent(/^691BEdificio Académico 2 · 1° Piso$/);
        expect(filas[2]).toHaveTextContent('Compartida · Análisis Numérico · Primer parcial');
        expect(filas[3]).toHaveTextContent('691CEdificio Académico 2 · 1° Piso');
        expect(screen.queryByText(/Sugerida/)).not.toBeInTheDocument();
        expect(screen.queryByRole('checkbox', { name: 'Aula 004' })).not.toBeInTheDocument();
        expect(screen.queryByRole('checkbox', { name: 'Aula 003' })).not.toBeInTheDocument();
        expect(screen.getByText('4 aulas')).toBeInTheDocument();

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
        expect(screen.getByRole('button', { name: /Continuar/ })).toBeEnabled();
    });

    it('filtra por edificio, sugeridas, elegidas y texto', async () => {
        montar();
        await irAAulas();
        const filtro = () => screen.getByRole('button', { name: /^Edificio:/ });
        const elegir = (nombre) => {
            fireEvent.click(filtro());
            fireEvent.click(screen.getByRole('option', { name: new RegExp(`^${nombre}`) }));
        };
        const visibles = () =>
            within(screen.getByRole('list', { name: 'Aulas' }))
                .getAllByRole('checkbox')
                .map((c) => c.getAttribute('aria-label'));

        expect(filtro()).toHaveAccessibleName('Edificio: Todos los edificios');
        fireEvent.click(filtro());
        expect(
            within(listaDe(screen.getByRole('listbox', { name: 'Edificio' })))
                .getAllByRole('option')
                .map((o) => o.textContent)
        ).toEqual([
            'Todos los edificios4',
            'Elegidas0',
            'Libres a esa hora1',
            'Departamento de Biología1',
            'Edificio Académico 23',
        ]);
        expect(screen.getByRole('group', { name: 'Edificios' })).toBeInTheDocument();
        fireEvent.keyDown(filtro(), { key: 'Escape' });
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();

        // Libre: ni clases de otros grupos ni otros exámenes a esa hora.
        elegir('Libres a esa hora');
        expect(visibles()).toEqual(['Aula 691B']);

        elegir('Departamento de Biología');
        expect(filtro()).toHaveAccessibleName('Edificio: Departamento de Biología');
        expect(visibles()).toEqual(['Aula 606']);
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 606' }));

        elegir('Elegidas');
        expect(visibles()).toEqual(['Aula 606']);
        pulsar('Quitar el aula 606');
        expect(screen.getByText('Sin resultados')).toBeInTheDocument();

        elegir('Todos los edificios');
        cambiar(screen.getByLabelText('Buscar aula'), 'biolog');
        expect(visibles()).toEqual(['Aula 606']);
        expect(screen.getByText('1 aula')).toBeInTheDocument();
    });

    it('el filtro de edificio se maneja con el teclado', async () => {
        montar();
        await irAAulas();
        const filtro = () => screen.getByRole('button', { name: /^Edificio:/ });

        fireEvent.keyDown(filtro(), { key: 'ArrowDown' });
        expect(screen.getByRole('listbox', { name: 'Edificio' })).toBeInTheDocument();
        fireEvent.keyDown(filtro(), { key: 'ArrowDown' });
        fireEvent.keyDown(filtro(), { key: 'Enter' });

        expect(filtro()).toHaveAccessibleName('Edificio: Elegidas');
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('al tocar un edificio en el mapa muestra solo sus aulas; otra vez, todas', async () => {
        montar();
        await irAAulas();
        const filtro = () => screen.getByRole('button', { name: /^Edificio:/ });
        const edificio = (nombre) => screen.getByText(`${nombre} · FCyT`).closest('g');
        const visibles = () =>
            within(screen.getByRole('list', { name: 'Aulas' }))
                .getAllByRole('checkbox')
                .map((c) => c.getAttribute('aria-label'));

        expect(screen.queryByText('Bloque Posgrado · FHCE')).not.toBeInTheDocument();

        fireEvent.click(edificio('Departamento de Biología'));
        expect(filtro()).toHaveAccessibleName('Edificio: Departamento de Biología');
        expect(visibles()).toEqual(['Aula 606']);

        fireEvent.click(edificio('Edificio Académico 2'));
        expect(visibles()).toEqual(['Aula 691B', 'Aula 691A', 'Aula 691C']);

        fireEvent.click(edificio('Edificio Académico 2'));
        expect(filtro()).toHaveAccessibleName('Edificio: Todos los edificios');
        expect(visibles()).toHaveLength(4);
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

        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 606' }));
        expect(resaltados()).toBe(2);
    });
});

describe('RegistrarExamen · paso 4, quién atiende', () => {
    it('el equipo se arma con quien registra y los auxiliares que se suman, y viaja al guardar', async () => {
        montar();
        await irAAulas();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 606' }));
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });

        const equipo = () => screen.getByRole('list', { name: 'Equipo' });
        expect(equipo()).toHaveTextContent('Blanco Coca Leticia · docente');
        // Todo el equipo atiende cualquier aula: no hay nada que ajustar por aula.
        expect(screen.queryByRole('button', { name: /Ajustar por aula/ })).toBeNull();
        await waitFor(() =>
            expect(
                within(screen.getByRole('list', { name: 'Auxiliares' }))
                    .getAllByRole('listitem')
                    .map((x) => x.textContent)
            ).toEqual([
                'Rojas Ledezma Andrea · usuario: andrea.rojas',
                'Vargas Arce Sergio · usuario: sergio.vargas',
            ])
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Sumar a Vargas Arce Sergio al equipo' })
        );
        expect(equipo()).toHaveTextContent('Vargas Arce Sergio · auxiliar');
        // Ya no se ofrece entre los que faltan por sumar.
        expect(
            screen.queryByRole('button', { name: 'Sumar a Vargas Arce Sergio al equipo' })
        ).toBeNull();

        pulsar('Registrar examen');

        expect(await screen.findByText('Examen registrado')).toBeInTheDocument();
        // Cualquiera del equipo atiende cualquier aula; cada una lleva a alguien
        // nominalmente a cargo, repartido por turnos.
        expect(api.post.mock.calls[0][1].aulas).toEqual([
            { aula_id: 32, encargado_id: 7, apoyo: [12] },
            { aula_id: 78, encargado_id: 12, apoyo: [] },
        ]);
    });

    it('con ingresos solo deja cambiar las normas, quién atiende y sumar aulas', async () => {
        detalle = { ...DETALLE, con_ingresos: true };
        montar('/examenes/nuevo?examen=4');

        expect(
            await screen.findByText('Con ingresos · solo normas, quién atiende y aulas nuevas')
        ).toBeInTheDocument();
        expect(screen.getByLabelText(/^Fecha/)).toBeDisabled();
        expect(screen.getByLabelText(/^Hora de inicio/)).toBeDisabled();
        expect(screen.getByLabelText(/^Tipo/)).toBeDisabled();

        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '2. Grupos' });
        screen.getAllByRole('checkbox').forEach((c) => expect(c).toBeDisabled());

        // Un aula inutilizable o llena se reemplaza: las que estaban quedan,
        // y se puede sumar otra.
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '3. Aulas' });
        expect(await screen.findByRole('checkbox', { name: 'Aula 691B' })).toBeDisabled();
        expect(screen.queryByRole('button', { name: 'Quitar el aula 691B' })).toBeNull();
        const nueva = screen.getByRole('checkbox', { name: 'Aula 606' });
        expect(nueva).toBeEnabled();
        fireEvent.click(nueva);
        expect(screen.getByRole('button', { name: 'Quitar el aula 606' })).toBeInTheDocument();
    });

    it('un aula que se agrega después ya cuenta con el equipo', async () => {
        montar();
        await irAAulas();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });
        fireEvent.click(
            await screen.findByRole('button', { name: 'Sumar a Vargas Arce Sergio al equipo' })
        );

        pulsar('Atrás');
        fireEvent.click(await screen.findByRole('checkbox', { name: 'Aula 606' }));
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });
        expect(screen.getByRole('list', { name: 'Equipo' })).toHaveTextContent(
            'Vargas Arce Sergio'
        );
        pulsar('Registrar examen');

        await screen.findByText('Examen registrado');
        expect(api.post.mock.calls[0][1].aulas).toEqual([
            { aula_id: 32, encargado_id: 7, apoyo: [12] },
            { aula_id: 78, encargado_id: 12, apoyo: [] },
        ]);
    });

    it('sacar a alguien del equipo; quien ya estaba a cargo de un aula se queda en ella', async () => {
        montar('/examenes/nuevo?examen=4&paso=3');
        await screen.findByRole('heading', { name: '4. Equipo' });
        const equipo = () => screen.getByRole('list', { name: 'Equipo' });

        expect(equipo()).toHaveTextContent('Vargas Arce Sergio');
        expect(equipo()).toHaveTextContent('Rojas Ledezma Andrea');
        fireEvent.click(
            within(equipo()).getByRole('button', {
                name: 'Sacar a Rojas Ledezma Andrea del equipo',
            })
        );
        expect(equipo()).not.toHaveTextContent('Rojas Ledezma Andrea');
        // Vuelve a la lista para sumarla de nuevo.
        expect(
            await screen.findByRole('button', { name: 'Sumar a Rojas Ledezma Andrea al equipo' })
        ).toBeVisible();

        pulsar('Guardar examen');
        await screen.findByText('Examen guardado');
        expect(api.put.mock.calls[0][1].aulas).toEqual([
            { aula_id: 32, encargado_id: 12, apoyo: [] },
        ]);
    });

    it('las aulas se reparten solas por turnos entre quien registra y el equipo', async () => {
        montar();
        await irAAulas();
        ['691A', '691B', '606', '691C'].forEach((a) =>
            fireEvent.click(screen.getByRole('checkbox', { name: `Aula ${a}` }))
        );
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });
        fireEvent.click(
            await screen.findByRole('button', { name: 'Sumar a Rojas Ledezma Andrea al equipo' })
        );
        fireEvent.click(
            screen.getByRole('button', { name: 'Sumar a Vargas Arce Sergio al equipo' })
        );

        pulsar('Registrar examen');
        await screen.findByText('Examen registrado');

        expect(api.post.mock.calls[0][1].aulas).toEqual([
            { aula_id: 31, encargado_id: 7, apoyo: [9, 12] },
            { aula_id: 32, encargado_id: 9, apoyo: [12] },
            { aula_id: 78, encargado_id: 12, apoyo: [9] },
            { aula_id: 33, encargado_id: 7, apoyo: [9, 12] },
        ]);
    });

    it('sin aulas no deja continuar y dice qué falta', async () => {
        montar();
        await irAAulas();

        expect(screen.getByRole('button', { name: /Continuar: Equipo/ })).toBeDisabled();
        expect(screen.getByText('Elija al menos un aula')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        expect(screen.getByRole('button', { name: /Continuar: Equipo/ })).toBeEnabled();
        expect(screen.queryByText('Elija al menos un aula')).not.toBeInTheDocument();
    });

    it('el aula elegida que está ocupada a esa hora lo sigue diciendo', async () => {
        montar();
        await irAAulas();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691C' }));

        const elegidas = screen.getByRole('list', { name: 'Aulas elegidas' });
        expect(elegidas).toHaveTextContent('691C· ocupada');
    });

    it('el auxiliar nuevo no deja escribir números en el nombre ni letras en el código', async () => {
        montar();
        await irAAulas();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });

        cambiar(screen.getByLabelText(/^Nombres/), 'Ro2sa');
        cambiar(screen.getByLabelText(/^Apellidos/), 'Quis9pe');
        cambiar(screen.getByLabelText(/^Código SIS del auxiliar/), 'SIS 2020-0123');

        expect(screen.getByLabelText(/^Nombres/)).toHaveValue('Rosa');
        expect(screen.getByLabelText(/^Apellidos/)).toHaveValue('Quispe');
        expect(screen.getByLabelText(/^Código SIS del auxiliar/)).toHaveValue('20200123');
    });

    it('Enter agrega al auxiliar nuevo, como el botón', async () => {
        montar();
        await irAAulas();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });

        cambiar(screen.getByLabelText(/^Nombres/), 'Rosa');
        cambiar(screen.getByLabelText(/^Apellidos/), 'Quispe Mamani');
        cambiar(screen.getByLabelText(/^Código SIS del auxiliar/), '202000123');
        fireEvent.keyDown(screen.getByLabelText(/^Código SIS del auxiliar/), { key: 'Enter' });

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/auxiliares', {
                nombre: 'Quispe Mamani Rosa',
                codigo_universitario: '202000123',
            })
        );
    });

    it('sin auxiliares la lista lo dice y el equipo es solo el docente', async () => {
        auxiliares = [];
        montar();
        await irAQuienAtiende();

        expect(await screen.findByText('Sin auxiliares')).toBeInTheDocument();
        expect(screen.getByRole('list', { name: 'Equipo' })).toHaveTextContent(
            'Blanco Coca Leticia · docente'
        );
        expect(screen.getByRole('button', { name: 'Registrar examen' })).toBeEnabled();
    });

    it('da de alta un auxiliar nuevo y queda disponible para las aulas', async () => {
        montar();
        await irAAulas();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });

        // Sin datos no se envía.
        pulsar(/Agregar/);
        expect(screen.getAllByText('Obligatorio')).toHaveLength(3);
        cambiar(screen.getByLabelText(/^Nombres/), 'Rosa');
        cambiar(screen.getByLabelText(/^Apellidos/), 'Quispe Mamani');
        cambiar(screen.getByLabelText(/^Código SIS del auxiliar/), '2020');
        pulsar(/Agregar/);
        expect(screen.getByText('Entre 5 y 10 dígitos')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();

        cambiar(screen.getByLabelText(/^Código SIS del auxiliar/), '202000123');
        pulsar(/Agregar/);

        expect(
            await screen.findByText(
                'Cuenta creada · usuario nuevo.auxiliar · la contraseña temporal se envió a 202000123@est.umss.edu'
            )
        ).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/auxiliares', {
            nombre: 'Quispe Mamani Rosa',
            codigo_universitario: '202000123',
        });
        expect(screen.getByLabelText(/^Nombres/)).toHaveValue('');
        expect(screen.getByLabelText(/^Apellidos/)).toHaveValue('');
        // Queda en el equipo sin más pasos.
        expect(screen.getByRole('list', { name: 'Equipo' })).toHaveTextContent(
            'Quispe Mamani Rosa · auxiliar'
        );
        pulsar('Registrar examen');
        await screen.findByText('Examen registrado');
        expect(api.post.mock.calls[1][1].aulas).toEqual([
            { aula_id: 32, encargado_id: 7, apoyo: [40] },
        ]);
    });

    it('el auxiliar que ya tiene cuenta se suma a la lista; el 422 queda en su campo', async () => {
        montar();
        await irAQuienAtiende();
        cambiar(screen.getByLabelText(/^Nombres/), 'Diego');
        cambiar(screen.getByLabelText(/^Apellidos/), 'Mamani Torrez');
        cambiar(screen.getByLabelText(/^Código SIS del auxiliar/), '201900456');

        api.post.mockRejectedValueOnce(
            errorHttp(422, {
                message: 'Ya hay una cuenta con ese correo.',
                errors: { codigo_universitario: ['Ya hay una cuenta con ese correo.'] },
            })
        );
        pulsar(/Agregar/);
        expect(await screen.findByText('Ya hay una cuenta con ese correo.')).toBeInTheDocument();

        api.post.mockRejectedValueOnce(
            errorHttp(409, { message: 'Auxiliar ya registrado.', data: AJENOS[0] })
        );
        cambiar(screen.getByLabelText(/^Código SIS del auxiliar/), '201900457');
        pulsar(/Agregar/);

        expect(await screen.findByText('Auxiliar ya registrado')).toBeInTheDocument();
        expect(screen.getByRole('list', { name: 'Equipo' })).toHaveTextContent(
            'Mamani Torrez Diego · auxiliar'
        );
    });

    it('busca entre los demás auxiliares y suma al elegido', async () => {
        montar();
        await irAQuienAtiende();
        await screen.findByRole('button', { name: 'Sumar a Rojas Ledezma Andrea al equipo' });

        cambiar(screen.getByLabelText('Buscar auxiliar'), 'ma');
        cambiar(screen.getByLabelText('Buscar auxiliar'), 'mamani');

        fireEvent.click(await screen.findByRole('button', { name: 'Sumar a Mamani Torrez Diego' }));

        expect(screen.getByRole('list', { name: 'Equipo' })).toHaveTextContent(
            'Mamani Torrez Diego · auxiliar'
        );
        expect(
            screen.queryByRole('button', { name: 'Sumar a Mamani Torrez Diego' })
        ).not.toBeInTheDocument();
        // Con menos de tres letras no se busca.
        expect(
            api.get.mock.calls.filter(([url, c]) => url === '/auxiliares' && c?.params?.buscar)
        ).toHaveLength(1);
    });

    it('los docentes con cuenta de los grupos sumados figuran en el equipo, pendientes hasta que acepten', async () => {
        montar();
        await llenarExamen();
        await abrirColaboracion();
        fireEvent.click(await casilla(/^Grupo 3 · Salazar/));
        // El docente del grupo 5 no tiene cuenta activa.
        fireEvent.click(await casilla(/^Grupo 5 · Montaño/));
        pulsar(/Continuar/);
        await screen.findByRole('list', { name: 'Aulas' });
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });

        const equipo = screen.getByRole('list', { name: 'Equipo' });
        expect(equipo).toHaveTextContent('Salazar Serrudo Carla · docente · invitación pendiente');
        expect(equipo).not.toHaveTextContent('Montaño');
        pulsar('Registrar examen');

        await screen.findByText('Examen registrado');
        expect(api.post.mock.calls[0][1].aulas).toEqual([
            { aula_id: 32, encargado_id: 7, apoyo: [] },
        ]);
    });

    it('al quitar el grupo, su docente deja el equipo', async () => {
        montar();
        await llenarExamen();
        await abrirColaboracion();
        fireEvent.click(await casilla(/^Grupo 3 · Salazar/));
        pulsar(/Continuar/);
        await screen.findByRole('list', { name: 'Aulas' });
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });
        expect(screen.getByRole('list', { name: 'Equipo' })).toHaveTextContent(
            'Salazar Serrudo Carla'
        );

        pulsar('Atrás');
        pulsar('Atrás');
        fireEvent.click(await casilla(/^Grupo 3 · Salazar/));
        pulsar(/Continuar/);
        await screen.findByRole('list', { name: 'Aulas' });
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });

        expect(screen.getByRole('list', { name: 'Equipo' })).not.toHaveTextContent(
            'Salazar Serrudo Carla'
        );
    });

    it('quita un auxiliar de su lista con confirmación; los del equipo no se tocan', async () => {
        montar();
        await irAAulas();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });
        fireEvent.click(
            await screen.findByRole('button', { name: 'Sumar a Rojas Ledezma Andrea al equipo' })
        );

        pulsar('Quitar a Vargas Arce Sergio de la lista');
        const dialogo = screen.getByRole('dialog', { name: 'Quitar auxiliar' });
        expect(dialogo).toHaveTextContent('Vargas Arce Sergio');
        // Cancelar no quita nada.
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Cancelar' }));
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(api.delete).not.toHaveBeenCalled();

        pulsar('Quitar a Vargas Arce Sergio de la lista');
        fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Quitar' }));

        expect(await screen.findByText('Auxiliar quitado')).toBeInTheDocument();
        expect(api.delete).toHaveBeenCalledWith('/auxiliares/12');
        await waitFor(() =>
            expect(
                screen.queryByRole('button', { name: 'Sumar a Vargas Arce Sergio al equipo' })
            ).not.toBeInTheDocument()
        );
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        // El del equipo sigue.
        expect(screen.getByRole('list', { name: 'Equipo' })).toHaveTextContent(
            'Rojas Ledezma Andrea'
        );
    });

    it('el auxiliar que atiende un examen pendiente no se quita y lo dice', async () => {
        montar();
        await irAQuienAtiende();
        await screen.findByRole('button', { name: 'Sumar a Rojas Ledezma Andrea al equipo' });
        api.delete.mockRejectedValueOnce(
            errorHttp(409, { message: 'Atiende un examen pendiente.', codigo: 'AUXILIAR_ASIGNADO' })
        );

        pulsar('Quitar a Rojas Ledezma Andrea de la lista');
        fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Quitar' }));

        expect(await screen.findByText('Atiende un examen pendiente')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Sumar a Rojas Ledezma Andrea al equipo' })
        ).toBeInTheDocument();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('el auxiliar sumado en la visita se quita sin tocar el servidor; el que solo atiende el examen no se quita', async () => {
        // El 12 atiende el examen pero ya no es de la cuenta.
        auxiliares = [AUXILIARES[0]];
        montar('/examenes/nuevo?examen=4&paso=3');
        await screen.findByRole('heading', { name: '4. Equipo' });
        const equipo = () => screen.getByRole('list', { name: 'Equipo' });
        expect(equipo()).toHaveTextContent('Vargas Arce Sergio');

        // Sacado del equipo, tampoco se ofrece quitarlo de la lista: no es suyo.
        fireEvent.click(
            within(equipo()).getByRole('button', { name: 'Sacar a Vargas Arce Sergio del equipo' })
        );
        expect(
            await screen.findByRole('button', { name: 'Sumar a Vargas Arce Sergio al equipo' })
        ).toBeVisible();
        expect(
            screen.queryByRole('button', { name: 'Quitar a Vargas Arce Sergio de la lista' })
        ).not.toBeInTheDocument();

        cambiar(screen.getByLabelText('Buscar auxiliar'), 'mamani');
        fireEvent.click(await screen.findByRole('button', { name: 'Sumar a Mamani Torrez Diego' }));
        fireEvent.click(
            within(equipo()).getByRole('button', { name: 'Sacar a Mamani Torrez Diego del equipo' })
        );

        pulsar('Quitar a Mamani Torrez Diego de la lista');
        fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Quitar' }));

        await waitFor(() =>
            expect(
                screen.queryByRole('button', { name: 'Sumar a Mamani Torrez Diego al equipo' })
            ).not.toBeInTheDocument()
        );
        expect(api.delete).not.toHaveBeenCalled();
    });

    it('el rechazo del servidor sobre las aulas se ve en el equipo y se va al cambiarlo', async () => {
        api.post.mockRejectedValueOnce(
            errorHttp(422, {
                message: 'El encargado no es docente ni auxiliar.',
                errors: { 'aulas.0.encargado_id': ['El encargado no es docente ni auxiliar.'] },
            })
        );
        montar();
        await irAAulas();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));

        await guardar('Registrar examen');

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'El encargado no es docente ni auxiliar.'
        );
        expect(screen.getByRole('heading', { name: '4. Equipo' })).toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', { name: 'Sumar a Rojas Ledezma Andrea al equipo' })
        );
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
});

describe('RegistrarExamen · código de acceso del aula', () => {
    it('muestra el código del aula, lo copia y lo regenera', async () => {
        const writeText = vi.fn().mockResolvedValue();
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        detalle = { ...detalle, propio: false };
        montar('/examenes/nuevo?examen=4');
        await screen.findByText(/Registrado por/);

        pulsar('Código de acceso del aula 691B');

        const dialogo = await screen.findByRole('dialog', { name: 'Código de acceso · Aula 691B' });
        expect(await within(dialogo).findByLabelText('Código')).toHaveTextContent('482913');
        expect(
            within(dialogo).getByText('Vargas Arce Sergio · lunes 23 de noviembre, 14:15')
        ).toBeInTheDocument();
        expect(api.get).toHaveBeenCalledWith('/examenes/4/aulas/32/codigo', expect.anything());

        fireEvent.click(within(dialogo).getByRole('button', { name: /Copiar/ }));
        expect(await screen.findByText('Código copiado')).toBeInTheDocument();
        expect(writeText).toHaveBeenCalledWith('482913');

        fireEvent.click(within(dialogo).getByRole('button', { name: /Regenerar/ }));
        // Pide confirmar: el código actual deja de abrir la puerta.
        expect(
            within(dialogo).getByText(
                'El código actual deja de abrir la puerta y se cortan sus accesos'
            )
        ).toBeInTheDocument();
        fireEvent.click(within(dialogo).getByRole('button', { name: /Regenerar/ }));
        expect(await screen.findByText('Código regenerado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/examenes/4/aulas/32/codigo');
        expect(within(dialogo).getByLabelText('Código')).toHaveTextContent('771204');

        fireEvent.click(within(dialogo).getAllByRole('button', { name: 'Cerrar' })[0]);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('consultar no genera: sin código ofrece «Generar» y después «Copiar» y «Regenerar»', async () => {
        codigo = null;
        detalle = {
            ...DETALLE,
            propio: false,
            aulas_detalle: [{ ...DETALLE.aulas_detalle[0], usa_codigo: false }],
        };
        montar('/examenes/nuevo?examen=4');
        await screen.findByText(/Registrado por/);
        const llave = screen.getByRole('button', { name: 'Código de acceso del aula 691B' });
        expect(llave).toHaveClass('text-slate-600');

        fireEvent.click(llave);
        const dialogo = await screen.findByRole('dialog', { name: 'Código de acceso · Aula 691B' });

        expect(await within(dialogo).findByText('Sin código')).toBeInTheDocument();
        expect(
            within(dialogo).getByText('Vargas Arce Sergio · lunes 23 de noviembre, 14:15')
        ).toBeInTheDocument();
        expect(within(dialogo).queryByLabelText('Código')).not.toBeInTheDocument();
        expect(within(dialogo).queryByRole('button', { name: /Copiar/ })).not.toBeInTheDocument();
        expect(
            within(dialogo).queryByRole('button', { name: /Regenerar/ })
        ).not.toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
        // Sin código la llave sigue sin color.
        expect(llave).toHaveClass('text-slate-600');

        fireEvent.click(within(dialogo).getByRole('button', { name: /Generar/ }));

        expect(await screen.findByText('Código generado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/examenes/4/aulas/32/codigo');
        expect(within(dialogo).getByLabelText('Código')).toHaveTextContent('771204');
        expect(within(dialogo).getByRole('button', { name: /Copiar/ })).toBeEnabled();
        expect(within(dialogo).getByRole('button', { name: /Regenerar/ })).toBeEnabled();
        expect(within(dialogo).queryByRole('button', { name: /^Generar/ })).not.toBeInTheDocument();
        await waitFor(() => expect(llave).toHaveClass('text-primary-700'));
    });

    it('si no se pudo generar lo avisa y sigue sin código', async () => {
        codigo = null;
        detalle = { ...detalle, propio: false };
        montar('/examenes/nuevo?examen=4');
        await screen.findByText(/Registrado por/);
        pulsar('Código de acceso del aula 691B');
        const dialogo = await screen.findByRole('dialog');
        await within(dialogo).findByText('Sin código');

        api.post.mockRejectedValueOnce(errorHttp(500));
        fireEvent.click(within(dialogo).getByRole('button', { name: /Generar/ }));

        expect(await screen.findByText('No se pudo generar')).toBeInTheDocument();
        expect(within(dialogo).getByText('Sin código')).toBeInTheDocument();
    });

    it('tras registrar, el resumen da el código de cada aula', async () => {
        montar();
        await irAAulas();
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '4. Equipo' });

        pulsar('Registrar examen');
        await screen.findByText('Examen registrado');
        pulsar('Código de acceso del aula 691B');

        expect(await screen.findByLabelText('Código')).toHaveTextContent('482913');
        expect(api.get).toHaveBeenCalledWith('/examenes/9/aulas/32/codigo', expect.anything());
    });

    it('si el código no carga ofrece reintentar', async () => {
        detalle = { ...DETALLE, propio: false };
        montar('/examenes/nuevo?examen=4');
        fireEvent.click(
            await screen.findByRole('button', { name: 'Código de acceso del aula 691B' })
        );
        const dialogo = await screen.findByRole('dialog');
        await within(dialogo).findByLabelText('Código');
        fireEvent.click(within(dialogo).getAllByRole('button', { name: 'Cerrar' })[0]);

        api.get.mockRejectedValueOnce(errorHttp(500));
        pulsar('Código de acceso del aula 691B');
        const otro = await screen.findByRole('dialog');
        expect(within(otro).getByRole('button', { name: /Generar/ })).toBeDisabled();
        expect(within(otro).queryByRole('button', { name: /Copiar/ })).not.toBeInTheDocument();
        fireEvent.click(await within(otro).findByRole('button', { name: 'Reintentar' }));
        expect(await within(otro).findByLabelText('Código')).toHaveTextContent('482913');
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

        await guardar('Registrar examen');

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
            aulas: [{ aula_id: 32, encargado_id: 7, apoyo: [] }],
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
        // Habilitar estudiantes sigue en el mismo asistente.
        expect(screen.getByRole('button', { name: /Continuar: Habilitar/ })).toBeEnabled();
        expect(screen.getByRole('link', { name: 'Otro examen a la misma hora' })).toHaveAttribute(
            'href',
            '/examenes/nuevo?copiar=9'
        );
    });

    it('con otra asignatura del día registra un examen por cada una, con los grupos propios marcados', async () => {
        montar();
        fireEvent.click(await screen.findByRole('button', { name: /Agregar otra asignatura/ }));
        fireEvent.click(
            within(screen.getByRole('listbox')).getByRole('option', {
                name: /Taller de Ingeniería de Software/,
            })
        );
        expect(screen.getByRole('button', { name: 'Quitar Taller de Ingeniería de Software' }));

        await llenarExamen();
        const taller = screen.getByRole('region', {
            name: 'Grupos de Taller de Ingeniería de Software',
        });
        expect(
            await within(taller).findByRole('checkbox', { name: /^Grupo 2 · Blanco/ })
        ).toBeChecked();
        expect(within(taller).getByRole('checkbox', { name: /^Grupo 5 · Blanco/ })).toBeChecked();
        // Los inscritos de las dos asignaturas: 65 de Introducción + 46 + 30 de Taller.
        expect(await screen.findByText('141 inscritos')).toBeInTheDocument();

        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '3. Aulas' });
        await guardar('Registrar examen');

        expect(await screen.findByText('Examen registrado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledTimes(2);
        expect(api.post).toHaveBeenNthCalledWith(
            1,
            '/examenes',
            expect.objectContaining({ asignatura_id: 20, grupos: [37] })
        );
        expect(api.post).toHaveBeenNthCalledWith(
            2,
            '/examenes',
            expect.objectContaining({ asignatura_id: 53, grupos: [100, 101], fecha: '2026-10-20' })
        );
        const tambien = await screen.findByText('También registrados');
        expect(
            within(tambien.closest('section') ?? tambien.parentElement).getByText(
                'Taller de Ingeniería de Software'
            )
        ).toBeInTheDocument();
    });

    it('las asignaturas elegidas quedan fijas, solo se quitan, y el desplegable no repite', async () => {
        montarVacio();

        fireEvent.click(await screen.findByRole('button', { name: /Elegir asignatura/ }));
        fireEvent.click(
            within(screen.getByRole('listbox')).getByRole('option', {
                name: /Introducción a la Programación/,
            })
        );
        fireEvent.click(screen.getByRole('button', { name: /Agregar otra asignatura/ }));
        fireEvent.click(
            within(screen.getByRole('listbox')).getByRole('option', {
                name: /Taller de Ingeniería de Software/,
            })
        );

        const lista = screen.getByRole('list', { name: /^Asignaturas/ });
        expect(lista).toHaveTextContent('Introducción a la Programación');
        expect(lista).toHaveTextContent('Taller de Ingeniería de Software');
        // Ya no queda ninguna por agregar y ninguna fila es un desplegable.
        expect(screen.queryByRole('button', { name: /Agregar otra asignatura/ })).toBeNull();
        expect(within(lista).queryByRole('listbox')).toBeNull();
        expect(within(lista).queryByRole('button', { name: /^Asignatura \d/ })).toBeNull();

        fireEvent.click(
            screen.getByRole('button', { name: 'Quitar Taller de Ingeniería de Software' })
        );
        expect(
            await screen.findByRole('button', { name: /Agregar otra asignatura/ })
        ).toBeVisible();
    });

    it('también se puede quitar la primera y la siguiente toma su lugar', async () => {
        montar();

        fireEvent.click(await screen.findByRole('button', { name: /Agregar otra asignatura/ }));
        fireEvent.click(
            within(screen.getByRole('listbox')).getByRole('option', {
                name: /Taller de Ingeniería de Software/,
            })
        );
        fireEvent.click(
            screen.getByRole('button', { name: 'Quitar Introducción a la Programación' })
        );

        expect(
            await screen.findByRole('button', { name: 'Quitar Taller de Ingeniería de Software' })
        ).toBeVisible();
        expect(
            screen.queryByRole('button', { name: 'Quitar Introducción a la Programación' })
        ).toBeNull();
        await llenarExamen();
        expect(await casilla(/^Grupo 5 · Blanco/)).toBeChecked();
    });

    it('sin ninguna asignatura no se continúa', async () => {
        montar();

        fireEvent.click(
            await screen.findByRole('button', { name: 'Quitar Introducción a la Programación' })
        );
        pulsar(/Continuar/);

        expect(await screen.findByText('Elija al menos una asignatura')).toBeVisible();
        expect(screen.queryByRole('heading', { name: '2. Grupos' })).toBeNull();
    });

    it('con varias asignaturas cada una tiene su sección, con lo elegido y las invitaciones plegadas', async () => {
        montar();
        fireEvent.click(await screen.findByRole('button', { name: /Agregar otra asignatura/ }));
        fireEvent.click(
            within(screen.getByRole('listbox')).getByRole('option', {
                name: /Taller de Ingeniería de Software/,
            })
        );
        await llenarExamen();

        const intro = await screen.findByRole('region', {
            name: 'Grupos de Introducción a la Programación',
        });
        const taller = await screen.findByRole('region', {
            name: 'Grupos de Taller de Ingeniería de Software',
        });
        // Misma estructura y un resumen de lo elegido en cada cabecera.
        expect(within(intro).getByText('1 grupo · 65 inscritos')).toBeVisible();
        expect(await within(taller).findByText('2 grupos · 76 inscritos')).toBeVisible();
        for (const seccion of [intro, taller]) {
            expect(within(seccion).getByText('Mis grupos')).toBeVisible();
            const invitar = within(seccion).getByRole('button', {
                name: /Para colaborar, añade grupos de otros docentes/,
            });
            expect(invitar).toHaveAttribute('aria-expanded', 'false');
        }

        // Plegadas: no se ven los grupos de otros docentes hasta abrirlas.
        expect(within(intro).queryByRole('checkbox', { name: /^Grupo 3 · Salazar/ })).toBeNull();
        fireEvent.click(
            within(intro).getByRole('button', {
                name: /Para colaborar, añade grupos de otros docentes/,
            })
        );
        fireEvent.click(within(intro).getByRole('checkbox', { name: /^Grupo 3 · Salazar/ }));
        expect(within(intro).getByText('2 grupos · 75 inscritos')).toBeVisible();

        // Una sección sin grupos lo dice en su propia cabecera.
        fireEvent.click(within(intro).getByRole('checkbox', { name: /^Grupo 2 · Blanco/ }));
        fireEvent.click(within(intro).getByRole('checkbox', { name: /^Grupo 3 · Salazar/ }));
        expect(within(intro).getByText('Elija al menos un grupo')).toBeVisible();
        expect(screen.getByRole('button', { name: /Continuar: Aulas/ })).toBeDisabled();
    });

    it('con una sola asignatura la sección se ve igual: cabecera con lo elegido y colaboración plegada', async () => {
        montar();
        await llenarExamen();

        const seccion = await screen.findByRole('region', {
            name: 'Grupos de Introducción a la Programación',
        });
        expect(within(seccion).getByText('1 grupo · 65 inscritos')).toBeVisible();
        expect(
            within(seccion).getByRole('button', { name: /Para colaborar, añade grupos/ })
        ).toHaveAttribute('aria-expanded', 'false');
        expect(within(seccion).queryByRole('checkbox', { name: /^Grupo 3 · Salazar/ })).toBeNull();
    });

    it('tras registrar, el mismo asistente sigue con habilitar estudiantes y los códigos QR', async () => {
        montar();
        await irAAulas();
        await guardar('Registrar examen');

        fireEvent.click(await screen.findByRole('button', { name: /Continuar: Habilitar/ }));
        expect(
            await screen.findByRole('heading', { name: '6. Habilitar estudiantes' })
        ).toBeVisible();

        pulsar(/Continuar: Códigos QR/);
        expect(await screen.findByRole('heading', { name: '7. Códigos QR' })).toBeVisible();
        expect(screen.getByRole('link', { name: 'Terminar' })).toHaveAttribute('href', '/examenes');

        // Atrás vuelve paso por paso.
        pulsar('Atrás');
        expect(
            await screen.findByRole('heading', { name: '6. Habilitar estudiantes' })
        ).toBeVisible();
        pulsar('Atrás');
        expect(await screen.findByText('Examen registrado')).toBeInTheDocument();
    });

    it('sin permiso de habilitación o de códigos QR el asistente no ofrece esos pasos', async () => {
        montar('/examenes/nuevo', usuarioDePrueba('Docente', { permisos: ['examenes'] }));
        await irAAulas();
        await guardar('Registrar examen');

        await screen.findByText('Examen registrado');
        expect(screen.queryByRole('button', { name: /Continuar: Habilitar/ })).toBeNull();
        const pasos = screen.getAllByRole('list')[0];
        expect(within(pasos).queryByText('Habilitar')).toBeNull();
        expect(within(pasos).queryByText('Códigos QR')).toBeNull();
    });

    it('en Grupos avisa de los grupos que ya tienen un examen de ese tipo y no deja continuar', async () => {
        ocupados = [
            { grupo_id: 37, codigo: '2', mensaje: 'El grupo 2 ya tiene un segundo parcial.' },
        ];
        montar();
        await campo(/^Fecha/);
        cambiar(screen.getByLabelText(/^Tipo/), 'SEGUNDO_PARCIAL');
        pulsar(/Continuar/);
        await screen.findByRole('heading', { name: '2. Grupos' });

        expect(await screen.findByText('Ya tiene un segundo parcial')).toBeVisible();
        expect(await screen.findByText('El grupo 2 ya tiene un segundo parcial.')).toBeVisible();
        expect(screen.getByRole('button', { name: /Continuar: Aulas/ })).toBeDisabled();
        // La consulta lleva el tipo y los grupos elegidos.
        expect(api.get).toHaveBeenCalledWith(
            '/examenes/opciones/ocupados',
            expect.objectContaining({
                params: expect.objectContaining({ tipo: 'SEGUNDO_PARCIAL', grupos: [37] }),
            })
        );

        // Sin el grupo ocupado, el aviso se va.
        fireEvent.click(screen.getByRole('checkbox', { name: /^Grupo 2 · Blanco/ }));
        await waitFor(() =>
            expect(screen.queryByText('El grupo 2 ya tiene un segundo parcial.')).toBeNull()
        );
    });

    it('sin normas también se registra', async () => {
        montar();
        await irAAulas();

        await guardar('Registrar examen');

        expect(await screen.findByText('Examen registrado')).toBeInTheDocument();
        expect(api.post.mock.calls[0][1]).toMatchObject({ normas: '', normas_marcadas: [] });
        expect(screen.getByText('Sin normas')).toBeInTheDocument();
    });

    it('tras registrar, volver a un paso guarda sobre el mismo examen', async () => {
        montar();
        await irAAulas();
        await guardar('Registrar examen');
        await screen.findByText('Examen registrado');

        pulsar(/Aulas/);
        await screen.findByRole('list', { name: 'Aulas' });
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691B' }));
        fireEvent.click(screen.getByRole('checkbox', { name: 'Aula 691A' }));
        await guardar('Guardar examen');

        expect(await screen.findByText('Examen guardado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledTimes(1);
        expect(api.put).toHaveBeenCalledWith(
            '/examenes/9',
            expect.objectContaining({
                aulas: [{ aula_id: 31, encargado_id: 7, apoyo: [] }],
                normas_conservadas: [],
            })
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

        await guardar('Registrar examen');

        expect(await screen.findByRole('heading', { name: '2. Grupos' })).toBeInTheDocument();
        expect(screen.getByRole('alert')).toHaveTextContent(
            'El grupo 3 ya tiene un primer parcial.'
        );

        // Al cambiar los grupos el mensaje se va.
        await abrirColaboracion();
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

        await guardar('Registrar examen');

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

        await guardar('Registrar examen');

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
        expect(await casilla(/C.I./)).not.toBeChecked();
        expect(await casilla(/Solo lápiz/)).toBeChecked();

        fireEvent.click(await casilla(/C.I./));
        fireEvent.click(await casilla(/Solo lápiz/));
        cambiar(screen.getByLabelText(/^Duración/), '100');
        pulsar(/Continuar/);
        expect(await casilla(/^Grupo 3 · Salazar/)).toBeChecked();
        expect(screen.getByText('75 inscritos')).toBeInTheDocument();
        pulsar(/Continuar/);
        await screen.findByRole('list', { name: 'Aulas' });
        await guardar('Guardar examen');

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
            aulas: [{ aula_id: 32, encargado_id: 12, apoyo: [9] }],
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

        await guardar('Guardar examen');

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'El examen ya tiene ingresos registrados: solo se pueden cambiar las normas.'
        );
        expect(screen.getByRole('heading', { name: '4. Equipo' })).toBeInTheDocument();
    });

    describe('un examen ya registrado se prepara en el mismo asistente', () => {
        const DE_LA_LISTA = {
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
            habilitados: 0,
            no_habilitados: 0,
            qr_emitidos: 0,
            codigos_aula: 0,
            propio: true,
            con_ingresos: false,
            con_incidencias: false,
            avance: {
                grupos: 'ok',
                aulas: 'ok',
                auxiliares: 'ok',
                habilitacion: 'ahora',
                qr: 'falta',
            },
        };

        beforeEach(() => {
            anteriores = [DE_LA_LISTA];
        });

        it('cada paso se abre en cualquier momento y lleva su estado', async () => {
            montar('/examenes/nuevo?examen=4&paso=0');
            await screen.findByRole('heading', { name: '1. Examen' });

            // Habilitar y códigos QR faltan; los del registro están completos.
            expect(
                await screen.findByRole('button', { name: 'Habilitar · incompleto' })
            ).toBeEnabled();
            expect(screen.getByRole('button', { name: 'Códigos QR · incompleto' })).toBeEnabled();

            fireEvent.click(screen.getByRole('button', { name: 'Habilitar · incompleto' }));
            expect(
                await screen.findByRole('heading', { name: '6. Habilitar estudiantes' })
            ).toBeVisible();

            // También se vuelve y se salta hacia atrás y adelante.
            fireEvent.click(screen.getByRole('button', { name: /^Aulas/ }));
            expect(await screen.findByRole('heading', { name: '3. Aulas' })).toBeVisible();
            fireEvent.click(screen.getByRole('button', { name: 'Códigos QR · incompleto' }));
            expect(await screen.findByRole('heading', { name: '7. Códigos QR' })).toBeVisible();
        });

        it('guarda un cambio sin salir del paso', async () => {
            api.put.mockResolvedValue({
                data: { data: { ...DETALLE }, message: 'Examen guardado.' },
            });
            montar('/examenes/nuevo?examen=4&paso=0');
            cambiar(await campo(/^Duración/), '100');

            pulsar('Guardar cambios');

            await waitFor(() => expect(api.put).toHaveBeenCalledTimes(1));
            expect(api.put.mock.calls[0][1]).toMatchObject({ duracion_minutos: 100 });
            expect(await screen.findByText('Examen guardado')).toBeInTheDocument();
            // Sigue en el mismo paso.
            expect(screen.getByRole('heading', { name: '1. Examen' })).toBeVisible();
        });

        it('la cabecera trae la fecha, los accesos a bitácora y sin conexión, y eliminar', async () => {
            montar('/examenes/nuevo?examen=4&paso=0');

            expect(await screen.findByText(/lunes 23 de noviembre, 14:15 · 120 min/)).toBeVisible();
            expect(await screen.findByRole('link', { name: 'Bitácora' })).toHaveAttribute(
                'href',
                '/examenes/detalle?examen=4&pestana=bitacora'
            );
            expect(screen.getByRole('link', { name: 'Sin conexión' })).toHaveAttribute(
                'href',
                '/examenes/detalle?examen=4&pestana=sin-conexion'
            );

            fireEvent.click(screen.getByRole('button', { name: /Eliminar/ }));
            expect(await screen.findByRole('dialog', { name: 'Eliminar examen' })).toBeVisible();
        });

        it('con ingresos o incidencias ya no se ofrece eliminar', async () => {
            anteriores = [{ ...DE_LA_LISTA, con_ingresos: true }];
            montar('/examenes/nuevo?examen=4&paso=0');

            await screen.findByRole('link', { name: 'Bitácora' });
            expect(screen.queryByRole('button', { name: /Eliminar/ })).not.toBeInTheDocument();
        });
    });

    it('al docente de un grupo incluido le muestra el examen sin poder modificarlo', async () => {
        detalle = { ...DETALLE, propio: false, registrado_por: 'Rodriguez Bilbao Erika' };
        montar('/examenes/nuevo?examen=4&paso=2');

        expect(await screen.findByText('Registrado por Rodriguez Bilbao Erika')).toBeVisible();
        expect(screen.getByText('1 aula: 691B')).toBeInTheDocument();
        expect(screen.getByRole('list', { name: 'Quién atiende' })).toHaveTextContent(
            '691B · Vargas Arce Sergio · Rojas Ledezma Andrea'
        );
        expect(screen.getByText('Mochilas al frente.')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Guardar|Continuar/ })).not.toBeInTheDocument();
        expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
    });

    it('el resumen nombra a los docentes de los grupos que ya están en el examen', async () => {
        detalle = { ...DETALLE, propio: false };
        montar('/examenes/nuevo?examen=4');

        expect(
            await screen.findByText('Docentes: Blanco Coca Leticia, Salazar Serrudo Carla')
        ).toBeVisible();
    });

    it('quien registró el examen ve el estado de cada invitación a otro docente', async () => {
        detalle = {
            ...DETALLE,
            propio: false,
            invitaciones: [
                {
                    id: 7,
                    grupo_id: 12,
                    grupo_codigo: '3',
                    docente: 'Ustariz Vargas Hernan',
                    estado: 'pendiente',
                },
                {
                    id: 8,
                    grupo_id: 13,
                    grupo_codigo: '4',
                    docente: 'Montano Quiroga Victor Hugo',
                    estado: 'rechazada',
                },
            ],
        };
        montar('/examenes/nuevo?examen=4');

        const lista = await screen.findByRole('list', { name: 'Invitaciones a otros docentes' });
        expect(
            within(lista).getByText(/Grupo 3 · Ustariz Vargas Hernan · Pendiente/)
        ).toBeVisible();
        expect(
            within(lista).getByText(/Grupo 4 · Montano Quiroga Victor Hugo · Rechazó/)
        ).toBeVisible();
    });

    it('un colaborador puede salirse del examen, con confirmación', async () => {
        detalle = { ...DETALLE, propio: false, registrado_por: 'Rodriguez Bilbao Erika' };
        api.post.mockResolvedValue({ data: { message: 'Salió del examen.' } });
        montar('/examenes/nuevo?examen=4');

        fireEvent.click(await screen.findByRole('button', { name: 'Salir del examen' }));
        expect(api.post).not.toHaveBeenCalled();
        const confirmar = screen.getByRole('group', { name: 'Salir del examen' });
        fireEvent.click(within(confirmar).getByRole('button', { name: 'Salir' }));

        await waitFor(() => expect(api.post).toHaveBeenCalledWith('/examenes/4/salir'));
    });

    it('con ingresos registrados el colaborador ya no puede salirse', async () => {
        detalle = { ...DETALLE, propio: false, con_ingresos: true };
        montar('/examenes/nuevo?examen=4');

        expect(await screen.findByText(/Registrado por/)).toBeVisible();
        expect(screen.queryByRole('button', { name: 'Salir del examen' })).not.toBeInTheDocument();
    });

    it.each([
        [403, 'Este examen no está a su cargo.', 'Este examen no está a su cargo'],
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
