import { useId, useState } from 'react';
import { Eye, EyeOff, GraduationCap, KeyRound, Lock } from 'lucide-react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import { usarSesion } from '../../sesion/SesionContexto';
import { destinoDe } from '../../navegacion/vistas';
import { plural } from '../../utiles/texto';
import Campo from '../../componentes/Campo';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import FondoAcceso from '../../componentes/FondoAcceso';
import Logo from '../../componentes/Logo';
import { useAviso } from '../../componentes/Aviso';
import usarTituloPestana from '../../utiles/usarTituloPestana';

// Acceso por nombre de usuario; el correo solo se utiliza como contacto.
const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// Se valida lo que se envía: sin los espacios de los extremos.
const SOLO_USUARIO = (v) =>
    /^[a-z0-9._-]+$/i.test(String(v).trim()) ? null : 'Solo letras, números, puntos y guiones';

// Las otras dos puertas de entrada: el estudiante y quien controla con el
// código de un aula.
const ENTRADAS = [
    ['/estudiante/ingreso', 'Soy estudiante', GraduationCap, 'bg-primary-100 text-primary-700'],
    ['/acceso-aula', 'Código de aula', KeyRound, 'bg-warning-100 text-warning-800'],
];

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
    usarTituloPestana('Iniciar sesión');
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
    const [recuperando, setRecuperando] = useState(false);
    // «Obligatorio» aparece al intentar enviar, no apenas se abre el diálogo.
    const [intentoEnlace, setIntentoEnlace] = useState(false);
    const [usuarioRecuperar, setUsuarioRecuperar] = useState('');
    const [errorRecuperar, setErrorRecuperar] = useState(null);
    const [enviandoEnlace, setEnviandoEnlace] = useState(false);
    const [aviso, avisar] = useAviso();

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
        if (SOLO_USUARIO(nombreUsuario)) return setFalloUsuario(SOLO_USUARIO(nombreUsuario));
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

    async function enviarEnlace(e) {
        e.preventDefault();
        setIntentoEnlace(true);
        const nombre = usuarioRecuperar.trim();
        if (!nombre || SOLO_USUARIO(nombre) || enviandoEnlace) return;
        setEnviandoEnlace(true);
        setErrorRecuperar(null);
        try {
            const respuesta = await api.post('/auth/recuperar-contrasena', { usuario: nombre });
            setRecuperando(false);
            // No dice si la cuenta existe ni si tiene correo: el servidor tampoco.
            avisar(
                String(
                    respuesta.data?.message ??
                        `Si ${nombre} tiene correo registrado, le llegará el enlace`
                ).replace(/\.$/, '')
            );
        } catch (error) {
            const campos = erroresDe(error);
            if (campos.usuario) setErrorRecuperar(campos.usuario);
            else avisar(mensajeDe(error, 'No se pudo enviar el enlace'), 'error');
        } finally {
            setEnviandoEnlace(false);
        }
    }

    return (
        <FondoAcceso>
            <div className="w-full max-w-sm">
                <div className="mb-8 flex flex-col items-center text-center">
                    <Logo className="mb-3 h-16 w-16 drop-shadow-sm" />
                    <h1 className="text-lg font-semibold text-slate-900">
                        Control de ingreso a exámenes
                    </h1>
                    <p className="mt-1 text-sm text-slate-600">Cuenta institucional</p>
                </div>

                <form
                    className="space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-lg shadow-primary-900/5 backdrop-blur"
                    onSubmit={iniciar}
                    noValidate
                >
                    <Campo
                        etiqueta="Usuario"
                        requerido
                        type="text"
                        placeholder="nombreapellido"
                        autoFocus
                        validar={SOLO_USUARIO}
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

                    <Boton type="submit" className="w-full" cargando={enviando}>
                        Iniciar sesión
                    </Boton>

                    <button
                        type="button"
                        className={`min-h-11 w-full rounded-lg text-center text-sm font-medium text-primary-700 hover:underline ${FOCO}`}
                        onClick={() => {
                            setUsuarioRecuperar(usuario);
                            setIntentoEnlace(false);
                            setErrorRecuperar(null);
                            setRecuperando(true);
                        }}
                    >
                        Recuperar contraseña
                    </button>
                </form>

                <div className="mt-4 grid grid-cols-2 gap-2">
                    {ENTRADAS.map(([a, texto, Icono, tono]) => (
                        <Link
                            key={a}
                            to={a}
                            className={`group flex min-h-14 items-center justify-center gap-2.5 rounded-xl border border-slate-200 bg-white/95 px-3 text-left text-sm font-medium text-slate-800 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md ${FOCO}`}
                        >
                            <span
                                className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${tono}`}
                            >
                                <Icono className="h-5 w-5" aria-hidden="true" />
                            </span>
                            <span className="whitespace-nowrap">{texto}</span>
                        </Link>
                    ))}
                </div>
            </div>

            {recuperando && (
                <Dialogo
                    titulo="Recuperar contraseña"
                    abierto
                    onCerrar={() => setRecuperando(false)}
                    acciones={
                        <>
                            <Boton
                                type="button"
                                variante="secundario"
                                onClick={() => setRecuperando(false)}
                            >
                                Cancelar
                            </Boton>
                            <Boton
                                type="submit"
                                form="formulario-recuperar"
                                cargando={enviandoEnlace}
                            >
                                Enviar enlace
                            </Boton>
                        </>
                    }
                >
                    <form id="formulario-recuperar" onSubmit={enviarEnlace} noValidate>
                        <Campo
                            etiqueta="Usuario"
                            requerido
                            placeholder="nombreapellido"
                            value={usuarioRecuperar}
                            autoCapitalize="none"
                            spellCheck={false}
                            validar={SOLO_USUARIO}
                            autoFocus
                            error={
                                errorRecuperar ??
                                (intentoEnlace && !usuarioRecuperar.trim()
                                    ? 'Obligatorio'
                                    : undefined)
                            }
                            onChange={(e) => {
                                setUsuarioRecuperar(e.target.value);
                                setErrorRecuperar(null);
                            }}
                        />
                    </form>
                </Dialogo>
            )}
            {aviso}
        </FondoAcceso>
    );
}
