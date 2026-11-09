import { useState } from 'react';
import { KeyRound } from 'lucide-react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import FondoAcceso, { CabeceraAcceso, TARJETA_ACCESO } from '../../componentes/FondoAcceso';
import { api } from '../../api/cliente';
import { erroresDe } from '../../api/errores';
import { ListaReglas, cumpleReglas } from './reglasContrasena';
import usarTituloPestana from '../../utiles/usarTituloPestana';
import { normalizarUsuario, PISTA_SIN_CORREO, usuarioValido } from '../../utiles/usuario';

// Se valida lo que se envía: sin los espacios de los extremos.
const SOLO_USUARIO = (valor) => (usuarioValido(valor) ? null : 'Usuario no válido');

const ENLACE_INVALIDO = 'Enlace no válido o vencido';

// Dos pantallas: la solicitud del enlace (por usuario) y la del enlace del
// correo (`/restablecer-contrasena?correo=…&token=…`).
export default function RecuperarContrasena() {
    const ubicacion = useLocation();
    const navegar = useNavigate();
    const parametros = new URLSearchParams(ubicacion.search);
    const token = parametros.get('token');
    const correo = parametros.get('correo') ?? '';
    const esRestablecimiento = ubicacion.pathname === '/restablecer-contrasena' || Boolean(token);
    usarTituloPestana(esRestablecimiento ? 'Restablecer contraseña' : 'Recuperar contraseña');
    const [usuario, setUsuario] = useState(ubicacion.state?.usuario ?? '');
    const [clave, setClave] = useState('');
    const [repetida, setRepetida] = useState('');
    const [errores, setErrores] = useState({});
    const [mensaje, setMensaje] = useState('');
    const [error, setError] = useState('');
    const [invalido, setInvalido] = useState(esRestablecimiento && !(token && correo));
    const [enviando, setEnviando] = useState(false);
    const cumple = cumpleReglas(clave);
    const coincide = clave !== '' && clave === repetida;

    async function solicitar(evento) {
        evento.preventDefault();
        setMensaje('');
        setError('');
        const nombre = normalizarUsuario(usuario);
        if (!nombre) return setError('Obligatorio');
        if (SOLO_USUARIO(nombre)) return setError(SOLO_USUARIO(nombre));

        setEnviando(true);
        try {
            const respuesta = await api.post('/auth/recuperar-contrasena', { usuario: nombre });
            setMensaje(
                `${
                    respuesta.data?.message ??
                    'Si la cuenta tiene correo registrado, recibirá el enlace de recuperación.'
                } ${PISTA_SIN_CORREO}`
            );
        } catch (fallo) {
            const campos = erroresDe(fallo);
            setError(campos.usuario ?? 'No se pudo enviar el enlace');
        } finally {
            setEnviando(false);
        }
    }

    async function restablecer(evento) {
        evento.preventDefault();
        if (!(cumple && coincide) || enviando) return;

        setEnviando(true);
        setErrores({});
        setError('');
        try {
            const respuesta = await api.post('/auth/restablecer-contrasena', {
                correo,
                token,
                password: clave,
                password_confirmation: repetida,
            });
            setMensaje(respuesta.data?.message ?? 'Contraseña actualizada.');
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (campos.password || campos.password_confirmation) {
                setErrores(campos);
            } else if (campos.token || campos.correo) {
                // Lo rechazado es el enlace: vencido, usado o alterado.
                setInvalido(true);
            } else {
                setError(fallo?.response?.data?.message ?? 'No se pudo guardar');
            }
        } finally {
            setEnviando(false);
        }
    }

    function contenido() {
        if (!esRestablecimiento) {
            return (
                <form className="space-y-4" onSubmit={solicitar} noValidate>
                    <Campo
                        etiqueta="Usuario"
                        requerido
                        type="text"
                        autoComplete="username"
                        autoCapitalize="none"
                        spellCheck={false}
                        value={usuario}
                        validar={SOLO_USUARIO}
                        ayuda={PISTA_SIN_CORREO}
                        error={error || undefined}
                        onChange={(evento) => {
                            setUsuario(evento.target.value);
                            setError('');
                        }}
                    />
                    {mensaje && (
                        <p
                            role="status"
                            className="rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-900"
                        >
                            {mensaje}
                        </p>
                    )}
                    <Boton type="submit" className="w-full" disabled={enviando}>
                        {enviando ? 'Enviando…' : 'Enviar enlace'}
                    </Boton>
                </form>
            );
        }

        if (invalido) {
            return (
                <p role="alert" className="text-center text-sm text-danger-700">
                    {ENLACE_INVALIDO}
                </p>
            );
        }

        if (mensaje) {
            return (
                <div className="space-y-4">
                    <p
                        role="status"
                        className="rounded-lg bg-success-50 px-3 py-2 text-sm text-success-700"
                    >
                        {mensaje}
                    </p>
                    <Boton
                        type="button"
                        className="w-full"
                        onClick={() => navegar('/login', { replace: true })}
                    >
                        Iniciar sesión
                    </Boton>
                </div>
            );
        }

        return (
            <form className="space-y-4" onSubmit={restablecer} noValidate>
                <Campo
                    etiqueta="Contraseña"
                    requerido
                    type="password"
                    autoComplete="new-password"
                    value={clave}
                    error={errores.password}
                    onChange={(evento) => {
                        setClave(evento.target.value);
                        setErrores({});
                    }}
                />
                <ListaReglas clave={clave} />
                <Campo
                    etiqueta="Repetir contraseña"
                    requerido
                    type="password"
                    autoComplete="new-password"
                    value={repetida}
                    error={errores.password_confirmation}
                    onChange={(evento) => setRepetida(evento.target.value)}
                    validar={(valor) => (valor === clave ? null : 'No coincide')}
                />
                {error && (
                    <p role="alert" className="text-sm text-danger-700">
                        {error}
                    </p>
                )}
                <Boton
                    type="submit"
                    className="w-full"
                    disabled={!(cumple && coincide) || enviando}
                >
                    {enviando ? 'Guardando…' : 'Restablecer contraseña'}
                </Boton>
            </form>
        );
    }

    return (
        <FondoAcceso>
            <div className="w-full max-w-md">
                <div className={TARJETA_ACCESO}>
                    <CabeceraAcceso
                        icono={KeyRound}
                        titulo={esRestablecimiento ? 'Contraseña nueva' : 'Recuperar contraseña'}
                        subtitulo="Cuenta institucional"
                    />
                    {contenido()}
                </div>
                <div className="mt-4 text-center">
                    <Link
                        className="text-sm font-medium text-primary-700 hover:underline"
                        to="/login"
                    >
                        Volver al inicio de sesión
                    </Link>
                </div>
            </div>
        </FondoAcceso>
    );
}
