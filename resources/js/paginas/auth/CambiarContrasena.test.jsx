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

const boton = () => screen.getByRole('button', { name: 'Guardar y entrar' });

function escribir(clave, repetida = clave) {
    fireEvent.change(screen.getByLabelText(/^Contraseña/), { target: { value: clave } });
    fireEvent.change(screen.getByLabelText(/^Repetir contraseña/), {
        target: { value: repetida },
    });
}

describe('CambiarContrasena', () => {
    beforeEach(() => vi.clearAllMocks());

    it('pide solo la contraseña nueva y su repetición, sin la temporal ni párrafos', () => {
        const { container } = montar();

        expect(screen.getByRole('heading', { name: 'Contraseña nueva' })).toBeInTheDocument();
        expect(screen.queryByLabelText(/temporal/i)).not.toBeInTheDocument();
        expect(container.querySelectorAll('input[type="password"]')).toHaveLength(2);
        expect(container.textContent).not.toMatch(/Hola|Actualice su contraseña|\btu\b/);
    });

    it('marca las reglas mientras se escribe y habilita el botón solo con todo cumplido', () => {
        montar();
        expect(boton()).toBeDisabled();

        escribir('abc');
        expect(boton()).toBeDisabled();
        expect(screen.getByText('10 caracteres o más').closest('li')).toHaveAttribute(
            'data-cumplida',
            'false'
        );

        escribir('Corina2026x', 'Corina2026');
        expect(screen.getByText('10 caracteres o más').closest('li')).toHaveAttribute(
            'data-cumplida',
            'true'
        );
        expect(screen.getByText('No coincide')).toBeInTheDocument();
        expect(boton()).toBeDisabled();

        escribir('Corina2026x');
        expect(boton()).toBeEnabled();
    });

    it('cambia la contraseña temporal y habilita la entrada a la aplicación', async () => {
        const actualizar = vi.fn().mockResolvedValue(usuarioDePrueba('Docente'));
        api.post.mockResolvedValue({
            data: { message: 'La contraseña se actualizó correctamente.' },
        });
        montar(actualizar);
        escribir('Corina2026x');
        fireEvent.click(boton());

        await waitFor(() =>
            expect(api.post).toHaveBeenCalledWith('/auth/cambiar-contrasena', {
                password: 'Corina2026x',
                password_confirmation: 'Corina2026x',
            })
        );
        expect(await screen.findByText('Acceso habilitado')).toBeInTheDocument();
        expect(actualizar).toHaveBeenCalledOnce();
    });

    it('muestra bajo el campo el rechazo del servidor', async () => {
        api.post.mockRejectedValue({
            response: {
                status: 422,
                data: {
                    errors: {
                        password: ['La contraseña nueva debe ser distinta de la temporal.'],
                    },
                },
            },
        });
        montar();
        escribir('Corina2026x');
        fireEvent.click(boton());

        expect(
            await screen.findByText('La contraseña nueva debe ser distinta de la temporal.')
        ).toBeInTheDocument();
    });
});
