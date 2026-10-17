import { fireEvent, screen, waitFor } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import Login from './Login';
import { errorHttp, renderConSesion, usuarioDePrueba } from '../../test/apoyo';

function Donde() {
    return <p>Estoy en {useLocation().pathname}</p>;
}

function montar({ usuario = null, entrar = vi.fn() } = {}) {
    return renderConSesion(
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="*" element={<Donde />} />
        </Routes>,
        { usuario, ruta: '/login', sesion: { entrar } }
    );
}

function completar(usuario, contrasena) {
    fireEvent.change(screen.getByLabelText(/Usuario/), { target: { value: usuario } });
    fireEvent.change(screen.getByLabelText(/^Contraseña/), { target: { value: contrasena } });
    fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));
}

describe('Login', () => {
    it('muestra el logo, el nombre y el formulario por usuario', () => {
        montar();

        expect(screen.getByRole('img', { name: 'Control de ingreso' })).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { level: 1, name: 'Ingreso al sistema' })
        ).toBeInTheDocument();
        expect(screen.getByText('Sistema Institucional de Verificación')).toBeInTheDocument();
        expect(screen.getByLabelText(/Usuario/)).toHaveAttribute('type', 'text');
        expect(screen.getByLabelText(/^Contraseña/)).toHaveAttribute('type', 'password');
    });

    it('no trae textos explicativos ni voseo', () => {
        const { container } = montar();
        expect(container.textContent).not.toMatch(/ingresá|tenés|podés|\bvos\b/i);
        expect(container.querySelectorAll('p')).toHaveLength(2);
    });

    it('entra con el usuario y lleva a la entrada de la cuenta', async () => {
        const docente = usuarioDePrueba('Docente');
        const entrar = vi.fn().mockResolvedValue(docente);
        montar({ entrar });
        completar(' leticia.blanco ', 'clave-correcta');

        expect(await screen.findByText('Estoy en /examenes')).toBeInTheDocument();
        expect(entrar).toHaveBeenCalledWith('leticia.blanco', 'clave-correcta');
    });

    it('una cuenta sin pantallas va a /sin-acceso', async () => {
        const entrar = vi.fn().mockResolvedValue(usuarioDePrueba('Auxiliar', { permisos: [] }));
        montar({ entrar });
        completar('auxiliar@umss.edu.bo', 'clave-correcta');

        expect(await screen.findByText('Estoy en /sin-acceso')).toBeInTheDocument();
    });

    it('con sesión abierta no muestra el formulario', () => {
        montar({ usuario: usuarioDePrueba('Administrador') });
        expect(screen.getByText('Estoy en /periodo')).toBeInTheDocument();
    });

    it('muestra el rechazo de credenciales', async () => {
        const entrar = vi
            .fn()
            .mockRejectedValue(errorHttp(401, { message: 'Credenciales incorrectas.' }));
        montar({ entrar });
        completar('rodrigo', 'clave-incorrecta');

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Usuario o contraseña incorrectos'
        );
        expect(screen.getByRole('button', { name: 'Iniciar sesión' })).toBeEnabled();
    });

    it('muestra el bloqueo de la cuenta', async () => {
        const entrar = vi.fn().mockRejectedValue(errorHttp(423, { minutos: 15 }));
        montar({ entrar });
        completar('rodrigo', 'clave');

        expect(await screen.findByRole('alert')).toHaveTextContent('Cuenta bloqueada · 15 minutos');
    });

    it('marca el campo que rechaza la validación del servidor', async () => {
        const entrar = vi
            .fn()
            .mockRejectedValue(
                errorHttp(422, { errors: { usuario: ['El usuario no es válido.'] } })
            );
        montar({ entrar });
        completar('rodrigo', 'clave');

        await waitFor(() =>
            expect(screen.getByLabelText(/Usuario/)).toHaveAttribute('aria-invalid', 'true')
        );
        expect(screen.getByText('No válido')).toBeInTheDocument();
    });

    it('sin conexión informa que no se pudo iniciar sesión', async () => {
        const entrar = vi.fn().mockRejectedValue(new Error('network'));
        montar({ entrar });
        completar('rodrigo', 'clave');

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo iniciar sesión');
    });

    it('no envía sin usuario ni sin contraseña', () => {
        const entrar = vi.fn();
        montar({ entrar });

        fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));
        expect(screen.getByText('Obligatorio')).toBeInTheDocument();
        fireEvent.change(screen.getByLabelText(/Usuario/), { target: { value: 'rodrigo' } });
        fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));
        expect(entrar).not.toHaveBeenCalled();
    });

    it('alterna entre ocultar y mostrar la contraseña', () => {
        montar();
        const campo = screen.getByLabelText(/^Contraseña/);

        fireEvent.click(screen.getByRole('button', { name: 'Mostrar contraseña' }));
        expect(campo).toHaveAttribute('type', 'text');

        fireEvent.click(screen.getByRole('button', { name: 'Ocultar contraseña' }));
        expect(campo).toHaveAttribute('type', 'password');
    });
});
