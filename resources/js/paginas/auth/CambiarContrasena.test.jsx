import { fireEvent, screen, waitFor } from '@testing-library/react';
import { Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../../api/cliente';
import { renderConSesion, usuarioDePrueba } from '../../test/apoyo';
import CambiarContrasena from './CambiarContrasena';

vi.mock('../../api/cliente', () => ({ api: { post: vi.fn() } }));

function montar(actualizar = vi.fn().mockResolvedValue(usuarioDePrueba('Docente'))) {
    return renderConSesion(
        <Routes>
            <Route path="/cambiar-contrasena" element={<CambiarContrasena />} />
            <Route path="/examenes" element={<p>Acceso habilitado</p>} />
        </Routes>,
        {
            usuario: usuarioDePrueba('Docente', { debe_cambiar_contrasena: true }),
            ruta: '/cambiar-contrasena',
            sesion: { actualizar },
        }
    );
}

function completar() {
    fireEvent.change(screen.getByLabelText('Contraseña temporal'), {
        target: { value: 'Temporary123!' },
    });
    fireEvent.change(screen.getByLabelText('Nueva contraseña'), {
        target: { value: 'NewStrongPassword123!' },
    });
    fireEvent.change(screen.getByLabelText('Confirmar nueva contraseña'), {
        target: { value: 'NewStrongPassword123!' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Cambiar contraseña' }));
}

describe('CambiarContrasena', () => {
    beforeEach(() => vi.clearAllMocks());

    it('requiere una contraseña que cumpla la política antes de enviar', () => {
        montar();
        fireEvent.change(screen.getByLabelText('Contraseña temporal'), {
            target: { value: 'Temporary123!' },
        });
        fireEvent.change(screen.getByLabelText('Nueva contraseña'), {
            target: { value: 'short' },
        });
        fireEvent.change(screen.getByLabelText('Confirmar nueva contraseña'), {
            target: { value: 'short' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Cambiar contraseña' }));

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Use al menos 12 caracteres, mayúscula, minúscula, número y símbolo.'
        );
        expect(api.post).not.toHaveBeenCalled();
    });

    it('cambia la contraseña temporal y habilita la entrada a la aplicación', async () => {
        const actualizar = vi.fn().mockResolvedValue(usuarioDePrueba('Docente'));
        api.post.mockResolvedValue({
            data: { message: 'La contraseña se actualizó correctamente.' },
        });
        montar(actualizar);
        completar();

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/auth/cambiar-contrasena', {
                contrasena_actual: 'Temporary123!',
                password: 'NewStrongPassword123!',
                password_confirmation: 'NewStrongPassword123!',
            })
        );
        expect(await screen.findByText('Acceso habilitado')).toBeInTheDocument();
        expect(actualizar).toHaveBeenCalledOnce();
    });

    it('muestra si la contraseña temporal actual no es correcta', async () => {
        api.post.mockRejectedValue({
            response: {
                status: 422,
                data: { errors: { contrasena_actual: ['La contraseña actual no es correcta.'] } },
            },
        });
        montar();
        completar();

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'La contraseña actual no es correcta.'
        );
    });
});
