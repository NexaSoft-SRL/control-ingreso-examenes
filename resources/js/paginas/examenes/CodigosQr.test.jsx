import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CodigosQr from './CodigosQr';
import { api } from '../../api/cliente';
import { olvidarEdificios } from '../../api/usarEdificios';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const EXAMENES = [
    {
        id: 1,
        asignatura: { id: 5, codigo: '2010010', nombre: 'Introducción a la Programación' },
        tipo: 'PRIMER_PARCIAL',
        tipo_texto: 'Primer parcial',
        fecha: '2026-10-12',
    },
    {
        id: 3,
        asignatura: { id: 9, codigo: '2010020', nombre: 'Base de Datos I' },
        tipo: 'FINAL',
        tipo_texto: 'Examen final',
        fecha: '2026-11-20',
    },
];
const GRUPOS = [
    { id: 36, codigo: '1' },
    { id: 37, codigo: '2' },
];
const AULAS = [
    { aula_id: 31, nombre: '691A' },
    { aula_id: 32, nombre: '691B' },
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
        aulas: ['691A', '691B'],
        pisos: [],
    },
];

// 45 habilitados: dos páginas de 25. Los dos últimos sin enviar.
const habilitados = ({ emitidos = true } = {}) =>
    Array.from({ length: 45 }, (_, i) => ({
        estudiante_id: i + 1,
        codigo: String(202100001 + i),
        nombre: `Apellido${String(i + 1).padStart(2, '0')}, Nombre`,
        grupo: i % 2 === 0 ? '1' : '2',
        aula: AULAS[i % 2].nombre,
        emitido: emitidos,
        enviado_en: emitidos && i < 43 ? '2026-10-07T16:09:12-04:00' : null,
        correo: `${202100001 + i}@est.umss.edu`,
        // El penúltimo falló; el último aún no se envió.
        envio: !emitidos ? 'sin_emitir' : i < 43 ? 'enviado' : i === 43 ? 'fallido' : 'sin_enviar',
        error: emitidos && i === 43 ? 'Buzón inexistente.' : null,
    }));

// La lista como la devuelve GET /examenes/{examen}/codigos-qr, filtrada y paginada aquí.
function listado(todos, params = {}) {
    const texto = (params.buscar ?? '').toLowerCase();
    const filtrados = todos.filter(
        (e) =>
            (!params.grupo || GRUPOS.find((g) => g.id === params.grupo)?.codigo === e.grupo) &&
            (!params.aula || AULAS.find((a) => a.aula_id === params.aula)?.nombre === e.aula) &&
            (!params.envio || e.envio === params.envio) &&
            (!texto || `${e.nombre} ${e.codigo}`.toLowerCase().includes(texto))
    );
    const pagina = params.pagina ?? 1;
    const porPagina = params.por_pagina ?? 25;
    const emitidos = todos.filter((e) => e.emitido).length;
    const enviados = todos.filter((e) => e.enviado_en).length;
    const fallidos = todos.filter((e) => e.envio === 'fallido').length;

    return {
        data: filtrados.slice((pagina - 1) * porPagina, pagina * porPagina),
        meta: {
            total: filtrados.length,
            pagina,
            por_pagina: porPagina,
            habilitados: todos.length,
            emitidos,
            enviados,
            fallidos,
            sin_aula: sinAulaSimulados,
            grupos: GRUPOS,
            aulas: AULAS,
        },
    };
}

function credencial(todos, id) {
    const e = todos.find((x) => x.estudiante_id === id);
    return {
        data: {
            facultad: 'FCyT',
            examen: 'Primer parcial · Introducción a la Programación',
            fecha: '2026-10-12',
            hora: '08:15',
            duracion: 90,
            estudiante: e.nombre,
            codigo: e.codigo,
            correo: e.correo,
            aula: {
                nombre: e.aula,
                edificio: 'Edificio Académico 2',
                piso: '1° Piso',
                edificio_id: 7,
            },
            emitido: e.emitido,
            qr: e.emitido ? 'data:image/png;base64,AAAA' : null,
        },
    };
}

