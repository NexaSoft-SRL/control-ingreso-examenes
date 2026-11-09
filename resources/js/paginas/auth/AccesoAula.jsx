import { Suspense, lazy, useEffect, useState } from 'react';
import { ArrowLeft, DoorOpen, Hash, Hourglass, LogIn, LogOut, User } from 'lucide-react';
import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { api, suscribirSesion, SIN_SESION } from '../../api/cliente';
import { erroresDe, estadoDe } from '../../api/errores';
import { usarSesion } from '../../sesion/SesionContexto';
import { AccesoAulaContexto } from '../../sesion/AccesoAulaContexto';
import { destinoDe } from '../../navegacion/vistas';
import Campo from '../../componentes/Campo';
import Boton from '../../componentes/Boton';
import Escudo from '../../componentes/Escudo';
import EstadoCarga from '../../componentes/EstadoCarga';
import usarTituloPestana from '../../utiles/usarTituloPestana';
import FondoAcceso, { CabeceraAcceso, TARJETA_ACCESO } from '../../componentes/FondoAcceso';
import { errorDeNombre } from '../../utiles/texto';

const PuntoControl = lazy(() => import('../ingreso/PuntoControl'));

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const SEIS_DIGITOS = (v) => (/^\d{6}$/.test(v) ? null : 'Seis dígitos');
const NOMBRE = (v) => errorDeNombre(v, 3);
// '2026-10-12T09:45:00-04:00' → '09:45'.
const horaDe = (iso) => String(iso ?? '').slice(11, 16);
// Cada cuánto pregunta por su estado: en espera, seguido; autorizado, para
// enterarse si el docente lo movió de aula o lo cortó.
const ESPERA_MS = 3000;
const AUTORIZADO_MS = 15000;

