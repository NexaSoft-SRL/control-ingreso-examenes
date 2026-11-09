import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { Route, Routes, useLocation } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import Login from './Login';
import { api } from '../../api/cliente';
import { errorHttp, renderConSesion, simularApi, usuarioDePrueba } from '../../test/apoyo';

vi.mock('../../api/cliente');

function Donde() {
    return <p>Estoy en {useLocation().pathname}</p>;
}

function montar({ entrar = vi.fn(), usuario = null, ruta = '/login' } = {}) {
    return renderConSesion(
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="*" element={<Donde />} />
        </Routes>,
        { usuario, ruta, sesion: { entrar } }
    );
}

const escribir = (campo, valor) => fireEvent.change(campo, { target: { value: valor } });
const campoUsuario = () => screen.getByLabelText(/^Usuario/);
const campoClave = () => screen.getByLabelText(/^Contraseña/);
const enviar = () => fireEvent.click(screen.getByRole('button', { name: 'Iniciar sesión' }));

function intentar(usuario = 'leticia.blanco', clave = 'Secreta123') {
    escribir(campoUsuario(), usuario);
    escribir(campoClave(), clave);
    enviar();
}

describe('Login', () => {
    it('pide usuario y contraseña, sin correo ni andamiaje', () => {
        montar();

        expect(screen.getByRole('heading', { name: 'Control de exámenes' })).toBeInTheDocument();
        expect(campoUsuario()).toHaveAttribute('autocomplete', 'username');
        expect(campoUsuario()).toHaveValue('');
        expect(campoClave()).toHaveAttribute('type', 'password');
        expect(screen.queryByText(/Simular estado/)).not.toBeInTheDocument();
        expect(screen.queryByText(/correo/i)).not.toBeInTheDocument();
    });

    it('enlaza al acceso con código de aula y al de estudiantes', () => {
        montar();
        expect(screen.getByRole('link', { name: 'Código de aula' })).toHaveAttribute(
            'href',
            '/acceso-aula'
        );
        expect(screen.getByRole('link', { name: 'Soy estudiante' })).toHaveAttribute(
            'href',
            '/estudiante/ingreso'
        );
    });

    it('valida antes de llamar al servidor', () => {
        const entrar = vi.fn();
        montar({ entrar });

        enviar();
        expect(screen.getByText('Obligatorio')).toBeInTheDocument();

        escribir(campoUsuario(), 'leticia/blanco');
        enviar();
        expect(
            screen.getByText('Solo letras y números, separados por un espacio')
        ).toBeInTheDocument();

        escribir(campoUsuario(), 'leticia.blanco');
        enviar();
        expect(screen.getByText('Obligatorio')).toBeInTheDocument();
        expect(entrar).not.toHaveBeenCalled();
    });

    it.each([
        ['Administrador', '/periodo'],
        ['Docente', '/examenes'],
        ['Auxiliar', '/ingreso'],
    ])('el %s entra a su pantalla', async (rol, destino) => {
        const entrar = vi.fn(() => Promise.resolve(usuarioDePrueba(rol)));
        montar({ entrar });
        intentar();

        expect(await screen.findByText(`Estoy en ${destino}`)).toBeInTheDocument();
        expect(entrar).toHaveBeenCalledWith('leticia.blanco', 'Secreta123');
    });

    it('acepta el usuario escrito como nombre y apellido', async () => {
        const entrar = vi.fn(() => Promise.resolve(usuarioDePrueba('Docente')));
        montar({ entrar });
        expect(campoUsuario()).toHaveAttribute('placeholder', 'nombre apellido');
        intentar('Letícia Blanco');

        expect(await screen.findByText('Estoy en /examenes')).toBeInTheDocument();
        expect(entrar).toHaveBeenCalledWith('leticia blanco', 'Secreta123');
    });

    it('con contraseña temporal va al primer ingreso', async () => {
        const cuenta = usuarioDePrueba('Docente', { debe_cambiar_contrasena: true });
        montar({ entrar: vi.fn(() => Promise.resolve(cuenta)) });
        intentar('corina.flores', 'Fa7-Kmq4-Ru9');

        expect(await screen.findByText('Estoy en /primer-ingreso')).toBeInTheDocument();
    });

    it('muestra el error con los intentos restantes', async () => {
        const rechazo = errorHttp(401, {
            message: 'Usuario o contraseña incorrectos.',
            intentos_restantes: 3,
        });
        montar({ entrar: vi.fn(() => Promise.reject(rechazo)) });
        intentar();

        expect(
            await screen.findByText('Usuario o contraseña incorrectos · 3 intentos restantes')
        ).toBeInTheDocument();
        expect(campoClave()).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByRole('button', { name: 'Iniciar sesión' })).toBeEnabled();

        escribir(campoClave(), 'otra');
        expect(screen.queryByText(/intentos restantes/)).not.toBeInTheDocument();
    });

    it('escribe el último intento en singular y omite la cifra si la cuenta no existe', async () => {
        const entrar = vi
            .fn()
            .mockRejectedValueOnce(errorHttp(401, { message: '…', intentos_restantes: 1 }))
            .mockRejectedValueOnce(
                errorHttp(401, { message: 'Usuario o contraseña incorrectos.' })
            );
        montar({ entrar });

        intentar();
        expect(
            await screen.findByText('Usuario o contraseña incorrectos · 1 intento restante')
        ).toBeInTheDocument();
        intentar('nadie.existe');
        expect(await screen.findByText('Usuario o contraseña incorrectos')).toBeInTheDocument();
    });

    it('muestra el bloqueo con sus minutos', async () => {
        montar({
            entrar: vi.fn(() =>
                Promise.reject(errorHttp(423, { message: 'Cuenta bloqueada.', minutos: 15 }))
            ),
        });
        intentar();

        expect(await screen.findByRole('alert')).toHaveTextContent('Cuenta bloqueada · 15 minutos');
    });

    it('una cuenta desactivada no lleva minutos', async () => {
        montar({
            entrar: vi.fn(() => Promise.reject(errorHttp(423, { message: 'Cuenta bloqueada.' }))),
        });
        intentar();

        const alerta = await screen.findByRole('alert');
        expect(alerta).toHaveTextContent('Cuenta bloqueada');
        expect(alerta).not.toHaveTextContent('minuto');
    });

    it('avisa de la contraseña temporal vencida y de un fallo del servidor', async () => {
        const entrar = vi
            .fn()
            .mockRejectedValueOnce(errorHttp(401, { message: '…', codigo: 'TEMPORAL_VENCIDA' }))
            .mockRejectedValueOnce(errorHttp(500));
        montar({ entrar });

        intentar();
        expect(await screen.findByText('Contraseña temporal vencida')).toBeInTheDocument();
        intentar();
        expect(await screen.findByText('No se pudo iniciar sesión')).toBeInTheDocument();
    });

    it('recuperar contraseña pide el usuario y envía el enlace', async () => {
        simularApi(api, {
            'POST /auth/contrasena/olvide': { message: 'Si la cuenta tiene un correo…' },
        });
        montar();
        escribir(campoUsuario(), 'Leticia.Blanco');

        fireEvent.click(screen.getByRole('button', { name: 'Recuperar contraseña' }));
        const dialogo = within(screen.getByRole('dialog', { name: 'Recuperar contraseña' }));
        expect(dialogo.getByLabelText(/Usuario/)).toHaveValue('Leticia.Blanco');
        // Quien no tiene correo sabe a quién acudir.
        expect(
            dialogo.getByText('Si no llega, pida una contraseña temporal a la administración.')
        ).toBeInTheDocument();
        fireEvent.click(dialogo.getByRole('button', { name: 'Enviar enlace' }));

        expect(await screen.findByRole('status')).toHaveTextContent(
            'Si leticia.blanco tiene correo registrado, le llegará el enlace. Si no llega, pida una contraseña temporal a la administración.'
        );
        expect(api.post).toHaveBeenCalledWith('/auth/contrasena/olvide', {
            usuario: 'leticia.blanco',
        });
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('recuperar no envía sin usuario', () => {
        simularApi(api, {});
        montar();

        fireEvent.click(screen.getByRole('button', { name: 'Recuperar contraseña' }));
        const dialogo = within(screen.getByRole('dialog'));
        // Al abrir no hay error y el foco está en el campo.
        expect(dialogo.queryByText('Obligatorio')).not.toBeInTheDocument();
        expect(dialogo.getByLabelText(/Usuario/)).toHaveFocus();
        fireEvent.click(dialogo.getByRole('button', { name: 'Enviar enlace' }));

        expect(dialogo.getByText('Obligatorio')).toBeInTheDocument();
        expect(api.post).not.toHaveBeenCalled();
    });

    it('con sesión abierta va directo a la entrada', () => {
        montar({ usuario: usuarioDePrueba('Administrador') });
        expect(screen.getByText('Estoy en /periodo')).toBeInTheDocument();
    });

    it('muestra el aviso con que llega desde el restablecimiento', async () => {
        renderConSesion(<Login />, {
            usuario: null,
            ruta: { pathname: '/login', state: { aviso: 'Contraseña actualizada' } },
        });
        await waitFor(() =>
            expect(screen.getByRole('status')).toHaveTextContent('Contraseña actualizada')
        );
    });
});
