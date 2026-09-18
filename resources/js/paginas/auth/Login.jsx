import React from 'react';
import PropTypes from 'prop-types';
import { Eye, EyeOff, ShieldCheck } from 'lucide-react';

/**
 * Pantalla de inicio de sesion (HU-01). Consume el endpoint ya construido en
 * el backend: POST /api/auth/login, con {email, password}.
 *
 * No hay registro publico ni recuperacion de contrasena automatica todavia
 * (no existe el endpoint): el enlace "Olvidaste tu contrasena" solo explica
 * como proceder mientras tanto, no dispara ninguna llamada.
 */
function Login({ onAutenticado }) {
    const [correo, setCorreo] = React.useState('');
    const [contrasena, setContrasena] = React.useState('');
    const [mostrarContrasena, setMostrarContrasena] = React.useState(false);
    const [cargando, setCargando] = React.useState(false);
    const [error, setError] = React.useState(null);
    const [ayudaContrasena, setAyudaContrasena] = React.useState(false);

    async function manejarEnvio(evento) {
        evento.preventDefault();
        setError(null);
        setCargando(true);

        try {
            const respuesta = await window.axios.post('/api/auth/login', {
                email: correo,
                password: contrasena,
            });

            onAutenticado(respuesta.data.user);
        } catch (excepcion) {
            const estado = excepcion.response?.status;

            if (estado === 401) {
                setError('Credenciales incorrectas.');
            } else if (estado === 422) {
                setError('Ingresa un correo y una contraseña válidos.');
            } else {
                setError('No se pudo conectar con el sistema. Intenta de nuevo.');
            }
        } finally {
            setCargando(false);
        }
    }

    return (
        <div className="flex min-h-screen w-full flex-col items-center justify-center gap-5 bg-slate-100 px-4 py-10 font-sans sm:px-6">
            <div className="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl sm:p-10">
                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-blue-50">
                    <ShieldCheck className="h-7 w-7 text-blue-600" strokeWidth={2} />
                </div>

                <p className="text-center text-xs font-bold tracking-wide text-blue-600">
                    FCYT · UMSS
                </p>

                <h1 className="mt-2 text-center text-xl font-bold text-slate-900 sm:text-2xl">
                    Control de ingreso a exámenes
                </h1>

                <p className="mt-1.5 mb-6 text-center text-sm text-slate-500">
                    Ingresá con tu cuenta institucional
                </p>

                <form onSubmit={manejarEnvio} noValidate>
                    <div className="mb-4 text-left">
                        <label
                            className="mb-1.5 block text-xs font-bold tracking-wide text-slate-700 uppercase"
                            htmlFor="correo"
                        >
                            Correo
                        </label>

                        <input
                            id="correo"
                            type="email"
                            className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            placeholder="usuario@fcyt.umss.edu.bo"
                            value={correo}
                            onChange={(evento) => setCorreo(evento.target.value)}
                            autoComplete="username"
                            required
                        />
                    </div>

                    <div className="mb-4 text-left">
                        <label
                            className="mb-1.5 block text-xs font-bold tracking-wide text-slate-700 uppercase"
                            htmlFor="contrasena"
                        >
                            Contraseña
                        </label>

                        <div className="relative flex items-center">
                            <input
                                id="contrasena"
                                type={mostrarContrasena ? 'text' : 'password'}
                                className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 pr-10 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                placeholder="••••••••"
                                value={contrasena}
                                onChange={(evento) => setContrasena(evento.target.value)}
                                autoComplete="current-password"
                                required
                            />

                            <button
                                type="button"
                                className="absolute right-3 flex text-slate-400 hover:text-slate-600"
                                onClick={() => setMostrarContrasena((valor) => !valor)}
                                aria-label={
                                    mostrarContrasena ? 'Ocultar contraseña' : 'Mostrar contraseña'
                                }
                            >
                                {mostrarContrasena ? (
                                    <EyeOff className="h-4 w-4" strokeWidth={1.75} />
                                ) : (
                                    <Eye className="h-4 w-4" strokeWidth={1.75} />
                                )}
                            </button>
                        </div>
                    </div>

                    {error && (
                        <p role="alert" className="mb-3 text-left text-sm text-red-600">
                            {error}
                        </p>
                    )}

                    <button
                        type="submit"
                        className="mt-1 w-full rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70"
                        disabled={cargando}
                    >
                        {cargando ? 'Ingresando…' : 'Iniciar sesión'}
                    </button>
                </form>

                <button
                    type="button"
                    className="mt-4 w-full text-sm font-semibold text-blue-600 hover:text-blue-700"
                    onClick={() => setAyudaContrasena((valor) => !valor)}
                >
                    ¿Olvidaste tu contraseña?
                </button>

                {ayudaContrasena && (
                    <p className="mx-auto mt-2.5 max-w-xs text-center text-xs text-slate-500">
                        Pídele al Administrador que te la restablezca; todavía no hay recuperación
                        automática.
                    </p>
                )}
            </div>

            <p className="text-center text-xs leading-relaxed text-slate-400">
                Sin registro público. Las cuentas las crea el Administrador.
                <br />
                Bloqueo tras 5 intentos fallidos.
            </p>
        </div>
    );
}

Login.propTypes = {
    onAutenticado: PropTypes.func.isRequired,
};

export default Login;
