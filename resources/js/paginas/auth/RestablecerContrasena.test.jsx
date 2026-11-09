import { fireEvent, screen } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import RestablecerContrasena from './RestablecerContrasena';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi } from '../../test/apoyo';

vi.mock('../../api/cliente');

function Donde() {
    const { pathname, state } = useLocation();
    return (
        <p>
            Estoy en {pathname} · {state?.aviso}
        </p>
    );
}

function montar(ruta = '/restablecer-contrasena?token=abc123&usuario=leticia.blanco') {
    return renderConSesion(
        <Routes>
            <Route path="/restablecer-contrasena" element={<RestablecerContrasena />} />
            <Route path="*" element={<Donde />} />
        </Routes>,
        { usuario: null, ruta }
    );
}

const escribir = (campo, valor) => fireEvent.change(campo, { target: { value: valor } });

function completar(valor = 'Abcdefghi1') {
    escribir(screen.getByLabelText(/^Contraseña/), valor);
    escribir(screen.getByLabelText(/Repetir contraseña/), valor);
    fireEvent.click(screen.getByRole('button', { name: 'Guardar' }));
}

describe('RestablecerContrasena', () => {
    it('envía el usuario y el token del enlace con la contraseña nueva', async () => {
        simularApi(api, {
            'POST /auth/contrasena/restablecer': { message: 'Contraseña actualizada.' },
        });
        montar();

        expect(screen.getByText('Restablecimiento · leticia.blanco')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Guardar' })).toBeDisabled();
        completar();

        expect(
            await screen.findByText('Estoy en /login · Contraseña actualizada')
        ).toBeInTheDocument();
        expect(api.post).toHaveBeenCalledWith('/auth/contrasena/restablecer', {
            usuario: 'leticia.blanco',
            token: 'abc123',
            contrasena_nueva: 'Abcdefghi1',
            contrasena_nueva_confirmation: 'Abcdefghi1',
        });
    });

    it('con el enlace vencido lo dice y ofrece volver al acceso', async () => {
        simularApi(api, {
            'POST /auth/contrasena/restablecer': errorHttp(422, {
                message: 'El enlace no es válido o ya venció. Solicite uno nuevo.',
            }),
        });
        montar();
        completar();

        expect(await screen.findByRole('alert')).toHaveTextContent('Enlace no válido o vencido');
        expect(screen.queryByRole('button', { name: 'Guardar' })).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Iniciar sesión' })).toHaveAttribute(
            'href',
            '/login'
        );
    });

    it('un rechazo de la contraseña queda bajo su campo', async () => {
        simularApi(api, {
            'POST /auth/contrasena/restablecer': errorHttp(422, {
                message: '…',
                errors: { contrasena_nueva: ['Mínimo 10 caracteres'] },
            }),
        });
        montar();
        completar();

        expect(await screen.findByText('Mínimo 10 caracteres')).toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('sin token o sin usuario el enlace no sirve', () => {
        montar('/restablecer-contrasena?usuario=leticia.blanco');

        expect(screen.getByRole('alert')).toHaveTextContent('Enlace no válido o vencido');
        expect(screen.queryByLabelText(/^Contraseña/)).not.toBeInTheDocument();
    });
});
