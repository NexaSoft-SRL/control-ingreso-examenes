import { useId, useState } from 'react';
import { Eye, EyeOff, Lock } from 'lucide-react';
import { Navigate, useNavigate } from 'react-router-dom';
import { erroresDe, estadoDe } from '../../api/errores';
import { usarSesion } from '../../sesion/SesionContexto';
import { destinoDe } from '../../navegacion/vistas';
import { plural } from '../../utiles/texto';
import Campo from '../../componentes/Campo';
import Boton from '../../componentes/Boton';
import Logo from '../../componentes/Logo';

// Acceso por correo y contraseña (POST /api/auth/login con `email`).
// Sin registro público: las cuentas las crea quien administra.
const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

const CORREO = (v) => (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v).trim()) ? null : 'No válido');

// El texto de cada rechazo del inicio de sesión.
function leerRechazo(error) {
    const estado = estadoDe(error);
    const datos = error?.response?.data ?? {};

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
                    ? `Correo o contraseña incorrectos · ${plural(restantes, 'intento restante', 'intentos restantes')}`
                    : 'Correo o contraseña incorrectos',
        };
    }
    if (estado === 422) {
        const campos = erroresDe(error);
        return {
            falloCorreo: campos.email ? 'No válido' : undefined,
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
    const [correo, setCorreo] = useState('');
    const [clave, setClave] = useState('');
    const [verClave, setVerClave] = useState(false);
    const [fallo, setFallo] = useState(null);
    const [falloCorreo, setFalloCorreo] = useState(null);
    const [bloqueo, setBloqueo] = useState(null);
    const [enviando, setEnviando] = useState(false);

    if (cuenta && !enviando) return <Navigate to={destinoDe(cuenta)} replace />;

    const limpiar = () => {
        setFallo(null);
        setFalloCorreo(null);
        setBloqueo(null);
    };

    async function iniciar(e) {
        e.preventDefault();
        if (enviando) return;
        const direccion = correo.trim();
        if (!direccion) return setFalloCorreo('Obligatorio');
        if (CORREO(direccion)) return setFalloCorreo(CORREO(direccion));
        if (!clave) return setFallo('Obligatorio');

        limpiar();
        setEnviando(true);
        try {
            const entrante = await entrar(direccion, clave);
            return navegar(destinoDe(entrante), { replace: true });
        } catch (error) {
            const rechazo = leerRechazo(error);
            setFallo(rechazo.fallo ?? null);
            setFalloCorreo(rechazo.falloCorreo ?? null);
            setBloqueo(rechazo.bloqueo ?? null);
            return setEnviando(false);
        }
    }

    return (
        <div className="flex min-h-dvh items-center justify-center bg-slate-100 px-4 py-10">
            <div className="w-full max-w-sm">
                <div className="mb-8 flex flex-col items-center text-center">
                    <Logo className="mb-3 h-16 w-16 drop-shadow-sm" />
                    <h1 className="text-lg font-semibold text-slate-900">Control de ingreso</h1>
                    <p className="mt-1 text-sm text-slate-600">Cuenta institucional</p>
                </div>

                <form
                    className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    onSubmit={iniciar}
                    noValidate
                >
                    <Campo
                        etiqueta="Correo"
                        requerido
                        type="email"
                        inputMode="email"
                        placeholder="usuario@umss.edu.bo"
                        value={correo}
                        autoComplete="username"
                        autoCapitalize="none"
                        spellCheck={false}
                        validar={CORREO}
                        error={falloCorreo ?? undefined}
                        onChange={(e) => {
                            setCorreo(e.target.value);
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
                                aria-label={verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'}
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
                        Iniciar sesión
                    </Boton>
                </form>
            </div>
        </div>
    );
}
