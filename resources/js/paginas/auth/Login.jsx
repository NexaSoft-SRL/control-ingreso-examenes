import { useEffect, useState } from 'react';
import { GraduationCap, KeyRound, Lock, LogIn, Mail, ShieldCheck, User } from 'lucide-react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe } from '../../api/errores';
import { usarSesion } from '../../sesion/SesionContexto';
import { destinoDe } from '../../navegacion/vistas';
import { plural } from '../../utiles/texto';
import Campo from '../../componentes/Campo';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import { useAviso } from '../../componentes/Aviso';
import usarTituloPestana from '../../utiles/usarTituloPestana';
import FondoAcceso, { CabeceraAcceso, TARJETA_ACCESO } from '../../componentes/FondoAcceso';
import SugerenciaInstalar from '../../componentes/SugerenciaInstalar';
import { normalizarUsuario, usuarioValido } from '../../utiles/usuario';

// Acceso por usuario y contraseña (el correo no es el identificador). Tras
// cinco intentos fallidos la cuenta se bloquea 15 minutos. Sin registro público.
const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// Se valida lo que se envía: sin los espacios de los extremos.
const SOLO_USUARIO = (v) =>
    usuarioValido(v) ? null : 'Solo letras y números, separados por un espacio';

// Para quien no tiene correo registrado o no recibe el enlace.
const PISTA_SIN_CORREO = 'Si no llega, pida una contraseña temporal a la administración.';

// El texto de cada rechazo del inicio de sesión (ruta 1 del contrato).
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
    if (estado === 401 && datos.codigo === 'TEMPORAL_VENCIDA') {
        return { fallo: 'Contraseña temporal vencida' };
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
        return { falloUsuario: campos.usuario, fallo: campos.password };
    }
    if (estado === 429) return { fallo: 'Demasiados intentos' };
    return { fallo: 'No se pudo iniciar sesión' };
}

export default function Login() {
    usarTituloPestana('Iniciar sesión');
    const { state } = useLocation();
    const navegar = useNavigate();
    const { usuario: cuenta, entrar } = usarSesion();
    const [usuario, setUsuario] = useState('');
    const [clave, setClave] = useState('');
    const [fallo, setFallo] = useState(null);
    const [falloUsuario, setFalloUsuario] = useState(null);
    const [bloqueo, setBloqueo] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [recuperando, setRecuperando] = useState(false);
    // «Obligatorio» aparece al intentar enviar, no apenas se abre el diálogo.
    const [intentoEnlace, setIntentoEnlace] = useState(false);
    const [usuarioRecuperar, setUsuarioRecuperar] = useState('');
    const [enviandoEnlace, setEnviandoEnlace] = useState(false);
    const [aviso, avisar] = useAviso();
    const avisoInicial = state?.aviso;

    useEffect(() => {
        if (avisoInicial) avisar(avisoInicial);
    }, [avisoInicial, avisar]);

    if (cuenta && !enviando) return <Navigate to={destinoDe(cuenta)} replace />;

    const limpiar = () => {
        setFallo(null);
        setFalloUsuario(null);
        setBloqueo(null);
    };

    async function iniciar(e) {
        e.preventDefault();
        if (enviando) return;
        const nombre = normalizarUsuario(usuario);
        if (!nombre) return setFalloUsuario('Obligatorio');
        if (SOLO_USUARIO(nombre)) return setFalloUsuario(SOLO_USUARIO(nombre));
        if (!clave) return setFallo('Obligatorio');

        limpiar();
        setEnviando(true);
        try {
            const entrante = await entrar(nombre, clave);
            return navegar(destinoDe(entrante), { replace: true });
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
        const nombre = normalizarUsuario(usuarioRecuperar);
        if (!nombre || SOLO_USUARIO(nombre) || enviandoEnlace) return;
        setEnviandoEnlace(true);
        try {
            await api.post('/auth/contrasena/olvide', { usuario: nombre });
            setRecuperando(false);
            // No dice si la cuenta existe ni si tiene correo: el servidor tampoco.
            avisar(
                `Si ${nombre} tiene correo registrado, le llegará el enlace. ${PISTA_SIN_CORREO}`
            );
        } catch {
            avisar('No se pudo enviar el enlace', 'error');
        } finally {
            setEnviandoEnlace(false);
        }
    }

    return (
        <FondoAcceso>
            <div className="w-full max-w-md">
                <div className={TARJETA_ACCESO}>
                    <CabeceraAcceso
                        icono={ShieldCheck}
                        titulo="Control de exámenes"
                        subtitulo="Cuenta institucional"
                    />

                    <form className="space-y-4" onSubmit={iniciar} noValidate>
                        <Campo
                            etiqueta="Usuario"
                            iconoEtiqueta={User}
                            requerido
                            placeholder="nombre apellido"
                            value={usuario}
                            autoFocus
                            autoComplete="username"
                            autoCapitalize="none"
                            spellCheck={false}
                            validar={SOLO_USUARIO}
                            error={falloUsuario ?? undefined}
                            onChange={(e) => {
                                setUsuario(e.target.value);
                                limpiar();
                            }}
                        />
                        <Campo
                            etiqueta="Contraseña"
                            iconoEtiqueta={Lock}
                            requerido
                            type="password"
                            placeholder="••••••••"
                            value={clave}
                            autoComplete="current-password"
                            error={fallo ?? undefined}
                            onChange={(e) => {
                                setClave(e.target.value);
                                limpiar();
                            }}
                        />

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
                            {!enviando && <LogIn className="h-4 w-4" aria-hidden="true" />}
                            Iniciar sesión
                        </Boton>

                        <button
                            type="button"
                            className={`flex min-h-11 w-full items-center justify-center gap-2 rounded-lg text-center text-sm font-medium text-primary-700 hover:underline ${FOCO}`}
                            onClick={() => {
                                setUsuarioRecuperar(usuario);
                                setIntentoEnlace(false);
                                setRecuperando(true);
                            }}
                        >
                            <Mail className="h-4 w-4" aria-hidden="true" />
                            Recuperar contraseña
                        </button>
                    </form>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-2">
                    {[
                        [
                            '/estudiante/ingreso',
                            'Soy estudiante',
                            GraduationCap,
                            'bg-primary-100 text-primary-700',
                        ],
                        [
                            '/acceso-aula',
                            'Código de aula',
                            KeyRound,
                            'bg-primary-100 text-primary-700',
                        ],
                    ].map(([a, texto, Icono, tono]) => (
                        <Link
                            key={a}
                            to={a}
                            className={`group flex min-h-14 items-center justify-center gap-2.5 rounded-xl border border-slate-200/70 bg-white px-3 text-left text-sm font-medium text-umss-marino shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md ${FOCO}`}
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
                <SugerenciaInstalar />
            </div>

            {recuperando && (
                <Dialogo
                    titulo="Recuperar contraseña"
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
                            placeholder="nombre apellido"
                            value={usuarioRecuperar}
                            autoCapitalize="none"
                            spellCheck={false}
                            validar={SOLO_USUARIO}
                            ayuda={PISTA_SIN_CORREO}
                            autoFocus
                            error={
                                intentoEnlace && !usuarioRecuperar.trim()
                                    ? 'Obligatorio'
                                    : undefined
                            }
                            onChange={(e) => setUsuarioRecuperar(e.target.value)}
                        />
                    </form>
                </Dialogo>
            )}
            {aviso}
        </FondoAcceso>
    );
}
