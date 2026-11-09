import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PuntoControl from './PuntoControl';
import { api } from '../../api/cliente';
import { AccesoAulaContexto } from '../../sesion/AccesoAulaContexto';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

// El lector de la cámara, sin cámara: dos botones que leen o fallan.
vi.mock('../../componentes/LectorQr', () => ({
    default: ({ activo, onLectura, onError }) =>
        activo ? (
            <div data-testid="lector">
                <button type="button" onClick={() => onLectura('TOKEN-DE-PRUEBA')}>
                    simular lectura
                </button>
                <button type="button" onClick={() => onError(new Error('sin cámara'))}>
                    simular fallo
                </button>
            </div>
        ) : null,
}));

const AULAS = [
    {
        aula_id: 31,
        nombre: '691A',
        ubicacion: 'Edificio Académico 2 · 1° Piso',
        examenes: [{ id: 1, texto: 'Primer parcial · Introducción a la Programación' }],
        abierta: true,
    },
    {
        aula_id: 68,
        nombre: '617',
        ubicacion: 'Aula 617-617C',
        examenes: [{ id: 1, texto: 'Primer parcial · Introducción a la Programación' }],
        abierta: true,
    },
];

const EDIFICIOS = [
    {
        id: 7,
        clave: 'fcyt_7',
        facultad: 'FCyT',
        nombre: 'Edificio Académico 2',
        poligono: [
            [-66.145, -17.3938],
            [-66.1448, -17.3938],
            [-66.1448, -17.3936],
            [-66.145, -17.3938],
        ],
        centro: [-66.1449, -17.3937],
        aulas: ['691A'],
        pisos: [],
    },
    {
        id: 14,
        clave: 'fcyt_14',
        facultad: 'FCyT',
        nombre: 'Aula 617-617C',
        poligono: [
            [-66.146, -17.3948],
            [-66.1458, -17.3948],
            [-66.1458, -17.3946],
            [-66.146, -17.3948],
        ],
        centro: [-66.1459, -17.3947],
        aulas: ['617'],
        pisos: [],
    },
];

const ingresosIniciales = () =>
    Array.from({ length: 8 }, (_, i) => ({
        id: 7001 + i,
        estudiante_id: 100 + i,
        codigo: String(202100001 + i),
        nombre: `Apellido${String(i + 1).padStart(2, '0')}, Nombre`,
        hora: `08:1${i}:00`,
        examen_id: i % 2 === 0 ? 1 : 8,
        excepcion: i === 1,
        expulsado: i === 2,
    }));

function puerta(ingresos, params = {}, cambios = {}) {
    const todos = params.ingresos === 'todos';
    const texto = (params.buscar ?? '').toLowerCase();
    const visibles = todos
        ? ingresos.filter((i) => !texto || `${i.nombre} ${i.codigo}`.toLowerCase().includes(texto))
        : ingresos.slice(0, 5);
    return {
        aula: {
            id: 31,
            nombre: '691A',
            ubicacion: 'Edificio Académico 2 · 1° Piso',
            edificio_id: 7,
        },
        hora_servidor: '2026-10-12T08:21:07-04:00',
        abierta: true,
        examenes: [
            {
                id: 1,
                asignatura: 'Introducción a la Programación',
                tipo: 'Primer parcial',
                hora: '08:15',
                ingresados: 38,
                asignados: 51,
                faltan: 13,
                normas: 'Sin celular.\nHoja de fórmulas A4.',
                normas_marcadas: ['C.I. a la vista', 'Sin mochilas'],
            },
            {
                id: 8,
                asignatura: 'Cálculo I',
                tipo: 'Primer parcial',
                hora: '08:15',
                ingresados: 21,
                asignados: 40,
                faltan: 19,
                normas: null,
                normas_marcadas: [],
            },
        ],
        ingresos: visibles,
        total_ingresos: ingresos.length,
        ...cambios,
    };
}

