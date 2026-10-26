import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import Boton from '../../componentes/Boton';
import Logo from '../../componentes/Logo';
import { api } from '../../api/cliente';
import { erroresDe } from '../../api/errores';

const politicaCumplida = (valor) =>
    valor.length >= 12 &&
    /[a-z]/.test(valor) &&
    /[A-Z]/.test(valor) &&
    /\d/.test(valor) &&
    /[^A-Za-z0-9]/.test(valor);

export default function RecuperarContrasena() {
    const ubicacion = useLocation();
    const navegar = useNavigate();
    const parametros = new URLSearchParams(ubicacion.search);
    const token = parametros.get('token');
    const correoInicial = parametros.get('correo') ?? '';
    const esRestablecimiento = Boolean(token && correoInicial);
    const [usuario, setUsuario] = useState(ubicacion.state?.usuario ?? '');
    const [nueva, setNueva] = useState('');
    const [confirmacion, setConfirmacion] = useState('');
    const [mensaje, setMensaje] = useState('');
    const [error, setError] = useState('');
    const [enviando, setEnviando] = useState(false);

    async function enviar(evento) {
        evento.preventDefault();
        setMensaje('');
        setError('');
        if (!esRestablecimiento && !usuario.trim()) {
            setError('Ingrese su nombre de usuario.');
            return;
        }

        setEnviando(true);
        try {
            if (esRestablecimiento) {
                if (!politicaCumplida(nueva)) {
                    setError('Use al menos 12 caracteres, mayúscula, minúscula, número y símbolo.');
                    setEnviando(false);
                    return;
                }
                if (nueva !== confirmacion) {
                    setError('La confirmación no coincide con la nueva contraseña.');
                    setEnviando(false);
                    return;
                }
                const respuesta = await api.post('/auth/restablecer-contrasena', {
                    correo: correoInicial,
                    token,
                    password: nueva,
                    password_confirmation: confirmacion,
                });
                setMensaje(respuesta.data?.message ?? 'La contraseña se restableció.');
                return;
            }

            const respuesta = await api.post('/auth/recuperar-contrasena', {
                usuario: usuario.trim(),
            });
            setMensaje(
                respuesta.data?.message ??
                    'Si la cuenta tiene ese correo, enviaremos instrucciones.'
            );
        } catch (fallo) {
            const campos = erroresDe(fallo);
            setError(
                campos.token ??
                    campos.correo ??
                    campos.password ??
                    fallo?.response?.data?.message ??
                    'No se pudo procesar la solicitud.'
            );
        } finally {
            setEnviando(false);
        }
    }

    return (
        <div className="min-h-dvh bg-slate-100">
            <header className="flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-5 sm:px-8">
                <Logo className="h-10 w-10 shrink-0" />
                <div>
                    <span className="block text-sm font-bold text-slate-900">UMSS</span>
                    <span className="block text-[10px] uppercase tracking-wide text-slate-500">
                        Control de ingreso
                    </span>
                </div>
            </header>
            <main className="mx-auto flex min-h-[calc(100dvh-4rem)] w-full max-w-5xl items-center justify-center px-4 py-10">
                <section className="w-full max-w-md">
                    <h1 className="text-2xl font-bold text-slate-900">
                        {esRestablecimiento ? 'Restablecer contraseña' : 'Recuperar contraseña'}
                    </h1>
                    <p className="mb-6 mt-1 text-sm text-slate-600">
                        {esRestablecimiento
                            ? 'Elija una nueva contraseña segura para su cuenta.'
                            : 'Enviaremos las instrucciones al correo de contacto de su cuenta.'}
                    </p>
                    <form
                        className="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                        onSubmit={enviar}
                        noValidate
                    >
                        {!esRestablecimiento && (
                            <label className="block text-sm font-medium text-slate-700">
                                Usuario
                                <input
                                    className="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm"
                                    type="text"
                                    autoComplete="username"
                                    value={usuario}
                                    onChange={(evento) => setUsuario(evento.target.value)}
                                    required
                                />
                            </label>
                        )}
                        {esRestablecimiento && (
                            <>
                                <label className="block text-sm font-medium text-slate-700">
                                    Nueva contraseña
                                    <input
                                        className="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm"
                                        type="password"
                                        autoComplete="new-password"
                                        value={nueva}
                                        onChange={(evento) => setNueva(evento.target.value)}
                                        required
                                    />
                                </label>
                                <label className="block text-sm font-medium text-slate-700">
                                    Confirmar nueva contraseña
                                    <input
                                        className="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm"
                                        type="password"
                                        autoComplete="new-password"
                                        value={confirmacion}
                                        onChange={(evento) => setConfirmacion(evento.target.value)}
                                        required
                                    />
                                </label>
                                <p className="text-xs text-slate-600">
                                    Mínimo 12 caracteres e incluya mayúscula, minúscula, número y
                                    símbolo.
                                </p>
                            </>
                        )}
                        {mensaje && (
                            <p
                                role="status"
                                className="rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-900"
                            >
                                {mensaje}
                            </p>
                        )}
                        {error && (
                            <p role="alert" className="text-sm text-danger-700">
                                {error}
                            </p>
                        )}
                        {mensaje && esRestablecimiento ? (
                            <Boton
                                type="button"
                                className="w-full"
                                onClick={() => navegar('/login', { replace: true })}
                            >
                                Ir al inicio de sesión
                            </Boton>
                        ) : (
                            <Boton type="submit" className="w-full" disabled={enviando}>
                                {enviando
                                    ? 'Procesando…'
                                    : esRestablecimiento
                                      ? 'Restablecer contraseña'
                                      : 'Enviar instrucciones'}
                            </Boton>
                        )}
                        <div className="text-center">
                            <Link
                                className="text-sm font-medium text-primary-700 hover:underline"
                                to="/login"
                            >
                                Volver al inicio de sesión
                            </Link>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    );
}
