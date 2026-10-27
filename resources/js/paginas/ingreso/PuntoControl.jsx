import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Camera, ChevronRight, ListChecks, Loader2, X } from 'lucide-react';
import LectorQr from './LectorQr';
import IngresosDelAula from './IngresosDelAula';
import VeredictoPuerta, { ubicacionDe } from './VeredictoPuerta';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarSondeo from '../../api/usarSondeo';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import EstadoCarga from '../../componentes/EstadoCarga';
import IconoAsignatura from '../../componentes/IconoAsignatura';
import { BotonIncidencia } from '../../componentes/Incidencias';
import Insignia from '../../componentes/Insignia';
import { usarSesion } from '../../sesion/SesionContexto';
import { colorDeAsignatura, fechaLarga, normalizar } from '../../utiles/texto';
import usarTituloPestana from '../../utiles/usarTituloPestana';

const SONDEO_MS = 10000;
const ESPERA_BUSQUEDA_MS = 300;
const FOCO = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600';
// Código universitario, o C.I. con su complemento («5003748-1A»).
const IDENTIFICADOR = /^\d{5,10}([\s-][0-9A-Za-z]{1,3}|[A-Za-z][0-9A-Za-z]{0,2})?$/;

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

function interpretarFallo(error, alternativa) {
    const estado = estadoDe(error);
    if (estado === 403) return sinPunto(mensajeDe(error, 'Sin asignación en esta aula'));
    if (estado === 401 || estado === 419) return 'La sesión terminó';
    if (!error?.response) return 'Sin conexión';
    return sinPunto(mensajeDe(error, alternativa));
}

const formaAula = PropTypes.shape({
    id: PropTypes.number.isRequired,
    nombre: PropTypes.string,
    edificio: PropTypes.string,
    piso: PropTypes.string,
    abierta: PropTypes.bool,
});

// Las aulas que la persona atiende hoy, para pasar de una a otra. Con
// muchas, se buscan por nombre.
function DialogoAulas({ abierto, aulas, actual, onElegir, onCerrar }) {
    const [buscada, setBuscada] = useState('');
    const texto = normalizar(buscada.trim());
    const visibles = aulas.filter((a) => normalizar(a.nombre).includes(texto));
    const cerrar = () => {
        setBuscada('');
        onCerrar();
    };

    return (
        <Dialogo titulo="Aula" abierto={abierto} onCerrar={cerrar}>
            {aulas.length > 6 && (
                <Buscador
                    valor={buscada}
                    onCambiar={setBuscada}
                    placeholder="Aula"
                    etiqueta="Buscar aula"
                    className="mb-2"
                />
            )}
            <ul className="divide-y divide-slate-100">
                {visibles.map((a) => (
                    <li key={a.id}>
                        <button
                            type="button"
                            onClick={() => {
                                setBuscada('');
                                onElegir(a.id);
                            }}
                            aria-current={a.id === actual || undefined}
                            className={`-mx-2 flex min-h-14 w-[calc(100%+1rem)] items-center gap-3 rounded-lg px-2 text-left hover:bg-slate-50 ${FOCO}`}
                        >
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold text-slate-900">
                                    Aula {a.nombre}
                                </span>
                                <span className="block truncate text-xs text-slate-600">
                                    {ubicacionDe(a)}
                                </span>
                            </span>
                            {a.id === actual ? (
                                <Insignia tono="primario">Actual</Insignia>
                            ) : (
                                !a.abierta && <Insignia tono="neutro">Sin examen</Insignia>
                            )}
                        </button>
                    </li>
                ))}
                {visibles.length === 0 && (
                    <li className="py-6 text-center text-sm text-slate-600">Sin resultados</li>
                )}
            </ul>
        </Dialogo>
    );
}

DialogoAulas.propTypes = {
    abierto: PropTypes.bool.isRequired,
    aulas: PropTypes.arrayOf(formaAula).isRequired,
    actual: PropTypes.number,
    onElegir: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};

// Normas de un examen: primero las marcadas, una por línea, después el texto libre.
function NormasDelExamen({ examen }) {
    const marcadas = examen.normas_marcadas ?? [];
    if (marcadas.length === 0 && !examen.normas) {
        return <span className="mt-1 block text-xs text-slate-700">Sin normas</span>;
    }
    return (
        <span className="mt-1 block text-xs text-slate-700">
            {marcadas.length > 0 && (
                <span role="list" aria-label="Normas marcadas" className="block">
                    {marcadas.map((norma, i) => (
                        <span key={`${i}-${norma}`} role="listitem" className="flex gap-1.5">
                            <span aria-hidden="true">·</span>
                            <span className="min-w-0 break-words">{norma}</span>
                        </span>
                    ))}
                </span>
            )}
            {examen.normas && (
                <span className={`block whitespace-pre-line ${marcadas.length > 0 ? 'mt-1' : ''}`}>
                    {examen.normas}
                </span>
            )}
        </span>
    );
}

