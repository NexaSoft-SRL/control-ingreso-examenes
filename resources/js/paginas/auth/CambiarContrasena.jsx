import { useState } from 'react';
import { KeyRound } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import FondoAcceso, { CabeceraAcceso, TARJETA_ACCESO } from '../../componentes/FondoAcceso';
import { api } from '../../api/cliente';
import { erroresDe } from '../../api/errores';
import { usarSesion } from '../../sesion/SesionContexto';
import { destinoDe } from '../../navegacion/vistas';
import { ListaReglas, cumpleReglas } from './reglasContrasena';
import usarTituloPestana from '../../utiles/usarTituloPestana';

// Primer ingreso: la cuenta entró con una contraseña temporal y no puede usar
// el sistema hasta cambiarla. Con la temporal el servidor no pide la actual.
export default function CambiarContrasena() {
    const navegar = useNavigate();
    const { actualizar, salir } = usarSesion();
    usarTituloPestana('Primer ingreso');
    const [clave, setClave] = useState('');
    const [repetida, setRepetida] = useState('');
    const [errores, setErrores] = useState({});
    const [error, setError] = useState('');
    const [enviando, setEnviando] = useState(false);
    const cumple = cumpleReglas(clave);
    const coincide = clave !== '' && clave === repetida;

    async function guardar(evento) {
        evento.preventDefault();
        if (!(cumple && coincide) || enviando) return;

        setEnviando(true);
        setErrores({});
        setError('');
        try {
            await api.post('/auth/cambiar-contrasena', {
                password: clave,
                password_confirmation: repetida,
            });
            const cuenta = await actualizar();
            navegar(destinoDe(cuenta), { replace: true });
        } catch (fallo) {
            const campos = erroresDe(fallo);
            setErrores(campos);
            if (!campos.password && !campos.password_confirmation) {
                setError(fallo?.response?.data?.message ?? 'No se pudo guardar');
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
                    <form className="space-y-4" onSubmit={guardar} noValidate>
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
                                setError('');
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
                            {enviando ? 'Guardando…' : 'Guardar y entrar'}
                        </Boton>
                        <button
                            type="button"
                            className="min-h-11 w-full text-sm font-medium text-primary-700 hover:underline"
                            onClick={async () => {
                                try {
                                    await salir();
                                    navegar('/login', { replace: true });
                                } catch {
                                    // Sin conexión la sesión sigue: se queda aquí.
                                }
                            }}
                        >
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </FondoAcceso>
    );
}
