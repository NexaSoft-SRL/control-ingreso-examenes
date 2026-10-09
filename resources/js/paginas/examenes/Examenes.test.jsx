import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Examenes from './Examenes';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

const INTRO = { id: 20, codigo: '2010010', nombre: 'Introducción a la Programación' };
const TALLER = { id: 53, codigo: '2010024', nombre: 'Taller de Ingeniería de Software' };

// Un examen del resumen, como lo devuelve GET /examenes (plan, 9 · B2).
function examen(cambios) {
    return {
        tipo: 'PRIMER_PARCIAL',
        tipo_texto: 'Primer parcial',
        hora: '08:15',
        duracion: 90,
        grupos: ['2'],
        inscritos: 65,
        aulas: [],
        habilitados: 0,
        no_habilitados: 0,
        sin_revisar: 65,
        qr_emitidos: 0,
        avance: { grupos: 'ok', aulas: 'ahora', habilitacion: 'falta', qr: 'falta' },
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
    habilitados: 251,
    no_habilitados: 9,
    avance: { grupos: 'ok', aulas: 'ok', habilitacion: 'ok', qr: 'ahora' },
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
    avance: { grupos: 'ok', aulas: 'ok', habilitacion: 'ahora', qr: 'falta' },
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
    avance: { grupos: 'ok', aulas: 'ok', habilitacion: 'ahora', qr: 'falta' },
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
            within(destacada).getByText(/4 grupos · 260 inscritos · 5 aulas · 3 de 4 pasos/)
        ).toBeInTheDocument();
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
        expect(within(filtro).getByRole('button', { name: /Todos/ })).toHaveTextContent('Todos4');
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
        expect(screen.getByRole('button', { name: /Pendientes/ })).toHaveTextContent(
            'Pendientes 2'
        );
    });

    it('«Pendientes» deja los que no están listos', async () => {
        examenes = [AJENO, { ...POR_HABILITAR, estado: 'Listo', accion: null }];
        hoy = '2026-10-01';
        montar();
        await tarjeta('Primer parcial · Taller de Ingeniería de Software');

        const pendientes = screen.getByRole('button', { name: /Pendientes/ });
        expect(pendientes).toHaveTextContent('Pendientes 1');
        expect(screen.getAllByRole('article')).toHaveLength(2);

        fireEvent.click(pendientes);

        expect(pendientes).toHaveAttribute('aria-pressed', 'true');
        expect(screen.getAllByRole('article')).toHaveLength(1);
    });

    it('si ninguno coincide muestra «Sin exámenes»', async () => {
        examenes = [{ ...POR_HABILITAR, estado: 'Listo', accion: null }];
        montar();
        await tarjeta('Segundo parcial · Taller de Ingeniería de Software');

        fireEvent.click(screen.getByRole('button', { name: /Pendientes/ }));

        expect(screen.getByText('Sin exámenes')).toBeInTheDocument();
        expect(screen.queryByRole('article')).not.toBeInTheDocument();
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
    it('despliega los cuatro pasos; no hay «Quién atiende» y los códigos QR quedan pendientes', async () => {
        montar();
        const fila = await tarjeta('Primer parcial · Introducción a la Programación');

        // El destacado viene desplegado.
        const pasos = within(fila).getAllByRole('listitem');
        expect(pasos.map((p) => p.textContent)).toEqual([
            'Grupos4 · 260 inscritos',
            'Aulas617, 624, 691A, 691B, 691E',
            'Habilitación251 de 260',
            'Códigos QRPendiente',
        ]);
        expect(screen.queryByText('Quién atiende')).not.toBeInTheDocument();
        expect(within(fila).getByText(/viernes 9 de octubre · 90 min/)).toBeInTheDocument();
        // `emitir_qr` no tiene destino.
        expect(within(fila).queryByRole('link', { name: /QR|código/i })).not.toBeInTheDocument();
        expect(within(fila).getByRole('link', { name: 'Habilitación' })).toHaveAttribute(
            'href',
            '/habilitacion?examen=1'
        );
    });

    it('pliega y despliega la fila', async () => {
        montar();
        const fila = await tarjeta('Segundo parcial · Introducción a la Programación');
        const boton = within(fila).getByRole('button', {
            name: 'Pasos de Segundo parcial · Introducción a la Programación',
        });

        // Con cuatro exámenes, los pendientes vienen desplegados.
        expect(boton).toHaveAttribute('aria-expanded', 'true');
        expect(within(fila).getByText('Sin elegir')).toBeInTheDocument();

        fireEvent.click(boton);
        expect(boton).toHaveAttribute('aria-expanded', 'false');
        expect(within(fila).queryByText('Sin elegir')).not.toBeInTheDocument();
    });

    it('con más de cuatro exámenes solo el destacado viene desplegado', async () => {
        examenes = [...examenes, { ...SIN_AULAS, id: 9, fecha: '2026-12-01' }];
        montar();

        const destacada = await tarjeta('Primer parcial · Introducción a la Programación');
        expect(within(destacada).getByRole('button', { name: /^Pasos de/ })).toHaveAttribute(
            'aria-expanded',
            'true'
        );
        const otra = await tarjeta('Primer parcial · Taller de Ingeniería de Software');
        expect(within(otra).getByRole('button', { name: /^Pasos de/ })).toHaveAttribute(
            'aria-expanded',
            'false'
        );
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

    it('«Habilitar» lleva a Habilitación con el examen', async () => {
        montar();

        const sinEmpezar = await tarjeta('Segundo parcial · Taller de Ingeniería de Software');
        expect(within(sinEmpezar).getByRole('link', { name: /Habilitar/ })).toHaveAttribute(
            'href',
            '/habilitacion?examen=5'
        );
        const empezada = await tarjeta('Primer parcial · Taller de Ingeniería de Software');
        expect(
            within(empezada).getByRole('link', { name: /Terminar habilitación/ })
        ).toHaveAttribute('href', '/habilitacion?examen=3');
    });

    it('sin el permiso de habilitación no dibuja sus enlaces', async () => {
        montar(usuarioDePrueba('Docente', { permisos: ['examenes'] }));
        const fila = await tarjeta('Segundo parcial · Taller de Ingeniería de Software');

        expect(within(fila).queryByRole('link', { name: /Habilit/ })).not.toBeInTheDocument();
        expect(within(fila).getByRole('link', { name: 'Editar' })).toBeInTheDocument();
    });

    it('solo quien lo registró lo edita o lo elimina', async () => {
        montar();

        const propio = await tarjeta('Segundo parcial · Introducción a la Programación');
        expect(within(propio).getByRole('link', { name: 'Editar' })).toHaveAttribute(
            'href',
            '/examenes/nuevo?examen=4'
        );
        expect(within(propio).getByRole('button', { name: 'Eliminar' })).toBeInTheDocument();

        const ajeno = await tarjeta('Primer parcial · Taller de Ingeniería de Software');
        expect(within(ajeno).getByText(/Rodriguez Bilbao Erika Patricia/)).toBeInTheDocument();
        expect(within(ajeno).queryByRole('link', { name: 'Editar' })).not.toBeInTheDocument();
        expect(within(ajeno).queryByRole('button', { name: 'Eliminar' })).not.toBeInTheDocument();
    });
});

describe('Examenes · eliminación', () => {
    it('elimina con confirmación', async () => {
        montar();
        const fila = await tarjeta('Segundo parcial · Introducción a la Programación');

        fireEvent.click(within(fila).getByRole('button', { name: 'Eliminar' }));
        expect(api.delete).not.toHaveBeenCalled();
        const dialogo = screen.getByRole('dialog', { name: 'Eliminar examen' });
        expect(dialogo).toHaveTextContent('Segundo parcial · Introducción a la Programación');
        expect(dialogo).toHaveTextContent('lunes 23 de noviembre, 14:15');

        fireEvent.click(within(dialogo).getByRole('button', { name: 'Eliminar' }));

        expect(await screen.findByText('Examen eliminado')).toBeInTheDocument();
        expect(api.delete).toHaveBeenCalledWith('/examenes/4');
        await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(3));
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('cancelar no elimina', async () => {
        montar();
        const fila = await tarjeta('Segundo parcial · Introducción a la Programación');

        fireEvent.click(within(fila).getByRole('button', { name: 'Eliminar' }));
        fireEvent.click(
            within(screen.getByRole('dialog')).getByRole('button', { name: 'Cancelar' })
        );

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(api.delete).not.toHaveBeenCalled();
    });

    it('con ingresos registrados muestra el rechazo y el examen sigue en la lista', async () => {
        api.delete.mockRejectedValueOnce(
            errorHttp(409, {
                message: 'El examen ya tiene ingresos registrados: no se puede eliminar.',
                codigo: 'EXAMEN_CON_INGRESOS',
            })
        );
        montar();
        const fila = await tarjeta('Segundo parcial · Introducción a la Programación');

        fireEvent.click(within(fila).getByRole('button', { name: 'Eliminar' }));
        fireEvent.click(
            within(screen.getByRole('dialog')).getByRole('button', { name: 'Eliminar' })
        );

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'El examen ya tiene ingresos registrados: no se puede eliminar.'
        );
        expect(screen.getAllByRole('article')).toHaveLength(4);
    });
});
