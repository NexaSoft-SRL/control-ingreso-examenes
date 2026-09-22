import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Login from './Login';

beforeEach(() => {
    window.axios = { post: vi.fn() };
});

function completarFormulario(correo, contrasena) {
    fireEvent.change(screen.getByLabelText('Correo'), { target: { value: correo } });
    fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: contrasena } });
    fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));
}

describe('Login', () => {
    it('muestra el formulario con los campos institucionales', () => {
        render(<Login onAutenticado={vi.fn()} />);

        expect(
            screen.getByRole('heading', { name: 'Control de ingreso a exámenes' })
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Correo')).toBeInTheDocument();
        expect(screen.getByLabelText('Contraseña')).toBeInTheDocument();
        expect(
            screen.getByText('Sin registro público. Las cuentas las crea el Administrador.', {
                exact: false,
            })
        ).toBeInTheDocument();
    });

    it('llama a onAutenticado con el usuario cuando el login es correcto', async () => {
        const alAutenticar = vi.fn();
        window.axios.post.mockResolvedValueOnce({
            data: { user: { id: 1, name: 'Rodrigo', email: 'rodrigo@fcyt.umss.edu.bo' } },
        });

        render(<Login onAutenticado={alAutenticar} />);
        completarFormulario('rodrigo@fcyt.umss.edu.bo', 'clave-correcta');

        await waitFor(() => {
            expect(alAutenticar).toHaveBeenCalledWith({
                id: 1,
                name: 'Rodrigo',
                email: 'rodrigo@fcyt.umss.edu.bo',
            });
        });

        expect(window.axios.post).toHaveBeenCalledWith('/api/auth/login', {
            email: 'rodrigo@fcyt.umss.edu.bo',
            password: 'clave-correcta',
        });
    });

    it('muestra un error genérico cuando las credenciales son incorrectas', async () => {
        window.axios.post.mockRejectedValueOnce({ response: { status: 401 } });

        render(<Login onAutenticado={vi.fn()} />);
        completarFormulario('rodrigo@fcyt.umss.edu.bo', 'clave-incorrecta');

        expect(await screen.findByRole('alert')).toHaveTextContent('Credenciales incorrectas.');
    });

    it('alterna entre ocultar y mostrar la contraseña', () => {
        render(<Login onAutenticado={vi.fn()} />);

        const campoContrasena = screen.getByLabelText('Contraseña');
        expect(campoContrasena).toHaveAttribute('type', 'password');

        fireEvent.click(screen.getByRole('button', { name: 'Mostrar contraseña' }));

        expect(campoContrasena).toHaveAttribute('type', 'text');
    });

    it('explica cómo recuperar la contraseña sin llamar a ningún endpoint', () => {
        render(<Login onAutenticado={vi.fn()} />);

        fireEvent.click(screen.getByRole('button', { name: '¿Olvidaste tu contraseña?' }));

        expect(
            screen.getByText('Pídele al Administrador que te la restablezca; todavía no hay', {
                exact: false,
            })
        ).toBeInTheDocument();
        expect(window.axios.post).not.toHaveBeenCalled();
    });
});
