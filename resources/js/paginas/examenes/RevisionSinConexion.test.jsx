import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RevisionSinConexion from './RevisionSinConexion';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

const irregularidad = (cambios = {}) => ({
    id: 1,
    tipo: 'INGRESO_DOBLE',
    tipo_texto: 'Ingreso doble',
    gravedad: 'ALTA',
    estudiante: 'García Ugarte Ximena · 202311253',
    aula: '692C',
    detalle: 'Admitida en 692C a las 08:15 y en 692D a las 08:17',
    datos: { dispositivo: 'a1b2c3' },
    estado: 'PENDIENTE',
    nota: null,
    resuelta_por: null,
    resuelta_en: null,
    ...cambios,
});

const sesion = (cambios = {}) => ({
    id: 1,
    dispositivo: 'a1b2c3',
    aula: '692C',
    operador: 'Flores Villarroel Corina',
    descargada_en: '2026-10-26 08:00:00',
    sincronizada_en: '2026-10-26 09:40:00',
    eventos: 12,
    estado: 'SINCRONIZADA',
    ...cambios,
});

const cuerpo = (cambios = {}) => ({
    usado: true,
    examen: { id: 25, asignatura: 'Cálculo I', tipo: 'FINAL' },
    sesiones: [sesion()],
    sin_sincronizar: [],
    irregularidades: [irregularidad()],
    resumen: { pendientes: 1, altas: 1, resueltas: 0, sin_sincronizar: 0 },
    ...cambios,
});

const montar = () => renderConSesion(<RevisionSinConexion examenId={25} />);

beforeEach(() => {
    vi.clearAllMocks();
});

describe('RevisionSinConexion', () => {
    it('avisa cuando no se usó el modo sin conexión', async () => {
        simularApi(api, {
            'GET /examenes/25/sin-conexion': cuerpo({
                usado: false,
                sesiones: [],
                irregularidades: [],
                resumen: { pendientes: 0, altas: 0, resueltas: 0, sin_sincronizar: 0 },
            }),
        });
        montar();
        expect(
            await screen.findByText('No se usó el modo sin conexión en este examen')
        ).toBeInTheDocument();
    });

    it('muestra los totales, las irregularidades y los puestos', async () => {
        simularApi(api, { 'GET /examenes/25/sin-conexion': cuerpo() });
        montar();

        expect(await screen.findByText('Ingreso doble')).toBeInTheDocument();
        expect(
            screen.getByText('Admitida en 692C a las 08:15 y en 692D a las 08:17')
        ).toBeInTheDocument();
        expect(
            screen.getByText(/García Ugarte Ximena · 202311253 · Aula 692C · puesto …a1b2c3/)
        ).toBeInTheDocument();
        expect(screen.getAllByText('Por resolver')).toHaveLength(2);
        expect(screen.getByText('Cerrado')).toBeInTheDocument();
        expect(screen.getByText(/08:00 → 09:40 · 12 lecturas/)).toBeInTheDocument();
    });

    it('señala los puestos que no enviaron su cierre', async () => {
        const huerfana = sesion({
            id: 2,
            estado: 'DESCARGADA',
            sincronizada_en: null,
            eventos: 0,
            dispositivo: 'ffeedd',
        });
        simularApi(api, {
            'GET /examenes/25/sin-conexion': cuerpo({
                sesiones: [huerfana],
                sin_sincronizar: [huerfana],
                irregularidades: [],
                resumen: { pendientes: 0, altas: 0, resueltas: 0, sin_sincronizar: 1 },
            }),
        });
        montar();

        const alerta = await screen.findByRole('alert');
        expect(alerta).toHaveTextContent('Sin cierre');
        expect(alerta).toHaveTextContent('Aula 692C');
        expect(alerta).toHaveTextContent('puesto …ffeedd');
        expect(screen.getByText('Sin irregularidades')).toBeInTheDocument();
    });

    it('resuelve una irregularidad con nota y recarga', async () => {
        const resolver = vi.fn(() => ({ message: 'Irregularidad resuelta.' }));
        let llamadas = 0;
        simularApi(api, {
            'GET /examenes/25/sin-conexion': () => {
                llamadas += 1;
                return llamadas === 1
                    ? cuerpo()
                    : cuerpo({
                          irregularidades: [
                              irregularidad({
                                  estado: 'CONFIRMADA',
                                  nota: 'Entró por 692C',
                                  resuelta_por: 'Flores Villarroel Corina',
                              }),
                          ],
                          resumen: { pendientes: 0, altas: 0, resueltas: 1, sin_sincronizar: 0 },
                      });
            },
            'POST /sin-conexion/irregularidades/1/resolucion': resolver,
        });
        montar();

        fireEvent.click(await screen.findByRole('button', { name: 'Resolver' }));
        const dialogo = await screen.findByRole('dialog');

        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));
        expect(await within(dialogo).findByText('Mínimo 5 caracteres')).toBeInTheDocument();
        expect(resolver).not.toHaveBeenCalled();

        fireEvent.change(within(dialogo).getByLabelText(/^Nota/), {
            target: { value: 'Entró por 692C' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        await waitFor(() => expect(resolver).toHaveBeenCalledTimes(1));
        expect(resolver.mock.calls[0][0].data).toEqual({
            estado: 'CONFIRMADA',
            nota: 'Entró por 692C',
        });
        expect(
            await screen.findByText(/Flores Villarroel Corina · Entró por 692C/)
        ).toBeInTheDocument();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('permite descartar y muestra el error del servidor', async () => {
        simularApi(api, {
            'GET /examenes/25/sin-conexion': cuerpo(),
            'POST /sin-conexion/irregularidades/1/resolucion': errorHttp(422, {
                message: 'Datos inválidos',
                errors: { nota: ['La nota es obligatoria.'] },
            }),
        });
        montar();

        fireEvent.click(await screen.findByRole('button', { name: 'Resolver' }));
        const dialogo = await screen.findByRole('dialog');
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Descartar' }));
        expect(within(dialogo).getByRole('button', { name: 'Descartar' })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        fireEvent.change(within(dialogo).getByLabelText(/^Nota/), {
            target: { value: 'Error del puesto' },
        });
        fireEvent.click(within(dialogo).getByRole('button', { name: 'Guardar' }));

        expect(await within(dialogo).findByText('La nota es obligatoria.')).toBeInTheDocument();
    });
});
