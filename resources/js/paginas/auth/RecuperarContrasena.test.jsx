import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../api/cliente';
import { renderConSesion } from '../../test/apoyo';
import RecuperarContrasena from './RecuperarContrasena';

vi.mock('../../api/cliente', () => ({ api: { post: vi.fn() } }));

const ENLACE = '/restablecer-contrasena?correo=ana%40example.invalid&token=abc123';

function escribir(clave, repetida = clave) {
    fireEvent.change(screen.getByLabelText(/^Contraseña/), { target: { value: clave } });
    fireEvent.change(screen.getByLabelText(/^Repetir contraseña/), {
        target: { value: repetida },
    });
}

describe('RecuperarContrasena', () => {
    beforeEach(() => vi.clearAllMocks());

    it('solicita el enlace usando el nombre de usuario, sin textos explicativos', async () => {
        api.post.mockResolvedValue({
            data: {
                message:
                    'Si la cuenta tiene correo registrado, recibirá el enlace de recuperación.',
            },
        });
        const { container } = renderConSesion(<RecuperarContrasena />, {
            ruta: '/recuperar-contrasena',
        });
        expect(container.textContent).not.toMatch(/Enviaremos|instrucciones/);

        fireEvent.change(screen.getByLabelText(/^Usuario/), {
            target: { value: 'ana.rojas' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Enviar enlace' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/auth/recuperar-contrasena', {
                usuario: 'ana.rojas',
            })
        );
        expect(await screen.findByRole('status')).toHaveTextContent(
            'recibirá el enlace de recuperación'
        );
    });

    it('exige el usuario antes de solicitar el enlace', () => {
        renderConSesion(<RecuperarContrasena />, { ruta: '/recuperar-contrasena' });
        fireEvent.click(screen.getByRole('button', { name: 'Enviar enlace' }));

        expect(screen.getByText('Obligatorio')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('un correo en «Usuario» no se envía', () => {
        renderConSesion(<RecuperarContrasena />, { ruta: '/recuperar-contrasena' });
        fireEvent.change(screen.getByLabelText(/^Usuario/), {
            target: { value: 'ana.rojas@umss.edu.bo' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Enviar enlace' }));

        expect(screen.getByText('Usuario no válido')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('restablece con las reglas cumplidas y la repetición', async () => {
        api.post.mockResolvedValue({ data: { message: 'Contraseña actualizada.' } });
        renderConSesion(<RecuperarContrasena />, { ruta: ENLACE });
        const boton = () => screen.getByRole('button', { name: 'Restablecer contraseña' });

        escribir('abc');
        expect(boton()).toBeDisabled();

        escribir('Recovered2026');
        expect(boton()).toBeEnabled();
        fireEvent.click(boton());

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/auth/restablecer-contrasena', {
                correo: 'ana@example.invalid',
                token: 'abc123',
                password: 'Recovered2026',
                password_confirmation: 'Recovered2026',
            })
        );
        expect(await screen.findByRole('status')).toHaveTextContent('Contraseña actualizada.');
        expect(screen.getByRole('button', { name: 'Iniciar sesión' })).toBeInTheDocument();
    });

    it('un enlace vencido o recortado muestra «Enlace no válido o vencido»', async () => {
        api.post.mockRejectedValue({
            response: {
                status: 422,
                data: { errors: { token: ['Enlace no válido o vencido.'] } },
            },
        });
        renderConSesion(<RecuperarContrasena />, { ruta: ENLACE });

        escribir('Recovered2026');
        fireEvent.click(screen.getByRole('button', { name: 'Restablecer contraseña' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Enlace no válido o vencido');
    });

    it('sin token en la dirección el enlace no es válido', () => {
        renderConSesion(<RecuperarContrasena />, { ruta: '/restablecer-contrasena' });

        expect(screen.getByRole('alert')).toHaveTextContent('Enlace no válido o vencido');
    });
});