const ESTUDIANTE = { id: 9, codigo: '202105877', nombre: 'Aguilar Cossío Mariana' };
const EXAMEN = { id: 1, asignatura: 'Introducción a la Programación', tipo: 'Primer parcial' };
const VEREDICTOS = {
    admitido: {
        veredicto: 'admitido',
        estudiante: ESTUDIANTE,
        examen: EXAMEN,
        dato: 'Registrado a las 08:21:07',
        pie: null,
        ingreso: { id: 7100, hora: '08:21:07' },
    },
    no_habilitado: {
        veredicto: 'no_habilitado',
        estudiante: ESTUDIANTE,
        examen: EXAMEN,
        dato: 'Adeuda la matrícula del semestre',
        pie: 'Registrado por Omonte Ojalvo Jose Roberto · 6 oct',
    },
    otra_aula: {
        veredicto: 'otra_aula',
        estudiante: ESTUDIANTE,
        examen: EXAMEN,
        dato: null,
        pie: null,
        aula_correcta: {
            id: 68,
            nombre: '617',
            edificio: 'Aula 617-617C',
            piso: 'Planta baja',
            edificio_id: 14,
        },
    },
    ya_ingreso: {
        veredicto: 'ya_ingreso',
        estudiante: ESTUDIANTE,
        examen: EXAMEN,
        dato: 'Ingresó a las 08:02:41 en esta aula',
        pie: null,
    },
    expulsado: {
        veredicto: 'expulsado',
        estudiante: ESTUDIANTE,
        examen: EXAMEN,
        dato: 'Expulsado a las 08:40',
        pie: null,
    },
    no_corresponde: {
        veredicto: 'no_corresponde',
        estudiante: ESTUDIANTE,
        examen: null,
        dato: 'Sin examen en el aula 691A a esta hora',
        pie: 'Intento registrado',
        examen_correspondiente: { texto: 'Primer parcial · Álgebra I', aula: '624' },
    },
};

const montar = ({ ruta = '/control', rol = 'Docente' } = {}) =>
    renderConSesion(<PuntoControl />, { usuario: usuarioDePrueba(rol), ruta });
const lecturas = (aula = 31) =>
    api.get.mock.calls
        .filter(([url]) => url === `/puerta/aulas/${aula}`)
        .map(([, config]) => config.params);
const enviados = (sufijo) =>
    api.post.mock.calls.filter(([url]) => url.endsWith(sufijo)).map(([, cuerpo]) => cuerpo);
const lista = () => screen.getByRole('list', { name: 'Ingresos' });
const cargada = () => screen.findByText('Apellido01, Nombre');

