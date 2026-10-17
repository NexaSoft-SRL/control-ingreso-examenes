import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Boton from '../../componentes/Boton';
import Logo from '../../componentes/Logo';
import { api } from '../../api/cliente';
import { erroresDe } from '../../api/errores';
import { usarSesion } from '../../sesion/SesionContexto';
import { destinoDe } from '../../navegacion/vistas';

const politicaCumplida = (valor) =>
    valor.length >= 12 &&
    /[a-z]/.test(valor) &&
    /[A-Z]/.test(valor) &&
    /\d/.test(valor) &&
    /[^A-Za-z0-9]/.test(valor);

export default function CambiarContrasena() {
    const navegar = useNavigate();
    const { usuario, actualizar, salir } = usarSesion();
    const [actual, setActual] = useState('');
    const [nueva, setNueva] = useState('');
    const [confirmacion, setConfirmacion] = useState('');
    const [error, setError] = useState('');
    const [enviando, setEnviando] = useState(false);

    async function guardar(evento) {
        evento.preventDefault();
        setError('');

        if (!actual || !nueva || !confirmacion) {
            setError('Complete todos los campos.');
            return;
        }
        if (!politicaCumplida(nueva)) {
            setError('Use al menos 12 caracteres, mayúscula, minúscula, número y símbolo.');
            return;
        }
        if (nueva !== confirmacion) {
            setError('La confirmación no coincide con la nueva contraseña.');
            return;
        }

        setEnviando(true);
        try {
            await api.post('/auth/cambiar-contrasena', {
                contrasena_actual: actual,
                password: nueva,
                password_confirmation: confirmacion,
            });
            const cuenta = await actualizar();
            navegar(destinoDe(cuenta), { replace: true });
        } catch (fallo) {
            const campos = erroresDe(fallo);
            setError(
                campos.contrasena_actual ??
                    campos.password ??
                    fallo?.response?.data?.message ??
                    'No se pudo actualizar la contraseña.'
            );
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
                    <h1 className="text-2xl font-bold text-slate-900">
                        Cambio de contraseña temporal
                    </h1>
                    <p className="mb-6 mt-1 text-sm text-slate-600">
                        Hola{usuario?.nombre ? `, ${usuario.nombre}` : ''}. Actualice su contraseña
                        para continuar.
                    </p>
                    <form
                        className="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
                        onSubmit={guardar}
                        noValidate
                    >
                        <label className="block text-sm font-medium text-slate-700">
                            Contraseña temporal
                            <input
                                className="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm"
                                type="password"
                                autoComplete="current-password"
                                value={actual}
                                onChange={(evento) => setActual(evento.target.value)}
                                required
                            />
                        </label>
                        <label className="block text-sm font-medium text-slate-700">
                            Nueva contraseña
                            <input
                                className="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm"
                                type="password"
                                autoComplete="new-password"
                                value={nueva}
                                onChange={(evento) => setNueva(evento.target.value)}
                                required
                            />
                        </label>
                        <label className="block text-sm font-medium text-slate-700">
                            Confirmar nueva contraseña
                            <input
                                className="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm"
                                type="password"
                                autoComplete="new-password"
                                value={confirmacion}
                                onChange={(evento) => setConfirmacion(evento.target.value)}
                                required
                            />
                        </label>
                        <p className="text-xs text-slate-600">
                            Mínimo 12 caracteres e incluya mayúscula, minúscula, número y símbolo.
                        </p>
                        {error && (
                            <p role="alert" className="text-sm text-danger-700">
                                {error}
                            </p>
                        )}
                        <Boton type="submit" className="w-full" disabled={enviando}>
                            {enviando ? 'Guardando…' : 'Cambiar contraseña'}
                        </Boton>
                        <button
                            type="button"
                            className="w-full text-sm font-medium text-primary-700 hover:underline"
                            onClick={async () => {
                                await salir();
                                navegar('/login', { replace: true });
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