NormasDelExamen.propTypes = {
    examen: PropTypes.shape({
        normas: PropTypes.string,
        normas_marcadas: PropTypes.arrayOf(PropTypes.string),
    }).isRequired,
};

// Punto de control (HU-19). Trabaja por aula: el aula puede tener más de un
// examen a la vez y, al identificar al estudiante, el servidor resuelve a cuál
// pertenece. Verificar y registrar son una sola operación. `?aula=` llega
// desde «En curso» (HU-22) con el aula ya elegida; con código de aula
// (`acceso`, HU-18) la puerta es la del acceso y se ve lo mismo que con
// cuenta, salvo la excepción y la expulsión.
export default function PuntoControl({ acceso = null }) {
    usarTituloPestana('Punto de control');
    const [parametros, setParametros] = useSearchParams();
    const contexto = usarConsulta('/punto-control/contexto');
    const examenes = Array.isArray(contexto.datos) ? contexto.datos : [];

    const aulas = Array.from(
        examenes
            .flatMap((examen) =>
                (examen.aulas ?? []).map((aula) => ({ ...aula, abierta: Boolean(examen.abierto) }))
            )
            .reduce((mapa, aula) => {
                const previa = mapa.get(aula.id);
                mapa.set(aula.id, { ...aula, abierta: aula.abierta || Boolean(previa?.abierta) });
                return mapa;
            }, new Map())
            .values()
    );

    const pedida = acceso?.aula_id ?? (Number(parametros.get('aula')) || null);
    const actual =
        aulas.find((a) => a.id === pedida) ?? aulas.find((a) => a.abierta) ?? aulas[0] ?? null;

    const elegirAula = (id) =>
        setParametros((previos) => {
            const siguiente = new URLSearchParams(previos);
            siguiente.set('aula', String(id));
            return siguiente;
        });

    if (!actual) {
        return (
            <div className="mx-auto max-w-md space-y-4 p-4 md:p-6">
                <h1 className="sr-only">Punto de control</h1>
                <div className="rounded-xl border border-slate-200 bg-white">
                    {contexto.error ? (
                        <p role="alert" className="px-4 py-8 text-center text-sm text-slate-600">
                            {interpretarFallo(contexto.error, 'No se pudieron cargar las aulas')}
                        </p>
                    ) : (
                        <EstadoCarga
                            cargando={contexto.cargando}
                            vacio
                            textoVacio="Sin aulas asignadas hoy"
                            onReintentar={contexto.recargar}
                            filas={4}
                        />
                    )}
                </div>
            </div>
        );
    }

    // Al cambiar de aula, el puesto empieza de nuevo.
    return (
        <Puesto
            key={actual.id}
            aula={actual}
            aulas={aulas}
            acceso={acceso}
            elegirAula={elegirAula}
        />
    );
}

PuntoControl.propTypes = {
    acceso: PropTypes.shape({
        aula_id: PropTypes.number,
        aula_nombre: PropTypes.string,
        examen_id: PropTypes.number,
        nombre_colaborador: PropTypes.string,
    }),
};

