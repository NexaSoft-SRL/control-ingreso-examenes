import { fireEvent, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Examenes from './Examenes';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const INTRO = { id: 20, codigo: '2010010', nombre: 'Introducción a la Programación' };
const TALLER = { id: 53, codigo: '2010024', nombre: 'Taller de Ingeniería de Software' };

// Un examen del resumen, como lo devuelve GET /examenes.
function examen(cambios) {
    return {
        tipo: 'PRIMER_PARCIAL',
        tipo_texto: 'Primer parcial',
        hora: '08:15',
        duracion: 90,
        grupos: ['2'],
        inscritos: 65,
        aulas: [],
        auxiliares: 0,
        habilitados: 0,
        no_habilitados: 0,
        sin_revisar: 65,
        qr_emitidos: 0,
        avance: {
            grupos: 'ok',
            aulas: 'ahora',
            auxiliares: 'falta',
            habilitacion: 'falta',
            qr: 'falta',
        },
        estado: 'Faltan aulas',
        accion: { clave: 'elegir_aulas', paso: 2 },
        propio: true,
        registrado_por: 'Blanco Coca Leticia',
        junto_con: 0,
        es_hoy: false,
        rendido: false,
        ...cambios,
    };
}

const CON_QR = examen({
    id: 1,
    asignatura: INTRO,
    fecha: '2026-10-09',
    hora: '12:30',
    grupos: ['1', '2', '3', '6'],
    inscritos: 260,
    aulas: ['617', '624', '691A', '691B', '691E'],
    auxiliares: 4,
    habilitados: 251,
    no_habilitados: 9,
    avance: { grupos: 'ok', aulas: 'ok', auxiliares: 'ok', habilitacion: 'ok', qr: 'ahora' },
    estado: 'Faltan códigos QR',
    accion: { clave: 'emitir_qr', paso: null },
    es_hoy: true,
});
const AJENO = examen({
    id: 3,
    asignatura: TALLER,
    fecha: '2026-10-11',
    grupos: ['2', '4'],
    inscritos: 90,
    aulas: ['690E', 'INFLAB'],
    habilitados: 44,
    no_habilitados: 1,
    avance: { grupos: 'ok', aulas: 'ok', auxiliares: 'ok', habilitacion: 'ahora', qr: 'falta' },
    estado: 'Falta habilitar',
    accion: { clave: 'habilitar', paso: null },
    propio: false,
    registrado_por: 'Rodriguez Bilbao Erika Patricia',
});
const SIN_AULAS = examen({
    id: 4,
    asignatura: INTRO,
    tipo: 'SEGUNDO_PARCIAL',
    tipo_texto: 'Segundo parcial',
    fecha: '2026-11-23',
    hora: '14:15',
});
const POR_HABILITAR = examen({
    id: 5,
    asignatura: TALLER,
    tipo: 'SEGUNDO_PARCIAL',
    tipo_texto: 'Segundo parcial',
    fecha: '2026-11-25',
    inscritos: 46,
    aulas: ['INFLAB'],
    avance: { grupos: 'ok', aulas: 'ok', auxiliares: 'ok', habilitacion: 'ahora', qr: 'falta' },
    estado: 'Falta habilitar',
    accion: { clave: 'habilitar', paso: null },
});

let examenes;
let hoy;

const montar = (usuario = usuarioDePrueba('Docente')) =>
    renderConSesion(<Examenes />, { usuario, ruta: '/examenes' });
const tarjeta = async (titulo) =>
    (await screen.findByRole('heading', { name: titulo })).closest('article');
const seccion = (rotulo) => screen.getByRole('heading', { name: rotulo }).closest('section');

beforeEach(() => {
    examenes = [SIN_AULAS, CON_QR, POR_HABILITAR, AJENO];
    hoy = '2026-10-09';
    simularApi(api, {
        'GET /examenes': () => ({
            data: examenes,
            meta: { periodo: '2/2026', hoy, hora_servidor: `${hoy}T08:00:00-04:00` },
        }),
        'DELETE /examenes/*': ({ url }) => {
            examenes = examenes.filter((e) => `/examenes/${e.id}` !== url);
            return '';
        },
    });
});

describe('Examenes · lista', () => {
    it('lista los exámenes agrupados por mes y destaca el de hoy', async () => {
        montar();

        const destacada = await tarjeta('Primer parcial · Introducción a la Programación');
        expect(seccion('Hoy')).toContainElement(destacada);
        expect(
            within(destacada).getByText(/4 grupos · 260 inscritos · 5 aulas/)
        ).toBeInTheDocument();
        expect(within(destacada).getByText(/4 de 5 pasos/)).toBeInTheDocument();
        expect(within(destacada).getByText('Faltan códigos QR')).toBeInTheDocument();
        expect(within(destacada).getByText('12:30')).toBeInTheDocument();

        expect(screen.getByText('Período 2/2026 · 4 exámenes · 2 asignaturas')).toBeInTheDocument();

        const octubre = seccion('Octubre 2026 · 1');
        expect(
            within(octubre).getByText('Primer parcial · Taller de Ingeniería de Software')
        ).toBeInTheDocument();
        expect(within(octubre).getByText(/2 grupos · 90 inscritos · 690E, INFLAB/)).toBeVisible();

        const noviembre = seccion('Noviembre 2026 · 2');
        const titulos = within(noviembre)
            .getAllByRole('heading', { level: 3 })
            .map((h) => h.textContent);
        expect(titulos).toEqual([
            'Segundo parcial · Introducción a la Programación',
            'Segundo parcial · Taller de Ingeniería de Software',
        ]);
        expect(within(noviembre).getByText(/1 grupo · 65 inscritos · Sin aulas/)).toBeVisible();
    });

    it('si hoy no hay examen, destaca el próximo', async () => {
        hoy = '2026-10-10';
        examenes = [SIN_AULAS, { ...CON_QR, es_hoy: false, rendido: true }, AJENO];
        montar();

        const destacada = await tarjeta('Primer parcial · Taller de Ingeniería de Software');
        expect(seccion('Próximo')).toContainElement(destacada);
        expect(screen.queryByRole('heading', { name: 'Hoy' })).not.toBeInTheDocument();
        // El que ya se rindió queda en su mes.
        expect(seccion('Octubre 2026 · 1')).toHaveTextContent('Introducción a la Programación');
    });

    it('filtra por asignatura, con los conteos', async () => {
        montar();
        await tarjeta('Primer parcial · Introducción a la Programación');

        const filtro = screen.getByRole('group', { name: 'Asignatura' });
        expect(within(filtro).getByRole('button', { name: /Todas/ })).toHaveTextContent('Todas4');
        const taller = within(filtro).getByRole('button', {
            name: /Taller de Ingeniería de Software/,
        });
        expect(taller).toHaveTextContent('2');

        fireEvent.click(taller);

        expect(taller).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getAllByRole('article')).toHaveLength(2);
        expect(screen.queryByText(/· Introducción a la Programación/)).not.toBeInTheDocument();
        // El de hoy es de otra asignatura: no se destaca.
        expect(screen.queryByRole('heading', { name: 'Hoy' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Pendientes/ })).not.toBeInTheDocument();
    });

    it('sin exámenes en el período muestra «Sin exámenes»', async () => {
        examenes = [];
        montar();

        expect(await screen.findByText('Sin exámenes')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Registrar examen/ })).toHaveAttribute(
            'href',
            '/examenes/nuevo'
        );
    });

    it('si la lista no carga ofrece reintentar', async () => {
        api.get.mockRejectedValueOnce(errorHttp(500));
        montar();

        fireEvent.click(await screen.findByRole('button', { name: 'Reintentar' }));
        expect(
            await tarjeta('Primer parcial · Introducción a la Programación')
        ).toBeInTheDocument();
    });
});

