import { useState } from 'react';
import { KeyRound, Lock } from 'lucide-react';
import { Navigate, useNavigate } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import { usarSesion } from '../../sesion/SesionContexto';
import { destinoDe } from '../../navegacion/vistas';
import Campo from '../../componentes/Campo';
import Boton from '../../componentes/Boton';
import { useAviso } from '../../componentes/Aviso';
import FondoAcceso, { CabeceraAcceso, TARJETA_ACCESO } from '../../componentes/FondoAcceso';
import usarTituloPestana from '../../utiles/usarTituloPestana';
import { ListaReglas, cumpleReglas } from './reglasContrasena';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// Primer ingreso: la cuenta se activó con una contraseña temporal y no se
// puede usar el sistema hasta cambiarla. Con contraseña temporal el servidor
// no pide la actual.
export default function PrimerIngreso() {
    usarTituloPestana('Primer ingreso');
    const navegar = useNavigate();
    const { usuario, actualizar, salir } = usarSesion();
    const [clave, setClave] = useState('');
    const [repetida, setRepetida] = useState('');
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();
    const cumple = cumpleReglas(clave);
    const coincide = clave !== '' && clave === repetida;

    if (!usuario) return <Navigate to="/login" replace />;
    if (!usuario.debe_cambiar_contrasena && !enviando) {
        return <Navigate to={destinoDe(usuario)} replace />;
    }

    async function guardar(e) {
        e.preventDefault();
        if (!(cumple && coincide) || enviando) return;
        setEnviando(true);
        setErrores({});
        try {
            await api.post('/auth/contrasena', {
                contrasena_nueva: clave,
                contrasena_nueva_confirmation: repetida,
            });
            const cuenta = await actualizar();
            navegar(destinoDe(cuenta), { replace: true });
        } catch (error) {
            const campos = erroresDe(error);
            setErrores(campos);
            if (Object.keys(campos).length === 0) {
                avisar(mensajeDe(error, 'No se pudo guardar'), 'error');
            }
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
                        subtitulo="Primer ingreso · cambio obligatorio"
                    />

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
                            Guardar y entrar
                        </Boton>
                    </form>
                </div>

                <button
                    type="button"
                    onClick={async () => {
                        try {
                            await salir();
                            navegar('/login', { replace: true });
                        } catch {
                            // Sin conexión la sesión sigue: se queda aquí.
                        }
                    }}
                    className={`mt-4 min-h-11 w-full rounded-lg text-center text-sm font-medium text-slate-700 hover:underline ${FOCO}`}
                >
                    Cerrar sesión
                </button>
            </div>
            {aviso}
        </FondoAcceso>
    );
}
