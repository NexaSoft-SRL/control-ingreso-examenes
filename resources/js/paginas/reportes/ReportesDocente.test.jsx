import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ReportesDocente from './ReportesDocente';
import { api } from '../../api/cliente';
import descargar from '../../api/descargar';
import RutaProtegida from '../../sesion/RutaProtegida';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');
vi.mock('../../api/descargar', () => ({ default: vi.fn() }));

const PROGRAMACION = { id: 20, nombre: 'Introducción a la Programación' };
const TALLER = { id: 53, nombre: 'Taller de Ingeniería de Software' };

const rendido = (id, asignatura, fecha, cambios = {}) => ({
    id,
    asignatura,
    tipo: 'Primer parcial',
    fecha,
    hora: '08:15',
    rendido: true,
    presentes: 198,
    habilitados: 255,
    no_habilitados: 9,
    ausentes: 57,
    asistencia: 78,
    incidencias: 9,
    ...cambios,
});
const sinRendir = (id, asignatura, fecha, cambios = {}) =>
    rendido(id, asignatura, fecha, {
        tipo: 'Segundo parcial',
        rendido: false,
        presentes: null,
        ausentes: null,
        asistencia: null,
        incidencias: null,
        habilitados: 44,
        no_habilitados: 0,
        ...cambios,
    });

const POCOS = [
    rendido(1, PROGRAMACION, '2026-10-12'),
    sinRendir(3, TALLER, '2026-11-25'),
    sinRendir(4, PROGRAMACION, '2026-11-23'),
];
const MUCHOS = Array.from({ length: 18 }, (_, i) =>
    rendido(100 + i, i % 2 ? TALLER : PROGRAMACION, `2026-10-${String(10 + i).padStart(2, '0')}`)
);

const INCIDENCIAS = [
    {
        id: 2,
        tipo: 'EXPULSION',
        tipo_texto: 'Expulsión',
        aula_id: 32,
        aula: '691B',
        estudiante: 'Aguilar Bustamante Adriana · 202110007',
        detalle: 'Consultaba el teléfono.',
        hora: '16:02',
        por: 'Blanco Coca Leticia',
        rol: 'Docente',
        atendida: true,
    },
    {
        id: 5,
        tipo: 'OTRA',
        tipo_texto: 'Otra',
        aula_id: 31,
        aula: '691A',
        estudiante: null,
        detalle: 'Faltan pupitres.',
        hora: '16:03',
        por: 'Salazar Serrudo Carla',
        rol: 'Docente',
        atendida: false,
    },
];

const UN_PERIODO = { data: [{ id: 1, codigo: '2/2026' }], principal: '2/2026' };
const DOS_PERIODOS = {
    data: [
        { id: 1, codigo: '2/2026' },
        { id: 2, codigo: '0/2026' },
    ],
    principal: '2/2026',
};

// El contrato de la ruta 77 en memoria.
function servidor(examenes = POCOS, rutas = {}) {
    const lista = ({ params = {} }) => {
        const delPeriodo = params.periodo === 2 ? [] : examenes;
        const filas = delPeriodo.filter(
            (e) => !params.asignatura || e.asignatura.id === params.asignatura
        );
        const pagina = params.pagina ?? 1;
        const porPagina = params.por_pagina ?? 8;
        const asignaturas = [PROGRAMACION, TALLER]
            .map((a) => ({
                ...a,
                examenes: delPeriodo.filter((e) => e.asignatura.id === a.id).length,
            }))
            .filter((a) => a.examenes > 0);
        return {
            data: filas.slice((pagina - 1) * porPagina, pagina * porPagina),
            meta: {
                total: filas.length,
                pagina,
                por_pagina: porPagina,
                examenes: delPeriodo.length,
                rendidos: delPeriodo.filter((e) => e.rendido).length,
                asignaturas,
            },
        };
    };

    simularApi(api, {
        'GET /periodos/vigentes': UN_PERIODO,
        'GET /reportes/mis-examenes': lista,
        'GET /examenes/*/incidencias': { data: INCIDENCIAS, meta: { sin_atender: 1, todas: 2 } },
        ...rutas,
    });
}