const RUTA = '/examenes/1/codigos-qr';
// Habilitados sin aula en la respuesta simulada (0 salvo en su prueba).
let sinAulaSimulados = 0;

const montar = (ruta = '/codigos-qr?examen=1') =>
    renderConSesion(<CodigosQr />, { usuario: usuarioDePrueba('Docente'), ruta });
const consultas = () =>
    api.get.mock.calls.filter(([url]) => url === RUTA).map(([, config]) => config.params);
const envios = () =>
    api.post.mock.calls.filter(([url]) => url === `${RUTA}/envios`).map(([, cuerpo]) => cuerpo);
const avisos = () =>
    api.post.mock.calls.filter(([url]) => url === `${RUTA}/avisos`).map(([, cuerpo]) => cuerpo);
const ENVIADO = { enviado_en: '2026-10-09T10:00:00-04:00', envio: 'enviado', error: null };
const dato = (etiqueta) => screen.getByText(etiqueta, { selector: 'span' }).parentElement;
const cargada = () => screen.findByText('Apellido01, Nombre', { selector: 'span' });

let alumnos;
let rutas;

beforeEach(() => {
    olvidarEdificios();
    alumnos = habilitados();
    rutas = {
        'GET /examenes': { data: EXAMENES, meta: { hoy: '2026-10-09' } },
        'GET /edificios': { data: EDIFICIOS, meta: { caja: null } },
        'GET /examenes/1/codigos-qr': ({ params }) => listado(alumnos, params),
        'GET /examenes/3/codigos-qr': ({ params }) => listado([], params),
        'GET /examenes/1/codigos-qr/*': ({ url }) =>
            credencial(alumnos, Number(url.split('/').pop())),
        'POST /examenes/1/codigos-qr': () => {
            const nuevos = alumnos.filter((e) => !e.emitido).length;
            alumnos = alumnos.map((e) => ({ ...e, emitido: true }));
            return {
                message: nuevos
                    ? `${nuevos} códigos emitidos.`
                    : 'Los códigos ya estaban emitidos.',
                emitidos: nuevos,
                total: alumnos.length,
            };
        },
        'POST /examenes/1/codigos-qr/envios': ({ data }) => {
            const texto = data.buscar ?? '';
            const antes = alumnos.filter((e) => e.enviado_en).length;
            alumnos = alumnos.map((e) =>
                !texto || e.codigo.includes(texto) ? { ...e, ...ENVIADO } : e
            );
            const cuantos = texto ? 1 : alumnos.length;
            return {
                message: `${cuantos} códigos enviados.`,
                encolados: cuantos,
                pendientes: 0,
                tanda: '2026-10-09T10:00:00-04:00',
                antes,
            };
        },
        'POST /examenes/1/codigos-qr/*/envio': ({ url }) => {
            const id = Number(url.split('/').at(-2));
            alumnos = alumnos.map((e) => (e.estudiante_id === id ? { ...e, ...ENVIADO } : e));
            return { message: '1 código enviado.', encolados: 1, pendientes: 0 };
        },
        'POST /examenes/1/codigos-qr/avisos': {
            message: '45 avisos enviados.',
            encolados: 45,
            pendientes: 0,
            tanda: '2026-10-09T10:00:00-04:00',
        },
    };
    simularApi(api, rutas);
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('Códigos QR', () => {
    it('muestra las cifras, la lista paginada y el estado de cada envío', async () => {
        montar();
        await cargada();

        expect(screen.getByRole('heading', { name: 'Códigos QR', level: 1 })).toBeInTheDocument();
        expect(dato('Habilitados')).toHaveTextContent('45');
        expect(dato('Emitidos')).toHaveTextContent('45');
        expect(dato('Enviados')).toHaveTextContent('43');
        expect(dato('Sin enviar')).toHaveTextContent('1');
        expect(dato('Fallidos')).toHaveTextContent('1');
        expect(consultas()[0]).toEqual({ pagina: 1, por_pagina: 25 });

        const filas = within(screen.getByRole('list')).getAllByRole('button');
        expect(filas).toHaveLength(25);
        expect(filas[0]).toHaveTextContent('202100001 · Grupo 1 · Aula 691A');
        expect(filas[0]).toHaveTextContent('Enviado');
        expect(screen.getByText('1–25 de 45 códigos')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: 'Página siguiente' }));
        const ultima = await screen.findByText('Apellido45, Nombre', { selector: 'span' });
        expect(ultima.closest('button')).toHaveTextContent('Sin enviar');
        expect(screen.getByText('Apellido44, Nombre').closest('button')).toHaveTextContent(
            'Fallido'
        );
        expect(consultas().at(-1)).toEqual({ pagina: 2, por_pagina: 25 });
    });

    it('dibuja la credencial con examen, fecha, hora, aula, edificio y piso', async () => {
        montar();
        await cargada();

        const tarjeta = (await screen.findByRole('heading', { name: 'Credencial' })).closest(
            'section'
        );
        await within(tarjeta).findByText('Apellido01, Nombre');
        expect(tarjeta).toHaveTextContent('UMSS · FCyT');
        expect(tarjeta).toHaveTextContent('Primer parcial · Introducción a la Programación');
        expect(tarjeta).toHaveTextContent('Código SIS 202100001');
        expect(tarjeta).toHaveTextContent('lunes 12 de octubre');
        expect(tarjeta).toHaveTextContent('08:15 · 90 min');
        expect(tarjeta).toHaveTextContent('Aula 691A');
        expect(tarjeta).toHaveTextContent('Edificio Académico 2 · 1° Piso');
        expect(tarjeta).toHaveTextContent('Enviado · 7 oct 2026, 16:09');
        expect(tarjeta).toHaveTextContent('202100001@est.umss.edu');
        expect(within(tarjeta).getByAltText('Código QR')).toHaveAttribute(
            'src',
            'data:image/png;base64,AAAA'
        );
        expect(tarjeta).not.toHaveTextContent('SCIQR1');
        expect(
            await screen.findByRole('heading', { name: 'Ubicación · aula 691A' })
        ).toBeInTheDocument();

        fireEvent.click(screen.getByText('Apellido02, Nombre').closest('button'));
        await within(tarjeta).findByText('Apellido02, Nombre');
        expect(tarjeta).toHaveTextContent('Aula 691B');
        expect(api.get).toHaveBeenCalledWith(`${RUTA}/2`, expect.anything());
    });

    it('filtra por grupo, aula y texto en el servidor', async () => {
        montar();
        await cargada();

        fireEvent.change(screen.getByLabelText('Grupo'), { target: { value: '37' } });
        await waitFor(() =>
            expect(consultas().at(-1)).toEqual({ grupo: 37, pagina: 1, por_pagina: 25 })
        );
        fireEvent.change(screen.getByLabelText('Aula'), { target: { value: '32' } });
        await waitFor(() => expect(consultas().at(-1)).toMatchObject({ grupo: 37, aula: 32 }));
        fireEvent.change(screen.getByLabelText('Buscar estudiante'), {
            target: { value: 'Apellido04' },
        });
        await waitFor(() =>
            expect(consultas().at(-1)).toEqual({
                grupo: 37,
                aula: 32,
                buscar: 'Apellido04',
                pagina: 1,
                por_pagina: 25,
            })
        );
        await waitFor(() =>
            expect(within(screen.getByRole('list')).getAllByRole('button')).toHaveLength(1)
        );

        fireEvent.change(screen.getByLabelText('Buscar estudiante'), {
            target: { value: 'zzz' },
        });
        expect(await screen.findByText('Sin resultados')).toBeInTheDocument();
    });

    it('emite los códigos en lote cuando faltan', async () => {
        alumnos = habilitados({ emitidos: false });
        montar();
        await cargada();

        expect(dato('Emitidos')).toHaveTextContent('0');
        expect(screen.queryByRole('button', { name: /Enviar por correo/ })).toBeNull();
        expect(screen.queryByRole('button', { name: /Imprimir credenciales/ })).toBeNull();
        expect(screen.getAllByText('Sin emitir').length).toBeGreaterThan(1);

        fireEvent.click(screen.getByRole('button', { name: /Emitir códigos/ }));

        expect(await screen.findByText('45 códigos emitidos')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith(RUTA);
        await waitFor(() => expect(dato('Emitidos')).toHaveTextContent('45'));
        expect(screen.queryByRole('button', { name: /Emitir códigos/ })).toBeNull();
        expect(screen.getByRole('button', { name: /Imprimir credenciales/ })).toBeEnabled();
        expect(await screen.findByAltText('Código QR')).toBeInTheDocument();
    });

    it('con habilitados sin aula no emite ni avisa y lleva a repartir', async () => {
        alumnos = habilitados({ emitidos: false });
        sinAulaSimulados = 3;
        montar();
        await cargada();

        expect(screen.getByText('3 habilitados sin aula')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Repartir aulas' })).toHaveAttribute(
            'href',
            '/habilitacion?examen=1'
        );
        expect(screen.getByRole('button', { name: /Emitir códigos/ })).toBeDisabled();
        expect(screen.getAllByRole('button', { name: /Enviar aviso/ })[0]).toBeDisabled();
        sinAulaSimulados = 0;
    });

    it('avisa si no hay a quién emitir', async () => {
        alumnos = habilitados({ emitidos: false });
        rutas['POST /examenes/1/codigos-qr'] = errorHttp(409, {
            message: 'El examen no tiene estudiantes habilitados.',
            codigo: 'SIN_HABILITADOS',
        });
        simularApi(api, rutas);
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: /Emitir códigos/ }));

        expect(await screen.findByText('Sin estudiantes habilitados')).toBeInTheDocument();
    });

    it('envía por correo a todo el examen y actualiza el estado', async () => {
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: /Enviar por correo/ }));
        const dialogo = screen.getByRole('dialog', { name: 'Enviar por correo' });
        expect(dialogo).toHaveTextContent('Primer parcial · Introducción a la Programación');
        expect(dialogo).toHaveTextContent('45 estudiantes');
        expect(dialogo).toHaveTextContent('Institucional');
        fireEvent.click(within(dialogo).getByRole('button', { name: /^Enviar$/ }));

        expect(await screen.findByText('45 códigos enviados')).toBeInTheDocument();
        expect(envios()).toEqual([{}]);
        expect(screen.queryByRole('dialog')).toBeNull();
        await waitFor(() => expect(dato('Sin enviar')).toHaveTextContent('0'));
        expect(dato('Fallidos')).toHaveTextContent('0');
    });

    it('con filtro, envía e imprime solo lo filtrado', async () => {
        const abrir = vi.spyOn(window, 'open').mockImplementation(() => null);
        montar();
        await cargada();

        fireEvent.change(screen.getByLabelText('Aula'), { target: { value: '32' } });
        await waitFor(() => expect(consultas().at(-1)).toMatchObject({ aula: 32 }));
        await screen.findByText('22 códigos');

        fireEvent.click(screen.getByRole('button', { name: /Imprimir credenciales/ }));
        expect(abrir).toHaveBeenCalledWith(
            '/api/examenes/1/codigos-qr/hoja?aula=32',
            '_blank',
            'noopener'
        );

        fireEvent.click(screen.getByRole('button', { name: /Enviar por correo/ }));
        const dialogo = screen.getByRole('dialog', { name: 'Enviar por correo' });
        expect(dialogo).toHaveTextContent('22 estudiantes · Aula 691B');
        fireEvent.click(within(dialogo).getByRole('button', { name: /^Enviar$/ }));

        await waitFor(() => expect(envios()).toEqual([{ aula: 32 }]));
    });

    it('repite el envío por tandas mientras avanza y se detiene si no', async () => {
        const pendientes = [120, 20, 20, 0];
        rutas['POST /examenes/1/codigos-qr/envios'] = () => ({
            message: '100 códigos enviados.',
            encolados: 100,
            pendientes: pendientes.shift(),
            tanda: '2026-10-09T10:00:00-04:00',
        });
        simularApi(api, rutas);
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: /Enviar por correo/ }));
        fireEvent.click(
            within(screen.getByRole('dialog')).getByRole('button', { name: /^Enviar$/ })
        );

        expect(await screen.findByText('20 códigos sin enviar')).toBeInTheDocument();
        // Desde la segunda vuelta devuelve la tanda: nadie lo recibe dos veces.
        expect(envios()).toEqual([
            {},
            { tanda: '2026-10-09T10:00:00-04:00' },
            { tanda: '2026-10-09T10:00:00-04:00' },
        ]);
    });

    it('filtra por estado de envío y muestra el error del fallido', async () => {
        montar();
        await cargada();

        fireEvent.change(screen.getByLabelText('Envío'), { target: { value: 'fallido' } });
        await waitFor(() =>
            expect(consultas().at(-1)).toEqual({ envio: 'fallido', pagina: 1, por_pagina: 25 })
        );
        const tarjeta = (await screen.findByRole('heading', { name: 'Credencial' })).closest(
            'section'
        );
        await within(tarjeta).findByText('Apellido44, Nombre');
        expect(within(tarjeta).getByText('Fallido')).toBeInTheDocument();
        expect(within(tarjeta).getByRole('alert')).toHaveTextContent('Buzón inexistente.');
        expect(within(tarjeta).getByRole('button', { name: /Reenviar/ })).toBeEnabled();

        // El envío en lote va solo a los fallidos.
        fireEvent.click(screen.getByRole('button', { name: /Enviar por correo/ }));
        const dialogo = screen.getByRole('dialog', { name: 'Enviar por correo' });
        expect(dialogo).toHaveTextContent('1 estudiante · Fallidos');
        fireEvent.click(within(dialogo).getByRole('button', { name: /^Enviar$/ }));
        await waitFor(() => expect(envios()).toEqual([{ envio: 'fallido' }]));
    });

    it('envía el aviso con las recomendaciones y muestra el resultado', async () => {
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: /Enviar aviso/ }));
        const dialogo = screen.getByRole('dialog', { name: 'Aviso por correo' });
        expect(dialogo).toHaveTextContent('Primer parcial · Introducción a la Programación');
        expect(dialogo).toHaveTextContent('lunes 12 de octubre');
        expect(dialogo).toHaveTextContent('45 estudiantes');
        fireEvent.change(within(dialogo).getByLabelText('Recomendaciones'), {
            target: { value: '  Llegar 20 minutos antes.  ' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: /Enviar aviso/ }));

        expect(await screen.findByText('45 avisos enviados')).toBeInTheDocument();
        expect(avisos()).toEqual([{ recomendaciones: 'Llegar 20 minutos antes.' }]);
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(screen.getByText(/^Aviso · .* · 45 enviados$/)).toBeInTheDocument();
    });

    it('el aviso respeta el filtro y avanza por tandas', async () => {
        const pendientes = [5, 0];
        rutas['POST /examenes/1/codigos-qr/avisos'] = () => ({
            message: '100 avisos enviados.',
            encolados: pendientes.length === 2 ? 17 : 5,
            pendientes: pendientes.shift(),
            tanda: '2026-10-09T10:00:00-04:00',
        });
        simularApi(api, rutas);
        montar();
        await cargada();

        fireEvent.change(screen.getByLabelText('Aula'), { target: { value: '32' } });
        await screen.findByText('22 códigos');
        fireEvent.click(screen.getByRole('button', { name: /Enviar aviso/ }));
        const dialogo = screen.getByRole('dialog', { name: 'Aviso por correo' });
        expect(dialogo).toHaveTextContent('22 estudiantes · Aula 691B');
        fireEvent.click(within(dialogo).getByRole('button', { name: /Enviar aviso/ }));

        expect(await screen.findByText('22 avisos enviados')).toBeInTheDocument();
        expect(avisos()).toEqual([{ aula: 32 }, { aula: 32, tanda: '2026-10-09T10:00:00-04:00' }]);
    });

    it('avisa si el aviso no tiene destinatarios o las recomendaciones no valen', async () => {
        rutas['POST /examenes/1/codigos-qr/avisos'] = errorHttp(422, {
            message: 'Máximo 2000 caracteres',
            errors: { recomendaciones: ['Máximo 2000 caracteres'] },
        });
        simularApi(api, rutas);
        montar();
        await cargada();

        fireEvent.click(screen.getByRole('button', { name: /Enviar aviso/ }));
        const dialogo = screen.getByRole('dialog', { name: 'Aviso por correo' });
        fireEvent.change(within(dialogo).getByLabelText('Recomendaciones'), {
            target: { value: 'a'.repeat(2001) },
        });
        expect(within(dialogo).getByRole('button', { name: /Enviar aviso/ })).toBeDisabled();
        fireEvent.change(within(dialogo).getByLabelText('Recomendaciones'), {
            target: { value: 'Traer lápiz' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: /Enviar aviso/ }));
        expect(await screen.findByText('Máximo 2000 caracteres')).toBeInTheDocument();
        // El diálogo sigue abierto, con lo escrito.
        expect(within(dialogo).getByLabelText('Recomendaciones')).toHaveValue('Traer lápiz');
    });

    it('reenvía una sola credencial por estudiante', async () => {
        montar();
        await cargada();
        const tarjeta = (await screen.findByRole('heading', { name: 'Credencial' })).closest(
            'section'
        );

        fireEvent.click(await within(tarjeta).findByRole('button', { name: /Reenviar/ }));
        expect(await screen.findByText('Código reenviado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith(`${RUTA}/1/envio`);
        expect(envios()).toEqual([]);
    });

    it('envía la credencial que aún no salió', async () => {
        montar();
        await cargada();
        fireEvent.change(screen.getByLabelText('Buscar estudiante'), {
            target: { value: 'Apellido45' },
        });
        const tarjeta = (await screen.findByRole('heading', { name: 'Credencial' })).closest(
            'section'
        );
        await within(tarjeta).findByText('Apellido45, Nombre');
        expect(within(tarjeta).getByText('Sin enviar')).toBeInTheDocument();

        fireEvent.click(within(tarjeta).getByRole('button', { name: /^Enviar$/ }));

        expect(await screen.findByText('Código enviado')).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith(`${RUTA}/45/envio`);
        await waitFor(() => expect(dato('Sin enviar')).toHaveTextContent('0'));
        expect(await within(tarjeta).findByText(/Enviado · 9 oct 2026/)).toBeInTheDocument();
    });

    it('sin habilitados no ofrece acciones', async () => {
        montar('/codigos-qr?examen=3');

        expect(await screen.findByText('Sin estudiantes habilitados')).toBeInTheDocument();
        expect(screen.getByText('Examen final · Base de Datos I')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Emitir códigos/ })).toBeNull();
    });

    it('cambia de examen con el selector', async () => {
        montar();
        await cargada();

        fireEvent.change(screen.getByRole('combobox', { name: /Examen/ }), {
            target: { value: '3' },
        });

        expect(await screen.findByText('Sin estudiantes habilitados')).toBeInTheDocument();
    });

    it('muestra el rechazo de un examen ajeno', async () => {
        rutas['GET /examenes/1/codigos-qr'] = errorHttp(403, {
            message: 'Este examen no es tuyo.',
            alcance: true,
        });
        simularApi(api, rutas);
        montar();

        expect(await screen.findByRole('alert')).toHaveTextContent('Este examen no es tuyo');
    });

    it('permite reintentar si la lista no carga, y avisa sin exámenes', async () => {
        rutas['GET /examenes/1/codigos-qr'] = errorHttp(500, {});
        simularApi(api, rutas);
        const { unmount } = montar();

        expect(await screen.findByText('No se pudo cargar')).toBeInTheDocument();
        rutas['GET /examenes/1/codigos-qr'] = ({ params }) => listado(alumnos, params);
        simularApi(api, rutas);
        fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
        await cargada();
        unmount();

        rutas['GET /examenes'] = { data: [], meta: {} };
        simularApi(api, rutas);
        montar('/codigos-qr');
        expect(await screen.findByText('Sin exámenes')).toBeInTheDocument();
    });
});