// La puerta con código de aula (D-03), para quien controla sin cuenta: pide
// el código de seis dígitos y el nombre, espera a que el docente lo autorice
// en persona y recién entonces muestra el punto de control de su aula, sin
// menú. La pantalla de la puerta lee el aula de `?aula=` y el acceso de
// `usarAccesoAula()`.
export default function AccesoAula() {
    usarTituloPestana('Código de aula');
    const { usuario } = usarSesion();
    const [, setParametros] = useSearchParams();
    const [acceso, setAcceso] = useState(null);
    const [comprobando, setComprobando] = useState(true);
    const [codigo, setCodigo] = useState('');
    const [nombre, setNombre] = useState('');
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);
    const hayCuenta = Boolean(usuario);

    const ponerAula = (id) =>
        setParametros(
            (previos) => {
                const siguiente = new URLSearchParams(previos);
                if (id) siguiente.set('aula', String(id));
                else siguiente.delete('aula');
                return siguiente;
            },
            { replace: true }
        );

    // Al recargar, el acceso sigue en la cookie: la ruta de estado dice si
    // espera, si ya está autorizado y en qué aula.
    useEffect(() => {
        if (hayCuenta) return undefined;
        let vigente = true;
        api.get('/auth/codigo-aula', { silencioso: true })
            .then((respuesta) => {
                const abierto = respuesta.data?.acceso ?? null;
                if (!vigente || !abierto) return;
                setAcceso(abierto);
                ponerAula(abierto.aula_id);
            })
            .catch(() => {})
            .finally(() => vigente && setComprobando(false));
        return () => {
            vigente = false;
        };
        // Solo al montar: `ponerAula` cambia con cada dirección.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [hayCuenta]);

    // Mientras hay acceso se pregunta por su estado: así entra apenas el
    // docente lo autoriza, cambia de aula si lo mueven y sale si lo cortan.
    const estado = acceso?.estado ?? null;
    useEffect(() => {
        if (!estado) return undefined;
        const reloj = setInterval(
            () => {
                api.get('/auth/codigo-aula', { silencioso: true })
                    .then((respuesta) => {
                        const abierto = respuesta.data?.acceso;
                        if (!abierto) return;
                        setAcceso(abierto);
                        ponerAula(abierto.aula_id);
                    })
                    .catch((error) => {
                        if (estadoDe(error) !== 401) return;
                        setAcceso(null);
                        ponerAula(null);
                        setErrores({
                            codigo: String(
                                error.response?.data?.message ?? 'El acceso se cerró'
                            ).replace(/\.$/, ''),
                        });
                    });
            },
            estado === 'pendiente' ? ESPERA_MS : AUTORIZADO_MS
        );
        return () => clearInterval(reloj);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [estado]);

    // El acceso vence al terminar el examen: la API responde 401.
    useEffect(
        () =>
            suscribirSesion((evento) => {
                if (evento === SIN_SESION) setAcceso(null);
            }),
        []
    );

    if (usuario) return <Navigate to={destinoDe(usuario)} replace />;
    // Mientras pregunta, el mismo fondo sin tarjeta: así no parpadea al
    // llegar desde el login.
    if (comprobando)
        return (
            <FondoAcceso>
                <span className="sr-only" role="status">
                    Cargando
                </span>
            </FondoAcceso>
        );

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
            const respuesta = await api.post('/auth/codigo-aula', {
                codigo,
                nombre: nombre.trim(),
            });
            const abierto = respuesta.data.acceso;
            setAcceso(abierto);
            ponerAula(abierto.aula_id);
            setCodigo('');
        } catch (error) {
            const campos = erroresDe(error);
            if (estadoDe(error) === 429) {
                const minutos = Math.max(
                    1,
                    Math.ceil((error.response?.data?.reintentar_en ?? 300) / 60)
                );
                setErrores({ codigo: `Demasiados intentos · espere ${minutos} min` });
            } else if (campos.nombre) setErrores({ nombre: campos.nombre });
            // El servidor dice si el examen todavía no abre o ya terminó.
            else
                setErrores({
                    codigo: String(campos.codigo ?? 'Código no válido').replace(/\.$/, ''),
                });
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    async function salir() {
        try {
            // La ruta propia del código de aula: el cierre de sesión de las
            // cuentas responde 401 a quien no tiene cuenta.
            await api.delete('/auth/codigo-aula', { silencioso: true });
        } catch {
            // Sin conexión se sale igual: el acceso vence con el examen.
        }
        setAcceso(null);
        ponerAula(null);
    }

    if (acceso?.estado === 'pendiente') {
        return (
            <FondoAcceso>
                <div className="w-full max-w-sm" role="status">
                    <div className={`space-y-4 text-center ${TARJETA_ACCESO}`}>
                        <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-warning-100 text-warning-800 ring-8 ring-warning-50">
                            <Hourglass className="h-7 w-7 animate-pulse" aria-hidden="true" />
                        </span>
                        <div>
                            <h1 className="text-2xl font-bold text-umss-marino">
                                Esperando autorización
                            </h1>
                            <p className="mt-1 text-sm text-slate-600">
                                Acérquese al docente para que lo autorice
                            </p>
                        </div>
                        <dl className="rounded-xl border border-primary-100 bg-primary-50 px-4 py-3 text-left text-sm">
                            <dt className="text-xs text-slate-500">Nombre</dt>
                            <dd className="font-semibold text-slate-900">{acceso.nombre}</dd>
                            <dt className="mt-2 text-xs text-slate-500">Aula del código</dt>
                            <dd className="font-semibold text-slate-900">{acceso.aula}</dd>
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
            <AccesoAulaContexto.Provider value={acceso}>
                <div className="flex min-h-dvh flex-col bg-fondo">
                    <header className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-2.5">
                        <div className="flex min-w-0 items-center gap-3">
                            <Escudo className="h-9" />
                            <span className="min-w-0">
                                <span className="line-clamp-2 block break-words text-sm font-medium leading-tight text-slate-800">
                                    {acceso.nombre ?? `Aula ${acceso.aula}`}
                                </span>
                                <span className="block truncate text-xs text-slate-600">
                                    {acceso.nombre ? `Aula ${acceso.aula}` : 'Código de aula'}
                                    {acceso.hasta && <span> · Hasta {horaDe(acceso.hasta)}</span>}
                                </span>
                            </span>
                        </div>
                        <Boton type="button" variante="secundario" tamano="chico" onClick={salir}>
                            <LogOut className="h-4 w-4" /> Salir
                        </Boton>
                    </header>
                    <main className="min-w-0 flex-1 pb-6">
                        <div className="mx-auto max-w-6xl px-4 py-5 md:px-8 md:py-8">
                            <Suspense fallback={<EstadoCarga cargando />}>
                                <PuntoControl />
                            </Suspense>
                        </div>
                    </main>
                </div>
            </AccesoAulaContexto.Provider>
        );
    }

    return (
        <FondoAcceso>
            <div className="w-full max-w-md">
                <div className={TARJETA_ACCESO}>
                    <CabeceraAcceso
                        icono={DoorOpen}
                        titulo="Punto de control"
                        subtitulo="Código de aula"
                    />

                    <form className="space-y-4" noValidate onSubmit={entrar}>
                        <Campo
                            etiqueta="Código"
                            iconoEtiqueta={Hash}
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
                            iconoEtiqueta={User}
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
                            {!enviando && <LogIn className="h-4 w-4" aria-hidden="true" />}
                            Iniciar sesión
                        </Boton>
                    </form>
                </div>

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
