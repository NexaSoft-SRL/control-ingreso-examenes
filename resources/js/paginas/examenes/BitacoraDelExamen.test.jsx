import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import BitacoraDelExamen from './BitacoraDelExamen';
import { api } from '../../api/cliente';
import { renderConSesion, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

const suceso = (instante, categoria, titulo, cambios = {}) => ({
    instante,
    categoria,
    titulo,
    detalle: '',
    aula: null,
    estudiante: null,
    codigo: null,
    por: 'Flores Villarroel Corina',
    ...cambios,
});

const SUCESOS = [
    suceso('2026-10-24 18:40:00', 'examen', 'Códigos QR emitidos'),
    suceso('2026-10-26 13:50:00', 'ingreso', 'Ingresó', {
        aula: '692C',
        estudiante: 'García Ugarte Ximena',
        codigo: '202311253',
        detalle: 'por código QR',
    }),
    suceso('2026-10-26 14:04:00', 'rechazo', 'Intento rechazado', {
        aula: '692D',
        estudiante: 'Mamani Heredia Carlos',
        codigo: '202311260',
        detalle: 'no habilitado',
        gravedad: 'GRAVE',
    }),
    suceso('2026-10-26 14:46:00', 'incidencia', 'Dispositivo no permitido', {
        aula: '692D',
        estudiante: 'Castro Condori Valeria',
        gravedad: 'GRAVE',
    }),
    suceso('2026-10-26 14:50:00', 'incidencia', 'Suplantación de identidad', {
        aula: '692C',
        estudiante: 'Rojas Vera Ana',
        gravedad: 'MUY_GRAVE',
    }),
];

const normalizar = (texto) =>
    texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

function respuestaBitacora(todos, parametros = {}, aulas = []) {
    const buscar = normalizar(parametros.buscar ?? '');
    const filtrados = todos.filter((s) => {
        const coincideTexto =
            !buscar ||
            normalizar(
                [
                    s.instante,
                    s.categoria,
                    s.titulo,
                    s.detalle,
                    s.aula,
                    s.estudiante,
                    s.codigo,
                    s.por,
                ]
                    .filter(Boolean)
                    .join(' ')
            ).includes(buscar);
        return (
            coincideTexto &&
            (!parametros.categoria || s.categoria === parametros.categoria) &&
            (!parametros.gravedad || s.gravedad === parametros.gravedad) &&
            (!parametros.aula || s.aula === parametros.aula)
        );
    });
    const pagina = Number(parametros.pagina ?? 1);
    const porPagina = Number(parametros.por_pagina ?? 100);

    return {
        aulas,
        data: filtrados.slice((pagina - 1) * porPagina, pagina * porPagina),
        meta: {
            total: filtrados.length,
            total_general: todos.length,
            pagina,
            por_pagina: porPagina,
            categorias: Object.fromEntries(
                [...new Set(todos.map((s) => s.categoria))].map((categoria) => [
                    categoria,
                    todos.filter((s) => s.categoria === categoria).length,
                ])
            ),
            gravedades: Object.fromEntries(
                [...new Set(todos.map((s) => s.gravedad).filter(Boolean))].map((gravedad) => [
                    gravedad,
                    todos.filter((s) => s.gravedad === gravedad).length,
                ])
            ),
            aulas: [...new Set(todos.map((s) => s.aula).filter(Boolean))].sort(),
        },
    };
}

function simularBitacora(sucesos = SUCESOS, aulas = []) {
    simularApi(api, {
        'GET /reportes/examenes/25/bitacora': ({ params }) =>
            respuestaBitacora(sucesos, params, aulas),
    });
}

beforeEach(() => {
    simularBitacora();
});

const montar = () => renderConSesion(<BitacoraDelExamen examenId={25} fecha="2026-10-26" />);

describe('BitacoraDelExamen', () => {
    it('muestra todo lo ocurrido por día, con hora, aula, estudiante y quién', async () => {
        montar();

        const dia = await screen.findByRole('region', { name: 'lunes 26 de octubre' });
        expect(within(dia).getAllByRole('listitem')).toHaveLength(4);
        expect(within(dia).getAllByRole('listitem')[0]).toHaveTextContent(
            '13:50:00IngresóAula 692CGarcía Ugarte Ximena · 202311253por código QRFlores Villarroel Corina'
        );
        expect(screen.getByRole('region', { name: 'sábado 24 de octubre' })).toHaveTextContent(
            'Códigos QR emitidos'
        );
        expect(screen.getByText('5 sucesos')).toBeInTheDocument();
        expect(within(dia).getAllByRole('listitem')[3]).toHaveTextContent(
            '14:50:00Suplantación de identidadMuy graveAula 692C'
        );
    });

    it('filtra por tipo, por aula y por estudiante', async () => {
        montar();
        await screen.findByRole('region', { name: 'lunes 26 de octubre' });

        fireEvent.click(screen.getByRole('button', { name: /^Rechazos/ }));
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(1));
        expect(screen.getByText('1 suceso de 5')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /^Todo/ }));
        await screen.findByLabelText('Aula');
        fireEvent.change(screen.getByLabelText('Aula'), { target: { value: '692D' } });
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(2));

        fireEvent.change(screen.getByLabelText('Buscar en bitácora'), {
            target: { value: 'castro' },
        });
        expect(await screen.findByText('Dispositivo no permitido')).toBeInTheDocument();
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(1));
    });

    it('al cerrar dice cuántos ingresaron y quién no se presentó', async () => {
        simularBitacora([
            ...SUCESOS,
            suceso('2026-10-26 16:00:00', 'examen', 'Cierre del examen', {
                detalle: 'Ingresaron 1 de 2 habilitados · 1 no se presentó',
                por: null,
            }),
            suceso('2026-10-26 16:00:00', 'ausente', 'No se presentó', {
                aula: '692D',
                estudiante: 'Zurita Paz Omar',
                codigo: '202311299',
                detalle: 'Habilitado',
                por: null,
            }),
        ]);
        montar();
        await screen.findByRole('region', { name: 'lunes 26 de octubre' });

        fireEvent.click(screen.getByRole('button', { name: /^No se presentaron/ }));
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(1));
        expect(screen.getByRole('listitem')).toHaveTextContent(
            '16:00:00No se presentóAula 692DZurita Paz Omar · 202311299Habilitado'
        );

        // Buscar al estudiante responde un reclamo: «no vino».
        fireEvent.click(screen.getByRole('button', { name: /^Todo/ }));
        await screen.findByLabelText('Buscar en bitácora');
        fireEvent.change(screen.getByLabelText('Buscar en bitácora'), {
            target: { value: 'zurita' },
        });
        expect(await screen.findByText('No se presentó')).toBeInTheDocument();
    });

    it('filtra por gravedad', async () => {
        montar();
        await screen.findByRole('region', { name: 'lunes 26 de octubre' });
        const controles = () => within(screen.getByRole('group', { name: 'Gravedad' }));

        expect(controles().getByRole('button', { name: /Todas/ })).toHaveTextContent('Todas3');
        fireEvent.click(controles().getByRole('button', { name: /Muy grave/ }));
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(1));
        expect(screen.getByText('Suplantación de identidad')).toBeInTheDocument();

        fireEvent.click(controles().getByRole('button', { name: /^Grave/ }));
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(2));
        expect(screen.getByText('2 sucesos de 5')).toBeInTheDocument();
    });

    it('arriba dice quién controló cada aula; abajo el inicio y el fin', async () => {
        simularBitacora(
            [
                suceso('2026-10-26 14:00:00', 'examen', 'Inicio del examen', {
                    detalle: 'Programado 14:00 – 15:30 · 90 min',
                    por: null,
                }),
                ...SUCESOS.slice(1),
                suceso('2026-10-26 23:59:59', 'examen', 'Fin del día del examen', {
                    detalle: 'Sin finalización manual',
                    por: null,
                }),
            ],
            [
                {
                    aula: '692C',
                    controlo: 'Blanco Coca Leticia (docente), Rojas Vera Ana (auxiliar)',
                    ingresos: 2,
                    primer_ingreso: '13:50',
                    ultimo_ingreso: '14:05',
                },
                {
                    aula: '692D',
                    controlo: 'Mamani Diego (código de aula)',
                    ingresos: 0,
                    primer_ingreso: null,
                    ultimo_ingreso: null,
                },
            ]
        );
        montar();

        const control = await screen.findByRole('region', { name: 'Control por aula' });
        expect(control).toHaveTextContent(
            'Aula 692C · controló: Blanco Coca Leticia (docente), Rojas Vera Ana (auxiliar) · 2 ingresos · 13:50 – 14:05'
        );
        expect(control).toHaveTextContent(
            'Aula 692D · controló: Mamani Diego (código de aula) · 0 ingresos'
        );
        const items = within(
            screen.getByRole('region', { name: 'lunes 26 de octubre' })
        ).getAllByRole('listitem');
        expect(items[0]).toHaveTextContent('Inicio del examenProgramado 14:00 – 15:30 · 90 min');
        expect(items.at(-1)).toHaveTextContent('Fin del día del examen');
        // Sin la hora ficticia del cierre y dicho llano.
        expect(items.at(-1)).not.toHaveTextContent('23:59:59');
        expect(items.at(-1)).toHaveTextContent('Cerrado automáticamente al final del día');
    });

    it('junta a los que no se presentaron en una fila que se despliega', async () => {
        const ausente = (nombre, codigo) =>
            suceso('2026-10-26 23:59:59', 'ausente', 'No se presentó', {
                aula: '692D',
                estudiante: nombre,
                codigo,
                detalle: 'Habilitado',
                por: null,
            });
        simularBitacora([
            ...SUCESOS,
            ausente('Zurita Paz Omar', '202311299'),
            ausente('Arce Lima Rosa', '202311300'),
            ausente('Vega Soto Luis', '202311301'),
        ]);
        montar();
        const dia = await screen.findByRole('region', { name: 'lunes 26 de octubre' });

        expect(within(dia).getByText('3 no se presentaron')).toBeInTheDocument();
        expect(within(dia).queryByText('Zurita Paz Omar')).toBeNull();

        const ver = within(dia).getByRole('button', { name: 'Ver' });
        expect(ver).toHaveAttribute('aria-expanded', 'false');
        fireEvent.click(ver);

        const lista = within(dia).getByRole('list', { name: 'No se presentaron' });
        expect(within(lista).getAllByRole('listitem')).toHaveLength(3);
        expect(lista).toHaveTextContent('Zurita Paz Omar · 202311299 · Aula 692D');
        fireEvent.click(within(dia).getByRole('button', { name: 'Ocultar' }));
        expect(within(dia).queryByRole('list', { name: 'No se presentaron' })).toBeNull();
    });

    it('sin sucesos lo dice y no ofrece descargar', async () => {
        simularBitacora([]);
        montar();

        expect(await screen.findByText('Sin sucesos todavía')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Bitácora del examen en PDF/ })).toBeDisabled();
    });

    it('carga sucesos en páginas de cien', async () => {
        const sucesos = Array.from({ length: 205 }, (_, i) => {
            const minutos = 13 * 60 + i;
            const hora = String(Math.floor(minutos / 60)).padStart(2, '0');
            const minuto = String(minutos % 60).padStart(2, '0');
            return suceso(`2026-10-26 ${hora}:${minuto}:00`, 'ingreso', 'Ingresó', {
                aula: '692C',
                estudiante: `Estudiante ${i}`,
            });
        });
        simularBitacora(sucesos);
        montar();

        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(100));
        fireEvent.click(screen.getByRole('button', { name: 'Mostrar más · quedan 105' }));
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(200));
        fireEvent.click(screen.getByRole('button', { name: 'Mostrar más · quedan 5' }));
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(205));
        expect(screen.queryByRole('button', { name: /Mostrar más/ })).not.toBeInTheDocument();
    });

    it('busca en toda la bitácora por responsable y detalle', async () => {
        const sucesos = Array.from({ length: 120 }, (_, i) =>
            suceso(`2026-10-26 13:${String(i % 60).padStart(2, '0')}:00`, 'ingreso', 'Ingresó')
        );
        sucesos.push(
            suceso('2026-10-26 16:00:00', 'incidencia', 'Revisión de excepción', {
                detalle: 'Se revisó la documentación',
                por: 'Rojas Vera Ana',
            })
        );
        simularBitacora(sucesos);
        montar();
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(100));

        const buscador = screen.getByLabelText('Buscar en bitácora');
        fireEvent.change(buscador, { target: { value: 'rojas vera ana' } });
        expect(await screen.findByText('Revisión de excepción')).toBeInTheDocument();
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(1));

        fireEvent.change(buscador, { target: { value: 'documentación' } });
        expect(await screen.findByText('Revisión de excepción')).toBeInTheDocument();
        await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(1));
    });
});
