import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Camera, ChevronRight, DoorOpen, Loader2, LogOut, RefreshCw } from 'lucide-react';
import LectorQr from './LectorQr';
import IncidenciasManuales from './IncidenciasManuales';
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
import Insignia from '../../componentes/Insignia';
import Seleccion from '../../componentes/Seleccion';
import { usarSesion } from '../../sesion/SesionContexto';
import { fechaLarga } from '../../utiles/texto';

const SONDEO_MS = 10000;
const ESPERA_BUSQUEDA_MS = 300;
const FOCO = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600';

// Etiquetas de los rechazos e intentos de «Incidencias de ingreso».
const RECHAZOS = {
    NO_HABILITADO: 'No habilitado',
    OTRA_AULA: 'Otra aula',
    YA_INGRESO: 'Ya ingresó',
    NO_CORRESPONDE: 'No corresponde',
    EXPULSADO: 'Expulsado',
    NO_INSCRITO: 'No inscrito',
    NO_REGISTRADO: 'Sin registro',
    QR_NO_VALIDO: 'Código QR no válido',
};

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');
// '2026-10-26 08:39:20' → '08:39'.
const horaDe = (instante) => String(instante ?? '').slice(11, 16);
const nombreDe = (fila) => [fila.apellidos, fila.nombres].filter(Boolean).join(' ');

function interpretarFallo(error, alternativa) {
    const estado = estadoDe(error);
    if (estado === 403) return 'Sin asignación en esta aula';
    if (estado === 401 || estado === 419) return 'La sesión terminó';
    if (!error?.response) return 'Sin conexión';
    return sinPunto(mensajeDe(error, alternativa));
}

