import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Reportes from './Reportes';
import { api } from '../../api/cliente';
import descargar from '../../api/descargar';
import RutaProtegida from '../../sesion/RutaProtegida';
import {
    errorHttp,
    renderConSesion,
    respuestaDiferida,
    simularApi,
    usuarioDePrueba,
} from '../../test/apoyo';

vi.mock('../../api/cliente');
vi.mock('../../api/descargar', () => ({ default: vi.fn() }));

const examen = (id, asignatura, facultad, cambios = {}) => ({
    id,
    asignatura,
    tipo: 'Primer parcial',
    docente: 'Blanco Coca Leticia',
    facultad,
    fecha: '2026-10-12',
    hora: '08:15',
    aulas: 5,
    ingresados: 108,
    habilitados: 255,
    ...cambios,
});

const EXAMENES = [
    examen(1, 'Introducción a la Programación', 'FCyT'),
    examen(2, 'Cálculo I', 'FCyT', { docente: 'Fiorilo Lozada Américo', aulas: 1 }),
    examen(3, 'Contabilidad I', 'FCE', { ingresados: 40, habilitados: 61 }),
    ...Array.from({ length: 20 }, (_, i) => examen(10 + i, `Asignatura ${i + 1}`, 'FHCE')),
];

const PERIODOS = [
    { id: 1, codigo: '2/2026', estado: 'Vigente' },
    { id: 3, codigo: '1/2026', estado: 'Cerrado' },
];

// El contrato de la ruta 73 en memoria: `conteos` ignora la facultad y
// `cifras` sale del alcance (o solo del examen elegido).
function listado({ params = {} }) {
    const texto = (params.buscar ?? '').toLowerCase();
    const enTexto = EXAMENES.filter(
        (e) => !texto || `${e.asignatura} ${e.docente}`.toLowerCase().includes(texto)
    );
    const filas = enTexto.filter(
        (e) => !params.facultad || e.facultad.toLowerCase() === params.facultad.toLowerCase()
    );
    const alcance = params.examen ? EXAMENES.filter((e) => e.id === params.examen) : filas;
    const pagina = params.pagina ?? 1;
    const porPagina = params.por_pagina ?? 10;
    const cuenta = (sigla) => enTexto.filter((e) => e.facultad === sigla).length;
    return {
        data: filas.slice((pagina - 1) * porPagina, pagina * porPagina),
        meta: {
            total: filas.length,
            pagina,
            por_pagina: porPagina,
            conteos: {
                todas: enTexto.length,
                FCyT: cuenta('FCyT'),
                FCE: cuenta('FCE'),
                FHCE: cuenta('FHCE'),
                FACH: 0,
            },
            cifras: {
                ingresos: alcance.reduce((s, e) => s + e.ingresados, 0),
                examenes: alcance.length,
                intentos: alcance.length ? 4 : 0,
                estudiantes: alcance.reduce((s, e) => s + e.habilitados, 0),
            },
        },
    };
}

function servidor(rutas = {}) {
    simularApi(api, {
        'GET /periodos': { data: PERIODOS, meta: { total: 2 } },
        'GET /periodos/vigentes': { data: [PERIODOS[0]], principal: '2/2026' },
        'GET /reportes/examenes': listado,
        ...rutas,
    });
}

const pedidos = () => api.get.mock.calls.filter(([url]) => url === '/reportes/examenes');
const ultimo = () => pedidos().at(-1)[1].params;
const admin = () => usuarioDePrueba('Administrador');
const tarjeta = (nombre) => screen.getByRole('heading', { name: nombre }).closest('li');

async function abrir(opciones = {}) {
    renderConSesion(<Reportes />, { usuario: admin(), ruta: '/reportes', ...opciones });
    return screen.findByRole('table');
}