function Puesto({ aula, aulas, acceso, elegirAula }) {
    const { usuario, puede } = usarSesion();
    const [aviso, avisar] = useAviso();
    const puerta = `/aulas/${aula.id}/punto-control`;
    const operador = acceso
        ? (acceso.nombre_colaborador ?? 'Código de aula')
        : (usuario?.nombre ?? '');

    const [cambiando, setCambiando] = useState(false);
    const [normasAbiertas, setNormasAbiertas] = useState(false);
    const [camara, setCamara] = useState(false);
    const [sinCamara, setSinCamara] = useState(null);
    const [verificando, setVerificando] = useState(false);
    const [veredicto, setVeredicto] = useState(null);
    const [manualAbierto, setManualAbierto] = useState(false);
    const [identificador, setIdentificador] = useState('');
    const [errorManual, setErrorManual] = useState(null);
    const [bloqueado, setBloqueado] = useState(false);
    const [todos, setTodos] = useState(false);
    const [buscar, setBuscar] = useState('');
    const [buscado, setBuscado] = useState('');
    const pendiente = useRef(false);

    useEffect(() => {
        const texto = buscar.trim();
        if (texto === buscado) return undefined;
        const reloj = setTimeout(() => setBuscado(texto), ESPERA_BUSQUEDA_MS);
        return () => clearTimeout(reloj);
    }, [buscar, buscado]);

    // La puerta del aula: ubicación, exámenes con sus cifras y normas.
    const resumen = usarSondeo(puerta, { intervalo: SONDEO_MS });
    const recientes = usarSondeo(`${puerta}/ingresos`, {
        parametros: todos ? { todos: 1, buscar: buscado } : {},
        intervalo: SONDEO_MS,
    });

    const datosPuerta = resumen.datos;
    const aulaInfo = { ...aula, ...(datosPuerta?.aula ?? {}) };
    const examenesAula = datosPuerta?.examenes ?? [];
    const abierta = datosPuerta?.abierta ?? true;
    const sinConexion = resumen.sinConexion || recientes.sinConexion;
    const sinAlcance = bloqueado || estadoDe(resumen.error) === 403;
    const hora = datosPuerta?.hora_servidor ?? '';
    const filas = Array.isArray(recientes.datos) ? recientes.datos : [];
    const total = recientes.meta?.total ?? filas.length;
    const totalIngresaron = examenesAula.reduce((s, x) => s + (x.ingresados ?? 0), 0);
    const totalFaltan = examenesAula.reduce((s, x) => s + (x.faltan ?? 0), 0);
    const fecha = fechaLarga(datosPuerta?.fecha);
    // La lista del aula («Mi aula», HU-21) es de las cuentas que la siguen.
    const destinoLista =
        usuario && puede('seguimiento_vivo') && examenesAula[0]
            ? `${puede('examenes') ? '/en-curso/aula' : '/mi-aula'}?examen=${examenesAula[0].id}&aula=${aula.id}`
            : null;

    const recargar = () => {
        recientes.recargar();
        resumen.recargar();
    };

    const verificar = async (cuerpo, medio) => {
        if (pendiente.current || sinAlcance) return;
        pendiente.current = true;
        setCamara(false);
        setVerificando(true);
        setErrorManual(null);
        try {
            const { data } = await api.post(`${puerta}/ingresos`, cuerpo);
            setVeredicto({ ...(data?.data ?? {}), medio });
            setIdentificador('');
        } catch (fallo) {
            const estado = estadoDe(fallo);
            const datos = fallo?.response?.data?.data;
            if (estado === 409 && datos && typeof datos === 'object') {
                // Un veredicto que no deja entrar: se muestra, no es un error.
                setVeredicto({ ...datos, medio });
                setIdentificador('');
            } else if (estado === 422 && Object.keys(erroresDe(fallo)).length > 0) {
                const campos = erroresDe(fallo);
                setManualAbierto(true);
                setErrorManual(campos.identificador ?? campos.tipo);
            } else {
                if (estado === 403) setBloqueado(true);
                avisar(interpretarFallo(fallo, 'No se pudo verificar'), 'error');
            }
        } finally {
            pendiente.current = false;
            setVerificando(false);
            recargar();
        }
    };

    const verificarManual = (evento) => {
        evento.preventDefault();
        const valor = identificador.trim();
        if (!valor) return setErrorManual('Obligatorio');
        if (!IDENTIFICADOR.test(valor)) return setErrorManual('Código SIS o C.I. no válido');
        return verificar({ identificador: valor.toUpperCase() }, 'manual');
    };

    const cerrarManual = () => {
        setManualAbierto(false);
        setIdentificador('');
        setErrorManual(null);
    };

    // El campo manual; bajo un veredicto leído a mano queda a la vista para
    // el siguiente.
    const formularioManual = (cerrable) => (
        <form className="flex w-full items-start gap-2" noValidate onSubmit={verificarManual}>
            <Campo
                etiqueta="Código SIS o C.I."
                placeholder="Código SIS o C.I."
                className="min-w-0 flex-1"
                autoCapitalize="characters"
                autoComplete="off"
                enterKeyHint="search"
                maxLength={14}
                autoFocus={cerrable}
                value={identificador}
                error={errorManual}
                onChange={(e) => {
                    setIdentificador(e.target.value);
                    setErrorManual(null);
                }}
            />
            <Boton type="submit" className="mt-[1.625rem] min-h-11">
                Verificar
            </Boton>
            {cerrable && (
                <button
                    type="button"
                    aria-label="Cerrar ingreso manual"
                    onClick={cerrarManual}
                    className={`mt-[1.625rem] flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 ${FOCO}`}
                >
                    <X className="h-5 w-5" />
                </button>
            )}
        </form>
    );

    // «Siguiente» deja la puerta lista para el próximo por la misma vía.
    const siguiente = () => {
        const medio = veredicto?.medio;
        setVeredicto(null);
        setIdentificador('');
        setErrorManual(null);
        setManualAbierto(medio === 'manual');
        if (medio === 'qr') setCamara(true);
    };

    const registrarExcepcion = async ({ tipo: tipoExcepcion, motivo }) => {
        const examenId = veredicto?.examen?.id ?? veredicto?.examen_id ?? null;
        const { data } = await api.post(`${puerta}/excepciones`, {
            tipo: tipoExcepcion,
            motivo,
            estudiante_id: veredicto?.estudiante?.id ?? null,
            examen_id: examenesAula.some((x) => x.id === examenId) ? examenId : null,
        });
        recargar();
        return data;
    };

    const expulsar = async (ingreso, motivo) => {
        try {
            await api.post(`${puerta}/ingresos/${ingreso.ingreso_id}/expulsion`, { motivo });
        } finally {
            recargar();
        }
    };

    // La incidencia va al examen del aula en el que la persona actúa: el
    // primero que no la rechace.
    const reportar = async (datos) => {
        let ultimo = new Error('Sin examen');
        for (const { id } of examenesAula) {
            try {
                return await api.post(`/examenes/${id}/incidencias`, datos);
            } catch (fallo) {
                ultimo = fallo;
                if (estadoDe(fallo) !== 403) break;
            }
        }
        throw ultimo;
    };

    return (
        <div className="mx-auto max-w-md space-y-4 p-4 md:p-6">
            <h1 className="sr-only">Punto de control</h1>
            <section aria-label="Aula" className="rounded-xl border border-slate-200 bg-white p-4">
                <div className="flex items-center justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-lg font-bold leading-tight text-slate-900">
                            Aula {aulaInfo.nombre}
                        </p>
                        {ubicacionDe(aulaInfo) && (
                            <p className="truncate text-sm text-slate-600">
                                {ubicacionDe(aulaInfo)}
                            </p>
                        )}
                    </div>
                    {aulas.length > 1 && !acceso && (
                        <button
                            type="button"
                            onClick={() => setCambiando(true)}
                            className={`min-h-11 shrink-0 rounded-lg px-3 text-sm font-medium text-primary-700 hover:bg-primary-50 ${FOCO}`}
                        >
                            Cambiar
                        </button>
                    )}
                </div>

                {datosPuerta && (
                    <>
                        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-600">
                            <span>
                                {fecha}
                                {examenesAula.length > 0 &&
                                    ` · ${totalIngresaron} ingresaron · Faltan ${totalFaltan}`}
                            </span>
                            {sinConexion && (
                                <Insignia tono="peligro" punto>
                                    Sin conexión
                                </Insignia>
                            )}
                        </p>
                        <ul
                            aria-label="Exámenes del aula"
                            className="mt-3 space-y-2 border-t border-slate-100 pt-3"
                        >
                            {examenesAula.map((x) => (
                                <li key={x.id} className="flex items-start gap-2.5">
                                    <IconoAsignatura
                                        nombre={x.asignatura}
                                        color={colorDeAsignatura(x.asignatura)}
                                        tamano="xs"
                                        className="mt-0.5"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-medium leading-snug text-slate-800">
                                            {x.asignatura}
                                        </span>
                                        <span className="block truncate text-xs text-slate-600">
                                            {x.tipo} · {x.hora}–{x.hora_fin}
                                            {!x.abierto && ' · Sin examen a esta hora'}
                                        </span>
                                        {normasAbiertas && <NormasDelExamen examen={x} />}
                                    </span>
                                    <span className="shrink-0 text-right tabular-nums">
                                        <span className="block text-sm font-medium text-slate-800">
                                            {x.ingresados} ingresaron
                                        </span>
                                        <span className="block text-xs text-slate-600">
                                            Faltan {x.faltan ?? 0}
                                        </span>
                                    </span>
                                </li>
                            ))}
                            {examenesAula.length === 0 && (
                                <li className="text-sm text-slate-600">Sin exámenes hoy</li>
                            )}
                        </ul>
                        <div className="-mx-2 mt-1 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                onClick={() => setNormasAbiertas((v) => !v)}
                                aria-expanded={normasAbiertas}
                                className={`flex min-h-11 items-center gap-1 rounded-lg px-2 text-sm font-medium text-primary-700 hover:bg-primary-50 ${FOCO}`}
                            >
                                <ChevronRight
                                    className={`h-4 w-4 transition-transform ${normasAbiertas ? 'rotate-90' : ''}`}
                                    strokeWidth={2.5}
                                />
                                Normas
                            </button>
                            <span className="flex items-center gap-1">
                                {examenesAula.length > 0 && (
                                    <BotonIncidencia
                                        aula={{ id: aulaInfo.id, nombre: aulaInfo.nombre }}
                                        por={operador}
                                        hora={hora}
                                        registrar={reportar}
                                        variante="enlace"
                                        className="px-2"
                                    />
                                )}
                                {destinoLista && (
                                    <Link
                                        to={destinoLista}
                                        className={`flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-primary-700 hover:bg-primary-50 ${FOCO}`}
                                    >
                                        <ListChecks className="h-4 w-4" /> Lista
                                    </Link>
                                )}
                            </span>
                        </div>
                    </>
                )}
            </section>

            {sinAlcance && (
                <p
                    role="alert"
                    className="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-700"
                >
                    {resumen.error
                        ? interpretarFallo(resumen.error, 'Sin asignación en esta aula')
                        : 'Sin asignación en esta aula'}
                </p>
            )}

            {!sinAlcance && verificando && (
                <div
                    role="status"
                    className="flex h-48 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white text-base font-medium text-slate-700"
                >
                    <Loader2 className="h-5 w-5 animate-spin" strokeWidth={2} />
                    Verificando…
                </div>
            )}

            {!sinAlcance && !verificando && !veredicto && !abierta && (
                <div className="flex h-48 items-center justify-center rounded-2xl border border-slate-200 bg-white text-base font-medium text-slate-700">
                    Sin examen a esta hora
                </div>
            )}

            {!sinAlcance && !verificando && !veredicto && abierta && (
                <section
                    aria-label="Identificación"
                    className="flex flex-col items-center gap-3 rounded-2xl border border-slate-200 bg-white p-5"
                >
                    {camara ? (
                        <div className="w-full">
                            <LectorQr
                                alCerrar={() => setCamara(false)}
                                alDetectar={(contenido) =>
                                    verificar({ tipo: 'qr', identificador: contenido }, 'qr')
                                }
                                alFallar={(texto) => {
                                    setCamara(false);
                                    setSinCamara(texto);
                                    setManualAbierto(true);
                                }}
                            />
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={() => {
                                setSinCamara(null);
                                setCamara(true);
                            }}
                            aria-label="Leer código QR"
                            className="flex h-40 w-40 items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 hover:border-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                        >
                            <Camera className="h-14 w-14 text-slate-400" strokeWidth={1.5} />
                        </button>
                    )}
                    <p
                        className="text-sm font-medium text-slate-700"
                        role={sinCamara ? 'alert' : undefined}
                    >
                        {sinCamara ?? 'Código QR del estudiante'}
                    </p>
                    {manualAbierto ? (
                        formularioManual(true)
                    ) : (
                        <Boton
                            type="button"
                            variante="secundario"
                            className="min-h-12 w-full"
                            onClick={() => setManualAbierto(true)}
                        >
                            Código SIS o C.I. manual
                        </Boton>
                    )}
                </section>
            )}

            {!sinAlcance && !verificando && veredicto && (
                <VeredictoPuerta
                    veredicto={veredicto}
                    aula={aulaInfo}
                    operador={operador}
                    hora={hora}
                    registrarExcepcion={registrarExcepcion}
                    // Con código de aula solo se verifica y se reportan incidencias.
                    conExcepciones={!acceso}
                    onSiguiente={siguiente}
                />
            )}

            {!sinAlcance && !verificando && veredicto?.medio === 'manual' && abierta && (
                <div className="rounded-2xl border border-slate-200 bg-white p-4">
                    {formularioManual(false)}
                </div>
            )}

            <IngresosDelAula
                ingresos={filas}
                total={total}
                examenes={examenesAula}
                todos={todos}
                onAlternar={() => {
                    setTodos((v) => !v);
                    setBuscar('');
                    setBuscado('');
                }}
                buscar={buscar}
                onBuscar={setBuscar}
                aula={aulaInfo.nombre}
                operador={operador}
                hora={hora}
                expulsar={acceso ? null : expulsar}
            />

            <DialogoAulas
                abierto={cambiando}
                aulas={aulas}
                actual={aula.id}
                onCerrar={() => setCambiando(false)}
                onElegir={(id) => {
                    setCambiando(false);
                    if (id !== aula.id) elegirAula(id);
                }}
            />

            {aviso}
        </div>
    );
}

Puesto.propTypes = {
    aula: formaAula.isRequired,
    aulas: PropTypes.arrayOf(formaAula).isRequired,
    acceso: PropTypes.object,
    elegirAula: PropTypes.func.isRequired,
};
