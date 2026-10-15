import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import LectorQr from './LectorQr';
import { ClipboardCheck, RefreshCw, Search } from 'lucide-react';
import { api } from '../../api/cliente';
import { estadoDe, mensajeDe } from '../../api/errores';
import SelectorExamen from '../../componentes/SelectorExamen';
import usarExamen from '../../componentes/usarExamen';

const RESULTADOS = {
    ADMITIDO: 'Ingreso autorizado',
    NO_HABILITADO: 'Estudiante no habilitado',
    OTRA_AULA: 'Corresponde a otra aula',
    YA_INGRESO: 'Ingreso registrado anteriormente',
    NO_CORRESPONDE: 'No corresponde al examen',
    EXPULSADO: 'Estudiante expulsado',
};

function urlPunto(examenId, aulaId) {
    return `/examenes/${examenId}/aulas/${aulaId}/punto-control`;
}

function interpretarFallo(error, alternativa) {
    const estado = estadoDe(error);

    if (estado === 403) {
        return 'No tienes asignación para operar en este examen y aula.';
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
    const [aulaId, setAulaId] = useState('');
    const [tipo, setTipo] = useState('codigo');
    const [identificador, setIdentificador] = useState('');
    const [candidato, setCandidato] = useState(null);
    const [veredicto, setVeredicto] = useState(null);
    const [historial, setHistorial] = useState([]);
    const [mensaje, setMensaje] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [bloqueado, setBloqueado] = useState(false);
    const pendiente = useRef(false);
    const versionContexto = useRef(0);
    const peticiones = useRef(new Set());
    const [lectorAbierto, setLectorAbierto] = useState(false);

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

    const [examen, elegirExamen] = usarExamen(examenes);
    const examenId = examen?.id ?? null;
    const aulas = Array.isArray(examen?.aulas) ? examen.aulas : [];
    const aulaValida = aulas.some((aula) => String(aula.id) === aulaId);
    const punto = examenId && aulaValida ? urlPunto(examenId, aulaId) : null;

    useEffect(() => {
        let activo = true;

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
    }, []);

    useEffect(() => {
        let activo = true;
        const controlador = new AbortController();

        if (punto) {
            api.get(`${punto}/ingresos`, {
                params: { limite: 20 },
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
    }, [punto]);

    const cambiarExamen = (id) => {
        if (pendiente.current) return;
        invalidarContexto();
        elegirExamen(id);
        setAulaId('');
        setCandidato(null);
        setVeredicto(null);
        setHistorial([]);
        setMensaje('');
        setBloqueado(false);
    };

    const cambiarAula = (id) => {
        if (pendiente.current) return;
        invalidarContexto();
        setAulaId(id);
        setCandidato(null);
        setVeredicto(null);
        setHistorial([]);
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
            !punto ||
            bloqueado ||
            pendiente.current ||
            !identificador.trim() ||
            (operacion === 'registrar' && !candidato)
        ) {
            return;
        }

        pendiente.current = true;
        const version = versionContexto.current;
        const controlador = new AbortController();
        peticiones.current.add(controlador);
        setOcupado(true);
        setMensaje('');

        const cuerpo = {
            tipo,
            identificador: identificador.trim(),
        };

        try {
            if (operacion === 'consultar') {
                setCandidato(null);
                setVeredicto(null);

                const { data } = await api.get(`${punto}/candidato`, {
                    params: cuerpo,
                    signal: controlador.signal,
                });

                if (version !== versionContexto.current) return;
                setCandidato(data?.data ?? null);
            } else {
                const { data } = await api.post(`${punto}/ingresos`, cuerpo, {
                    signal: controlador.signal,
                });

                if (version !== versionContexto.current) return;
                setVeredicto(data?.data ?? null);

                if (data?.data?.admitido === true) {
                    const respuesta = await api.get(`${punto}/ingresos`, {
                        params: { limite: 20 },
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

            if (operacion === 'registrar' && estado === 409) {
                setVeredicto(
                    error.response?.data?.data ?? {
                        veredicto: 'YA_INGRESO',
                        admitido: false,
                    }
                );
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
            peticiones.current.delete(controlador);
            pendiente.current = false;
            setOcupado(false);
        }
    };

    return (
        <div className="mx-auto max-w-5xl space-y-6 p-4 md:p-6">
            <header>
                <h1 className="text-2xl font-bold text-slate-900">Punto de control</h1>
                <p className="mt-1 text-sm text-slate-600">
                    Identifica al estudiante y confirma su ingreso al examen.
                </p>
            </header>

            <section className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 md:grid-cols-2">
                {cargandoExamenes ? (
                    <p role="status">Cargando exámenes…</p>
                ) : examenes.length === 0 ? (
                    <p>No tienes exámenes y aulas asignados para operar.</p>
                ) : (
                    <SelectorExamen
                        valor={examenId}
                        onCambiar={cambiarExamen}
                        examenes={examenes}
                        deshabilitado={ocupado}
                    />
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
                    disabled={!punto || ocupado || bloqueado}
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
                    disabled={!punto || ocupado || bloqueado || !identificador.trim()}
                    className="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 font-medium text-white disabled:opacity-50"
                >
                    <Search size={17} />
                    {ocupado ? 'Procesando…' : 'Consultar estudiante'}
                </button>

                {candidato && (
                    <div className="space-y-3 rounded-lg bg-slate-50 p-4">
                        <h3 className="font-semibold">{candidato.nombre}</h3>
                        <p className="text-sm text-slate-700">Código: {candidato.codigo}</p>
                        <p className="text-sm text-slate-700">
                            {candidato.habilitado === true
                                ? 'Habilitado'
                                : 'Habilitación no confirmada'}
                        </p>

                        <button
                            type="button"
                            disabled={ocupado || bloqueado || veredicto !== null}
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
        </div>
    );
}