const pedidos = (url) => api.get.mock.calls.filter(([ruta]) => ruta === url);
const paginas = () =>
    pedidos('/reportes/mis-examenes')
        .map(([, config]) => config.params)
        .filter((p) => p.por_pagina === 8);
const dato = (etiqueta) => screen.getByText(etiqueta).parentElement;
const tarjeta = (nombre) => screen.getByRole('heading', { name: nombre }).closest('section');

async function abrir(opciones = {}) {
    renderConSesion(<ReportesDocente />, { ruta: '/mis-reportes', ...opciones });
    return screen.findByRole('heading', { name: 'Exámenes del período' });
}

describe('Reportes del docente', () => {
    beforeEach(() => {
        descargar.mockReset();
        descargar.mockImplementation((_ruta, { nombre }) => Promise.resolve(nombre));
        servidor();
    });

    it('muestra el período, las cifras del examen elegido y sus exámenes', async () => {
        await abrir();

        expect(screen.getByText('Período 2/2026 · 3 exámenes · 1 rendido')).toBeInTheDocument();
        expect(dato('Ingresaron')).toHaveTextContent('198');
        expect(dato('Habilitados')).toHaveTextContent('255');
        expect(dato('Ausentes')).toHaveTextContent('57');
        expect(dato('No habilitados')).toHaveTextContent('9');
        expect(tarjeta('Asistencia por examen')).toHaveTextContent('198 de 255 · 78 %');
        expect(tarjeta('Estudiantes ausentes')).toHaveTextContent('57 de 255');

        const lista = tarjeta('Exámenes del período');
        const fila = within(lista).getByRole('button', { name: /12 oct/ });
        expect(fila).toHaveAttribute('aria-pressed', 'true');
        expect(fila).toHaveTextContent('78 %');
        expect(fila).toHaveTextContent('198 de 255');
        expect(within(lista).getAllByText('Sin rendir')).toHaveLength(2);
        expect(screen.queryByLabelText('Período')).not.toBeInTheDocument();
    });

    it('un examen sin rendir no tiene cifras ni descargas', async () => {
        await abrir({ ruta: '/mis-reportes?examen=3' });

        expect(
            screen.getByRole('heading', {
                name: /Segundo parcial · Taller de Ingeniería de Software\s*· 25 nov 2026/,
            })
        ).toBeInTheDocument();
        expect(dato('Ingresaron')).toHaveTextContent('—');
        expect(dato('Ausentes')).toHaveTextContent('—');
        expect(dato('Habilitados')).toHaveTextContent('44');
        expect(tarjeta('Asistencia por examen')).toHaveTextContent('— de 44 · — %');
        expect(screen.getByRole('button', { name: 'Asistencia por examen en PDF' })).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Estudiantes ausentes en hoja de cálculo' })
        ).toBeDisabled();
        expect(tarjeta('Incidencias del examen')).toHaveTextContent('—');
        expect(
            screen.getByRole('button', { name: 'Incidencias del examen en PDF' })
        ).toBeDisabled();
        expect(screen.queryByRole('heading', { name: 'Incidencias' })).not.toBeInTheDocument();
        expect(api.get.mock.calls.some(([url]) => url.endsWith('/incidencias'))).toBe(false);
    });

    it('elige el examen desde la lista de arriba, sin otro selector', async () => {
        await abrir();
        const lista = tarjeta('Exámenes del período');
        expect(screen.queryByRole('button', { name: /^Examen:/ })).not.toBeInTheDocument();

        fireEvent.click(within(lista).getByRole('button', { name: /25 nov/ }));
        expect(
            await screen.findByRole('heading', {
                name: /Taller de Ingeniería de Software\s*· 25 nov 2026/,
            })
        ).toBeInTheDocument();
        expect(within(lista).getByRole('button', { name: /25 nov/ })).toHaveAttribute(
            'aria-pressed',
            'true'
        );

        fireEvent.click(within(lista).getByRole('button', { name: /12 oct/ }));
        await waitFor(() => expect(dato('Ingresaron')).toHaveTextContent('198'));
    });

    it('descarga la asistencia y los ausentes del examen en los dos formatos', async () => {
        descargar.mockResolvedValueOnce('asistencia_2026-10-12.pdf');
        await abrir();

        fireEvent.click(screen.getByRole('button', { name: 'Asistencia por examen en PDF' }));
        expect(await screen.findByText('asistencia_2026-10-12.pdf descargado')).toBeVisible();
        expect(descargar).toHaveBeenCalledWith('/reportes/asistencia/exportar', {
            parametros: { examen: 1, formato: 'pdf' },
            nombre: 'asistencia_2026-10-12.pdf',
        });

        fireEvent.click(
            screen.getByRole('button', { name: 'Estudiantes ausentes en hoja de cálculo' })
        );
        expect(await screen.findByText('ausentes_2026-10-12.xlsx descargado')).toBeVisible();
        expect(descargar).toHaveBeenLastCalledWith('/reportes/ausentes/exportar', {
            parametros: { examen: 1, formato: 'xlsx' },
            nombre: 'ausentes_2026-10-12.xlsx',
        });
    });

    it('descarga las incidencias del examen en los dos formatos', async () => {
        await abrir();

        expect(tarjeta('Incidencias del examen')).toHaveTextContent('9 registros');
        fireEvent.click(screen.getByRole('button', { name: 'Incidencias del examen en PDF' }));
        expect(await screen.findByText('incidencias_2026-10-12.pdf descargado')).toBeVisible();
        expect(descargar).toHaveBeenCalledWith('/reportes/incidencias/exportar', {
            parametros: { examen: 1, formato: 'pdf' },
            nombre: 'incidencias_2026-10-12.pdf',
        });

        fireEvent.click(
            screen.getByRole('button', { name: 'Incidencias del examen en hoja de cálculo' })
        );
        await waitFor(() =>
            expect(descargar).toHaveBeenLastCalledWith('/reportes/incidencias/exportar', {
                parametros: { examen: 1, formato: 'xlsx' },
                nombre: 'incidencias_2026-10-12.xlsx',
            })
        );
    });

    it('las incidencias se descargan también sin el permiso de seguimiento', async () => {
        await abrir({
            usuario: usuarioDePrueba('Docente', { permisos: ['reportes_examenes'] }),
        });

        expect(screen.getByRole('button', { name: 'Incidencias del examen en PDF' })).toBeEnabled();
    });

    it('avisa el mensaje del servidor si la descarga falla', async () => {
        descargar.mockRejectedValue(
            errorHttp(403, { message: 'Este examen no está a su cargo.', alcance: true })
        );
        await abrir();

        fireEvent.click(screen.getByRole('button', { name: 'Asistencia por examen en PDF' }));

        expect(await screen.findByText('Este examen no está a su cargo.')).toBeVisible();
    });

    it('lista las incidencias del examen rendido, sin acciones', async () => {
        await abrir();

        const incidencias = tarjeta('Incidencias reportadas');
        expect(await within(incidencias).findByText('Expulsión')).toBeInTheDocument();
        expect(incidencias).toHaveTextContent('Aula 691B · 16:02');
        expect(incidencias).toHaveTextContent('Faltan pupitres.');
        expect(within(incidencias).queryByRole('button', { name: /Atender|Corregir/ })).toBeNull();
        expect(pedidos('/examenes/1/incidencias')[0][1].params).toEqual({ estado: 'todas' });
    });

    it('en la pestaña Bitácora muestra la línea de tiempo del examen sin descargar', async () => {
        servidor(undefined, {
            'GET /reportes/examenes/*/bitacora': {
                data: [
                    {
                        instante: '2026-10-09 08:12:00',
                        categoria: 'ingreso',
                        titulo: 'Ingresó',
                        detalle: 'por código QR',
                        aula: '691A',
                        estudiante: 'Aguilar Bustamante Adriana',
                        codigo: '202110007',
                        por: 'Blanco Coca Leticia',
                    },
                ],
                aulas: [],
            },
        });
        await abrir();

        fireEvent.click(screen.getByRole('tab', { name: 'Bitácora' }));

        expect(await screen.findByText('Aguilar Bustamante Adriana')).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: 'Bitácora' })).toHaveAttribute(
            'aria-selected',
            'true'
        );
        expect(
            api.get.mock.calls.some(([ruta]) => /^\/reportes\/examenes\/\d+\/bitacora$/.test(ruta))
        ).toBe(true);
        // Los reportes descargables quedan en la otra pestaña.
        expect(
            screen.queryByRole('heading', { name: 'Asistencia por examen' })
        ).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('tab', { name: 'Reportes' }));
        expect(screen.getByRole('heading', { name: 'Asistencia por examen' })).toBeInTheDocument();
    });

    it('filtra las incidencias del examen por gravedad', async () => {
        await abrir();

        const incidencias = tarjeta('Incidencias reportadas');
        await within(incidencias).findByText('Expulsión');
        const gravedad = within(within(incidencias).getByRole('group', { name: 'Gravedad' }));
        fireEvent.click(gravedad.getByRole('button', { name: /Leve/ }));

        expect(within(incidencias).queryByText('Expulsión')).toBeNull();
        expect(incidencias).toHaveTextContent('Faltan pupitres.');
        fireEvent.click(gravedad.getByRole('button', { name: /Muy grave/ }));
        expect(within(incidencias).getByText('Expulsión')).toBeInTheDocument();
        expect(incidencias).not.toHaveTextContent('Faltan pupitres.');
    });

    it('sin el permiso de seguimiento no pide las incidencias', async () => {
        await abrir({
            usuario: usuarioDePrueba('Docente', { permisos: ['reportes_examenes'] }),
        });

        expect(screen.queryByRole('heading', { name: 'Incidencias' })).not.toBeInTheDocument();
        expect(api.get.mock.calls.some(([url]) => url.endsWith('/incidencias'))).toBe(false);
    });

    it('filtra por asignatura y pagina en el servidor', async () => {
        servidor(MUCHOS);
        await abrir();
        const lista = tarjeta('Exámenes del período');

        expect(await within(lista).findByText('1–8 de 18 exámenes')).toBeInTheDocument();
        fireEvent.click(within(lista).getByRole('button', { name: 'Página siguiente' }));
        await waitFor(() => expect(paginas().at(-1)).toEqual({ pagina: 2, por_pagina: 8 }));

        fireEvent.click(
            within(screen.getByRole('group', { name: 'Asignatura' })).getByRole('button', {
                name: /Taller de Ingeniería de Software/,
            })
        );
        await waitFor(() =>
            expect(paginas().at(-1)).toEqual({ asignatura: 53, pagina: 1, por_pagina: 8 })
        );
        expect(await within(lista).findByText('1–8 de 9 exámenes')).toBeInTheDocument();
    });

    it('con varios períodos vigentes deja elegir uno', async () => {
        servidor(POCOS, { 'GET /periodos/vigentes': DOS_PERIODOS });
        await abrir();

        expect(screen.getByText('3 exámenes · 1 rendido')).toBeInTheDocument();
        fireEvent.change(await screen.findByLabelText('Período'), { target: { value: '2' } });

        expect(await screen.findByText('Sin exámenes')).toBeVisible();
        expect(screen.getByText('Período 0/2026 · 0 exámenes · 0 rendidos')).toBeInTheDocument();
        expect(paginas().at(-1)).toEqual({ periodo: 2, pagina: 1, por_pagina: 8 });
        expect(screen.getByLabelText('Período')).toBeInTheDocument();
    });

    it('sin exámenes lo dice', async () => {
        servidor([]);
        renderConSesion(<ReportesDocente />);

        expect(await screen.findByText('Sin exámenes')).toBeVisible();
        expect(screen.queryByRole('button', { name: /PDF/ })).not.toBeInTheDocument();
    });

    it('muestra el error de lectura con «Reintentar»', async () => {
        servidor(POCOS, { 'GET /reportes/mis-examenes': errorHttp(500, { message: 'Error' }) });
        renderConSesion(<ReportesDocente />);

        expect(await screen.findByText('No se pudo cargar')).toBeVisible();

        servidor();
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        expect(await screen.findByRole('heading', { name: 'Exámenes del período' })).toBeVisible();
    });

    it('la vista exige el permiso de reportes de sus exámenes', async () => {
        renderConSesion(
            <RutaProtegida ruta="/mis-reportes">
                <ReportesDocente />
            </RutaProtegida>,
            { usuario: usuarioDePrueba('Administrador'), ruta: '/mis-reportes' }
        );

        expect(await screen.findByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
        expect(pedidos('/reportes/mis-examenes')).toHaveLength(0);
    });
});
