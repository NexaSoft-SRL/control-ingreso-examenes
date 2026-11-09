import { useState } from 'react';
import { KeyRound, Lock } from 'lucide-react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe } from '../../api/errores';
import Campo from '../../componentes/Campo';
import Boton from '../../componentes/Boton';
import usarTituloPestana from '../../utiles/usarTituloPestana';
import FondoAcceso, { CabeceraAcceso, TARJETA_ACCESO } from '../../componentes/FondoAcceso';
import { ListaReglas, cumpleReglas } from './reglasContrasena';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// Página del enlace del correo (`/restablecer-contrasena?token=…&usuario=…`).
// La maqueta no la dibuja: toma el aspecto del primer ingreso.
export default function RestablecerContrasena() {
    usarTituloPestana('Restablecer contraseña');
    const navegar = useNavigate();
    const [parametros] = useSearchParams();
    const token = parametros.get('token') ?? '';
    const usuario = parametros.get('usuario') ?? '';
    const [clave, setClave] = useState('');
    const [repetida, setRepetida] = useState('');
    const [errores, setErrores] = useState({});
    const [invalido, setInvalido] = useState(!token || !usuario);
    const [enviando, setEnviando] = useState(false);
    const cumple = cumpleReglas(clave);
    const coincide = clave !== '' && clave === repetida;

    async function guardar(e) {
        e.preventDefault();
        if (!(cumple && coincide) || enviando) return;
        setEnviando(true);
        setErrores({});
        try {
            await api.post('/auth/contrasena/restablecer', {
                usuario,
                token,
                contrasena_nueva: clave,
                contrasena_nueva_confirmation: repetida,
            });
            navegar('/login', { replace: true, state: { aviso: 'Contraseña actualizada' } });
        } catch (error) {
            const campos = erroresDe(error);
            setErrores(campos);
            // Sin un error de la contraseña, lo rechazado es el enlace.
            if (!campos.contrasena_nueva && !campos.contrasena_nueva_confirmation)
                setInvalido(true);
            setEnviando(false);
        }
    }

    return (
        <FondoAcceso>
            <div className="w-full max-w-md">
                <div className={TARJETA_ACCESO}>
                    <CabeceraAcceso
                        icono={KeyRound}
                        titulo="Contraseña nueva"
                        subtitulo={usuario ? `Restablecimiento · ${usuario}` : 'Restablecimiento'}
                    />

                    {invalido ? (
                        <div
                            role="alert"
                            className="rounded-lg bg-danger-50 px-4 py-3 text-center text-sm font-medium text-danger-700"
                        >
                            Enlace no válido o vencido
                        </div>
                    ) : (
                        <form className="space-y-4" noValidate onSubmit={guardar}>
                            <Campo
                                etiqueta="Contraseña"
                                iconoEtiqueta={Lock}
                                requerido
                                type="password"
                                autoComplete="new-password"
                                value={clave}
                                error={errores.contrasena_nueva}
                                onChange={(e) => {
                                    setClave(e.target.value);
                                    setErrores({});
                                }}
                            />
                            <ListaReglas clave={clave} />
                            <Campo
                                etiqueta="Repetir contraseña"
                                iconoEtiqueta={Lock}
                                requerido
                                type="password"
                                autoComplete="new-password"
                                value={repetida}
                                error={errores.contrasena_nueva_confirmation}
                                onChange={(e) => setRepetida(e.target.value)}
                                validar={(v) => (v === clave ? null : 'No coincide')}
                            />
                            <Boton
                                type="submit"
                                className="w-full"
                                disabled={!(cumple && coincide) || enviando}
                            >
                                Guardar
                            </Boton>
                        </form>
                    )}
                </div>

                <Link
                    to="/login"
                    className={`mt-4 flex min-h-11 w-full items-center justify-center rounded-lg text-sm font-medium text-primary-700 hover:underline ${FOCO}`}
                >
                    Iniciar sesión
                </Link>
            </div>
        </FondoAcceso>
    );
}
