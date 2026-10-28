import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Respaldo from './Respaldo';
import { api } from '../../api/cliente';
import {
    errorHttp,
    renderConSesion,
    respuestaDiferida,
    simularApi,
    usuarioDePrueba,
} from '../../test/apoyo';

vi.mock('../../api/cliente');

const respaldosEjemplo = [
    {
        id: 1,
        archivo: 'respaldo-20261028-131500.sql',
        tipo: 'Puntual',
        tamano: '5.6 MB',
        tamano_bytes: 5872025,
        estado: 'Correcto',
        fecha: '2026-10-28 13:15',
        created_at: '2026-10-28 13:15:00',
    },
];

const montar = (usuario = usuarioDePrueba('Administrador')) =>
    renderConSesion(<Respaldo />, { usuario, ruta: '/respaldo' });

describe('Respaldo - Cabecera, programación y generación', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        simularApi(api, {
            'GET /respaldos': { data: respaldosEjemplo },
        });
    });

    it('sin permiso muestra «Sin permiso» sin consultar nada (criterio 12)', async () => {
        montar(usuarioDePrueba('Docente'));

        expect(
            await screen.findByRole('heading', { level: 1, name: 'Sin permiso' })
        ).toBeInTheDocument();
        expect(api.get).not.toHaveBeenCalled();
    });

    it('con respaldos muestra la programación, el último correcto y el botón «Respaldar ahora» (criterio 1)', async () => {
        montar();

        expect(
            await screen.findByRole('heading', { level: 1, name: 'Respaldo' })
        ).toBeInTheDocument();
        expect(screen.getByText('Diario, 03:00 · último: 28 oct 2026, 13:15')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Respaldar ahora' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { level: 2, name: 'Historial' })).toBeInTheDocument();
    });

    it('sin respaldos muestra «Sin respaldos» y no dice «último» (criterio 1)', async () => {
        simularApi(api, {
            'GET /respaldos': { data: [] },
        });

        montar();

        expect(
            await screen.findByRole('heading', { level: 1, name: 'Respaldo' })
        ).toBeInTheDocument();
        expect(screen.getAllByText('Sin respaldos').length).toBeGreaterThan(0);
        expect(screen.queryByText(/último/i)).not.toBeInTheDocument();
    });

    it('un intento que falla no cuenta como el último correcto (criterio 5)', async () => {
        simularApi(api, {
            'GET /respaldos': {
                data: [
                    {
                        id: 2,
                        archivo: 'respaldo-20261028-140000.sql',
                        tipo: 'Puntual',
                        estado: 'Fallido',
                        error: 'Error de conexión',
                        fecha: '2026-10-28 14:00',
                        created_at: '2026-10-28 14:00:00',
                    },
                    {
                        id: 1,
                        archivo: 'respaldo-20261028-120000.sql',
                        tipo: 'Puntual',
                        estado: 'Correcto',
                        fecha: '2026-10-28 12:00',
                        created_at: '2026-10-28 12:00:00',
                    },
                ],
            },
        });

        montar();

        expect(
            await screen.findByRole('heading', { level: 1, name: 'Respaldo' })
        ).toBeInTheDocument();
        expect(screen.getByText('Diario, 03:00 · último: 28 oct 2026, 12:00')).toBeInTheDocument();
        expect(
            screen.queryByText('Diario, 03:00 · último: 28 oct 2026, 14:00')
        ).not.toBeInTheDocument();
    });

    it('al pulsar «Respaldar ahora» dice «Respaldando» mientras dura y avisa «Respaldo generado» (criterio 3)', async () => {
        const deferred = respuestaDiferida();
        simularApi(api, {
            'GET /respaldos': { data: respaldosEjemplo },
            'POST /respaldos': () => deferred.promesa,
        });

        montar();

        const boton = await screen.findByRole('button', { name: 'Respaldar ahora' });
        fireEvent.click(boton);

        expect(screen.getByRole('button', { name: 'Respaldando' })).toBeDisabled();

        deferred.resolver({
            data: {
                id: 3,
                archivo: 'respaldo-20261028-160000.sql',
                tipo: 'Puntual',
                estado: 'Correcto',
                fecha: '2026-10-28 16:00',
                created_at: '2026-10-28 16:00:00',
            },
        });

        expect(await screen.findByText('Respaldo generado')).toBeInTheDocument();
        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Respaldar ahora' })).not.toBeDisabled();
        });
    });

    it('si la generación falla avisa «No se pudo generar el respaldo» y vuelve a leer el historial (criterio 5)', async () => {
        let consultasGet = 0;
        simularApi(api, {
            'GET /respaldos': () => {
                consultasGet++;
                return { data: respaldosEjemplo };
            },
            'POST /respaldos': errorHttp(500, { message: 'Falla al volcar datos' }),
        });

        montar();

        const boton = await screen.findByRole('button', { name: 'Respaldar ahora' });
        expect(consultasGet).toBe(1);

        fireEvent.click(boton);

        expect(await screen.findByText('No se pudo generar el respaldo')).toBeInTheDocument();
        await waitFor(() => {
            expect(consultasGet).toBeGreaterThan(1);
        });
    });

    it('si el historial no carga muestra «No se pudo cargar» con «Reintentar» (criterio 12)', async () => {
        simularApi(api, {
            'GET /respaldos': errorHttp(500, { message: 'Error de servidor' }),
        });

        montar();

        expect(await screen.findByText('No se pudo cargar')).toBeInTheDocument();
        const botonReintentar = screen.getByRole('button', { name: 'Reintentar' });
        expect(botonReintentar).toBeInTheDocument();

        // Al pulsar reintentar consulta de nuevo
        simularApi(api, {
            'GET /respaldos': { data: respaldosEjemplo },
        });

        fireEvent.click(botonReintentar);

        expect(
            await screen.findByText('Diario, 03:00 · último: 28 oct 2026, 13:15')
        ).toBeInTheDocument();
    });
});
