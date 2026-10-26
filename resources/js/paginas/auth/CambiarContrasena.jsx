import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Logo from '../../componentes/Logo';
import { api } from '../../api/cliente';
import { erroresDe } from '../../api/errores';
import { usarSesion } from '../../sesion/SesionContexto';
import { destinoDe } from '../../navegacion/vistas';
import { ListaReglas, cumpleReglas } from './reglasContrasena';

// Primer ingreso: la cuenta entró con una contraseña temporal y no puede usar
// el sistema hasta cambiarla. Con la temporal el servidor no pide la actual.
export default function CambiarContrasena() {
    const navegar = useNavigate();
    const { actualizar, salir } = usarSesion();
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
                    <h1 className="text-2xl font-bold text-slate-900">Contraseña nueva</h1>
                    <p className="mb-6 mt-1 text-sm text-slate-600">
                        Primer ingreso · cambio obligatorio
                    </p>
                    <form
                        className="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                        onSubmit={guardar}
                        noValidate
                    >
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
                </section>
            </main>
        </div>
    );
}
