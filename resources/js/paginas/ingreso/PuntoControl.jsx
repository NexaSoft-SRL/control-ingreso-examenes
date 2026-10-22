import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import LectorQr from './LectorQr';
import { ClipboardCheck, DoorOpen, LogOut, RefreshCw, Search } from 'lucide-react';
import { api } from '../../api/cliente';
import { estadoDe, mensajeDe } from '../../api/errores';

const RESULTADOS = {
    ADMITIDO: 'Ingreso autorizado',
    NO_HABILITADO: 'Estudiante no habilitado',
    OTRA_AULA: 'Corresponde a otra aula',
    YA_INGRESO: 'Ingreso registrado anteriormente',
    NO_CORRESPONDE: 'No corresponde al examen',
    EXPULSADO: 'Estudiante expulsado',
};

function interpretarFallo(error, alternativa) {
    const estado = estadoDe(error);

    if (estado === 403) {
        return 'No tienes asignación para operar en esta aula.';
    }

    if (estado === 401 || estado === 419) {
        return 'La sesión ha terminado.';
    }

    return mensajeDe(error, alternativa);
}

function Aula({ aulas, valor, alCambiar, deshabilitado = false }) {
    return (
        <label className="block space-y-2 text-sm font-medium text-slate-700">
            Aula de control
            <select
                aria-label="Aula de control"
                disabled={deshabilitado}
                className="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                value={valor}
                onChange={(evento) => alCambiar(evento.target.value)}
            >
                <option value="">Seleccionar aula</option>
                {aulas.map((aula) => (
                    <option key={aula.id} value={aula.id}>
                        {aula.nombre ?? aula.codigo ?? `Aula ${aula.id}`}
                    </option>
                ))}
            </select>
        </label>
    );
}

Aula.propTypes = {
    aulas: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            nombre: PropTypes.string,
            codigo: PropTypes.string,
        })
    ).isRequired,
    valor: PropTypes.string.isRequired,
    alCambiar: PropTypes.func.isRequired,
    deshabilitado: PropTypes.bool,
};

