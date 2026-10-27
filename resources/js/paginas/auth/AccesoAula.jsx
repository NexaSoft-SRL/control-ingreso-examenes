import { Suspense, lazy, useCallback, useEffect, useState } from 'react';
import { ArrowLeft, DoorOpen, Hourglass, LogOut } from 'lucide-react';
import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe } from '../../api/errores';
import { usarSesion, PantallaDeCarga } from '../../sesion/SesionContexto';
import { destinoDe } from '../../navegacion/vistas';
import Campo from '../../componentes/Campo';
import Boton from '../../componentes/Boton';
import EstadoCarga from '../../componentes/EstadoCarga';
import FondoAcceso from '../../componentes/FondoAcceso';
import Logo from '../../componentes/Logo';

const PuntoControl = lazy(() => import('../ingreso/PuntoControl'));

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const TOKEN = 'codigo_aula_token';
// Un nombre de persona: es la firma de lo que registre.
const NOMBRE_VALIDO = /^\p{L}+(?:(?:\.\s*|\s+|['-])\p{L}+)*\.?$/u;
const SEIS_DIGITOS = (v) => (/^\d{6}$/.test(v) ? null : 'Seis dígitos');
const NOMBRE = (v) => {
    const texto = String(v ?? '').trim();
    if (!texto) return 'Obligatorio';
    return (texto.match(/\p{L}/gu) ?? []).length >= 3 && NOMBRE_VALIDO.test(texto)
        ? null
        : 'Mínimo 3 letras, sin signos seguidos';
};
// '2026-10-12 09:45:00' → '09:45'.
const horaDe = (instante) => String(instante ?? '').slice(11, 16);
const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');
// Cada cuánto pregunta por su estado: en espera, seguido; autorizado, para
// enterarse si el docente lo movió de aula o lo cortó.
const ESPERA_MS = 3000;
const AUTORIZADO_MS = 15000;

function leerToken() {
    try {
        return localStorage.getItem(TOKEN);
    } catch {
        return null;
    }
}

function guardarToken(token) {
    try {
        if (token) localStorage.setItem(TOKEN, token);
        else localStorage.removeItem(TOKEN);
    } catch {
        // Sin almacenamiento se pierde el acceso al recargar.
    }
}

// La puerta con código de aula (HU-18), para quien controla sin cuenta: pide
// el código de seis dígitos y el nombre, espera a que el docente lo autorice
// en persona y recién entonces muestra el punto de control de su aula, sin
// menú. Si el docente lo mueve de aula, la puerta cambia; si lo corta, vuelve
// al formulario con el motivo.
export default function AccesoAula() {
    const { usuario } = usarSesion();
    const [parametros, setParametros] = useSearchParams();
    const [acceso, setAcceso] = useState(null);
    const [comprobando, setComprobando] = useState(() => Boolean(leerToken()));
    const [codigo, setCodigo] = useState(() => parametros.get('codigo') ?? '');
    const [nombre, setNombre] = useState('');
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);

    const ponerAula = useCallback(
        (id) =>
            setParametros(
                (previos) => {
                    const siguiente = new URLSearchParams(previos);
                    siguiente.delete('codigo');
                    if (id) siguiente.set('aula', String(id));
                    else siguiente.delete('aula');
                    return siguiente;
                },
                { replace: true }
            ),
        [setParametros]
    );

    const cerrar = useCallback(
        (motivo) => {
            guardarToken(null);
            setAcceso(null);
            ponerAula(null);
            if (motivo) setErrores({ codigo: sinPunto(motivo) });
        },
        [ponerAula]
    );

    // Pregunta por el acceso guardado: al recargar recupera si espera o si
    // ya está autorizado y en qué aula.
    const consultar = useCallback(
        () =>
            api
                .get('/auth/codigo-aula/sesion', { silencioso: true })
                .then((respuesta) => {
                    const abierto = respuesta.data?.data ?? null;
                    if (!abierto) return;
                    setAcceso(abierto);
                    ponerAula(abierto.aula_id);
                })
                .catch((error) => {
                    if (estadoDe(error) === 401) {
                        cerrar(error.response?.data?.message ?? 'El acceso se cerró');
                    }
                }),
        [cerrar, ponerAula]
    );

    useEffect(() => {
        if (usuario || !leerToken()) return undefined;
        let vigente = true;
        consultar().finally(() => vigente && setComprobando(false));
        return () => {
            vigente = false;
        };
        // Solo al montar.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const estado = acceso?.estado ?? null;
    useEffect(() => {
        if (!estado) return undefined;
        const reloj = setInterval(consultar, estado === 'pendiente' ? ESPERA_MS : AUTORIZADO_MS);
        return () => clearInterval(reloj);
    }, [estado, consultar]);

    if (usuario) return <Navigate to={destinoDe(usuario)} replace />;
    if (comprobando) return <PantallaDeCarga />;

    async function entrar(e) {
        e.preventDefault();
        const faltas = {
            codigo: SEIS_DIGITOS(codigo) ?? undefined,
            nombre: NOMBRE(nombre) ?? undefined,
        };
        if (faltas.codigo || faltas.nombre) return setErrores(faltas);
        if (enviando) return undefined;

        setEnviando(true);
        setErrores({});
        try {
            const { data } = await api.post('/auth/codigo-aula', {
                codigo,
                nombre_colaborador: nombre.trim(),
            });
            const abierto = data?.data;
            guardarToken(abierto?.token);
            setAcceso(abierto);
            ponerAula(abierto?.aula_id);
            setCodigo('');
        } catch (error) {
            const campos = erroresDe(error);
            if (estadoDe(error) === 429) {
                const minutos = Math.max(
                    1,
                    Math.ceil((error.response?.data?.reintentar_en ?? 300) / 60)
                );
                setErrores({ codigo: `Demasiados intentos · espere ${minutos} min` });
            } else if (campos.nombre_colaborador) {
                setErrores({ nombre: campos.nombre_colaborador });
            } else {
                // El servidor dice si el examen todavía no abre o ya terminó.
                setErrores({ codigo: sinPunto(campos.codigo ?? 'Código no válido') });
            }
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    async function salir() {
        try {
            await api.post('/auth/codigo-aula/salir', {}, { silencioso: true });
        } catch {
            // Sin conexión se sale igual: el acceso vence con el examen.
        }
        cerrar(null);
    }

    if (acceso?.estado === 'pendiente') {
        return (
            <FondoAcceso>
                <div className="w-full max-w-sm" role="status">
                    <div className="space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 text-center shadow-lg shadow-primary-900/5 backdrop-blur">
                        <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-warning-100 text-warning-800">
                            <Hourglass className="h-6 w-6 animate-pulse" aria-hidden="true" />
                        </span>
                        <div>
                            <h1 className="text-lg font-semibold text-slate-900">
                                Esperando autorización
                            </h1>
                            <p className="mt-1 text-sm text-slate-600">
                                Acérquese al docente para que lo autorice
                            </p>
                        </div>
                        <dl className="rounded-xl bg-slate-50 px-4 py-3 text-left text-sm">
                            <dt className="text-xs text-slate-500">Nombre</dt>
                            <dd className="font-semibold text-slate-900">
                                {acceso.nombre_colaborador}
                            </dd>
                            <dt className="mt-2 text-xs text-slate-500">Aula del código</dt>
                            <dd className="font-semibold text-slate-900">{acceso.aula_nombre}</dd>
                        </dl>
                        <Boton
                            type="button"
                            variante="secundario"
                            className="w-full"
                            onClick={salir}
                        >
                            Cancelar
                        </Boton>
                    </div>
                </div>
            </FondoAcceso>
        );
    }

    if (acceso) {
        return (
            <div className="flex min-h-dvh flex-col bg-slate-100">
                <header className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-2.5">
                    <div className="flex min-w-0 items-center gap-3">
                        <Logo className="h-8 w-8" />
                        <span className="min-w-0">
                            <span className="line-clamp-2 block break-words text-sm font-medium leading-tight text-slate-800">
                                {acceso.nombre_colaborador}
                            </span>
                            <span className="block truncate text-xs text-slate-600">
                                Aula {acceso.aula_nombre}
                                {acceso.expira_en && (
                                    <span> · Hasta {horaDe(acceso.expira_en)}</span>
                                )}
                            </span>
                        </span>
                    </div>
                    <Boton type="button" variante="secundario" tamano="chico" onClick={salir}>
                        <LogOut className="h-4 w-4" /> Salir
                    </Boton>
                </header>
                <main className="min-w-0 flex-1 pb-6">
                    <Suspense fallback={<EstadoCarga cargando />}>
                        <PuntoControl acceso={acceso} />
                    </Suspense>
                </main>
            </div>
        );
    }

    return (
        <FondoAcceso>
            <div className="w-full max-w-sm">
                <div className="mb-6 flex flex-col items-center text-center">
                    <div className="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-white">
                        <DoorOpen className="h-6 w-6" />
                    </div>
                    <h1 className="text-lg font-semibold text-slate-900">Punto de control</h1>
                    <p className="mt-1 text-sm text-slate-600">Código de aula</p>
                </div>

                <form
                    className="space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-lg shadow-primary-900/5 backdrop-blur"
                    noValidate
                    onSubmit={entrar}
                >
                    <Campo
                        etiqueta="Código"
                        requerido
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={6}
                        placeholder="000000"
                        value={codigo}
                        validar={SEIS_DIGITOS}
                        error={errores.codigo}
                        onChange={(e) => {
                            setCodigo(e.target.value.replace(/\D/g, '').slice(0, 6));
                            setErrores({});
                        }}
                    />
                    <Campo
                        etiqueta="Nombre"
                        requerido
                        autoComplete="name"
                        placeholder="Apellidos y nombres"
                        value={nombre}
                        validar={NOMBRE}
                        error={errores.nombre}
                        onChange={(e) => {
                            setNombre(e.target.value);
                            setErrores({});
                        }}
                    />
                    <Boton type="submit" className="w-full" cargando={enviando}>
                        Iniciar sesión
                    </Boton>
                </form>

                <Link
                    to="/login"
                    className={`mt-4 flex min-h-11 w-full items-center justify-center rounded-lg text-sm font-medium text-primary-700 hover:underline ${FOCO}`}
                >
                    <ArrowLeft className="mr-1.5 h-4 w-4" aria-hidden="true" /> Cuenta institucional
                </Link>
            </div>
        </FondoAcceso>
    );
}
