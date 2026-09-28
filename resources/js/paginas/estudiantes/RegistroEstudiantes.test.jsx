import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import RegistroEstudiantes from './RegistroEstudiantes';

vi.mock('axios');

const estudiante = {
    id: 15,
    codigo_universitario: '201900123',
    carrera: 'Ingeniería de Sistemas',
    nombre: 'Lucía',
    apellido: 'Fernández',
    ci: '7788990',
    correo: 'lucia@umss.edu.bo',
    activo: true,
};

beforeEach(() => {
    vi.resetAllMocks();
    axios.get.mockResolvedValue({ data: [estudiante] });
});

describe('RegistroEstudiantes', () => {
    it('da de baja al estudiante sin sacarlo del padrón', async () => {
        axios.delete.mockResolvedValue({ data: { ...estudiante, activo: false } });

        render(<RegistroEstudiantes />);
        await screen.findByText('Fernández');

        fireEvent.click(screen.getByRole('button', { name: 'Dar de baja' }));

        await waitFor(() => expect(axios.delete).toHaveBeenCalledWith('/api/students/15'));
        expect(await screen.findByText('INACTIVO')).toBeInTheDocument();
        expect(screen.getByText('Fernández')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Reactivar' })).toBeInTheDocument();
    });

    it('reactiva al estudiante dado de baja', async () => {
        axios.get.mockResolvedValue({ data: [{ ...estudiante, activo: false }] });
        axios.put.mockResolvedValue({ data: estudiante });

        render(<RegistroEstudiantes />);
        await screen.findByText('Fernández');

        fireEvent.click(screen.getByRole('button', { name: 'Reactivar' }));

        await waitFor(() =>
            expect(axios.put).toHaveBeenCalledWith(
                '/api/students/15',
                expect.objectContaining({ activo: true, ci: '7788990' })
            )
        );
        expect(await screen.findByText('ACTIVO')).toBeInTheDocument();
    });

    it('avisa si la baja falla', async () => {
        axios.delete.mockRejectedValue({ response: { data: { message: 'No se pudo.' } } });

        render(<RegistroEstudiantes />);
        await screen.findByText('Fernández');

        fireEvent.click(screen.getByRole('button', { name: 'Dar de baja' }));

        expect(await screen.findByText('No se pudo.')).toBeInTheDocument();
    });
});
