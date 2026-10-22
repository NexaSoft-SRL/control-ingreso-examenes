import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AccesoAula from './AccesoAula';
import { api } from '../../api/cliente';

vi.mock('../../api/cliente', () => ({
    api: {
        post: vi.fn(),
    },
}));

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual('react-router-dom');
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    };
});

describe('AccesoAula', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        localStorage.clear();
    });

    it('muestra los campos de código y nombre del colaborador', () => {
        render(
            <MemoryRouter>
                <AccesoAula />
            </MemoryRouter>
        );

        expect(
            screen.getByRole('heading', { name: 'Acceso a Puerta de Aula' })
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Código de aula')).toBeInTheDocument();
        expect(screen.getByLabelText('Nombre')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Ingresar al aula/i })).toBeInTheDocument();
    });

    it('valida que el código tenga 6 dígitos numéricos y el nombre sea obligatorio', async () => {
        render(
            <MemoryRouter>
                <AccesoAula />
            </MemoryRouter>
        );

        fireEvent.click(screen.getByRole('button', { name: /Ingresar al aula/i }));

        expect((await screen.findAllByText('Obligatorio')).length).toBe(2);
        expect(api.post).not.toHaveBeenCalled();

        fireEvent.change(screen.getByLabelText(/Código de aula/), { target: { value: '123' } });
        fireEvent.change(screen.getByLabelText('Nombre'), { target: { value: 'A' } });
        fireEvent.click(screen.getByRole('button', { name: /Ingresar al aula/i }));

        expect(await screen.findByText('Seis dígitos')).toBeInTheDocument();
        expect(screen.getByText('Mínimo 3 caracteres')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('toma el código desde los parámetros de búsqueda si viene en la url', () => {
        render(
            <MemoryRouter initialEntries={['/acceso-aula?codigo=654321']}>
                <AccesoAula />
            </MemoryRouter>
        );

        expect(screen.getByLabelText('Código de aula')).toHaveValue('654321');
    });

    it('envía los datos al backend, almacena el token y redirige a punto de control', async () => {
        api.post.mockResolvedValueOnce({
            data: {
                data: {
                    token: 'tok-colaborador-123',
                    examen_id: 10,
                    aula_id: 20,
                    aula_nombre: '691A',
                    nombre_colaborador: 'Carlos Gómez',
                    expira_en: '2026-10-20 12:00:00',
                },
            },
        });

        render(
            <MemoryRouter>
                <AccesoAula />
            </MemoryRouter>
        );

        fireEvent.change(screen.getByLabelText('Código de aula'), { target: { value: '123456' } });
        fireEvent.change(screen.getByLabelText('Nombre'), {
            target: { value: 'Carlos Gómez' },
        });
        fireEvent.click(screen.getByRole('button', { name: /Ingresar al aula/i }));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/auth/codigo-aula', {
                codigo: '123456',
                nombre_colaborador: 'Carlos Gómez',
            });
        });

        expect(localStorage.getItem('codigo_aula_token')).toBe('tok-colaborador-123');
        expect(mockNavigate).toHaveBeenCalledWith('/punto-control?examen=10&aula=20', {
            replace: true,
        });
    });

    it('muestra el mensaje de error si el código es inválido o fue revocado', async () => {
        api.post.mockRejectedValueOnce({
            response: {
                status: 401,
                data: {
                    message: 'Código de aula no válido o revocado.',
                },
            },
        });

        render(
            <MemoryRouter>
                <AccesoAula />
            </MemoryRouter>
        );

        fireEvent.change(screen.getByLabelText('Código de aula'), { target: { value: '999999' } });
        fireEvent.change(screen.getByLabelText('Nombre'), {
            target: { value: 'Carlos Gómez' },
        });
        fireEvent.click(screen.getByRole('button', { name: /Ingresar al aula/i }));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Código de aula no válido o revocado.'
        );
        expect(localStorage.getItem('codigo_aula_token')).toBeNull();
    });
});
