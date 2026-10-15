import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import PuntoControl from './PuntoControl';
import { api } from '../../api/cliente';

vi.mock('../../api/cliente', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

const examen = {
    id: 10,
    fecha: '2026-10-15',
    tipo: 'parcial',
    asignatura: 'Programación',
    aulas: [{ id: 7, nombre: 'A-101' }],
};

function montar() {
    return render(
        <MemoryRouter>
            <PuntoControl />
        </MemoryRouter>
    );
}

async function preparar() {
    montar();

    await screen.findByRole('combobox', { name: 'Aula de control' });

    await waitFor(() => {
        expect(screen.getByRole('option', { name: 'A-101' })).toBeInTheDocument();
    });

    fireEvent.change(screen.getByRole('combobox', { name: 'Aula de control' }), {
        target: { value: '7' },
    });

    await waitFor(() => {
        expect(api.get).toHaveBeenCalledWith('/examenes/10/aulas/7/punto-control/ingresos', {
            params: { limite: 20 },
            signal: expect.any(AbortSignal),
        });
    });
}

describe('PuntoControl HU-19', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta.endsWith('/ingresos')) {
                return Promise.resolve({ data: { data: [] } });
            }

            if (ruta.endsWith('/candidato')) {
                return Promise.resolve({
                    data: {
                        data: {
                            estudiante_id: 5,
                            codigo: '202100001',
                            nombre: 'Estudiante de prueba',
                            inscrito: true,
                            habilitado: true,
                            aula_asignada_id: 7,
                        },
                    },
                });
            }

            return Promise.reject(new Error('Ruta inesperada'));
        });
    });

    it('consulta antes de permitir confirmar el ingreso', async () => {
        await preparar();

        expect(screen.queryByRole('button', { name: 'Confirmar ingreso' })).not.toBeInTheDocument();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        expect(await screen.findByText('Estudiante de prueba')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Confirmar ingreso' })).toBeEnabled();
    });

    it('registra solo después de confirmar', async () => {
        api.post.mockResolvedValue({
            data: { data: { veredicto: 'ADMITIDO', admitido: true } },
        });

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));
        await screen.findByText('Estudiante de prueba');

        expect(api.post).not.toHaveBeenCalled();

        fireEvent.click(screen.getByRole('button', { name: 'Confirmar ingreso' }));

        await screen.findByText('Ingreso autorizado');

        expect(api.post).toHaveBeenCalledWith(
            '/examenes/10/aulas/7/punto-control/ingresos',
            {
                tipo: 'codigo',
                identificador: '202100001',
            },
            { signal: expect.any(AbortSignal) }
        );
    });

    it('bloquea la operación cuando el backend devuelve 403', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            return Promise.reject({
                response: { status: 403, data: { message: 'No autorizado' } },
            });
        });

        await preparar();

        expect(
            await screen.findByText('Punto de control bloqueado por falta de asignación.')
        ).toBeInTheDocument();

        expect(screen.getByRole('button', { name: 'Consultar estudiante' })).toBeDisabled();
    });

    it('mantiene el contexto mientras una consulta está pendiente', async () => {
        let responder;

        const pendiente = new Promise((resolver) => {
            responder = resolver;
        });

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                ...examen,
                                aulas: [
                                    { id: 7, nombre: 'A-101' },
                                    { id: 8, nombre: 'A-102' },
                                ],
                            },
                        ],
                    },
                });
            }

            if (ruta.endsWith('/candidato')) return pendiente;

            return Promise.resolve({ data: { data: [] } });
        });

        await preparar();

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(
                '/examenes/10/aulas/7/punto-control/candidato',
                expect.objectContaining({
                    signal: expect.any(AbortSignal),
                })
            );
        });

        // Durante la consulta no pueden cambiar los datos
        // del contexto asociado a la solicitud.
        const selectorExamen = screen.getByText('Examen').closest('label').querySelector('select');

        expect(selectorExamen).toBeDisabled();

        const selectorAula = screen.getByRole('combobox', {
            name: 'Aula de control',
        });

        expect(selectorAula).toBeDisabled();
        expect(selectorAula).toHaveValue('7');

        expect(
            screen.getByRole('combobox', {
                name: 'Tipo de identificación',
            })
        ).toBeDisabled();

        expect(
            screen.getByRole('textbox', {
                name: 'Identificador',
            })
        ).toBeDisabled();

        const llamada = api.get.mock.calls.find(([ruta]) => ruta.endsWith('/candidato'));

        responder({
            data: {
                data: {
                    estudiante_id: 5,
                    codigo: '202100001',
                    nombre: 'Estudiante de prueba',
                    habilitado: true,
                },
            },
        });

        await screen.findByText('Estudiante de prueba');

        expect(llamada[1].signal).toBeInstanceOf(AbortSignal);
    });

    it('cancela una consulta pendiente al desmontar la pantalla', async () => {
        let responder;

        const pendiente = new Promise((resolver) => {
            responder = resolver;
        });

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta.endsWith('/candidato')) {
                return pendiente;
            }

            return Promise.resolve({ data: { data: [] } });
        });

        const vista = montar();

        await screen.findByRole('option', { name: 'A-101' });

        fireEvent.change(screen.getByRole('combobox', { name: 'Aula de control' }), {
            target: { value: '7' },
        });

        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Consultar estudiante' }));

        let señal;

        await waitFor(() => {
            const llamada = api.get.mock.calls.find(([ruta]) => ruta.endsWith('/candidato'));

            expect(llamada).toBeDefined();

            señal = llamada[1].signal;
        });

        expect(señal.aborted).toBe(false);

        vista.unmount();

        expect(señal.aborted).toBe(true);

        responder({
            data: {
                data: {
                    estudiante_id: 5,
                    codigo: '202100001',
                    nombre: 'Estudiante obsoleto',
                    habilitado: true,
                },
            },
        });
    });

    it('sin asignaciones no permite operar ni consultar ingresos', async () => {
        api.get.mockResolvedValue({
            data: { data: [] },
        });

        montar();

        expect(
            await screen.findByText('No tienes exámenes y aulas asignados para operar.')
        ).toBeInTheDocument();

        expect(
            screen.getByRole('button', {
                name: 'Consultar estudiante',
            })
        ).toBeDisabled();

        expect(
            screen.getByRole('button', {
                name: 'Abrir cámara QR',
            })
        ).toBeDisabled();

        expect(api.get).toHaveBeenCalledWith('/punto-control/contexto');
        expect(api.get).toHaveBeenCalledTimes(1);
        expect(api.post).not.toHaveBeenCalled();
    });

    it('muestra el identificador real del historial Laravel', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({ data: { data: [examen] } });
            }

            if (ruta.endsWith('/ingresos')) {
                return Promise.resolve({
                    data: {
                        data: [
                            {
                                ingreso_id: 42,
                                estudiante_id: 5,
                                codigo: '202100001',
                                nombres: 'Estudiante',
                                apellidos: 'de prueba',
                                aula_id: 7,
                                registrado_en: '2026-10-15 08:10:00',
                                operador: 'Auxiliar',
                            },
                        ],
                    },
                });
            }

            return Promise.reject(new Error('Ruta inesperada'));
        });

        await preparar();

        expect(await screen.findByText(/Ingreso #42/)).toBeInTheDocument();
        expect(screen.getByText(/Estudiante 5/)).toBeInTheDocument();
    });

    it('informa el fallo de carga del contexto sin permitir operar', async () => {
        api.get.mockRejectedValue({
            response: {
                status: 403,
                data: { message: 'No autorizado' },
            },
        });

        montar();

        expect(
            await screen.findByText('No tienes asignación para operar en este examen y aula.')
        ).toBeInTheDocument();

        expect(screen.getByRole('button', { name: 'Consultar estudiante' })).toBeDisabled();

        expect(api.post).not.toHaveBeenCalled();
    });

    it('ignora un 403 tardío del historial al cambiar de aula', async () => {
        let rechazarHistorialAnterior;

        const historialAnterior = new Promise((resolver, rechazar) => {
            rechazarHistorialAnterior = rechazar;
        });

        const examenConDosAulas = {
            ...examen,
            aulas: [
                { id: 7, nombre: 'A-101' },
                { id: 8, nombre: 'A-102' },
            ],
        };

        api.get.mockImplementation((ruta) => {
            if (ruta === '/punto-control/contexto') {
                return Promise.resolve({
                    data: { data: [examenConDosAulas] },
                });
            }

            if (ruta === '/examenes/10/aulas/7/punto-control/ingresos') {
                return historialAnterior;
            }

            if (ruta === '/examenes/10/aulas/8/punto-control/ingresos') {
                return Promise.resolve({
                    data: { data: [] },
                });
            }

            return Promise.reject(new Error('Ruta inesperada'));
        });

        montar();

        const selectorAula = await screen.findByRole('combobox', {
            name: 'Aula de control',
        });

        await screen.findByRole('option', { name: 'A-102' });

        fireEvent.change(selectorAula, {
            target: { value: '7' },
        });

        let señalAnterior;

        await waitFor(() => {
            const llamada = api.get.mock.calls.find(
                ([ruta]) => ruta === '/examenes/10/aulas/7/punto-control/ingresos'
            );

            expect(llamada).toBeDefined();
            señalAnterior = llamada[1].signal;
        });

        expect(señalAnterior.aborted).toBe(false);

        fireEvent.change(selectorAula, {
            target: { value: '8' },
        });

        await waitFor(() => {
            expect(señalAnterior.aborted).toBe(true);
        });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(
                '/examenes/10/aulas/8/punto-control/ingresos',
                expect.objectContaining({
                    params: { limite: 20 },
                    signal: expect.any(AbortSignal),
                })
            );
        });

        // Simula un cliente HTTP que entrega un error incluso
        // después de haber recibido la señal de cancelación.
        rechazarHistorialAnterior({
            response: {
                status: 403,
                data: { message: 'Acceso anterior denegado' },
            },
        });

        await waitFor(() => {
            expect(selectorAula).toHaveValue('8');
        });

        // Permite procesar la promesa antigua antes de comprobar el estado.
        await waitFor(() => {
            expect(
                screen.getByRole('button', {
                    name: 'Consultar estudiante',
                })
            ).toBeDisabled();
        });

        expect(
            screen.queryByText('Punto de control bloqueado por falta de asignación.')
        ).not.toBeInTheDocument();

        expect(
            screen.queryByText('No tienes asignación para operar en este examen y aula.')
        ).not.toBeInTheDocument();

        // El aula 8 debe poder habilitar una consulta nueva.
        fireEvent.change(screen.getByRole('textbox', { name: 'Identificador' }), {
            target: { value: '202100001' },
        });

        expect(
            screen.getByRole('button', {
                name: 'Consultar estudiante',
            })
        ).toBeEnabled();
    });
});