async function verificarManual(valor) {
    fireEvent.click(await screen.findByRole('button', { name: 'Código SIS o C.I. manual' }));
    fireEvent.change(screen.getByPlaceholderText('Código SIS o C.I.'), {
        target: { value: valor },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Verificar' }));
}

let ingresos;
let rutas;
let veredicto;

beforeEach(() => {
    ingresos = ingresosIniciales();
    veredicto = VEREDICTOS.admitido;
    rutas = {
        'GET /puerta/aulas': { data: AULAS, hoy: '2026-10-12', acceso: null },
        'GET /puerta/aulas/31': ({ params }) => puerta(ingresos, params),
        'GET /puerta/aulas/68': ({ params }) =>
            puerta([], params, {
                aula: { id: 68, nombre: '617', ubicacion: 'Aula 617-617C', edificio_id: 14 },
            }),
        'GET /puerta/aulas/31/edificios': { data: EDIFICIOS, meta: { caja: null } },
        'POST /puerta/aulas/31/verificaciones': () => veredicto,
        'POST /puerta/aulas/31/excepciones': ({ data }) => ({
            message: 'Situación excepcional registrada: cambio de aula.',
            incidencia_id: 40,
            ingreso: data.tipo === 'CAMBIO_AULA' ? { id: 7002, hora: '08:22:10' } : null,
        }),
        'POST /puerta/ingresos/*/expulsion': ({ url }) => {
            const id = Number(url.split('/')[3]);
            ingresos = ingresos.map((i) => (i.id === id ? { ...i, expulsado: true } : i));
            return { message: 'Expulsión registrada.', incidencia_id: 41 };
        },
        'POST /examenes/*/incidencias': { data: { id: 50 }, message: 'Incidencia registrada.' },
    };
    simularApi(api, rutas);
});

describe('Punto de control', () => {
    it('muestra el aula, sus exámenes con normas y los ingresos recientes', async () => {
        montar();
        await cargada();

        expect(screen.getByRole('heading', { name: 'Punto de control' })).toBeInTheDocument();
        expect(screen.getByText('Aula 691A')).toBeInTheDocument();
        expect(screen.getByText('Edificio Académico 2 · 1° Piso')).toBeInTheDocument();
        expect(screen.getByText('12 oct · 08:15')).toBeInTheDocument();
        const examenes = within(screen.getByRole('list', { name: 'Exámenes del aula' }));
        expect(examenes.getByText('Introducción a la Programación')).toBeInTheDocument();
        expect(examenes.getByText('38 ingresaron')).toBeInTheDocument();
        expect(examenes.getByText('Faltan 13')).toBeInTheDocument();
        expect(examenes.getByText('Cálculo I')).toBeInTheDocument();
        expect(examenes.getByText('21 ingresaron')).toBeInTheDocument();
        expect(examenes.getByText('Faltan 19')).toBeInTheDocument();
        expect(lecturas()[0]).toEqual({});

        expect(screen.queryByText(/Sin celular/)).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Normas' }));
        expect(screen.getByText(/Sin celular/)).toHaveTextContent('Hoja de fórmulas A4.');
        const marcadas = within(screen.getByRole('list', { name: 'Normas marcadas' }));
        expect(marcadas.getAllByRole('listitem')).toHaveLength(2);
        expect(marcadas.getByText('C.I. a la vista')).toBeInTheDocument();
        expect(marcadas.getByText('Sin mochilas')).toBeInTheDocument();
        expect(screen.getByText('Sin normas')).toBeInTheDocument();

        expect(screen.getByText('Ingresos recientes')).toBeInTheDocument();
        expect(within(lista()).getAllByRole('listitem')).toHaveLength(5);
        expect(within(lista()).getByText('Excepción')).toBeInTheDocument();
        expect(within(lista()).getByText('Expulsado')).toBeInTheDocument();
    });

    it('identifica leyendo el QR y vuelve al visor con la cámara encendida', async () => {
        montar();
        await cargada();

        expect(screen.queryByTestId('lector')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: 'Leer código QR' }));
        fireEvent.click(screen.getByRole('button', { name: 'simular lectura' }));

        expect(await screen.findByText('Ingreso admitido')).toBeInTheDocument();
        expect(enviados('/verificaciones')).toEqual([{ token: 'TOKEN-DE-PRUEBA' }]);
        expect(screen.getByText('Aguilar Cossío Mariana')).toBeInTheDocument();
        expect(screen.getByText('Código SIS 202105877')).toBeInTheDocument();
        expect(screen.getByText('Introducción a la Programación · Primer parcial')).toBeVisible();
        expect(screen.getByText('Registrado a las 08:21:07')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /excepci/i })).toBeNull();
        expect(screen.queryByTestId('lector')).toBeNull();
        await waitFor(() => expect(lecturas().length).toBeGreaterThan(1));

        fireEvent.click(screen.getByRole('button', { name: /Siguiente/ }));
        expect(screen.getByTestId('lector')).toBeInTheDocument();
        expect(screen.queryByText('Ingreso admitido')).toBeNull();
    });

    it('muestra la foto del estudiante para comparar la cara, o que no tiene', async () => {
        veredicto = {
            ...VEREDICTOS.ya_ingreso,
            estudiante: { ...ESTUDIANTE, foto: 'data:image/jpeg;base64,AAAA' },
            pie: 'Incidencia registrada',
        };
        montar();
        await cargada();
        fireEvent.click(screen.getByRole('button', { name: 'Leer código QR' }));
        fireEvent.click(screen.getByRole('button', { name: 'simular lectura' }));

        expect(
            await screen.findByRole('img', { name: 'Foto de Aguilar Cossío Mariana' })
        ).toHaveAttribute('src', 'data:image/jpeg;base64,AAAA');
        expect(screen.getByText('Incidencia registrada')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Siguiente/ }));
        veredicto = VEREDICTOS.admitido;
        fireEvent.click(screen.getByRole('button', { name: 'simular lectura' }));
        expect(await screen.findByText('Sin foto')).toBeInTheDocument();
    });

    it('con la cámara no disponible abre el ingreso manual', async () => {
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Leer código QR' }));
        fireEvent.click(screen.getByRole('button', { name: 'simular fallo' }));

        expect(screen.getByText('Cámara no disponible')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Código SIS o C.I.')).toBeInTheDocument();
        expect(screen.queryByTestId('lector')).toBeNull();
    });

    it('identifica a mano por código o documento y valida el dato', async () => {
        veredicto = VEREDICTOS.no_habilitado;
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Código SIS o C.I. manual' }));
        fireEvent.click(screen.getByRole('button', { name: 'Verificar' }));
        expect(screen.getByText('Obligatorio')).toBeInTheDocument();
        fireEvent.change(screen.getByPlaceholderText('Código SIS o C.I.'), {
            target: { value: '12a' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Verificar' }));
        expect(screen.getByText('Código SIS o C.I. no válido')).toBeInTheDocument();
        expect(enviados('/verificaciones')).toEqual([]);

        fireEvent.change(screen.getByPlaceholderText('Código SIS o C.I.'), {
            target: { value: '202105877' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Verificar' }));

        expect(await screen.findByText('No habilitado')).toBeInTheDocument();
        expect(enviados('/verificaciones')).toEqual([{ identificador: '202105877' }]);
        expect(screen.getByText('Adeuda la matrícula del semestre')).toBeInTheDocument();
        expect(
            screen.getByText('Registrado por Omonte Ojalvo Jose Roberto · 6 oct')
        ).toBeInTheDocument();

        // Venía del ingreso manual: «Siguiente» no enciende la cámara y deja
        // el campo abierto, vacío y con el foco para el próximo.
        fireEvent.click(screen.getByRole('button', { name: /Siguiente/ }));
        expect(screen.queryByTestId('lector')).toBeNull();
        const campo = screen.getByPlaceholderText('Código SIS o C.I.');
        expect(campo).toHaveValue('');
        expect(campo).toHaveFocus();
    });

    it('acepta el C.I. con complemento', async () => {
        montar();
        await cargada();
        await verificarManual('5003748-1a');

        expect(await screen.findByText('Ingreso admitido')).toBeInTheDocument();
        expect(enviados('/verificaciones')).toEqual([{ identificador: '5003748-1A' }]);
    });

    it('muestra el veredicto «No registrado» si no está en el padrón', async () => {
        rutas['POST /puerta/aulas/31/verificaciones'] = errorHttp(422, {
            message: 'Sin registro',
            errors: { identificador: ['Sin registro'] },
        });
        simularApi(api, rutas);
        montar();
        await cargada();

        await verificarManual('99999999');

        expect(await screen.findByText('No registrado')).toBeInTheDocument();
        expect(screen.getByText('99999999 · sin registro en el padrón')).toBeInTheDocument();
    });

    it('otra aula: indica aula, edificio, piso y mapa, y admite como excepción', async () => {
        veredicto = VEREDICTOS.otra_aula;
        montar();
        await cargada();
        await verificarManual('202105877');

        expect(await screen.findByText('Otra aula')).toBeInTheDocument();
        expect(screen.getByText('Aula 617', { selector: 'p' })).toBeInTheDocument();
        expect(screen.getByText('Aula 617-617C · Planta baja')).toBeInTheDocument();
        const mapa = await screen.findByRole('img', { name: 'Mapa de edificios del campus' });
        expect(mapa).toHaveTextContent('Aquí');
        // Los edificios salen de la ruta de la puerta, no de la del sistema.
        expect(api.get).toHaveBeenCalledWith('/puerta/aulas/31/edificios', expect.anything());
        expect(api.get.mock.calls.some(([url]) => url === '/edificios')).toBe(false);

        fireEvent.click(screen.getByRole('button', { name: 'Admitir como excepción' }));
        expect(screen.getByLabelText('Tipo')).toHaveValue('CAMBIO_AULA');
        expect(screen.getByText('Blanco Coca Leticia · 08:21')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Registrar' })).toBeDisabled();
        fireEvent.change(screen.getByLabelText(/Motivo/), {
            target: { value: 'Su aula está llena' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Registrar' }));

        expect(
            await screen.findByText(
                'Situación excepcional registrada: cambio de aula · Ingresó a las 08:22:10'
            )
        ).toBeInTheDocument();
        expect(enviados('/excepciones')).toEqual([
            { tipo: 'CAMBIO_AULA', motivo: 'Su aula está llena', estudiante_id: 9, examen_id: 1 },
        ]);
        expect(screen.queryByRole('button', { name: 'Admitir como excepción' })).toBeNull();
        // Admitido: el veredicto deja de decir «Otra aula».
        expect(screen.getByText('Admitido como excepción')).toBeInTheDocument();
        expect(screen.queryByText('Otra aula')).toBeNull();
        // Ya no se lo manda a otra aula: rinde en esta, sin mapa.
        expect(screen.getByText('Rinde en Aula 691A')).toBeInTheDocument();
        expect(screen.queryByText('Aula 617')).toBeNull();
        expect(screen.queryByRole('img', { name: 'Mapa de edificios del campus' })).toBeNull();
    });

    it('no es de esta aula: muestra dónde le toca y registra la situación con motivo', async () => {
        veredicto = VEREDICTOS.no_corresponde;
        rutas['POST /puerta/aulas/31/excepciones'] = ({ data }) =>
            data.motivo.length < 5
                ? errorHttp(422, { errors: { motivo: ['Mínimo 5 caracteres'] } })
                : {
                      message: 'Situación excepcional registrada: problema de identificación.',
                      incidencia_id: 41,
                      ingreso: null,
                  };
        simularApi(api, rutas);
        montar();
        await cargada();
        await verificarManual('202105877');

        expect(await screen.findByText('No es de esta aula')).toBeInTheDocument();
        expect(screen.getByText('Sin examen en el aula 691A a esta hora')).toBeInTheDocument();
        expect(screen.getByText('Primer parcial · Álgebra I · Aula 624')).toBeInTheDocument();
        expect(screen.getByText('Intento registrado')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Situación excepcional' }));
        const tipo = screen.getByLabelText('Tipo');
        expect(tipo).toHaveValue('PROBLEMA_IDENTIFICACION');
        // Sin examen resuelto no se ofrece el cambio de aula.
        expect(within(tipo).queryByRole('option', { name: 'Cambio de aula' })).toBeNull();

        fireEvent.change(screen.getByLabelText(/Motivo/), { target: { value: 'abc' } });
        fireEvent.click(screen.getByRole('button', { name: 'Registrar' }));
        expect(screen.getByText('Mínimo 5 caracteres')).toBeInTheDocument();
        expect(enviados('/excepciones')).toEqual([]);

        fireEvent.change(screen.getByLabelText(/Motivo/), {
            target: { value: 'Carnet ilegible' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Registrar' }));

        expect(
            await screen.findByText('Situación excepcional registrada: problema de identificación')
        ).toBeInTheDocument();
        expect(enviados('/excepciones')).toEqual([
            {
                tipo: 'PROBLEMA_IDENTIFICACION',
                motivo: 'Carnet ilegible',
                estudiante_id: 9,
                examen_id: null,
            },
        ]);
    });

    it('avisa si la excepción no puede admitir', async () => {
        veredicto = VEREDICTOS.otra_aula;
        rutas['POST /puerta/aulas/31/excepciones'] = errorHttp(409, {
            message: 'El estudiante ya ingresó a este examen.',
            codigo: 'YA_INGRESO',
        });
        simularApi(api, rutas);
        montar();
        await cargada();
        await verificarManual('202105877');

        fireEvent.click(await screen.findByRole('button', { name: 'Admitir como excepción' }));
        fireEvent.change(screen.getByLabelText(/Motivo/), {
            target: { value: 'Su aula está llena' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Registrar' }));

        expect(
            await screen.findByText('El estudiante ya ingresó a este examen')
        ).toBeInTheDocument();
    });

    it.each([
        ['ya_ingreso', 'Ya ingresó', 'Ingresó a las 08:02:41 en esta aula'],
        ['expulsado', 'Expulsado', 'Expulsado a las 08:40'],
    ])('%s: muestra la hora y solo ofrece «Siguiente»', async (clave, titulo, dato) => {
        veredicto = VEREDICTOS[clave];
        montar();
        await cargada();
        await verificarManual('202105877');

        const tarjeta = (await screen.findByText(dato)).closest('[role=status]');
        expect(within(tarjeta).getByText(titulo)).toBeInTheDocument();
        expect(within(tarjeta).getAllByRole('button')).toHaveLength(1);
        expect(within(tarjeta).getByRole('button', { name: /Siguiente/ })).toBeInTheDocument();
    });

    it('registra una expulsión con motivo obligatorio', async () => {
        montar();
        await cargada();

        fireEvent.click(
            screen.getByRole('button', { name: 'Registrar expulsión de Apellido01, Nombre' })
        );
        const dialogo = screen.getByRole('dialog', { name: 'Expulsión' });
        expect(dialogo).toHaveTextContent('Aula 691A · Ingresó 08:10:00');
        expect(dialogo).toHaveTextContent('Blanco Coca Leticia · 08:21');
        const registrar = within(dialogo).getByRole('button', { name: 'Registrar' });
        expect(registrar).toBeDisabled();

        fireEvent.change(within(dialogo).getByLabelText(/Motivo/), { target: { value: 'abc' } });
        fireEvent.click(registrar);
        expect(within(dialogo).getByText('Mínimo 5 caracteres')).toBeInTheDocument();

        fireEvent.change(within(dialogo).getByLabelText(/Motivo/), {
            target: { value: 'Usó el celular' },
        });
        fireEvent.click(registrar);

        expect(await screen.findByText('Expulsión registrada')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/puerta/ingresos/7001/expulsion', {
            motivo: 'Usó el celular',
        });
        await waitFor(() => expect(within(lista()).getAllByText('Expulsado')).toHaveLength(2));
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('avisa si el ingreso ya estaba expulsado', async () => {
        rutas['POST /puerta/ingresos/*/expulsion'] = errorHttp(409, {
            message: 'El estudiante ya fue expulsado.',
            codigo: 'YA_EXPULSADO',
        });
        simularApi(api, rutas);
        montar();
        await cargada();

        fireEvent.click(
            screen.getByRole('button', { name: 'Registrar expulsión de Apellido01, Nombre' })
        );
        fireEvent.change(screen.getByLabelText(/Motivo/), { target: { value: 'Usó el celular' } });
        fireEvent.click(screen.getByRole('button', { name: 'Registrar' }));

        expect(await screen.findByText('Ya estaba expulsado')).toBeInTheDocument();
    });

    it('reporta una incidencia desde la puerta en el examen que la admite', async () => {
        rutas['POST /examenes/*/incidencias'] = ({ url }) =>
            url === '/examenes/1/incidencias'
                ? errorHttp(403, { message: 'Esta aula no está a su cargo.', alcance: true })
                : { data: { id: 50 }, message: 'Incidencia registrada.' };
        simularApi(api, rutas);
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: /Incidencia/ }));
        const dialogo = screen.getByRole('dialog', { name: 'Incidencia' });
        expect(dialogo).toHaveTextContent('Aula 691A · 08:21 · Blanco Coca Leticia');
        fireEvent.change(within(dialogo).getByLabelText(/Tipo/), {
            target: { value: 'MATERIAL_NO_PERMITIDO' },
        });
        fireEvent.change(within(dialogo).getByLabelText(/Detalle/), {
            target: { value: 'Calculadora programable' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Registrar' }));

        expect(await screen.findByText('Incidencia registrada')).toBeInTheDocument();
        const llamadas = api.post.mock.calls.filter(([url]) => url.endsWith('/incidencias'));
        expect(llamadas.map(([url]) => url)).toEqual([
            '/examenes/1/incidencias',
            '/examenes/8/incidencias',
        ]);
        expect(llamadas[1][1]).toEqual({
            aula_id: 31,
            tipo: 'MATERIAL_NO_PERMITIDO',
            estudiante: null,
            detalle: 'Calculadora programable',
        });
    });

    it('despliega todos los ingresos y busca en el servidor', async () => {
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Ver los 8' }));
        expect(await screen.findByText('Ingresos · 8')).toBeInTheDocument();
        await waitFor(() => expect(within(lista()).getAllByRole('listitem')).toHaveLength(8));
        expect(lecturas().at(-1)).toEqual({ ingresos: 'todos' });

        fireEvent.change(screen.getByLabelText('Buscar ingreso'), {
            target: { value: 'Apellido07' },
        });
        await waitFor(() =>
            expect(lecturas().at(-1)).toEqual({ ingresos: 'todos', buscar: 'Apellido07' })
        );
        await waitFor(() => expect(within(lista()).getAllByRole('listitem')).toHaveLength(1));

        fireEvent.change(screen.getByLabelText('Buscar ingreso'), { target: { value: 'zzz' } });
        expect(await within(lista()).findByText('Sin resultados')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Ver menos' }));
        await waitFor(() => expect(within(lista()).getAllByRole('listitem')).toHaveLength(5));
    });

    it('pasa a otra de las aulas que la persona atiende', async () => {
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: 'Cambiar' }));
        const dialogo = screen.getByRole('dialog', { name: 'Aula' });
        expect(within(dialogo).getByText('Actual')).toBeInTheDocument();
        fireEvent.click(within(dialogo).getByText('Aula 617').closest('button'));

        expect(await screen.findByText('Sin ingresos')).toBeInTheDocument();
        expect(screen.getByText('Aula 617')).toBeInTheDocument();
        expect(lecturas(68)).toHaveLength(1);
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('toma el aula de `?aula=` y enlaza la lista según la ruta', async () => {
        const { unmount } = montar({ ruta: '/control?aula=68' });
        expect(await screen.findByText('Sin ingresos')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Lista/ })).toHaveAttribute(
            'href',
            '/en-curso/aula?examen=1&aula=68'
        );
        expect(lecturas(31)).toHaveLength(0);
        unmount();

        montar({ ruta: '/ingreso', rol: 'Auxiliar' });
        await cargada();
        expect(screen.getByRole('link', { name: /Lista/ })).toHaveAttribute(
            'href',
            '/mi-aula?examen=1&aula=31'
        );
    });

    it('con el aula cerrada no ofrece el visor', async () => {
        rutas['GET /puerta/aulas/31'] = ({ params }) => puerta([], params, { abierta: false });
        simularApi(api, rutas);
        montar();

        expect(await screen.findByText('Sin examen a esta hora')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Leer código QR' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Código SIS o C.I. manual' })).toBeNull();
    });

    it('avisa si el examen del aula terminó mientras se verificaba', async () => {
        rutas['POST /puerta/aulas/31/verificaciones'] = errorHttp(409, {
            message: 'El aula no tiene un examen abierto.',
            codigo: 'AULA_SIN_EXAMEN',
        });
        simularApi(api, rutas);
        montar();
        await cargada();
        await verificarManual('202105877');

        expect(await screen.findByText('Sin examen a esta hora')).toBeInTheDocument();
    });

    it('sin aulas hoy, con el aula ajena y con error de carga', async () => {
        rutas['GET /puerta/aulas'] = { data: [], hoy: '2026-10-12', acceso: null };
        simularApi(api, rutas);
        const vacio = montar();
        expect(await screen.findByText('Ningún examen abierto en tus aulas')).toBeInTheDocument();
        vacio.unmount();

        rutas['GET /puerta/aulas'] = { data: AULAS, hoy: '2026-10-12', acceso: null };
        rutas['GET /puerta/aulas/31'] = errorHttp(403, {
            message: 'Esta aula no está a su cargo.',
            alcance: true,
        });
        simularApi(api, rutas);
        const ajena = montar();
        expect(await screen.findByRole('alert')).toHaveTextContent('Esta aula no está a su cargo');
        ajena.unmount();

        rutas['GET /puerta/aulas/31'] = errorHttp(500, {});
        simularApi(api, rutas);
        montar();
        expect(await screen.findByText('No se pudo cargar')).toBeInTheDocument();
        rutas['GET /puerta/aulas/31'] = ({ params }) => puerta(ingresos, params);
        simularApi(api, rutas);
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        await cargada();
    });

    it('con código de aula opera sin cuenta y sin cambio de aula, con el mapa de la puerta', async () => {
        veredicto = VEREDICTOS.otra_aula;
        rutas['GET /puerta/aulas'] = {
            data: [AULAS[0]],
            hoy: '2026-10-12',
            acceso: { aula_id: 31, aula: '691A', examen_id: 8, nombre: 'Rojas Vargas Andrea' },
        };
        simularApi(api, rutas);
        const acceso = {
            aula_id: 31,
            aula: '691A',
            examen_id: 8,
            nombre: 'Rojas Vargas Andrea',
            hasta: null,
        };
        renderConSesion(
            <AccesoAulaContexto.Provider value={acceso}>
                <PuntoControl />
            </AccesoAulaContexto.Provider>,
            { usuario: null, ruta: '/acceso-aula?aula=31' }
        );
        await cargada();

        expect(screen.queryByRole('button', { name: 'Cambiar' })).toBeNull();
        expect(screen.queryByRole('link', { name: /Lista/ })).toBeNull();

        await verificarManual('202105877');
        expect(await screen.findByText('Aula 617-617C · Planta baja')).toBeInTheDocument();
        // Con código de aula no se registran excepciones ni expulsiones.
        expect(screen.queryByRole('button', { name: 'Admitir como excepción' })).toBeNull();
        expect(screen.queryByRole('button', { name: /Registrar expulsión/ })).toBeNull();
        // Los edificios del sistema exigen cuenta: el mapa usa los de la puerta.
        expect(
            await screen.findByRole('img', { name: 'Mapa de edificios del campus' })
        ).toHaveTextContent('Aquí');
        expect(api.get).toHaveBeenCalledWith('/puerta/aulas/31/edificios', expect.anything());
        expect(api.get.mock.calls.some(([url]) => url === '/edificios')).toBe(false);

        fireEvent.click(screen.getByRole('button', { name: /Siguiente/ }));
        fireEvent.click(screen.getByRole('button', { name: /Incidencia/ }));
        fireEvent.change(screen.getByLabelText(/Tipo/), { target: { value: 'OTRA' } });
        fireEvent.change(screen.getByLabelText(/Detalle/), {
            target: { value: 'Ruido en el pasillo' },
        });
        fireEvent.click(
            within(screen.getByRole('dialog')).getByRole('button', { name: 'Registrar' })
        );
        await screen.findByText('Incidencia registrada');
        expect(api.post).toHaveBeenCalledWith('/examenes/8/incidencias', expect.anything());
    });

    it('sin examen que le corresponda, el veredicto dice «No inscrito»', async () => {
        veredicto = { ...VEREDICTOS.no_corresponde, examen_correspondiente: null };
        montar();
        await cargada();
        await verificarManual('202105877');

        expect(await screen.findByText('No inscrito')).toBeInTheDocument();
        expect(screen.queryByText('No es de esta aula')).toBeNull();
    });

    it('marca los ingresos tarde y deja el campo manual bajo el veredicto', async () => {
        ingresos = [{ ...ingresosIniciales()[0], hora: '08:40' }];
        montar();
        expect(await screen.findByText('Tarde')).toBeInTheDocument();

        await verificarManual('202105877');
        expect(await screen.findByText('Ingreso admitido')).toBeInTheDocument();
        const campo = screen.getByPlaceholderText('Código SIS o C.I.');
        expect(campo).toHaveValue('');
        expect(screen.queryByRole('button', { name: 'Cerrar ingreso manual' })).toBeNull();

        veredicto = VEREDICTOS.no_habilitado;
        fireEvent.change(campo, { target: { value: '202105878' } });
        fireEvent.click(screen.getByRole('button', { name: 'Verificar' }));
        expect(await screen.findByText('No habilitado')).toBeInTheDocument();
    });
});
