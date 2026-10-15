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
        });
    });
}

describe('PuntoControl HU-19', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        api.get.mockImplementation((ruta) => {
            if (ruta === '/examenes') {
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

        expect(api.post).toHaveBeenCalledWith('/examenes/10/aulas/7/punto-control/ingresos', {
            tipo: 'codigo',
            identificador: '202100001',
        });
    });

    it('bloquea la operación cuando el backend devuelve 403', async () => {
        api.get.mockImplementation((ruta) => {
            if (ruta === '/examenes') {
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
});