export default function PuntoControl() {
    const [examenes, setExamenes] = useState([]);
    const [cargandoExamenes, setCargandoExamenes] = useState(true);
    // `?aula=` llega desde «En curso» (HU-22) con el aula ya elegida.
    const [parametros] = useSearchParams();
    const [aulaId, setAulaId] = useState(() => parametros.get('aula') ?? '');
    const [tipo, setTipo] = useState('codigo');
    const [identificador, setIdentificador] = useState('');
    const [candidato, setCandidato] = useState(null);
    const [veredicto, setVeredicto] = useState(null);
    const [historial, setHistorial] = useState([]);
    const [incidencias, setIncidencias] = useState([]);
    const [mensaje, setMensaje] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [bloqueado, setBloqueado] = useState(false);
    const pendiente = useRef(false);
    const versionContexto = useRef(0);
    const peticiones = useRef(new Set());
    const [lectorAbierto, setLectorAbierto] = useState(false);
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

    const invalidarContexto = () => {
        versionContexto.current += 1;
        peticiones.current.forEach((controlador) => controlador.abort());
        peticiones.current.clear();
        setLectorAbierto(false);
    };

    useEffect(() => {
        const actuales = peticiones.current;

        return () => {
            versionContexto.current += 1;
            actuales.forEach((controlador) => controlador.abort());
            actuales.clear();
        };
    }, []);

    const aulas = Array.from(
        new Map(
            examenes.flatMap((item) =>
                (Array.isArray(item.aulas) ? item.aulas : []).map((aula) => [aula.id, aula])
            )
        ).values()
    );

    const aulaValida = aulas.some((aula) => String(aula.id) === aulaId);
    const puerta = sesionColaborador
        ? `/examenes/${sesionColaborador.examen_id}/aulas/${sesionColaborador.aula_id}/punto-control`
        : aulaValida
          ? `/aulas/${aulaId}/punto-control`
          : null;

    useEffect(() => {
        let activo = true;

        if (sesionColaborador) {
            setExamenes([
                {
                    id: sesionColaborador.examen_id,
                    tipo: 'Examen',
                    asignatura: { nombre: 'Examen' },
                    aulas: [
                        {
                            id: sesionColaborador.aula_id,
                            nombre: sesionColaborador.aula_nombre,
                        },
                    ],
                },
            ]);
            setAulaId(String(sesionColaborador.aula_id));
            setCargandoExamenes(false);
            return;
        }

        api.get('/punto-control/contexto')
            .then(({ data }) => {
                if (activo) {
                    setExamenes(Array.isArray(data?.data) ? data.data : []);
                }
            })
            .catch((error) => {
                if (activo) {
                    setMensaje(interpretarFallo(error, 'No se pudieron cargar los exámenes.'));
                }
            })
            .finally(() => {
                if (activo) setCargandoExamenes(false);
            });

        return () => {
            activo = false;
        };
    }, [sesionColaborador]);

    useEffect(() => {
        let activo = true;
        const controlador = new AbortController();

        if (puerta) {
            api.get(`${puerta}/ingresos`, {
                params: { limite: 5 },
                signal: controlador.signal,
            })
                .then(({ data }) => {
                    if (activo) {
                        setHistorial(Array.isArray(data?.data) ? data.data : []);
                    }
                })
                .catch((error) => {
                    if (activo && !controlador.signal.aborted) {
                        setHistorial([]);
                        setMensaje(interpretarFallo(error, 'No se pudo consultar el historial.'));
                        if (estadoDe(error) === 403) setBloqueado(true);
                    }
                });
        }

        return () => {
            activo = false;
            controlador.abort();
        };
    }, [puerta]);

    useEffect(() => {
        let activo = true;
        const controlador = new AbortController();

        setIncidencias([]);

        if (puerta) {
            api.get(`${puerta}/incidencias`, {
                params: { limite: 20 },
                signal: controlador.signal,
            })
                .then(({ data }) => {
                    if (activo) {
                        setIncidencias(Array.isArray(data?.data) ? data.data : []);
                    }
                })
                .catch((error) => {
                    if (activo && !controlador.signal.aborted) {
                        setIncidencias([]);
                        setMensaje(
                            interpretarFallo(error, 'No se pudieron consultar las incidencias.')
                        );
                        if (estadoDe(error) === 403) setBloqueado(true);
                    }
                });
        }

        return () => {
            activo = false;
            controlador.abort();
        };
    }, [puerta]);

    useEffect(() => {
        if (!puerta) return undefined;

        let activo = true;
        let consultando = false;
        const controlador = new AbortController();

        const actualizar = async () => {
            if (consultando) return;
            consultando = true;

            try {
                const { data } = await api.get(`${puerta}/ingresos`, {
                    params: { limite: 5 },
                    signal: controlador.signal,
                });

                if (activo) {
                    setHistorial(Array.isArray(data?.data) ? data.data : []);
                }
            } catch (error) {
                if (activo && !controlador.signal.aborted && estadoDe(error) === 403) {
                    setBloqueado(true);
                }
            } finally {
                consultando = false;
            }
        };

        const temporizador = setInterval(actualizar, 10000);

        return () => {
            activo = false;
            clearInterval(temporizador);
            controlador.abort();
        };
    }, [puerta]);

    const cambiarAula = (id) => {
        if (pendiente.current) return;
        invalidarContexto();
        setAulaId(id);
        setCandidato(null);
        setVeredicto(null);
        setHistorial([]);
        setIncidencias([]);
        setMensaje('');
        setBloqueado(false);
    };

    const cambiarIdentificacion = (nuevoTipo, nuevoIdentificador) => {
        if (pendiente.current) return;
        invalidarContexto();
        setTipo(nuevoTipo);
        setIdentificador(nuevoIdentificador);
        setCandidato(null);
        setVeredicto(null);
        setMensaje('');
    };

    const ejecutar = async (operacion) => {
        if (
            !puerta ||
            bloqueado ||
            pendiente.current ||
            !identificador.trim() ||
            (operacion === 'registrar' && candidato?.admisible !== true)
        ) {
            return;
        }

        pendiente.current = true;
        const version = versionContexto.current;
        const controlador = new AbortController();
        peticiones.current.add(controlador);
        setOcupado(true);
        setMensaje('');

        let registroIntentado = false;

        const cuerpo = {
            tipo,
            identificador: identificador.trim(),
        };

        try {
            if (operacion === 'consultar') {
                setCandidato(null);
                setVeredicto(null);

                let resultado;
                if (sesionColaborador) {
                    const { data } = await api.get(`${puerta}/candidato`, {
                        params: cuerpo,
                        signal: controlador.signal,
                    });
                    resultado = data?.data;
                } else {
                    const { data } = await api.post(`${puerta}/identificar`, cuerpo, {
                        signal: controlador.signal,
                    });
                    resultado = data?.data;
                }

                if (version !== versionContexto.current) return;

                if (!resultado || typeof resultado.admisible !== 'boolean') {
                    throw new Error('Respuesta de identificación incompleta.');
                }

                setCandidato({
                    ...resultado,
                    codigo:
                        tipo === 'qr'
                            ? 'QR verificado'
                            : (resultado.codigo ?? identificador.trim()),
                });
            } else {
                registroIntentado = true;
                const { data } = await api.post(`${puerta}/ingresos`, cuerpo, {
                    signal: controlador.signal,
                });

                if (version !== versionContexto.current) return;
                setVeredicto(data?.data ?? null);

                if (data?.data?.admitido === true) {
                    const respuesta = await api.get(`${puerta}/ingresos`, {
                        params: { limite: 5 },
                        signal: controlador.signal,
                    });

                    if (version !== versionContexto.current) return;
                    setHistorial(Array.isArray(respuesta.data?.data) ? respuesta.data.data : []);
                }
            }
        } catch (error) {
            if (controlador.signal.aborted || version !== versionContexto.current) {
                return;
            }

            const estado = estadoDe(error);

            if (
                operacion === 'registrar' &&
                estado === 409 &&
                error.response?.data?.data?.veredicto
            ) {
                setVeredicto(error.response.data.data);
            } else {
                setMensaje(
                    interpretarFallo(
                        error,
                        operacion === 'consultar'
                            ? 'No se pudo consultar al estudiante.'
                            : 'No se pudo registrar el ingreso.'
                    )
                );
            }

            if (estado === 403) setBloqueado(true);
        } finally {
            if (
                registroIntentado &&
                !controlador.signal.aborted &&
                version === versionContexto.current
            ) {
                try {
                    const respuesta = await api.get(`${puerta}/incidencias`, {
                        params: { limite: 20 },
                        signal: controlador.signal,
                    });

                    if (version === versionContexto.current) {
                        setIncidencias(
                            Array.isArray(respuesta.data?.data) ? respuesta.data.data : []
                        );
                    }
                } catch (error) {
                    if (!controlador.signal.aborted && version === versionContexto.current) {
                        setMensaje(
                            (anterior) =>
                                anterior ||
                                interpretarFallo(
                                    error,
                                    'No se pudieron actualizar las incidencias.'
                                )
                        );

                        if (estadoDe(error) === 403) setBloqueado(true);
                    }
                }
            }

            peticiones.current.delete(controlador);
            pendiente.current = false;
            setOcupado(false);
        }
    };

    return (
        <div className="mx-auto max-w-5xl space-y-6 p-4 md:p-6">
            {sesionColaborador && (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-900 shadow-sm">
                    <div className="flex items-center gap-2">
                        <DoorOpen className="h-5 w-5 text-primary-600 shrink-0" />
                        <span>
                            Colaborador de aula:{' '}
                            <strong>{sesionColaborador.nombre_colaborador}</strong> · Aula{' '}
                            <strong>{sesionColaborador.aula_nombre}</strong>
                            {sesionColaborador.expira_en && (
                                <span className="text-primary-700 ml-1">
                                    · Acceso válido hasta las{' '}
                                    <strong>{sesionColaborador.expira_en.slice(11, 16)}</strong>
                                </span>
                            )}
                        </span>
                    </div>
                    <button
                        type="button"
                        onClick={cerrarSesionColaborador}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-danger-700 hover:text-danger-800 hover:underline"
                    >
                        <LogOut className="h-3.5 w-3.5" /> Salir de aula
                    </button>
                </div>
            )}

            <header>
                <h1 className="text-2xl font-bold text-slate-900">Punto de control</h1>
                <p className="mt-1 text-sm text-slate-600">
                    Identifica al estudiante y confirma su ingreso al examen.
                </p>
            </header>

            <section className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 md:grid-cols-2">
                {cargandoExamenes ? (
                    <p role="status">Cargando aulas asignadas…</p>
                ) : aulas.length === 0 ? (
                    <p>No tienes exámenes y aulas asignados para operar.</p>
                ) : (
                    <p className="text-sm text-slate-600">
                        Selecciona el aula. El examen correspondiente se identificará
                        automáticamente al consultar al estudiante.
                    </p>
                )}

                <Aula
                    aulas={aulas}
                    valor={aulaId}
                    alCambiar={cambiarAula}
                    deshabilitado={ocupado}
                />
            </section>

            <section className="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
                <h2 className="text-lg font-semibold text-slate-900">Identificación</h2>

                <div className="grid gap-4 md:grid-cols-3">
                    <label className="block space-y-2 text-sm font-medium">
                        Tipo
                        <select
                            aria-label="Tipo de identificación"
                            disabled={ocupado}
                            value={tipo}
                            onChange={(e) => cambiarIdentificacion(e.target.value, '')}
                            className="block w-full rounded-lg border border-slate-300 p-2"
                        >
                            <option value="codigo">Código universitario</option>
                            <option value="documento">Documento</option>
                            <option value="qr">Contenido QR</option>
                        </select>
                    </label>

                    <label className="block space-y-2 text-sm font-medium md:col-span-2">
                        Identificador
                        <input
                            aria-label="Identificador"
                            disabled={ocupado}
                            value={identificador}
                            onChange={(e) => cambiarIdentificacion(tipo, e.target.value)}
                            maxLength={2048}
                            placeholder="Ingresar identificación"
                            className="block w-full rounded-lg border border-slate-300 p-2"
                        />
                    </label>
                </div>

                <button
                    type="button"
                    disabled={!puerta || ocupado || bloqueado}
                    onClick={() => setLectorAbierto(true)}
                    className="ml-2 rounded-lg border border-slate-300 px-4 py-2 font-medium text-slate-900 disabled:opacity-50"
                >
                    Abrir cámara QR
                </button>

                {lectorAbierto && (
                    <LectorQr
                        alCerrar={() => setLectorAbierto(false)}
                        alDetectar={(contenido) => {
                            cambiarIdentificacion('qr', contenido);
                        }}
                    />
                )}

                <button
                    type="button"
                    onClick={() => ejecutar('consultar')}
                    disabled={!puerta || ocupado || bloqueado || !identificador.trim()}
                    className="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 font-medium text-white disabled:opacity-50"
                >
                    <Search size={17} />
                    {ocupado ? 'Procesando…' : 'Consultar estudiante'}
                </button>

                {candidato && (
                    <div className="space-y-3 rounded-lg bg-slate-50 p-4">
                        <h3 className="font-semibold">Resultado de identificación</h3>
                        {candidato.nombre && (
                            <p className="text-base font-bold text-slate-900">{candidato.nombre}</p>
                        )}
                        <p className="break-all text-sm text-slate-700">
                            Identificador: {candidato.codigo}
                        </p>
                        <p className="text-sm font-semibold">
                            {candidato.ambiguo
                                ? 'Identificación ambigua entre exámenes'
                                : (RESULTADOS[candidato.veredicto] ??
                                  candidato.veredicto ??
                                  'No se encontró un examen inequívoco')}
                        </p>
                        {candidato.examen_id != null && (
                            <p className="text-sm text-slate-600">
                                Examen identificado: {candidato.examen_id}
                            </p>
                        )}

                        <button
                            type="button"
                            disabled={
                                ocupado ||
                                bloqueado ||
                                veredicto !== null ||
                                candidato.admisible !== true
                            }
                            onClick={() => ejecutar('registrar')}
                            className="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 font-medium text-white disabled:opacity-50"
                        >
                            <ClipboardCheck size={17} />
                            Confirmar ingreso
                        </button>
                    </div>
                )}

                {veredicto && (
                    <div role="status" className="rounded-lg border border-slate-300 p-4">
                        <p className="font-semibold">
                            {RESULTADOS[veredicto.veredicto] ??
                                veredicto.veredicto ??
                                'Resultado desconocido'}
                        </p>
                        <p className="text-sm">
                            {veredicto.admitido
                                ? 'Ingreso registrado correctamente.'
                                : 'Ingreso no autorizado.'}
                        </p>
                    </div>
                )}

                {mensaje && (
                    <p role="alert" className="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                        {mensaje}
                    </p>
                )}

                {bloqueado && (
                    <p className="text-sm font-semibold text-red-700">
                        Punto de control bloqueado por falta de asignación.
                    </p>
                )}
            </section>

            <section className="rounded-xl border border-slate-200 bg-white p-5">
                <div className="mb-4 flex items-center justify-between gap-2">
                    <h2 className="text-lg font-semibold">Ingresos recientes</h2>
                    <RefreshCw size={18} className="text-slate-500" />
                </div>

                {historial.length === 0 ? (
                    <p className="text-sm text-slate-600">No hay ingresos para mostrar.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {historial.map((registro, indice) => (
                            <li
                                key={registro.ingreso_id ?? indice}
                                className="py-3 text-sm text-slate-700"
                            >
                                Ingreso #{registro.ingreso_id ?? '—'} · Estudiante{' '}
                                {registro.estudiante_id ?? '—'}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section
                aria-label="Incidencias de ingreso"
                className="space-y-4 rounded-xl border border-slate-200 bg-white p-5"
            >
                <h2 className="text-lg font-semibold text-slate-900">Incidencias de ingreso</h2>

                {incidencias.length === 0 ? (
                    <p className="text-sm text-slate-600">No hay incidencias para mostrar.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {incidencias.map((incidencia) => (
                            <li
                                key={incidencia.incidencia_id}
                                className="space-y-2 py-3 text-sm text-slate-700"
                            >
                                <p className="font-semibold break-words text-slate-900">
                                    {incidencia.nombres || incidencia.apellidos
                                        ? [incidencia.apellidos, incidencia.nombres]
                                              .filter(Boolean)
                                              .join(', ')
                                        : 'Estudiante no encontrado'}
                                </p>
                                {incidencia.codigo && (
                                    <p className="break-all">Código: {incidencia.codigo}</p>
                                )}
                                <p className="break-words">
                                    Rechazo:{' '}
                                    {RESULTADOS[incidencia.tipo_rechazo] ?? incidencia.tipo_rechazo}
                                </p>
                                <p className="whitespace-pre-wrap break-words">
                                    Detalle: {incidencia.detalle}
                                </p>
                                <p className="break-words text-slate-500">
                                    Fecha: {incidencia.registrado_en}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}