describe('Reportes de la universidad', () => {
    beforeEach(() => {
        descargar.mockReset();
        descargar.mockImplementation((_ruta, { nombre }) => Promise.resolve(nombre));
        servidor();
    });

    it('lista los exámenes de todas las facultades con sus cifras', async () => {
        const tabla = await abrir();

        // Abre en el período vigente principal.
        expect(ultimo()).toEqual({ periodo: 1, pagina: 1, por_pagina: 10 });
        expect(screen.getByLabelText('Período')).toHaveValue('1');
        const fila = within(tabla).getByText('Introducción a la Programación').closest('tr');
        expect(fila).toHaveTextContent('Primer parcial · Blanco Coca Leticia');
        expect(fila).toHaveTextContent('12 oct 2026, 08:15');
        expect(fila).toHaveTextContent('108 de 255');
        expect(within(tabla).getAllByRole('row')).toHaveLength(11);
        expect(screen.getAllByText('1–10 de 23 exámenes')[0]).toBeInTheDocument();

        expect(tarjeta('Horarios de ingreso')).toHaveTextContent('2.416 ingresos');
        expect(tarjeta('Registrados por examen')).toHaveTextContent('23 exámenes');
        expect(tarjeta('Intentos rechazados')).toHaveTextContent('4 intentos');
        expect(tarjeta('Resumen por estudiante')).toHaveTextContent('5.671 estudiantes');
        expect(screen.getByRole('button', { name: /^FCyT/ })).toHaveTextContent('2');
    });

    it('muestra el esqueleto mientras carga y el error con «Reintentar»', async () => {
        const espera = respuestaDiferida();
        servidor({ 'GET /reportes/examenes': () => espera.promesa });
        renderConSesion(<Reportes />, { usuario: admin() });

        expect(await screen.findByRole('status', { name: 'Cargando' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Horarios de ingreso en PDF' })).toBeDisabled();

        espera.resolver(errorHttp(500, { message: 'Error' }));
        expect(await screen.findByText('No se pudo cargar')).toBeVisible();

        servidor();
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        expect(await screen.findByRole('table')).toBeInTheDocument();
    });

    it('filtra por facultad, período y fechas en el servidor', async () => {
        await abrir();

        fireEvent.click(screen.getByRole('button', { name: /^FCE/ }));
        await waitFor(() => expect(ultimo().facultad).toBe('FCE'));
        expect(await screen.findByText('1 examen')).toBeInTheDocument();
        expect(tarjeta('Horarios de ingreso')).toHaveTextContent('40 ingresos');

        fireEvent.change(await screen.findByLabelText('Período'), { target: { value: '3' } });
        fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-10-12' } });
        fireEvent.change(screen.getByLabelText('Hasta'), { target: { value: '2026-10-31' } });

        await waitFor(() =>
            expect(ultimo()).toEqual({
                facultad: 'FCE',
                periodo: 3,
                desde: '2026-10-12',
                hasta: '2026-10-31',
                pagina: 1,
                por_pagina: 10,
            })
        );
        expect(screen.getByRole('option', { name: '1/2026' })).toBeInTheDocument();
    });

    it('busca por asignatura o docente y vuelve a la página 1', async () => {
        await abrir();
        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        await waitFor(() => expect(ultimo().pagina).toBe(2));

        fireEvent.change(screen.getByLabelText('Buscar'), { target: { value: 'fiorilo' } });

        await waitFor(() => expect(ultimo()).toMatchObject({ buscar: 'fiorilo', pagina: 1 }));
        expect(await screen.findByText('1 examen')).toBeInTheDocument();
    });

    it('no consulta con un rango de fechas invertido', async () => {
        await abrir();
        const antes = pedidos().length;

        fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-10-20' } });
        await waitFor(() => expect(pedidos().length).toBe(antes + 1));
        fireEvent.change(screen.getByLabelText('Hasta'), { target: { value: '2026-10-01' } });

        expect(await screen.findByText('Anterior a «Desde»')).toBeVisible();
        expect(screen.getByText('Sin resultados')).toBeVisible();
        expect(screen.getByRole('button', { name: 'Horarios de ingreso en PDF' })).toBeDisabled();
        expect(pedidos().length).toBe(antes + 1);
    });

    it('con un examen elegido las cifras y las descargas son de ese examen', async () => {
        const tabla = await abrir();

        fireEvent.click(within(tabla).getByRole('button', { name: 'Cálculo I' }));

        await waitFor(() => expect(ultimo().examen).toBe(2));
        await waitFor(() =>
            expect(tarjeta('Registrados por examen')).toHaveTextContent('1 examen')
        );
        expect(within(tabla).getByRole('button', { name: 'Cálculo I' })).toHaveAttribute(
            'aria-pressed',
            'true'
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Registrados por examen en hoja de cálculo' })
        );
        expect(await screen.findByText('registrados_examen_examen2.xlsx descargado')).toBeVisible();
        expect(descargar).toHaveBeenCalledWith('/reportes/registrados/exportar', {
            parametros: { periodo: 1, examen: 2, formato: 'xlsx' },
            nombre: 'registrados_examen_examen2.xlsx',
        });

        fireEvent.click(screen.getByRole('button', { name: 'Quitar el examen elegido' }));
        await waitFor(() => expect(ultimo().examen).toBeUndefined());

        // Un clic en cualquier parte de la fila lo elige; otro lo suelta.
        const fila = within(tabla).getByRole('button', { name: 'Cálculo I' }).closest('tr');
        fireEvent.click(within(fila).getByText('FCyT'));
        await waitFor(() => expect(ultimo().examen).toBe(2));
        fireEvent.click(within(fila).getByText('FCyT'));
        await waitFor(() => expect(ultimo().examen).toBeUndefined());
    });

    it('descarga con los filtros vigentes y avisa con el nombre del servidor', async () => {
        descargar.mockResolvedValue('horarios_ingreso_fcyt_2026-10-09.pdf');
        await abrir();
        fireEvent.click(screen.getByRole('button', { name: /^FCyT/ }));
        await waitFor(() => expect(ultimo().facultad).toBe('FCyT'));
        fireEvent.change(screen.getByLabelText('Buscar'), { target: { value: 'fiorilo' } });
        await waitFor(() => expect(ultimo().buscar).toBe('fiorilo'));

        fireEvent.click(screen.getByRole('button', { name: 'Horarios de ingreso en PDF' }));

        expect(
            await screen.findByText('horarios_ingreso_fcyt_2026-10-09.pdf descargado')
        ).toBeVisible();
        expect(descargar).toHaveBeenCalledWith('/reportes/horarios/exportar', {
            parametros: { periodo: 1, facultad: 'FCyT', buscar: 'fiorilo', formato: 'pdf' },
            nombre: 'horarios_ingreso_fcyt.pdf',
        });
    });

    it('descarga el resumen por estudiante con los filtros vigentes', async () => {
        await abrir();
        fireEvent.click(screen.getByRole('button', { name: /^FCE/ }));
        await waitFor(() => expect(ultimo().facultad).toBe('FCE'));
        await waitFor(() =>
            expect(tarjeta('Resumen por estudiante')).toHaveTextContent('61 estudiantes')
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Resumen por estudiante en hoja de cálculo' })
        );

        expect(await screen.findByText('resumen_estudiantes_fce.xlsx descargado')).toBeVisible();
        expect(descargar).toHaveBeenCalledWith('/reportes/estudiantes/exportar', {
            parametros: { periodo: 1, facultad: 'FCE', formato: 'xlsx' },
            nombre: 'resumen_estudiantes_fce.xlsx',
        });
    });

    it('una descarga en curso no bloquea las demás ni la lista', async () => {
        const espera = respuestaDiferida();
        descargar.mockImplementationOnce(() => espera.promesa);
        await abrir();

        const pdf = screen.getByRole('button', { name: 'Intentos rechazados en PDF' });
        fireEvent.click(pdf);

        expect(pdf).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Intentos rechazados en hoja de cálculo' })
        ).toBeEnabled();
        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        await waitFor(() => expect(ultimo().pagina).toBe(2));

        espera.resolver('intentos_rechazados_umss_2026-10-09.pdf');
        expect(
            await screen.findByText('intentos_rechazados_umss_2026-10-09.pdf descargado')
        ).toBeVisible();
        expect(pdf).toBeEnabled();
    });

    it('avisa el mensaje del servidor si la descarga falla', async () => {
        descargar.mockRejectedValue(
            errorHttp(409, { message: 'No hay exámenes en el alcance.', codigo: 'SIN_DATOS' })
        );
        await abrir();

        fireEvent.click(screen.getByRole('button', { name: 'Horarios de ingreso en PDF' }));

        expect(await screen.findByText('No hay exámenes en el alcance.')).toBeVisible();
    });

    it('abre la bitácora del examen elegido para auditar un reclamo', async () => {
        servidor({
            'GET /reportes/universidad/examenes/2/bitacora': {
                data: [
                    {
                        instante: '2026-10-12 08:19:00',
                        categoria: 'rechazo',
                        titulo: 'Intento rechazado',
                        detalle: 'no habilitado',
                        aula: '691A',
                        estudiante: 'Mamani Heredia Carlos',
                        codigo: '202311260',
                        por: 'Blanco Coca Leticia',
                    },
                ],
            },
        });
        const tabla = await abrir();
        expect(screen.queryByRole('button', { name: /Bitácora/ })).not.toBeInTheDocument();

        fireEvent.click(within(tabla).getByRole('button', { name: 'Cálculo I' }));
        fireEvent.click(await screen.findByRole('button', { name: /^Bitácora/ }));

        const dialogo = await screen.findByRole('dialog', { name: /Cálculo I/ });
        expect(await within(dialogo).findByText('Intento rechazado')).toBeVisible();
        expect(within(dialogo).getByText('Mamani Heredia Carlos', { exact: false })).toBeVisible();
    });

    it('sin exámenes deshabilita las descargas', async () => {
        await abrir();

        fireEvent.change(screen.getByLabelText('Buscar'), { target: { value: 'zzz' } });

        expect(await screen.findByText('Sin resultados')).toBeVisible();
        expect(tarjeta('Registrados por examen')).toHaveTextContent('0 exámenes');
        expect(screen.getByRole('button', { name: 'Horarios de ingreso en PDF' })).toBeDisabled();
    });

    it('sin el permiso de períodos ofrece solo los vigentes', async () => {
        await abrir({
            usuario: usuarioDePrueba('Docente', {
                rol: 'Coordinación',
                permisos: ['reportes_universidad'],
            }),
        });

        expect(await screen.findByRole('option', { name: '2/2026' })).toBeInTheDocument();
        expect(screen.queryByRole('option', { name: '1/2026' })).not.toBeInTheDocument();
        expect(api.get.mock.calls.some(([url]) => url === '/periodos')).toBe(false);
    });

    it('la vista exige el permiso de reportes de la universidad', async () => {
        renderConSesion(
            <RutaProtegida ruta="/reportes">
                <Reportes />
            </RutaProtegida>,
            { usuario: usuarioDePrueba('Docente'), ruta: '/reportes' }
        );

        expect(await screen.findByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
        expect(pedidos()).toHaveLength(0);
    });
});
