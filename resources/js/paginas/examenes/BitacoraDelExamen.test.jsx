import { fireEvent, screen, within } from '@testing-library/react';
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

beforeEach(() => {
    simularApi(api, { 'GET /reportes/examenes/25/bitacora': { data: SUCESOS } });
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
        expect(screen.getAllByRole('listitem')).toHaveLength(1);
        expect(screen.getByText('1 suceso de 5')).toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /^Todo/ }));
        fireEvent.change(screen.getByLabelText('Aula'), { target: { value: '692D' } });
        expect(screen.getAllByRole('listitem')).toHaveLength(2);

        fireEvent.change(screen.getByLabelText('Buscar estudiante'), {
            target: { value: 'castro' },
        });
        expect(screen.getAllByRole('listitem')).toHaveLength(1);
        expect(screen.getByText('Dispositivo no permitido')).toBeInTheDocument();
    });

    it('al cerrar dice cuántos ingresaron y quién no se presentó', async () => {
        simularApi(api, {
            'GET /reportes/examenes/25/bitacora': {
                data: [
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
                ],
            },
        });
        montar();
        await screen.findByRole('region', { name: 'lunes 26 de octubre' });

        fireEvent.click(screen.getByRole('button', { name: /^No se presentaron/ }));
        expect(screen.getAllByRole('listitem')).toHaveLength(1);
        expect(screen.getByRole('listitem')).toHaveTextContent(
            '16:00:00No se presentóAula 692DZurita Paz Omar · 202311299Habilitado'
        );

        // Buscar al estudiante responde un reclamo: «no vino».
        fireEvent.click(screen.getByRole('button', { name: /^Todo/ }));
        fireEvent.change(screen.getByLabelText('Buscar estudiante'), {
            target: { value: 'zurita' },
        });
        expect(screen.getByText('No se presentó')).toBeInTheDocument();
    });

    it('filtra por gravedad', async () => {
        montar();
        await screen.findByRole('region', { name: 'lunes 26 de octubre' });
        const gravedad = within(screen.getByRole('group', { name: 'Gravedad' }));

        expect(gravedad.getByRole('button', { name: /Todas/ })).toHaveTextContent('Todas3');
        fireEvent.click(gravedad.getByRole('button', { name: /Muy grave/ }));
        expect(screen.getAllByRole('listitem')).toHaveLength(1);
        expect(screen.getByText('Suplantación de identidad')).toBeInTheDocument();

        fireEvent.click(gravedad.getByRole('button', { name: /^Grave/ }));
        expect(screen.getAllByRole('listitem')).toHaveLength(2);
        expect(screen.getByText('2 sucesos de 5')).toBeInTheDocument();
    });

    it('arriba dice quién controló cada aula; abajo el inicio y el fin', async () => {
        simularApi(api, {
            'GET /reportes/examenes/25/bitacora': {
                aulas: [
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
                ],
                data: [
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
            },
        });
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
    });

    it('sin sucesos lo dice y no ofrece descargar', async () => {
        simularApi(api, { 'GET /reportes/examenes/25/bitacora': { data: [] } });
        montar();

        expect(await screen.findByText('Sin sucesos todavía')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Bitácora del examen en PDF/ })).toBeDisabled();
    });
});
