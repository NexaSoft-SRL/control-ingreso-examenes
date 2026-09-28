import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Bitacora from './Bitacora';
import { guardarSesion, limpiarSesion } from '../../componentes/sesion.js';

const operaciones = [
    {
        id: 2,
        usuario: { id: 7, name: 'Jofre Ticona', email: 'jofre@umss.edu.bo' },
        operacion: 'asignatura.eliminar',
        tabla_afectada: 'asignaturas',
        registro_id: 12,
        descripcion: null,
        fecha_operacion: '2026-09-17 16:57:00',
    },
    {
        id: 1,
        usuario: null,
        operacion: 'asignatura.registrar',
        tabla_afectada: 'asignaturas',
        registro_id: 12,
        descripcion: null,
        fecha_operacion: '2026-09-16 09:05:00',
    },
];

beforeEach(() => {
    // El menu lateral se dibuja segun los permisos del rol (HU-02).
    guardarSesion({ id: 1, name: 'Administrador', permisos: ['bitacora'] });
    window.axios = { get: vi.fn().mockResolvedValue({ data: { data: operaciones } }) };
});

afterEach(() => {
    limpiarSesion();
});

describe('Bitacora', () => {
    it('carga las operaciones desde la API', async () => {
        render(<Bitacora onNavigate={vi.fn()} />);

        expect(await screen.findByText('Mostrando 2 eventos')).toBeInTheDocument();
        expect(window.axios.get).toHaveBeenCalledWith('/api/bitacora', { params: {} });
        expect(screen.getByText('17/Sep/2026 16:57')).toBeInTheDocument();
        expect(screen.getAllByText('Asignatura #12')).toHaveLength(2);
        expect(screen.getByText('Usuario eliminado')).toBeInTheDocument();
    });

    it('envía los filtros al backend', async () => {
        render(<Bitacora onNavigate={vi.fn()} />);
        await screen.findByText('Mostrando 2 eventos');

        fireEvent.change(screen.getByLabelText('Usuario'), { target: { value: '7' } });
        fireEvent.change(screen.getByLabelText('Fecha'), { target: { value: '2026-09-17' } });
        fireEvent.change(screen.getByLabelText('Operación'), {
            target: { value: 'asignatura.eliminar' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Filtrar' }));

        await waitFor(() =>
            expect(window.axios.get).toHaveBeenLastCalledWith('/api/bitacora', {
                params: { usuario_id: '7', fecha: '2026-09-17', operacion: 'asignatura.eliminar' },
            })
        );
    });

    it('avisa cuando no hay resultados', async () => {
        window.axios.get.mockResolvedValue({ data: { data: [] } });
        render(<Bitacora onNavigate={vi.fn()} />);

        expect(
            await screen.findByText('No hay eventos para los filtros seleccionados.')
        ).toBeInTheDocument();
    });

    it('manda al login si la sesión no es válida', async () => {
        window.axios.get.mockRejectedValue({ response: { status: 401 } });
        const onNavigate = vi.fn();
        render(<Bitacora onNavigate={onNavigate} />);

        await waitFor(() => expect(onNavigate).toHaveBeenCalledWith('login'));
    });

    it('muestra un error si falla la conexión', async () => {
        window.axios.get.mockRejectedValue(new Error('network'));
        render(<Bitacora onNavigate={vi.fn()} />);

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'No se pudo cargar la bitácora. Intenta de nuevo.'
        );
    });

    it('nombra las operaciones nuevas y el intento sin identificar', async () => {
        window.axios.get.mockResolvedValue({
            data: {
                data: [
                    {
                        id: 3,
                        usuario: null,
                        operacion: 'sesion.fallida',
                        tabla_afectada: 'usuarios',
                        registro_id: null,
                        descripcion: 'Intento fallido con el identificador ana@umss.edu.bo.',
                        fecha_operacion: '2026-09-28 08:10:00',
                    },
                    {
                        id: 4,
                        usuario: { id: 7, name: 'Jofre Ticona', email: 'jofre@umss.edu.bo' },
                        operacion: 'estudiante.baja',
                        tabla_afectada: 'students',
                        registro_id: 15,
                        descripcion: null,
                        fecha_operacion: '2026-09-28 08:12:00',
                    },
                ],
            },
        });

        render(<Bitacora onNavigate={vi.fn()} />);

        // La etiqueta sale dos veces: en la fila y en el filtro de operaciones.
        expect(await screen.findAllByText('Intento de sesión fallido')).not.toHaveLength(0);
        expect(screen.getByText('Sin identificar')).toBeInTheDocument();
        expect(screen.getAllByText('Baja de estudiante')).not.toHaveLength(0);
        expect(screen.getByText('Estudiante #15')).toBeInTheDocument();
    });

    it('marca Bitácora como seleccionada en el menú', async () => {
        render(<Bitacora onNavigate={vi.fn()} />);
        await screen.findByText('Mostrando 2 eventos');

        expect(screen.getAllByText('Bitácora')).toHaveLength(2);
    });
});