// Las aulas que la persona atiende hoy, para pasar de una a otra.
function DialogoAulas({ abierto, aulas, actual, onElegir, onCerrar }) {
    return (
        <Dialogo titulo="Aula" abierto={abierto} onCerrar={onCerrar}>
            <ul className="divide-y divide-slate-100">
                {aulas.map((a) => (
                    <li key={a.id}>
                        <button
                            type="button"
                            onClick={() => onElegir(a.id)}
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
            </ul>
        </Dialogo>
    );
}

const formaAula = PropTypes.shape({
    id: PropTypes.number.isRequired,
    nombre: PropTypes.string,
    edificio: PropTypes.string,
    piso: PropTypes.string,
    abierta: PropTypes.bool,
});

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
// pertenece. Verificar y registrar son una sola operación, como en la guía.
// `?aula=` llega desde «En curso» (HU-22) con el aula ya elegida.
export default function PuntoControl() {
    const sesion = usarSesion();
    const [parametros, setParametros] = useSearchParams();
    const [aviso, avisar] = useAviso();
    const [sesionColaborador, setSesionColaborador] = useState(() => {
        try {
            const guardada = localStorage.getItem('codigo_aula_sesion');
            return guardada ? JSON.parse(guardada) : null;
        } catch {
            return null;
        }
    });

    const cerrarSesionColaborador = async () => {
        try {
            await api.post('/auth/codigo-aula/salir');
        } catch {
            // Ignorar
        }
        try {
            localStorage.removeItem('codigo_aula_token');
            localStorage.removeItem('codigo_aula_sesion');
        } catch {
            // Ignorar
        }
        setSesionColaborador(null);
        window.location.href = '/acceso-aula';
    };

    useEffect(() => {
        if (!sesionColaborador) return undefined;

        const verificarVigencia = async () => {
            try {
                await api.get('/auth/codigo-aula/sesion', { silencioso: true });
            } catch (error) {
                const estado = estadoDe(error);
                if (estado === 401 || estado === 419) {
                    try {
                        localStorage.removeItem('codigo_aula_token');
                        localStorage.removeItem('codigo_aula_sesion');
                    } catch {
                        // Ignorar
                    }
                    setSesionColaborador(null);
                    window.location.href = '/acceso-aula';
                }
            }
        };

        const intervalo = setInterval(verificarVigencia, 15000);
        return () => clearInterval(intervalo);
    }, [sesionColaborador]);

    // Exámenes y aulas que la persona atiende hoy.
    const contexto = usarConsulta('/punto-control/contexto', { activa: !sesionColaborador });
    const examenes = sesionColaborador
        ? [
              {
                  id: sesionColaborador.examen_id,
                  asignatura: { nombre: '' },
                  abierto: true,
                  aulas: [{ id: sesionColaborador.aula_id, nombre: sesionColaborador.aula_nombre }],
              },
          ]
        : Array.isArray(contexto.datos)
          ? contexto.datos
          : [];

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

    const pedida = Number(parametros.get('aula')) || null;
    const actual =
        aulas.find((a) => a.id === pedida) ?? aulas.find((a) => a.abierta) ?? aulas[0] ?? null;

    const elegirAula = (id) =>
        setParametros((previos) => {
            const siguiente = new URLSearchParams(previos);
            siguiente.set('aula', String(id));
            return siguiente;
        });

    const usuario = sesion.usuario;
    const operador = sesionColaborador
        ? (sesionColaborador.nombre_colaborador ?? 'Código de aula')
        : (usuario?.nombre ?? '');

    return (
        <div className="mx-auto max-w-2xl space-y-4 p-4 md:p-6">
            {sesionColaborador && (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-900 shadow-sm">
                    <div className="flex items-center gap-2">
                        <DoorOpen className="h-5 w-5 shrink-0 text-primary-600" />
                        <span>
                            Colaborador de aula:{' '}
                            <strong>{sesionColaborador.nombre_colaborador}</strong> · Aula{' '}
                            <strong>{sesionColaborador.aula_nombre}</strong>
                            {sesionColaborador.expira_en && (
                                <span className="ml-1 text-primary-700">
                                    · Hasta las{' '}
                                    <strong>{sesionColaborador.expira_en.slice(11, 16)}</strong>
                                </span>
                            )}
                        </span>
                    </div>
                    <button
                        type="button"
                        onClick={cerrarSesionColaborador}
                        className="inline-flex min-h-11 items-center gap-1.5 text-xs font-semibold text-danger-700 hover:text-danger-800 hover:underline"
                    >
                        <LogOut className="h-3.5 w-3.5" /> Salir de aula
                    </button>
                </div>
            )}

            <h1 className="text-2xl font-bold text-slate-900">Punto de control</h1>

            {!actual ? (
                <div className="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-700">
                    {contexto.cargando ? (
                        <p role="status">Cargando aulas…</p>
                    ) : contexto.error ? (
                        <p role="alert">
                            {interpretarFallo(contexto.error, 'No se pudieron cargar las aulas')}
                        </p>
                    ) : (
                        <p>Sin aulas asignadas hoy</p>
                    )}
                </div>
            ) : (
                <Puesto
                    // Al cambiar de aula, el puesto empieza de nuevo.
                    key={actual.id}
                    aula={actual}
                    aulas={aulas}
                    examenes={examenes}
                    sesionColaborador={sesionColaborador}
                    operador={operador}
                    elegirAula={elegirAula}
                    avisar={avisar}
                />
            )}

            {aviso}
        </div>
    );
}

function Puesto({ aula, aulas, examenes, sesionColaborador, operador, elegirAula, avisar }) {
    const puerta = sesionColaborador
        ? `/examenes/${sesionColaborador.examen_id}/aulas/${sesionColaborador.aula_id}/punto-control`
        : `/aulas/${aula.id}/punto-control`;

    const [cambiando, setCambiando] = useState(false);
    const [normasAbiertas, setNormasAbiertas] = useState(false);
    const [camara, setCamara] = useState(false);
    const [sinCamara, setSinCamara] = useState(null);
    const [verificando, setVerificando] = useState(false);
    const [veredicto, setVeredicto] = useState(null);
    const [tipo, setTipo] = useState('codigo');
    const [identificador, setIdentificador] = useState('');
    const [errorManual, setErrorManual] = useState(null);
    const [bloqueado, setBloqueado] = useState(false);
    const [todos, setTodos] = useState(false);
    const [buscar, setBuscar] = useState('');
    const [buscado, setBuscado] = useState('');
    const [ingresoAExpulsar, setIngresoAExpulsar] = useState(null);
    const [motivoExpulsion, setMotivoExpulsion] = useState('');
    const [errorExpulsion, setErrorExpulsion] = useState(null);
    const [guardandoExpulsion, setGuardandoExpulsion] = useState(false);
    const campoManual = useRef(null);
    const pendiente = useRef(false);

    useEffect(() => {
        const texto = buscar.trim();
        if (texto === buscado) return undefined;
        const reloj = setTimeout(() => setBuscado(texto), ESPERA_BUSQUEDA_MS);
        return () => clearTimeout(reloj);
    }, [buscar, buscado]);

    // La puerta del aula: ubicación, exámenes con sus cifras y normas.
    const resumen = usarSondeo(sesionColaborador ? null : puerta, { intervalo: SONDEO_MS });
    const recientes = usarSondeo(`${puerta}/ingresos`, {
        parametros: sesionColaborador ? { limite: 5 } : todos ? { todos: 1, buscar: buscado } : {},
        intervalo: SONDEO_MS,
    });
    const incidencias = usarConsulta(`${puerta}/incidencias`, { parametros: { limite: 20 } });

    const datosPuerta = resumen.datos;
    const aulaInfo = { ...aula, ...(datosPuerta?.aula ?? {}) };
    const examenesAula = datosPuerta?.examenes ?? [];
    const abierta = sesionColaborador ? true : (datosPuerta?.abierta ?? true);
    const sinConexion = resumen.sinConexion || recientes.sinConexion;
    const sinAlcance =
        bloqueado || estadoDe(resumen.error) === 403 || estadoDe(recientes.error) === 403;
    const hora = datosPuerta?.hora_servidor ?? '';
    const filas = Array.isArray(recientes.datos) ? recientes.datos : [];
    const total = recientes.meta?.total ?? filas.length;
    const filasIncidencias = Array.isArray(incidencias.datos) ? incidencias.datos : [];

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
                setErrorManual(campos.identificador ?? campos.tipo);
            } else {
                if (estado === 403) setBloqueado(true);
                avisar(interpretarFallo(fallo, 'No se pudo verificar'), 'error');
            }
        } finally {
            pendiente.current = false;
            setVerificando(false);
            recientes.recargar();
            incidencias.recargar();
            resumen.recargar();
        }
    };

    const verificarManual = (evento) => {
        evento.preventDefault();
        const valor = identificador.trim();
        if (!valor) return setErrorManual('Obligatorio');
        if (!/^\d{5,10}$/.test(valor)) {
            return setErrorManual(tipo === 'codigo' ? 'Código SIS no válido' : 'C.I. no válido');
        }
        return verificar({ tipo, identificador: valor }, 'manual');
    };

    // «Siguiente» deja la puerta lista para el próximo por la misma vía.
    const siguiente = () => {
        const medio = veredicto?.medio;
        setVeredicto(null);
        setIdentificador('');
        setErrorManual(null);
        if (medio === 'qr') setCamara(true);
        else setTimeout(() => campoManual.current?.querySelector('input')?.focus(), 0);
    };

    const registrarExcepcion = async ({ tipo: tipoExcepcion, motivo }) => {
        const examenId = veredicto?.examen?.id ?? veredicto?.examen_id ?? null;
        const { data } = await api.post(`${puerta}/excepciones`, {
            tipo: tipoExcepcion,
            motivo,
            estudiante_id: veredicto?.estudiante?.id ?? null,
            examen_id: examenesAula.some((x) => x.id === examenId) ? examenId : null,
        });
        recientes.recargar();
        resumen.recargar();
        return data;
    };

    const confirmarExpulsion = async () => {
        const motivo = motivoExpulsion.trim();
        if (guardandoExpulsion || ingresoAExpulsar === null) return;
        if (motivo.length < 5) {
            setErrorExpulsion('Mínimo 5 caracteres');
            return;
        }
        setGuardandoExpulsion(true);
        try {
            const { data } = await api.post(`${puerta}/ingresos/${ingresoAExpulsar}/expulsion`, {
                motivo,
            });
            setIngresoAExpulsar(null);
            setMotivoExpulsion('');
            setErrorExpulsion(null);
            avisar(
                data?.data?.hora
                    ? `Expulsión registrada · ${data.data.hora}`
                    : 'Expulsión registrada'
            );
        } catch (fallo) {
            const campo = erroresDe(fallo).motivo;
            if (campo) setErrorExpulsion(campo);
            else avisar(interpretarFallo(fallo, 'No se pudo registrar la expulsión'), 'error');
        } finally {
            setGuardandoExpulsion(false);
            recientes.recargar();
            resumen.recargar();
        }
    };

    const fecha = fechaLarga(datosPuerta?.fecha);

    return (
        <>
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
                    {aulas.length > 1 && (
                        <button
                            type="button"
                            onClick={() => setCambiando(true)}
                            className={`min-h-11 shrink-0 rounded-lg px-3 text-sm font-medium text-primary-700 hover:bg-primary-50 ${FOCO}`}
                        >
                            Cambiar
                        </button>
                    )}
                </div>

                {(fecha || sinConexion) && (
                    <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-600">
                        {fecha && <span>{fecha}</span>}
                        {sinConexion && (
                            <Insignia tono="peligro" punto>
                                Sin conexión
                            </Insignia>
                        )}
                    </p>
                )}

                {!sesionColaborador && datosPuerta && (
                    <>
                        <ul
                            aria-label="Exámenes del aula"
                            className="mt-3 space-y-2 border-t border-slate-100 pt-3"
                        >
                            {examenesAula.map((x) => (
                                <li key={x.id} className="flex items-start gap-2.5">
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
                                            {x.ingresados} / {x.asignados} ingresados
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
                        <button
                            type="button"
                            onClick={() => setNormasAbiertas((v) => !v)}
                            aria-expanded={normasAbiertas}
                            className={`-mx-2 mt-1 flex min-h-11 items-center gap-1 rounded-lg px-2 text-sm font-medium text-primary-700 hover:bg-primary-50 ${FOCO}`}
                        >
                            <ChevronRight
                                className={`h-4 w-4 transition-transform ${normasAbiertas ? 'rotate-90' : ''}`}
                                strokeWidth={2.5}
                            />
                            Normas
                        </button>
                    </>
                )}
            </section>

            {sinAlcance && (
                <p
                    role="alert"
                    className="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-700"
                >
                    Sin asignación en esta aula
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
                    className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5"
                >
                    {camara ? (
                        <LectorQr
                            alCerrar={() => setCamara(false)}
                            alDetectar={(contenido) =>
                                verificar({ tipo: 'qr', identificador: contenido }, 'qr')
                            }
                            alFallar={(texto) => {
                                setCamara(false);
                                setSinCamara(texto);
                                setTimeout(
                                    () => campoManual.current?.querySelector('input')?.focus(),
                                    0
                                );
                            }}
                        />
                    ) : (
                        <Boton
                            type="button"
                            variante="secundario"
                            className="min-h-12 w-full"
                            onClick={() => {
                                setSinCamara(null);
                                setCamara(true);
                            }}
                        >
                            <Camera className="h-5 w-5" /> Leer código QR
                        </Boton>
                    )}
                    {sinCamara && (
                        <p role="alert" className="text-sm text-slate-700">
                            {sinCamara}
                        </p>
                    )}

                    <form
                        ref={campoManual}
                        noValidate
                        onSubmit={verificarManual}
                        className="grid grid-cols-1 items-start gap-2 sm:grid-cols-[9rem_1fr_auto]"
                    >
                        <Seleccion
                            etiqueta="Identificación"
                            aria-label="Tipo de identificación"
                            value={tipo}
                            onChange={(e) => {
                                setTipo(e.target.value);
                                setErrorManual(null);
                            }}
                        >
                            <option value="codigo">Código SIS</option>
                            <option value="documento">C.I.</option>
                        </Seleccion>
                        <Campo
                            etiqueta={tipo === 'codigo' ? 'Código SIS' : 'C.I.'}
                            aria-label="Identificador"
                            placeholder={tipo === 'codigo' ? 'Código SIS' : 'C.I.'}
                            inputMode="numeric"
                            autoComplete="off"
                            enterKeyHint="search"
                            maxLength={10}
                            value={identificador}
                            error={errorManual}
                            onChange={(e) => {
                                setIdentificador(e.target.value);
                                setErrorManual(null);
                            }}
                        />
                        <Boton type="submit" className="sm:mt-[1.625rem]">
                            Verificar
                        </Boton>
                    </form>
                </section>
            )}

            {!sinAlcance && !verificando && veredicto && (
                <VeredictoPuerta
                    veredicto={veredicto}
                    aula={aulaInfo}
                    operador={operador}
                    hora={hora}
                    registrarExcepcion={registrarExcepcion}
                    // Con código de aula solo se verifica.
                    conExcepciones={!sesionColaborador}
                    onSiguiente={siguiente}
                />
            )}

            <section
                aria-label="Ingresos del aula"
                className="rounded-xl border border-slate-200 bg-white p-5"
            >
                <div className="mb-3 flex items-center justify-between gap-2">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Ingresos{total > 0 ? ` · ${total}` : ''}
                    </h2>
                    <button
                        type="button"
                        aria-label="Actualizar ingresos"
                        onClick={() => recientes.recargar()}
                        className={`flex h-11 w-11 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 ${FOCO}`}
                    >
                        <RefreshCw
                            className={`h-5 w-5 ${recientes.actualizando ? 'animate-spin' : ''}`}
                        />
                    </button>
                </div>

                {todos && (
                    <Buscador
                        valor={buscar}
                        onCambiar={setBuscar}
                        placeholder="Nombre o código SIS"
                        etiqueta="Buscar ingreso"
                        className="mb-2"
                    />
                )}

                {filas.length === 0 ? (
                    <p className="text-sm text-slate-600">
                        {todos && buscado ? 'Sin resultados' : 'Sin ingresos'}
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {filas.map((registro) => (
                            <li key={registro.ingreso_id} className="py-3 text-sm text-slate-700">
                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span className="font-semibold text-slate-900">
                                        {nombreDe(registro)}
                                    </span>
                                    <span>· {registro.codigo}</span>
                                    <span className="tabular-nums">
                                        · {registro.hora ?? horaDe(registro.registrado_en)}
                                    </span>
                                    {registro.es_excepcion && (
                                        <Insignia tono="advertencia">Excepción</Insignia>
                                    )}
                                    {registro.expulsado && (
                                        <Insignia tono="peligro">
                                            {registro.expulsado_a
                                                ? `Expulsado · ${registro.expulsado_a}`
                                                : 'Expulsado'}
                                        </Insignia>
                                    )}
                                </div>

                                {!sesionColaborador &&
                                    !registro.expulsado &&
                                    ingresoAExpulsar !== registro.ingreso_id && (
                                        <button
                                            type="button"
                                            disabled={guardandoExpulsion}
                                            onClick={() => {
                                                setIngresoAExpulsar(registro.ingreso_id);
                                                setMotivoExpulsion('');
                                                setErrorExpulsion(null);
                                            }}
                                            className={`mt-1 min-h-11 rounded-lg px-2 text-sm font-medium text-danger-700 hover:bg-danger-50 disabled:opacity-50 ${FOCO}`}
                                        >
                                            Expulsar
                                        </button>
                                    )}

                                {!sesionColaborador &&
                                    !registro.expulsado &&
                                    ingresoAExpulsar === registro.ingreso_id && (
                                        <div className="mt-2 space-y-2 rounded-lg border border-danger-200 bg-danger-50/40 p-3">
                                            <Campo
                                                etiqueta="Motivo de expulsión"
                                                requerido
                                                maxLength={500}
                                                value={motivoExpulsion}
                                                error={errorExpulsion}
                                                ayuda="Mínimo 5 caracteres"
                                                onChange={(e) => {
                                                    setMotivoExpulsion(e.target.value);
                                                    setErrorExpulsion(null);
                                                }}
                                            />
                                            <div className="flex flex-wrap gap-2">
                                                <Boton
                                                    type="button"
                                                    variante="peligro"
                                                    disabled={
                                                        guardandoExpulsion ||
                                                        motivoExpulsion.trim().length < 5
                                                    }
                                                    onClick={confirmarExpulsion}
                                                >
                                                    {guardandoExpulsion
                                                        ? 'Registrando…'
                                                        : 'Confirmar expulsión'}
                                                </Boton>
                                                <Boton
                                                    type="button"
                                                    variante="secundario"
                                                    disabled={guardandoExpulsion}
                                                    onClick={() => {
                                                        setIngresoAExpulsar(null);
                                                        setMotivoExpulsion('');
                                                        setErrorExpulsion(null);
                                                    }}
                                                >
                                                    Cancelar
                                                </Boton>
                                            </div>
                                        </div>
                                    )}
                            </li>
                        ))}
                    </ul>
                )}

                {!sesionColaborador && (todos || total > filas.length) && (
                    <button
                        type="button"
                        onClick={() => {
                            setTodos((v) => !v);
                            setBuscar('');
                            setBuscado('');
                        }}
                        className={`-mx-2 mt-2 min-h-11 rounded-lg px-2 text-sm font-medium text-primary-700 hover:bg-primary-50 ${FOCO}`}
                    >
                        {todos ? 'Ver recientes' : `Ver los ${total}`}
                    </button>
                )}
            </section>

            {!sesionColaborador && <IncidenciasManuales aulaId={aula.id} examenes={examenes} />}

            <section
                aria-label="Incidencias de ingreso"
                className="space-y-3 rounded-xl border border-slate-200 bg-white p-5"
            >
                <h2 className="text-lg font-semibold text-slate-900">Incidencias de ingreso</h2>

                {filasIncidencias.length === 0 ? (
                    <p className="text-sm text-slate-600">Sin incidencias</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {filasIncidencias.map((incidencia) => (
                            <li
                                key={incidencia.incidencia_id}
                                className="space-y-1 py-3 text-sm text-slate-700"
                            >
                                <p className="flex flex-wrap items-center gap-2">
                                    <span className="break-words font-semibold text-slate-900">
                                        {nombreDe(incidencia) || 'Sin estudiante'}
                                    </span>
                                    {incidencia.codigo && <span>· {incidencia.codigo}</span>}
                                    <Insignia tono="peligro">
                                        {RECHAZOS[incidencia.tipo_rechazo] ??
                                            incidencia.tipo_rechazo}
                                    </Insignia>
                                </p>
                                <p className="whitespace-pre-wrap break-words">
                                    {incidencia.detalle}
                                </p>
                                <p className="text-slate-600">
                                    {[
                                        incidencia.operador,
                                        incidencia.hora ?? horaDe(incidencia.registrado_en),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

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
        </>
    );
}

Puesto.propTypes = {
    aula: formaAula.isRequired,
    aulas: PropTypes.arrayOf(formaAula).isRequired,
    examenes: PropTypes.arrayOf(PropTypes.object).isRequired,
    sesionColaborador: PropTypes.object,
    operador: PropTypes.string,
    elegirAula: PropTypes.func.isRequired,
    avisar: PropTypes.func.isRequired,
};