describe('Examenes · pasos y acción siguiente', () => {
    it('despliega los cinco pasos, con «Quién atiende» y los códigos QR', async () => {
        montar();
        const fila = await tarjeta('Primer parcial · Introducción a la Programación');

        // El destacado viene desplegado.
        const pasos = within(fila).getAllByRole('listitem');
        expect(pasos.map((p) => p.textContent)).toEqual([
            'Grupos4 · 260 inscritos',
            'Aulas617, 624, 691A, 691B, 691E',
            'Quién atiende4 personas',
            'Habilitación251 de 260',
            'Códigos QRPendiente',
        ]);
        expect(within(fila).getByText('viernes 9 de octubre')).toBeInTheDocument();
        expect(within(fila).getByText('90 min')).toBeInTheDocument();
        // El pie solo lleva datos: habilitación y códigos QR son pestañas del examen.
        expect(within(fila).queryByRole('link', { name: 'Habilitación' })).not.toBeInTheDocument();

        const sinAuxiliares = await tarjeta('Segundo parcial · Taller de Ingeniería de Software');
        expect(within(sinAuxiliares).getByText('Solo docente')).toBeInTheDocument();
        const sinAulas = await tarjeta('Segundo parcial · Introducción a la Programación');
        expect(within(sinAulas).getAllByRole('listitem')[2]).toHaveTextContent(
            'Quién atiendePendiente'
        );
    });

    it('el paso de códigos QR muestra los emitidos', async () => {
        examenes = [
            { ...CON_QR, qr_emitidos: 120 },
            {
                ...POR_HABILITAR,
                habilitados: 46,
                qr_emitidos: 46,
                avance: { ...POR_HABILITAR.avance, habilitacion: 'ok', qr: 'ok' },
                estado: 'Listo',
                accion: { clave: 'ver_qr', paso: null },
            },
        ];
        montar();

        const aMedias = await tarjeta('Primer parcial · Introducción a la Programación');
        expect(within(aMedias).getAllByRole('listitem')[4]).toHaveTextContent(
            'Códigos QR120 de 251'
        );

        const lista = await tarjeta('Segundo parcial · Taller de Ingeniería de Software');
        expect(within(lista).getByText('Listo')).toBeInTheDocument();
        expect(within(lista).getByText(/5 de 5 pasos/)).toBeInTheDocument();
        expect(within(lista).getByRole('link', { name: 'Códigos QR' })).toHaveAttribute(
            'href',
            '/examenes/detalle?examen=5&pestana=qr'
        );
        expect(within(lista).getAllByRole('listitem')[4]).toHaveTextContent(
            'Códigos QR46 emitidos'
        );
        // Un solo enlace a los códigos: la acción siguiente.
        expect(within(lista).getAllByRole('link', { name: 'Códigos QR' })).toHaveLength(1);
    });

    it('el examen de hoy tiene una sola acción: «En curso»', async () => {
        montar();
        const fila = await tarjeta('Primer parcial · Introducción a la Programación');

        expect(within(fila).getByRole('link', { name: /En curso/ })).toHaveAttribute(
            'href',
            '/en-curso?examen=1'
        );
        expect(
            within(fila).queryByRole('link', { name: 'Emitir códigos QR' })
        ).not.toBeInTheDocument();

        const otra = await tarjeta('Segundo parcial · Taller de Ingeniería de Software');
        expect(within(otra).queryByRole('link', { name: /En curso/ })).not.toBeInTheDocument();
        expect(within(otra).getByRole('link', { name: /Habilitar/ })).toHaveAttribute(
            'href',
            '/examenes/detalle?examen=5&pestana=habilitacion'
        );
    });

    it('el examen de hoy ya rendido o sin aulas no ofrece «En curso»', async () => {
        examenes = [
            { ...CON_QR, rendido: true },
            { ...SIN_AULAS, es_hoy: true, fecha: '2026-10-09' },
        ];
        montar();
        await tarjeta('Primer parcial · Introducción a la Programación');

        expect(screen.queryByRole('link', { name: /En curso/ })).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Emitir códigos/ })).toHaveAttribute(
            'href',
            '/examenes/detalle?examen=1&pestana=qr'
        );
    });

    it('sin el permiso de códigos QR no dibuja su enlace', async () => {
        examenes = [{ ...CON_QR, es_hoy: false, fecha: '2026-10-20' }];
        montar(usuarioDePrueba('Docente', { permisos: ['examenes', 'habilitacion'] }));
        const fila = await tarjeta('Primer parcial · Introducción a la Programación');

        expect(within(fila).queryByRole('link', { name: /QR|códigos/i })).not.toBeInTheDocument();
        // Queda el título, que abre el examen.
        expect(within(fila).getAllByRole('link')).toHaveLength(1);
    });

    it('cada fila muestra sus pasos y su título abre la página del examen', async () => {
        montar();
        const fila = await tarjeta('Segundo parcial · Introducción a la Programación');

        expect(within(fila).getByRole('list', { name: 'Pasos' })).toBeInTheDocument();
        expect(within(fila).getByText('Sin elegir')).toBeInTheDocument();
        expect(
            within(fila).getByRole('link', {
                name: 'Segundo parcial · Introducción a la Programación',
            })
        ).toHaveAttribute('href', expect.stringMatching(/^\/examenes\/detalle\?examen=\d+$/));
        expect(within(fila).queryByRole('button', { name: /^Pasos de/ })).not.toBeInTheDocument();
    });

    it('con más de cuatro exámenes todos siguen mostrando sus pasos', async () => {
        examenes = [...examenes, { ...SIN_AULAS, id: 9, fecha: '2026-12-01' }];
        montar();

        const otra = await tarjeta('Primer parcial · Taller de Ingeniería de Software');
        expect(within(otra).getByRole('list', { name: 'Pasos' })).toBeInTheDocument();
    });

    it('«Elegir aulas» lleva a la edición en el paso de aulas', async () => {
        montar();
        const fila = await tarjeta('Segundo parcial · Introducción a la Programación');

        expect(within(fila).getByRole('link', { name: /Elegir aulas/ })).toHaveAttribute(
            'href',
            '/examenes/nuevo?examen=4&paso=2'
        );
        expect(within(fila).queryByRole('link', { name: 'Habilitación' })).not.toBeInTheDocument();
    });

    it('«Asignar quién atiende» lleva a la edición en el paso de quién atiende', async () => {
        examenes = [
            {
                ...POR_HABILITAR,
                avance: { ...POR_HABILITAR.avance, auxiliares: 'ahora', habilitacion: 'falta' },
                estado: 'Falta quién atiende',
                accion: { clave: 'quien_atiende', paso: 3 },
            },
        ];
        montar();
        const fila = await tarjeta('Segundo parcial · Taller de Ingeniería de Software');

        expect(within(fila).getByRole('link', { name: /Asignar quién atiende/ })).toHaveAttribute(
            'href',
            '/examenes/nuevo?examen=5&paso=3'
        );
        expect(within(fila).getAllByRole('listitem')[2]).toHaveTextContent(
            'Quién atiendeMás de 2 aulas por persona'
        );
    });

    it('«Habilitar» lleva a Habilitación con el examen', async () => {
        montar();

        const sinEmpezar = await tarjeta('Segundo parcial · Taller de Ingeniería de Software');
        expect(within(sinEmpezar).getByRole('link', { name: /Habilitar/ })).toHaveAttribute(
            'href',
            '/examenes/detalle?examen=5&pestana=habilitacion'
        );
        const empezada = await tarjeta('Primer parcial · Taller de Ingeniería de Software');
        expect(
            within(empezada).getByRole('link', { name: /Terminar habilitación/ })
        ).toHaveAttribute('href', '/examenes/detalle?examen=3&pestana=habilitacion');
    });

    it('sin el permiso de habilitación no dibuja sus enlaces', async () => {
        montar(usuarioDePrueba('Docente', { permisos: ['examenes'] }));
        const fila = await tarjeta('Segundo parcial · Taller de Ingeniería de Software');

        expect(within(fila).queryByRole('link', { name: /Habilit/ })).not.toBeInTheDocument();
        expect(within(fila).getAllByRole('link')).toHaveLength(1);
    });

    it('al volver de eliminar un examen muestra el aviso', async () => {
        renderConSesion(<Examenes />, {
            usuario: usuarioDePrueba('Docente'),
            ruta: { pathname: '/examenes', state: { aviso: 'Examen eliminado' } },
        });

        expect(await screen.findByText('Examen eliminado')).toBeInTheDocument();
    });

    it('editar y eliminar van en la página del examen, no en la tarjeta', async () => {
        montar();

        const propio = await tarjeta('Segundo parcial · Introducción a la Programación');
        expect(within(propio).queryByRole('link', { name: 'Editar' })).not.toBeInTheDocument();
        expect(within(propio).queryByRole('button', { name: 'Eliminar' })).not.toBeInTheDocument();

        const ajeno = await tarjeta('Primer parcial · Taller de Ingeniería de Software');
        expect(within(ajeno).getByText(/Rodriguez Bilbao Erika Patricia/)).toBeInTheDocument();
    });
});
