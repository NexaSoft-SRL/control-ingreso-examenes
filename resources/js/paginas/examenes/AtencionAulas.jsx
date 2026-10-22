import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { Copy, Check, DoorOpen, KeyRound, Plus, RefreshCw, Users, X } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import Insignia from '../../componentes/Insignia';
import { plural } from '../../utiles/texto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

export default function AtencionAulas({
    aulas,
    aulasGuardadas = null,
    nombreAula,
    ubicacionAula,
    docentesDisponibles = [],
    cuenta,
    atencion,
    onCambiarAtencion,
    examenId = null,
    fechaExamen = '',
    errores = {},
}) {
    const [aviso, avisar] = useAviso();

    const [misAuxiliares, setMisAuxiliares] = useState([]);
    const [cargandoAuxiliares, setCargandoAuxiliares] = useState(false);
    const [busqueda, setBusqueda] = useState('');
    const [resultadosBusqueda, setResultadosBusqueda] = useState(null);
    const [buscando, setBuscando] = useState(false);

    // Formulario de auxiliar nuevo (CA-6, CA-7)
    const [nuevoNombre, setNuevoNombre] = useState('');
    const [nuevoCodigo, setNuevoCodigo] = useState('');
    const [falloNombre, setFalloNombre] = useState(null);
    const [falloCodigo, setFalloCodigo] = useState(null);
    const [creandoAuxiliar, setCreandoAuxiliar] = useState(false);
    const [formKey, setFormKey] = useState(0);

    // Diálogo para quitar auxiliar de la lista (CA-8)
    const [auxiliarParaQuitar, setAuxiliarParaQuitar] = useState(null);
    const [quitando, setQuitando] = useState(false);

    // Diálogo «Código de acceso» por aula (CA-11, CA-12, CA-13)
    const [aulaParaCodigo, setAulaParaCodigo] = useState(null);
    const [generandoCodigo, setGenerandoCodigo] = useState(false);
    const [copiado, setCopiado] = useState(false);

    // Diálogo para añadir auxiliares a un aula específica
    const [aulaSeleccionadaParaAuxiliar, setAulaSeleccionadaParaAuxiliar] = useState(null);

    // Cargar la lista habitual de auxiliares del docente (CA-5)
    useEffect(() => {
        let activo = true;
        setCargandoAuxiliares(true);

        api.get('/auxiliares')
            .then(({ data }) => {
                if (activo) {
                    setMisAuxiliares(Array.isArray(data?.data) ? data.data : []);
                }
            })
            .catch(() => {
                // Silencioso en carga inicial
            })
            .finally(() => {
                if (activo) setCargandoAuxiliares(false);
            });

        return () => {
            activo = false;
        };
    }, []);

    // Buscar auxiliares con tope de 20 (CA-5)
    useEffect(() => {
        const texto = busqueda.trim();
        if (texto.length < 3) {
            setResultadosBusqueda(null);
            return;
        }

        let activo = true;
        setBuscando(true);
        const temporizador = setTimeout(() => {
            api.get('/auxiliares', { params: { buscar: texto } })
                .then(({ data }) => {
                    if (activo) {
                        const lista = Array.isArray(data?.data) ? data.data : [];
                        setResultadosBusqueda(lista.slice(0, 20));
                    }
                })
                .catch(() => {
                    if (activo) setResultadosBusqueda([]);
                })
                .finally(() => {
                    if (activo) setBuscando(false);
                });
        }, 300);

        return () => {
            activo = false;
            clearTimeout(temporizador);
        };
    }, [busqueda]);

    // CA-4: Repartir con más de dos aulas y al menos un auxiliar
    const repartir = () => {
        if (aulas.length <= 2 || misAuxiliares.length === 0) return;

        const nuevo = { ...atencion };
        aulas.forEach((aulaId, index) => {
            const actual = nuevo[aulaId] ?? { a_cargo: null, apoyo: [], codigo: null };
            if (index === 0) {
                nuevo[aulaId] = { ...actual, a_cargo: cuenta?.id ?? null };
            } else if (index - 1 < misAuxiliares.length) {
                const aux = misAuxiliares[index - 1];
                nuevo[aulaId] = {
                    ...actual,
                    a_cargo: aux.id,
                    apoyo: (actual.apoyo ?? []).filter((a) => a.id !== aux.id),
                };
            } else {
                nuevo[aulaId] = { ...actual, a_cargo: cuenta?.id ?? null };
            }
        });
        onCambiarAtencion(nuevo);
    };

    // CA-2 & CA-3: Cambiar docente o auxiliar a cargo
    const cambiarACargo = (aulaId, personaId) => {
        const idNum = personaId ? Number(personaId) : null;
        const actual = atencion[aulaId] ?? { a_cargo: null, apoyo: [], codigo: null };
        // CA-3: quien pasa a estar a cargo sale del apoyo
        const nuevoApoyo = (actual.apoyo ?? []).filter((a) => a.id !== idNum);

        onCambiarAtencion({
            ...atencion,
            [aulaId]: {
                ...actual,
                a_cargo: idNum,
                apoyo: nuevoApoyo,
            },
        });
    };

    // CA-3: Añadir auxiliar a apoyo
    const asignarAuxiliarApoyo = (aulaId, auxiliar) => {
        const actual = atencion[aulaId] ?? { a_cargo: null, apoyo: [], codigo: null };
        // No permitir si ya está a cargo o en apoyo
        if (actual.a_cargo === auxiliar.id) return;
        if ((actual.apoyo ?? []).some((a) => a.id === auxiliar.id)) return;

        onCambiarAtencion({
            ...atencion,
            [aulaId]: {
                ...actual,
                apoyo: [...(actual.apoyo ?? []), auxiliar],
            },
        });
    };

    // CA-3: Quitar auxiliar de apoyo con la equis
    const quitarAuxiliarApoyo = (aulaId, auxiliarId) => {
        const actual = atencion[aulaId] ?? { a_cargo: null, apoyo: [], codigo: null };
        onCambiarAtencion({
            ...atencion,
            [aulaId]: {
                ...actual,
                apoyo: (actual.apoyo ?? []).filter((a) => a.id !== auxiliarId),
            },
        });
    };

    // CA-6 & CA-7: Dar de alta nuevo auxiliar
    const agregarNuevoAuxiliar = async (e) => {
        e.preventDefault();
        setFalloNombre(null);
        setFalloCodigo(null);

        const nombre = nuevoNombre.trim();
        const codigo = nuevoCodigo.trim();

        let hayError = false;
        if (!nombre) {
            setFalloNombre('Obligatorio');
            hayError = true;
        } else if (nombre.length < 3) {
            setFalloNombre('Mínimo 3 caracteres');
            hayError = true;
        }

        if (!codigo) {
            setFalloCodigo('Obligatorio');
            hayError = true;
        } else if (!/^[0-9]{5,10}$/.test(codigo)) {
            setFalloCodigo('Entre 5 y 10 dígitos');
            hayError = true;
        }

        if (hayError) return;

        setCreandoAuxiliar(true);
        try {
            const { data } = await api.post('/auxiliares', {
                nombre_completo: nombre,
                codigo_estudiante: codigo,
            });

            const nuevo = data?.data;
            if (nuevo) {
                setMisAuxiliares((prev) => {
                    if (prev.some((a) => a.id === nuevo.id)) return prev;
                    return [...prev, nuevo];
                });
            }

            if (data?.message === 'Auxiliar ya registrado') {
                avisar('Auxiliar ya registrado');
            } else {
                avisar('Auxiliar agregado');
            }

            setNuevoNombre('');
            setNuevoCodigo('');
            setFormKey((k) => k + 1);
        } catch (error) {
            const erroresServidor = erroresDe(error);
            if (erroresServidor?.nombre_completo) {
                setFalloNombre(erroresServidor.nombre_completo);
            }
            if (erroresServidor?.codigo_estudiante) {
                setFalloCodigo(erroresServidor.codigo_estudiante);
            }
            if (mensajeDe(error) === 'Ya existe') {
                setFalloCodigo('Ya existe');
            } else if (!erroresServidor?.nombre_completo && !erroresServidor?.codigo_estudiante) {
                avisar(mensajeDe(error, 'Error al registrar auxiliar'), 'error');
            }
        } finally {
            setCreandoAuxiliar(false);
        }
    };

    // CA-8: Quitar auxiliar de la lista con confirmación
    const confirmarQuitarAuxiliar = async () => {
        if (!auxiliarParaQuitar) return;
        const auxId = auxiliarParaQuitar.id;
        setQuitando(true);

        try {
            await api.delete(`/auxiliares/${auxId}`);
            // Quitar de la lista
            setMisAuxiliares((prev) => prev.filter((a) => a.id !== auxId));

            // Quitar de las aulas asignadas (a cargo vuelve a quien registra)
            const nuevo = { ...atencion };
            Object.keys(nuevo).forEach((aulaId) => {
                const info = nuevo[aulaId];
                if (info) {
                    const cambioCargo =
                        info.a_cargo === auxId ? (cuenta?.id ?? null) : info.a_cargo;
                    const nuevoApoyo = (info.apoyo ?? []).filter((a) => a.id !== auxId);
                    nuevo[aulaId] = {
                        ...info,
                        a_cargo: cambioCargo,
                        apoyo: nuevoApoyo,
                    };
                }
            });
            onCambiarAtencion(nuevo);

            avisar('Auxiliar quitado');
            setAuxiliarParaQuitar(null);
        } catch (error) {
            const msg = mensajeDe(error);
            if (msg.includes('pendiente') || msg === 'Atiende un examen pendiente') {
                avisar('Atiende un examen pendiente', 'error');
            } else {
                avisar(msg, 'error');
            }
            setAuxiliarParaQuitar(null);
        } finally {
            setQuitando(false);
        }
    };

    // CA-11, CA-12, CA-13: Generar o regenerar código
    const generarCodigoAula = async (aulaId, esRegenerar = false) => {
        if (!examenId) return;
        setGenerandoCodigo(true);

        try {
            const { data } = await api.post(`/examenes/${examenId}/aulas/${aulaId}/codigo`);
            const codigoGenerado = data?.data?.codigo;

            const actual = atencion[aulaId] ?? { a_cargo: null, apoyo: [], codigo: null };
            onCambiarAtencion({
                ...atencion,
                [aulaId]: {
                    ...actual,
                    codigo: codigoGenerado,
                },
            });

            if (esRegenerar) {
                avisar('Código regenerado');
            } else {
                avisar('Código generado');
            }
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudo generar el código'), 'error');
        } finally {
            setGenerandoCodigo(false);
        }
    };

    const copiarCodigo = async (codigo) => {
        if (!codigo) return;
        let copiadoExito = false;
        if (navigator?.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(codigo);
                copiadoExito = true;
            } catch {
                copiadoExito = false;
            }
        }

        if (!copiadoExito) {
            try {
                const textarea = document.createElement('textarea');
                textarea.value = codigo;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                copiadoExito = document.execCommand('copy');
                document.body.removeChild(textarea);
            } catch {
                copiadoExito = false;
            }
        }

        if (copiadoExito) {
            setCopiado(true);
            avisar('Código copiado');
            setTimeout(() => setCopiado(false), 2000);
        } else {
            avisar('Error al copiar el código', 'error');
        }
    };

    // CA-1: Si el examen no tiene aulas muestra «Sin aulas»
    if (aulas.length === 0) {
        return (
            <div className="mt-6 rounded-xl border border-slate-200 bg-white p-5">
                <h3 className="text-base font-semibold text-slate-900">Quién atiende</h3>
                <p className="mt-2 text-sm text-slate-500">Sin aulas</p>
                {aviso}
            </div>
        );
    }

    const cantidadAulas = aulas.length;
    const puedeRepartir = cantidadAulas > 2 && misAuxiliares.length > 0;

    return (
        <div className="mt-6 space-y-6">
            {/* Encabezado del paso (CA-1, CA-4) */}
            <div className="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h3 className="text-base font-semibold text-slate-900">
                                4. Quién atiende
                            </h3>
                            <Insignia tono="neutro">{plural(cantidadAulas, 'aula')}</Insignia>
                        </div>
                    </div>

                    {/* CA-4: Botón Repartir */}
                    {puedeRepartir && (
                        <Boton
                            type="button"
                            tamano="chico"
                            variante="secundario"
                            onClick={repartir}
                            title="Deja un auxiliar a cargo de cada aula y las que sobran con el docente"
                        >
                            Repartir
                        </Boton>
                    )}
                </div>

                {/* Listado de aulas asignadas (CA-1, CA-2, CA-3, CA-11) */}
                <div className="grid gap-4 md:grid-cols-2">
                    {aulas.map((aulaId) => {
                        const info = atencion[aulaId] ?? { a_cargo: null, apoyo: [], codigo: null };
                        const nombre = nombreAula(aulaId);
                        const ubicacion = ubicacionAula ? ubicacionAula(aulaId) : '';
                        const aCargoId = info.a_cargo ?? cuenta?.id ?? '';
                        const listaApoyo = info.apoyo ?? [];
                        const errorAula =
                            errores[`personal.${aulaId}`] || errores[`aulas.${aulaId}`];

                        // Auxiliares que se pueden añadir a apoyo (que no estén a cargo ni en apoyo ya)
                        const auxiliaresParaApoyo = misAuxiliares.filter(
                            (a) => a.id !== aCargoId && !listaApoyo.some((p) => p.id === a.id)
                        );

                        return (
                            <div
                                key={aulaId}
                                className="rounded-lg border border-slate-200 bg-slate-50/60 p-4 space-y-3"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <span className="font-semibold text-slate-800 flex items-center gap-1.5 text-sm">
                                            <DoorOpen className="h-4 w-4 text-slate-500" />
                                            Aula {nombre}
                                        </span>
                                        {ubicacion && (
                                            <p className="text-xs text-slate-500 mt-0.5">
                                                {ubicacion}
                                            </p>
                                        )}
                                    </div>

                                    {/* CA-11: Llave para abrir «Código de acceso» */}
                                    {(() => {
                                        const aulaGuardada = Boolean(
                                            examenId &&
                                                (aulasGuardadas !== null
                                                    ? aulasGuardadas.includes(aulaId)
                                                    : true)
                                        );
                                        return (
                                            <button
                                                type="button"
                                                disabled={!aulaGuardada}
                                                onClick={() => setAulaParaCodigo(aulaId)}
                                                aria-label={`Código de acceso del aula ${nombre}`}
                                                title={
                                                    aulaGuardada
                                                        ? 'Código de acceso'
                                                        : 'Disponible tras guardar el examen'
                                                }
                                                className={`flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors ${
                                                    aulaGuardada
                                                        ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                                                        : 'border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed'
                                                } ${FOCO}`}
                                            >
                                                <KeyRound className="h-3.5 w-3.5" />
                                                {info.codigo ? info.codigo : 'Llave'}
                                            </button>
                                        );
                                    })()}
                                </div>

                                {/* CA-2: A cargo */}
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 mb-1">
                                        A cargo
                                    </label>
                                    <select
                                        aria-label={`A cargo del aula ${nombre}`}
                                        value={aCargoId}
                                        onChange={(e) => cambiarACargo(aulaId, e.target.value)}
                                        className={`w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 ${FOCO}`}
                                    >
                                        <optgroup label="Docentes">
                                            {cuenta && (
                                                <option value={cuenta.id}>
                                                    {cuenta.nombre}
                                                </option>
                                            )}
                                            {docentesDisponibles
                                                .filter((d) => d.id !== cuenta?.id)
                                                .map((doc) => (
                                                    <option key={doc.id} value={doc.id}>
                                                        {doc.nombre}
                                                    </option>
                                                ))}
                                        </optgroup>
                                        {misAuxiliares.length > 0 && (
                                            <optgroup label="Auxiliares">
                                                {misAuxiliares.map((aux) => (
                                                    <option key={aux.id} value={aux.id}>
                                                        {aux.nombre} (Auxiliar)
                                                    </option>
                                                ))}
                                            </optgroup>
                                        )}
                                    </select>
                                </div>

                                {/* CA-3: Apoyo */}
                                <div>
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="text-xs font-medium text-slate-700">
                                            Apoyo ({listaApoyo.length})
                                        </label>
                                        {auxiliaresParaApoyo.length > 0 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setAulaSeleccionadaParaAuxiliar(aulaId)
                                                }
                                                className="inline-flex items-center gap-0.5 text-xs font-medium text-primary-600 hover:text-primary-800"
                                            >
                                                <Plus className="h-3 w-3" /> Sumar apoyo
                                            </button>
                                        )}
                                    </div>

                                    {listaApoyo.length > 0 ? (
                                        <ul
                                            className="flex flex-wrap gap-1.5"
                                            aria-label={`Apoyo del aula ${nombre}`}
                                        >
                                            {listaApoyo.map((aux) => (
                                                <li
                                                    key={aux.id}
                                                    className="inline-flex items-center gap-1 rounded-full bg-slate-200/90 px-2.5 py-0.5 text-xs text-slate-800"
                                                >
                                                    <span>{aux.nombre}</span>
                                                    <button
                                                        type="button"
                                                        aria-label={`Quitar a ${aux.nombre} de ${nombre}`}
                                                        onClick={() =>
                                                            quitarAuxiliarApoyo(aulaId, aux.id)
                                                        }
                                                        className="h-3.5 w-3.5 rounded-full hover:bg-slate-300 text-slate-600 inline-flex items-center justify-center"
                                                    >
                                                        <X className="h-3 w-3" />
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-xs text-slate-500 italic">Sin apoyo</p>
                                    )}
                                </div>

                                {errorAula && (
                                    <p role="alert" className="text-xs text-danger-600 mt-1">
                                        {errorAula}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Sección de Auxiliares: lista, búsqueda y alta (CA-5, CA-6, CA-7, CA-8) */}
            <div className="rounded-xl border border-slate-200 bg-white p-5 space-y-5">
                <div className="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h4 className="text-sm font-semibold text-slate-900 flex items-center gap-2">
                        <Users className="h-4 w-4 text-primary-600" />
                        Auxiliares {misAuxiliares.length > 0 ? `(${misAuxiliares.length})` : ''}
                    </h4>
                </div>

                {/* CA-5: Lista de auxiliares del docente o «Sin auxiliares» */}
                <div>
                    {cargandoAuxiliares ? (
                        <p className="text-xs text-slate-500">Cargando auxiliares...</p>
                    ) : misAuxiliares.length > 0 ? (
                        <ul
                            aria-label="Auxiliares registrados"
                            className="divide-y divide-slate-100 rounded-lg border border-slate-200"
                        >
                            {misAuxiliares.map((aux) => (
                                <li
                                    key={aux.id}
                                    className="flex items-center justify-between p-3 text-xs"
                                >
                                    <div>
                                        <p className="font-semibold text-slate-800">{aux.nombre}</p>
                                        <p className="text-slate-500">
                                            {aux.codigo} · {aux.correo}
                                        </p>
                                    </div>
                                    {/* CA-8: Quitar auxiliar con equis */}
                                    <button
                                        type="button"
                                        aria-label={`Quitar auxiliar ${aux.nombre}`}
                                        onClick={() => setAuxiliarParaQuitar(aux)}
                                        className="p-1.5 text-slate-400 hover:text-danger-600 hover:bg-slate-100 rounded-lg transition-colors"
                                        title="Quitar auxiliar"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-xs text-slate-500 italic">Sin auxiliares</p>
                    )}
                </div>

                {/* CA-5: Buscar auxiliar global */}
                <div className="space-y-2 border-t border-slate-200 pt-4">
                    <Buscador
                        etiqueta="Buscar auxiliar"
                        placeholder="Buscar auxiliar"
                        valor={busqueda}
                        onCambiar={setBusqueda}
                    />

                    {busqueda.trim().length >= 3 && (
                        <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-2">
                            {buscando ? (
                                <p className="text-xs text-slate-500 p-2">Buscando auxiliares...</p>
                            ) : (resultadosBusqueda ?? []).length > 0 ? (
                                <ul className="divide-y divide-slate-100 max-h-48 overflow-y-auto">
                                    {resultadosBusqueda.map((aux) => {
                                        const yaEstaEnLista = misAuxiliares.some(
                                            (a) => a.id === aux.id
                                        );
                                        return (
                                            <li
                                                key={aux.id}
                                                className="flex items-center justify-between p-2 text-xs hover:bg-slate-100/80 rounded"
                                            >
                                                <div>
                                                    <span className="font-medium text-slate-800">
                                                        {aux.nombre}
                                                    </span>
                                                    <span className="text-slate-500 ml-1.5">
                                                        {aux.codigo} · {aux.correo}
                                                    </span>
                                                </div>
                                                <Boton
                                                    type="button"
                                                    tamano="chico"
                                                    variante={
                                                        yaEstaEnLista ? 'secundario' : 'primario'
                                                    }
                                                    disabled={yaEstaEnLista}
                                                    onClick={async () => {
                                                        try {
                                                            const { data } = await api.post(
                                                                '/auxiliares',
                                                                {
                                                                    auxiliar_id: aux.id,
                                                                    nombre_completo: aux.nombre,
                                                                    codigo_estudiante:
                                                                        aux.codigo || aux.usuario,
                                                                }
                                                            );
                                                            const nuevo = data?.data ?? aux;
                                                            setMisAuxiliares((prev) => {
                                                                if (
                                                                    prev.some(
                                                                        (a) => a.id === nuevo.id
                                                                    )
                                                                ) {
                                                                    return prev;
                                                                }
                                                                return [...prev, nuevo];
                                                            });
                                                            avisar('Auxiliar agregado');
                                                            setBusqueda('');
                                                        } catch (e) {
                                                            avisar(
                                                                mensajeDe(
                                                                    e,
                                                                    'Error al agregar auxiliar'
                                                                ),
                                                                'error'
                                                            );
                                                        }
                                                    }}
                                                >
                                                    {yaEstaEnLista ? 'Agregado' : 'Agregar'}
                                                </Boton>
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : (
                                <p className="text-xs text-slate-500 p-2 italic">
                                    Sin coincidencias en auxiliares activos.
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {/* CA-6, CA-7: Formulario de Auxiliar nuevo */}
                <form
                    key={formKey}
                    onSubmit={agregarNuevoAuxiliar}
                    noValidate
                    className="space-y-3 rounded-lg border border-slate-200 bg-slate-50/50 p-4"
                >
                    <h5 className="text-xs font-semibold uppercase tracking-wider text-slate-700">
                        Auxiliar nuevo
                    </h5>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Campo
                            etiqueta="Nombre completo"
                            requerido
                            value={nuevoNombre}
                            onChange={(e) => {
                                setNuevoNombre(e.target.value);
                                setFalloNombre(null);
                            }}
                            error={falloNombre}
                            placeholder="Ej. Juan Pérez López"
                        />
                        <Campo
                            etiqueta="Código universitario"
                            requerido
                            value={nuevoCodigo}
                            onChange={(e) => {
                                setNuevoCodigo(e.target.value);
                                setFalloCodigo(null);
                            }}
                            error={falloCodigo}
                            placeholder="Ej. 202104523"
                        />
                    </div>
                    <div className="flex justify-end pt-1">
                        <Boton type="submit" disabled={creandoAuxiliar}>
                            {creandoAuxiliar ? 'Agregando...' : 'Agregar'}
                        </Boton>
                    </div>
                </form>
            </div>

            {/* Modal para sumar apoyo a un aula específica */}
            {aulaSeleccionadaParaAuxiliar !== null && (
                <Dialogo
                    abierto={aulaSeleccionadaParaAuxiliar !== null}
                    onCerrar={() => setAulaSeleccionadaParaAuxiliar(null)}
                    titulo={`Sumar apoyo · Aula ${nombreAula(aulaSeleccionadaParaAuxiliar)}`}
                >
                    <div className="space-y-3">
                        <p className="text-xs text-slate-600">
                            Seleccionar auxiliar para apoyar en esta aula:
                        </p>
                        <ul className="divide-y divide-slate-100 max-h-56 overflow-y-auto border border-slate-200 rounded-lg">
                            {misAuxiliares
                                .filter(
                                    (aux) =>
                                        aux.id !==
                                            atencion[aulaSeleccionadaParaAuxiliar]?.a_cargo &&
                                        !(atencion[aulaSeleccionadaParaAuxiliar]?.apoyo ?? []).some(
                                            (p) => p.id === aux.id
                                        )
                                )
                                .map((aux) => (
                                    <li
                                        key={aux.id}
                                        className="flex items-center justify-between p-2.5 text-xs hover:bg-slate-50"
                                    >
                                        <div>
                                            <p className="font-semibold text-slate-800">
                                                {aux.nombre}
                                            </p>
                                            <p className="text-slate-500">
                                                {aux.codigo} · {aux.correo}
                                            </p>
                                        </div>
                                        <Boton
                                            type="button"
                                            tamano="chico"
                                            onClick={() => {
                                                asignarAuxiliarApoyo(
                                                    aulaSeleccionadaParaAuxiliar,
                                                    aux
                                                );
                                                setAulaSeleccionadaParaAuxiliar(null);
                                            }}
                                        >
                                            Sumar
                                        </Boton>
                                    </li>
                                ))}
                            {misAuxiliares.filter(
                                (aux) =>
                                    aux.id !== atencion[aulaSeleccionadaParaAuxiliar]?.a_cargo &&
                                    !(atencion[aulaSeleccionadaParaAuxiliar]?.apoyo ?? []).some(
                                        (p) => p.id === aux.id
                                    )
                            ).length === 0 && (
                                <li className="p-3 text-center text-xs text-slate-500 italic">
                                    No hay más auxiliares disponibles para esta aula.
                                </li>
                            )}
                        </ul>
                    </div>
                </Dialogo>
            )}

            {/* CA-8: Modal de confirmación «Quitar auxiliar» */}
            {auxiliarParaQuitar && (
                <Dialogo
                    abierto={Boolean(auxiliarParaQuitar)}
                    onCerrar={() => setAuxiliarParaQuitar(null)}
                    titulo={auxiliarParaQuitar ? `Quitar a ${auxiliarParaQuitar.nombre}` : 'Quitar auxiliar'}
                >
                    <div className="space-y-4">
                        <p className="text-sm text-slate-700">
                            Se quitará a <strong>{auxiliarParaQuitar.nombre}</strong> de la
                            lista de auxiliares y de las aulas asignadas.
                        </p>
                        <div className="flex justify-end gap-2 border-t border-slate-200 pt-3">
                            <Boton
                                type="button"
                                variante="secundario"
                                onClick={() => setAuxiliarParaQuitar(null)}
                                disabled={quitando}
                            >
                                Cancelar
                            </Boton>
                            <Boton
                                type="button"
                                variante="peligro"
                                onClick={confirmarQuitarAuxiliar}
                                disabled={quitando}
                            >
                                {quitando ? 'Quitando...' : 'Quitar auxiliar'}
                            </Boton>
                        </div>
                    </div>
                </Dialogo>
            )}

            {/* CA-11, CA-12, CA-13: Modal «Código de acceso» */}
            {aulaParaCodigo !== null && (
                <Dialogo
                    abierto={aulaParaCodigo !== null}
                    onCerrar={() => {
                        setAulaParaCodigo(null);
                        setCopiado(false);
                    }}
                    titulo="Código de acceso"
                >
                    {(() => {
                        const aulaId = aulaParaCodigo;
                        const nombre = nombreAula(aulaId);
                        const info = atencion[aulaId] ?? {};
                        const codigo = info.codigo;
                        const aCargoId = info.a_cargo ?? cuenta?.id;
                        const docenteObj = [cuenta, ...docentesDisponibles, ...misAuxiliares].find(
                            (p) => p?.id === aCargoId
                        );
                        const aCargoNombre = docenteObj?.nombre ?? 'Sin asignar';

                        return (
                            <div className="space-y-4">
                                <div className="rounded-lg bg-slate-50 p-3 text-xs space-y-1.5 border border-slate-200">
                                    <p>
                                        <span className="font-semibold text-slate-700">Aula:</span>{' '}
                                        {nombre}
                                    </p>
                                    <p>
                                        <span className="font-semibold text-slate-700">
                                            A cargo:
                                        </span>{' '}
                                        {aCargoNombre}
                                    </p>
                                    <p>
                                        <span className="font-semibold text-slate-700">
                                            Fecha del examen:
                                        </span>{' '}
                                        {fechaExamen || 'Sin fecha fijada'}
                                    </p>
                                </div>

                                {codigo ? (
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between rounded-lg border border-slate-300 bg-white p-3">
                                            <span className="font-mono text-xl font-bold tracking-widest text-slate-900">
                                                {codigo}
                                            </span>
                                            <div className="flex gap-2">
                                                <Boton
                                                    type="button"
                                                    tamano="chico"
                                                    variante="secundario"
                                                    onClick={() => copiarCodigo(codigo)}
                                                >
                                                    {copiado ? (
                                                        <Check className="h-3.5 w-3.5 text-success-600" />
                                                    ) : (
                                                        <Copy className="h-3.5 w-3.5" />
                                                    )}
                                                    {copiado ? 'Copiado' : 'Copiar'}
                                                </Boton>
                                                <Boton
                                                    type="button"
                                                    tamano="chico"
                                                    variante="secundario"
                                                    disabled={generandoCodigo}
                                                    onClick={() => generarCodigoAula(aulaId, true)}
                                                >
                                                    <RefreshCw className="h-3.5 w-3.5" />
                                                    Regenerar
                                                </Boton>
                                            </div>
                                        </div>
                                        <p className="text-xs text-slate-500">
                                            El código anterior dejará de ser válido.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        <p className="text-xs text-slate-600">
                                            Sin código generado.
                                        </p>
                                        <div className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-3">
                                            <span className="text-xs font-medium text-slate-500">
                                                Sin código
                                            </span>
                                            <Boton
                                                type="button"
                                                tamano="chico"
                                                disabled={generandoCodigo}
                                                onClick={() => generarCodigoAula(aulaId, false)}
                                            >
                                                {generandoCodigo ? 'Generando...' : 'Generar'}
                                            </Boton>
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })()}
                </Dialogo>
            )}

            {aviso}
        </div>
    );
}

AtencionAulas.propTypes = {
    aulas: PropTypes.arrayOf(PropTypes.number).isRequired,
    aulasGuardadas: PropTypes.arrayOf(PropTypes.number),
    nombreAula: PropTypes.func.isRequired,
    ubicacionAula: PropTypes.func,
    docentesDisponibles: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            nombre: PropTypes.string.isRequired,
        })
    ),
    cuenta: PropTypes.shape({
        id: PropTypes.number,
        nombre: PropTypes.string,
        rol: PropTypes.string,
    }),
    atencion: PropTypes.object.isRequired,
    onCambiarAtencion: PropTypes.func.isRequired,
    examenId: PropTypes.number,
    fechaExamen: PropTypes.string,
    errores: PropTypes.object,
};
