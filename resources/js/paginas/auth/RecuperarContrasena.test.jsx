import { fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../api/cliente';
import { renderConSesion } from '../../test/apoyo';
import RecuperarContrasena from './RecuperarContrasena';

vi.mock('../../api/cliente', () => ({ api: { post: vi.fn() } }));

describe('RecuperarContrasena', () => {
    beforeEach(() => vi.clearAllMocks());

    it('solicita instrucciones usando el correo de contacto', async () => {
        api.post.mockResolvedValue({
            data: {
                message:
                    'Si la cuenta tiene ese correo de contacto, enviaremos instrucciones para recuperar el acceso.',
            },
        });
        renderConSesion(<RecuperarContrasena />, { ruta: '/recuperar-contrasena' });

        fireEvent.change(screen.getByLabelText('Correo electrónico'), {
            target: { value: 'ana@example.invalid' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Enviar instrucciones' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/auth/recuperar-contrasena', {
                correo: 'ana@example.invalid',
            })
        );
        expect(await screen.findByRole('status')).toHaveTextContent('enviaremos instrucciones');
    });

    it('restablece con token de un solo uso y nueva contraseña confirmada', async () => {
        api.post.mockResolvedValue({
            data: { message: 'La contraseña se restableció. Ya puedes iniciar sesión.' },
        });
        renderConSesion(<RecuperarContrasena />, {
            ruta: '/restablecer-contrasena?correo=ana%40example.invalid&token=abc123',
        });

        fireEvent.change(screen.getByLabelText('Nueva contraseña'), {
            target: { value: 'RecoveredPassword123!' },
        });
        fireEvent.change(screen.getByLabelText('Confirmar nueva contraseña'), {
            target: { value: 'RecoveredPassword123!' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Restablecer contraseña' }));

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/auth/restablecer-contrasena', {
                correo: 'ana@example.invalid',
                token: 'abc123',
                password: 'RecoveredPassword123!',
                password_confirmation: 'RecoveredPassword123!',
            })
        );
        expect(await screen.findByRole('status')).toHaveTextContent('Ya puedes iniciar sesión');
    });

    it('explica cuando el enlace de recuperación venció', async () => {
        api.post.mockRejectedValue({
            response: {
                status: 422,
                data: { errors: { token: ['El enlace de recuperación no es válido o venció.'] } },
            },
        });
        renderConSesion(<RecuperarContrasena />, {
            ruta: '/restablecer-contrasena?correo=ana%40example.invalid&token=vencido',
        });

        fireEvent.change(screen.getByLabelText('Nueva contraseña'), {
            target: { value: 'RecoveredPassword123!' },
        });
        fireEvent.change(screen.getByLabelText('Confirmar nueva contraseña'), {
            target: { value: 'RecoveredPassword123!' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Restablecer contraseña' }));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'El enlace de recuperación no es válido o venció.'
        );
    });
});
