import { useId, useState } from 'react';
import { Eye, EyeOff, Lock } from 'lucide-react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { erroresDe, estadoDe } from '../../api/errores';
import { usarSesion } from '../../sesion/SesionContexto';
import { destinoDe } from '../../navegacion/vistas';
import { plural } from '../../utiles/texto';
import Campo from '../../componentes/Campo';
import Boton from '../../componentes/Boton';
import Logo from '../../componentes/Logo';

// Acceso por nombre de usuario; el correo solo se utiliza como contacto.
const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// El texto de cada rechazo del inicio de sesión.
function leerRechazo(error) {
    const estado = estadoDe(error);
    const datos = error?.response?.data ?? {};

    if (datos.code === 'TEMPORARY_PASSWORD_EXPIRED') {
        return { fallo: 'Contraseña temporal vencida' };
    }
    // También la cuenta desactivada (sin minutos), solo con la contraseña correcta.
    if (estado === 423) {
        return {
            bloqueo: datos.minutos
                ? `Cuenta bloqueada · ${plural(datos.minutos, 'minuto')}`
                : 'Cuenta bloqueada',
        };
    }
    if (estado === 401) {
        const restantes = datos.intentos_restantes;
        return {
            fallo:
                typeof restantes === 'number'
                    ? `Usuario o contraseña incorrectos · ${plural(restantes, 'intento restante', 'intentos restantes')}`
                    : 'Usuario o contraseña incorrectos',
        };
    }
    if (estado === 422) {
        const campos = erroresDe(error);
        return {
            falloUsuario: campos.usuario ? 'No válido' : undefined,
            fallo: campos.password ? 'Obligatorio' : undefined,
        };
    }
    if (estado === 429) return { fallo: 'Demasiados intentos' };
    return { fallo: 'No se pudo iniciar sesión' };
}

