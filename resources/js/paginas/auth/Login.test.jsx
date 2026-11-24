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
        expect(screen.getByRole('link', { name: 'Personal de control' })).toHaveAttribute(
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

        // Lo que el usuario no admite ni entra al campo, y se avisa.
        escribir(campoUsuario(), 'leticia/blanco');
        expect(campoUsuario()).toHaveValue('leticiablanco');
        expect(screen.getByText('Solo letras y números')).toBeInTheDocument();

        // Lo que entra pero no tiene forma de usuario se rechaza antes de enviar.
        escribir(campoUsuario(), '.leticia');
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
            await screen.findByText('Usuario o contraseña incorrectos · le quedan 3 intentos')
        ).toBeInTheDocument();
        expect(campoClave()).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByRole('button', { name: 'Iniciar sesión' })).toBeEnabled();

        escribir(campoClave(), 'otra');
        expect(screen.queryByText(/le quedan/)).not.toBeInTheDocument();
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
            await screen.findByText(
                'Usuario o contraseña incorrectos · último intento antes del bloqueo'
            )
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

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Cuenta bloqueada por intentos fallidos · se habilita sola en 15 minutos'
        );
    });

    it('una cuenta desactivada no lleva minutos', async () => {
        montar({
            entrar: vi.fn(() => Promise.reject(errorHttp(423, { message: 'Cuenta bloqueada.' }))),
        });
        intentar();

        const alerta = await screen.findByRole('alert');
        expect(alerta).toHaveTextContent(
            'Cuenta bloqueada · pida que la habiliten en la administración'
        );
        expect(alerta).not.toHaveTextContent('minuto');
    });

    it('avisa de la contraseña temporal vencida y de un fallo del servidor', async () => {
        const entrar = vi
            .fn()
            .mockRejectedValueOnce(errorHttp(401, { message: '…', codigo: 'TEMPORAL_VENCIDA' }))
            .mockRejectedValueOnce(errorHttp(500));
        montar({ entrar });

        intentar();
        expect(
            await screen.findByText('Contraseña temporal vencida · pida otra a la administración')
        ).toBeInTheDocument();
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

        // La confirmación queda dentro del diálogo hasta cerrarlo.
        const confirmacion = await screen.findByRole('status');
        expect(confirmacion).toHaveTextContent('Enlace enviado');
        expect(confirmacion).toHaveTextContent('Revise su correo; vale 60 minutos.');
        expect(confirmacion).toHaveTextContent(
            'Si no llega en unos minutos, mire en no deseados o pida una contraseña temporal a la administración.'
        );
        expect(api.post).toHaveBeenCalledWith('/auth/contrasena/olvide', {
            usuario: 'leticia.blanco',
        });
        expect(screen.getByRole('dialog', { name: 'Recuperar contraseña' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Enviar enlace' })).not.toBeInTheDocument();

        // El botón del pie es el último de los que se llaman «Cerrar».
        fireEvent.click(screen.getAllByRole('button', { name: 'Cerrar' }).at(-1));
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

    it('llega con el cuadro de recuperar abierto desde un enlace vencido', async () => {
        renderConSesion(<Login />, {
            usuario: null,
            ruta: { pathname: '/login', state: { recuperar: true } },
        });

        expect(await screen.findByRole('dialog', { name: 'Recuperar contraseña' })).toBeVisible();
    });

    it('el usuario no pasa de 60 caracteres, como en el servidor', () => {
        montar();

        expect(campoUsuario()).toHaveAttribute('maxlength', '60');
    });
});