export default function Login() {
    const navegar = useNavigate();
    const { usuario: cuenta, entrar } = usarSesion();
    const idClave = useId();
    const [usuario, setUsuario] = useState('');
    const [clave, setClave] = useState('');
    const [verClave, setVerClave] = useState(false);
    const [fallo, setFallo] = useState(null);
    const [falloUsuario, setFalloUsuario] = useState(null);
    const [bloqueo, setBloqueo] = useState(null);
    const [enviando, setEnviando] = useState(false);

    if (cuenta && !enviando) {
        return (
            <Navigate
                to={cuenta.debe_cambiar_contrasena ? '/cambiar-contrasena' : destinoDe(cuenta)}
                replace
            />
        );
    }

    const limpiar = () => {
        setFallo(null);
        setFalloUsuario(null);
        setBloqueo(null);
    };

    async function iniciar(e) {
        e.preventDefault();
        if (enviando) return;
        const nombreUsuario = usuario.trim();
        if (!nombreUsuario) return setFalloUsuario('Obligatorio');
        if (!clave) return setFallo('Obligatorio');

        limpiar();
        setEnviando(true);
        try {
            const entrante = await entrar(nombreUsuario, clave);
            return navegar(
                entrante?.debe_cambiar_contrasena ? '/cambiar-contrasena' : destinoDe(entrante),
                { replace: true }
            );
        } catch (error) {
            const rechazo = leerRechazo(error);
            setFallo(rechazo.fallo ?? null);
            setFalloUsuario(rechazo.falloUsuario ?? null);
            setBloqueo(rechazo.bloqueo ?? null);
            return setEnviando(false);
        }
    }

    return (
        <div className="min-h-dvh bg-slate-100">
            <header className="flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-5 sm:px-8">
                <Logo className="h-10 w-10 shrink-0" />
                <div className="border-r border-slate-200 pr-4">
                    <span className="block text-sm font-bold text-slate-900">UMSS</span>
                    <span className="block text-[10px] uppercase tracking-wide text-slate-500">
                        Control de ingreso
                    </span>
                </div>
                <span className="hidden text-sm font-medium text-slate-600 sm:block">
                    Sistema Institucional de Verificación
                </span>
                <span className="ml-auto text-sm font-semibold text-primary-700">
                    Iniciar sesión
                </span>
            </header>

            <main className="mx-auto flex min-h-[calc(100dvh-4rem)] w-full max-w-5xl items-start justify-center px-4 py-10 sm:items-center sm:px-8">
                <div className="w-full max-w-md">
                    <div className="mb-6 text-center sm:text-left">
                        <h1 className="text-2xl font-bold text-slate-900">
                            Control de ingreso a exámenes
                        </h1>
                        <p className="mt-1 text-sm text-slate-600">Cuenta institucional</p>
                    </div>

                    <form
                        className="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                        onSubmit={iniciar}
                        noValidate
                    >
                        <Campo
                            etiqueta="Usuario"
                            requerido
                            type="text"
                            placeholder="Ingrese su usuario"
                            value={usuario}
                            autoComplete="username"
                            autoCapitalize="none"
                            spellCheck={false}
                            error={falloUsuario ?? undefined}
                            onChange={(e) => {
                                setUsuario(e.target.value);
                                limpiar();
                            }}
                        />

                        <div>
                            <label
                                htmlFor={idClave}
                                className="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Contraseña
                                <span className="ml-0.5 text-danger-600" aria-hidden="true">
                                    *
                                </span>
                            </label>
                            <div
                                className={`flex overflow-hidden rounded-lg border bg-white focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/40 ${
                                    fallo ? 'border-danger-600' : 'border-slate-300'
                                }`}
                            >
                                <input
                                    id={idClave}
                                    type={verClave ? 'text' : 'password'}
                                    className="min-h-11 min-w-0 flex-1 bg-transparent px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-500 focus:outline-none"
                                    placeholder="••••••••"
                                    value={clave}
                                    autoComplete="current-password"
                                    aria-required="true"
                                    aria-invalid={fallo ? 'true' : undefined}
                                    onChange={(e) => {
                                        setClave(e.target.value);
                                        limpiar();
                                    }}
                                />
                                <button
                                    type="button"
                                    className={`flex h-11 w-11 shrink-0 items-center justify-center text-slate-500 hover:text-slate-700 ${FOCO}`}
                                    onClick={() => setVerClave((v) => !v)}
                                    aria-label={
                                        verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'
                                    }
                                    aria-pressed={verClave}
                                >
                                    {verClave ? (
                                        <EyeOff className="h-4 w-4" strokeWidth={2} />
                                    ) : (
                                        <Eye className="h-4 w-4" strokeWidth={2} />
                                    )}
                                </button>
                            </div>
                            {fallo && (
                                <p role="alert" className="mt-1.5 text-sm text-danger-600">
                                    {fallo}
                                </p>
                            )}
                        </div>

                        {bloqueo && (
                            <div
                                role="alert"
                                className="flex items-start gap-2 rounded-lg bg-danger-50 px-3.5 py-3 text-sm text-danger-700"
                            >
                                <Lock className="mt-0.5 h-4 w-4 shrink-0" strokeWidth={2} />
                                <p>{bloqueo}</p>
                            </div>
                        )}

                        <Boton type="submit" className="w-full" disabled={enviando}>
                            {enviando ? 'Ingresando…' : 'Iniciar sesión'}
                        </Boton>
                        <div className="space-y-2 text-center text-xs text-slate-600">
                            <div>
                                <Link
                                    className="text-sm font-medium text-primary-700 hover:underline"
                                    to="/recuperar-contrasena"
                                    state={{ usuario: usuario.trim() }}
                                >
                                    ¿Olvidó su contraseña?
                                </Link>
                            </div>
                            <div className="border-t border-slate-200 pt-3">
                                <Link
                                    to="/acceso-aula"
                                    className="font-medium text-primary-600 hover:text-primary-700 hover:underline"
                                >
                                    Ingresar con código de aula
                                </Link>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    );
}
