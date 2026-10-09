import PropTypes from 'prop-types';
import { useState } from 'react';
import { ArrowRight, CheckCircle2, DoorOpen, ScrollText, Users, X } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarEdificios from '../../api/usarEdificios';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Campo from '../../componentes/Campo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import MapaCampus from '../../componentes/MapaCampus';
import Pasos from '../../componentes/Pasos';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import { usarSesion } from '../../sesion/SesionContexto';
import { fechaLarga, normalizar, plural } from '../../utiles/texto';
import NormasDelExamen from './NormasDelExamen';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const PASOS = ['Examen', 'Grupos', 'Aulas', 'Listo'];
// Con más grupos que estos, la lista lleva buscador y desplazamiento propio.
const MUCHOS_GRUPOS = 8;
const VACIO = 'rounded-lg border border-slate-200 py-4 text-center text-sm text-slate-600';
const ERROR = 'mt-1.5 text-sm text-danger-600';

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');
const duracionValida = (texto) =>
    /^\d+$/.test(String(texto).trim()) && Number(texto) >= 15 && Number(texto) <= 480;
const alternar = (lista, valor) =>
    lista.includes(valor) ? lista.filter((v) => v !== valor) : [...lista, valor];
// En qué paso se corrige cada campo que rechaza el servidor.
const pasoDe = (campo) => (campo.startsWith('grupos') ? 1 : campo.startsWith('aulas') ? 2 : 0);

function ubicacionDe(aula, facultad) {
    const lugar = [aula.edificio, aula.piso].filter(Boolean).join(' · ') || 'Sin edificio';
    return aula.facultad && aula.facultad !== facultad ? `${lugar} · ${aula.facultad}` : lugar;
}

const FORMA_GRUPO = PropTypes.shape({
    id: PropTypes.number.isRequired,
    codigo: PropTypes.string.isRequired,
    docente: PropTypes.string,
    inscritos: PropTypes.number,
});

const FORMA_DETALLE = PropTypes.shape({
    id: PropTypes.number.isRequired,
    asignatura: PropTypes.shape({
        id: PropTypes.number.isRequired,
        codigo: PropTypes.string,
        nombre: PropTypes.string.isRequired,
    }).isRequired,
    tipo: PropTypes.string.isRequired,
    tipo_texto: PropTypes.string,
    fecha: PropTypes.string.isRequired,
    hora: PropTypes.string.isRequired,
    duracion: PropTypes.number.isRequired,
    inscritos: PropTypes.number,
    estado: PropTypes.string,
    propio: PropTypes.bool,
    registrado_por: PropTypes.string,
    normas: PropTypes.string,
    normas_marcadas: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            plantilla_id: PropTypes.number,
            texto: PropTypes.string.isRequired,
        })
    ),
    grupos_detalle: PropTypes.arrayOf(FORMA_GRUPO),
    aulas_detalle: PropTypes.arrayOf(
        PropTypes.shape({ aula_id: PropTypes.number.isRequired, nombre: PropTypes.string })
    ),
});

function FilaGrupo({ g, marcado, onCambiar }) {
    return (
        <li>
            <label className="flex min-h-11 cursor-pointer items-center gap-3 px-4 py-2">
                <input
                    type="checkbox"
                    className="h-4 w-4 shrink-0"
                    checked={marcado}
                    onChange={onCambiar}
                    aria-label={`Grupo ${g.codigo} · ${g.docente ?? 'Sin docente'}`}
                />
                <span className="w-20 shrink-0 text-sm font-medium text-slate-800">
                    Grupo {g.codigo}
                </span>
                <span className="min-w-0 flex-1 truncate text-sm text-slate-600">
                    {g.docente ?? 'Sin docente'}
                </span>
                <span className="shrink-0 text-xs text-slate-600">{g.inscritos}</span>
            </label>
        </li>
    );
}

FilaGrupo.propTypes = {
    g: FORMA_GRUPO.isRequired,
    marcado: PropTypes.bool.isRequired,
    onCambiar: PropTypes.func.isRequired,
};

function FilaAula({ aula, ubicacion, marcado, sugerida, compartida, onCambiar }) {
    return (
        <li>
            <label
                className={`flex min-h-11 cursor-pointer flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 ${marcado ? 'bg-primary-50' : ''}`}
            >
                <input
                    type="checkbox"
                    className="h-4 w-4 shrink-0"
                    checked={marcado}
                    onChange={onCambiar}
                    aria-label={`Aula ${aula.nombre}`}
                />
                <span className="w-16 shrink-0 truncate text-sm font-semibold text-slate-800">
                    {aula.nombre}
                </span>
                <span className="min-w-0 flex-1 truncate text-xs text-slate-600">{ubicacion}</span>
                {sugerida && (
                    <span className="shrink-0 text-xs font-medium text-primary-700">Sugerida</span>
                )}
                {compartida && (
                    <span className="w-full min-w-0 pl-7 text-xs font-medium text-warning-700 sm:w-auto sm:pl-0">
                        Compartida · {compartida}
                    </span>
                )}
            </label>
        </li>
    );
}

FilaAula.propTypes = {
    aula: PropTypes.shape({ id: PropTypes.number.isRequired, nombre: PropTypes.string.isRequired })
        .isRequired,
    ubicacion: PropTypes.string.isRequired,
    marcado: PropTypes.bool.isRequired,
    sugerida: PropTypes.bool,
    compartida: PropTypes.string,
    onCambiar: PropTypes.func.isRequired,
};

// El examen tal como quedó guardado: lo que se ve tras registrar y lo que ve
// el docente de un grupo incluido, que no lo modifica.
function Resumen({ examen, recien = false }) {
    const { puede } = usarSesion();
    const grupos = examen.grupos_detalle ?? [];
    const aulas = examen.aulas_detalle ?? [];
    const marcadas = examen.normas_marcadas ?? [];
    const libres = (examen.normas ?? '').trim();

    return (
        <Tarjeta>
            <div className="flex items-start gap-3">
                {recien && <CheckCircle2 className="mt-0.5 h-6 w-6 shrink-0 text-success-600" />}
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <p className="min-w-0 break-words text-base font-semibold text-slate-900">
                            {examen.tipo_texto ?? examen.tipo} · {examen.asignatura.nombre}
                        </p>
                        {examen.estado && (
                            <Insignia tono="advertencia" punto>
                                {examen.estado}
                            </Insignia>
                        )}
                    </div>
                    <p className="text-sm text-slate-600">
                        {fechaLarga(examen.fecha)}, {examen.hora} · {examen.duracion} min ·{' '}
                        {plural(grupos.length, 'grupo')} ({grupos.map((g) => g.codigo).join(', ')})
                        · {plural(examen.inscritos ?? 0, 'inscrito')}
                    </p>
                    <p className="mt-2 flex items-start gap-2 text-sm text-slate-700">
                        <DoorOpen className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                        <span className="min-w-0 break-words">
                            {aulas.length > 0
                                ? `${plural(aulas.length, 'aula')}: ${aulas.map((a) => a.nombre).join(', ')}`
                                : 'Sin aulas'}
                        </span>
                    </p>
                    <div className="mt-1 flex items-start gap-2 text-sm text-slate-700">
                        <ScrollText className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                        {marcadas.length > 0 || libres ? (
                            <div className="min-w-0 flex-1">
                                {marcadas.length > 0 && (
                                    <ul aria-label="Normas" className="list-inside list-disc">
                                        {marcadas.map((n) => (
                                            <li key={n.id} className="break-words">
                                                {n.texto}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {libres && (
                                    <p className="whitespace-pre-line break-words">{libres}</p>
                                )}
                            </div>
                        ) : (
                            <span>Sin normas</span>
                        )}
                    </div>
                    {!recien && examen.registrado_por && (
                        <p className="mt-2 text-xs text-slate-600">
                            Registrado por {examen.registrado_por}
                        </p>
                    )}
                </div>
            </div>
            <div className="mt-4 flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4">
                <Link
                    to="/examenes"
                    className={`inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 ${FOCO}`}
                >
                    Ver exámenes
                </Link>
                {aulas.length > 0 && puede('habilitacion') && (
                    <Link
                        to={`/habilitacion?examen=${examen.id}`}
                        className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700 ${FOCO}`}
                    >
                        Habilitar estudiantes <ArrowRight className="h-4 w-4" />
                    </Link>
                )}
            </div>
        </Tarjeta>
    );
}

Resumen.propTypes = { examen: FORMA_DETALLE.isRequired, recien: PropTypes.bool };

// Las normas del examen que quedaron sin plantilla (la plantilla se quitó).
const sueltasDe = (examen) => (examen?.normas_marcadas ?? []).filter((n) => !n.plantilla_id);

// El asistente: examen y normas, grupos, aulas y resumen. Todo se guarda de
// una vez al final (POST o PUT /examenes).
function Asistente({ asignaturas, tipos, periodos, inicial, pasoInicial, onExamen }) {
    const [examen, setExamen] = useState(inicial);
    const [paso, setPaso] = useState(pasoInicial);
    const [asignaturaId, setAsignaturaId] = useState(
        inicial?.asignatura.id ?? asignaturas[0]?.id ?? null
    );
    const [tipo, setTipo] = useState(inicial?.tipo ?? tipos[0]?.valor ?? '');
    const [fecha, setFecha] = useState(inicial?.fecha ?? '');
    const [hora, setHora] = useState(inicial?.hora ?? '');
    const [duracion, setDuracion] = useState(String(inicial?.duracion ?? '90'));
    const [normas, setNormas] = useState(inicial?.normas ?? '');
    const [marcadas, setMarcadas] = useState(() =>
        (inicial?.normas_marcadas ?? []).map((n) => n.plantilla_id).filter(Boolean)
    );
    const [conservadas, setConservadas] = useState(() => sueltasDe(inicial).map((n) => n.id));
    // `null`: todavía no se tocó; valen los grupos propios de la asignatura.
    const [grupos, setGrupos] = useState(() => inicial?.grupos_detalle?.map((g) => g.id) ?? null);
    const [aulas, setAulas] = useState(() => inicial?.aulas_detalle?.map((a) => a.aula_id) ?? []);
    const [buscado, setBuscado] = useState('');
    const [busqueda, setBusqueda] = useState('');
    const [edificio, setEdificio] = useState('');
    const [errores, setErrores] = useState({});
    const [rechazo, setRechazo] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();

    const asignatura = asignaturas.find((a) => a.id === asignaturaId) ?? null;
    const periodo = periodos.find((p) => p.codigo === asignatura?.periodo) ?? null;

    const opciones = usarConsulta('/examenes/opciones/grupos', {
        parametros: { asignatura_id: asignaturaId },
        activa: asignaturaId !== null,
    });
    const propios = opciones.datos?.propios ?? [];
    const otrosTodos = opciones.datos?.otros ?? [];
    const todos = [...propios, ...otrosTodos];
    const elegidosIds = grupos ?? propios.map((g) => g.id);
    const elegidos = todos.filter((g) => elegidosIds.includes(g.id));
    const inscritos = elegidos.reduce((s, g) => s + (g.inscritos ?? 0), 0);
    const textoGrupo = normalizar(buscado.trim());
    const otros = otrosTodos.filter(
        (g) =>
            !textoGrupo ||
            normalizar(`grupo ${g.codigo} ${g.docente ?? 'sin docente'}`).includes(textoGrupo)
    );
    const muchos = otrosTodos.length > MUCHOS_GRUPOS;
    const cargandoGrupos = opciones.cargando || opciones.actualizando;

    const todasLasAulas = usarConsulta('/aulas');
    const { edificios, caja } = usarEdificios();
    const enAulas = paso === 2;
    const opcionesAulas = usarConsulta('/examenes/opciones/aulas', {
        parametros: {
            grupos: elegidosIds,
            fecha,
            hora_inicio: hora,
            duracion_minutos: Number(duracion),
            examen_id: examen?.id,
        },
        activa:
            enAulas && elegidosIds.length > 0 && Boolean(fecha && hora) && duracionValida(duracion),
    });
    const sugeridas = opcionesAulas.datos?.sugeridas ?? [];
    const compartidas = opcionesAulas.datos?.compartidas ?? {};
    const catalogo = todasLasAulas.datos ?? [];
    const facultad = asignatura?.facultad ?? null;
    // Una sola lista: las sugeridas primero, después las de la facultad de
    // la asignatura y al final el resto.
    const orden = (a) => (sugeridas.includes(a.id) ? 0 : a.facultad === facultad ? 1 : 2);
    const ordenadas = [...catalogo].sort((a, b) => orden(a) - orden(b));
    const edificiosConAulas = [
        ...new Map(
            catalogo
                .filter((a) => a.edificio_id)
                .map((a) => [
                    a.edificio_id,
                    { id: a.edificio_id, nombre: a.edificio, facultad: a.facultad },
                ])
        ).values(),
    ].sort(
        (a, b) =>
            Number(a.facultad !== facultad) - Number(b.facultad !== facultad) ||
            String(a.nombre).localeCompare(String(b.nombre), 'es', { numeric: true })
    );
    const textoAula = normalizar(busqueda.trim());
    const delFiltro = (a) =>
        edificio === 'sugeridas'
            ? sugeridas.includes(a.id)
            : edificio === 'elegidas'
              ? aulas.includes(a.id)
              : edificio
                ? String(a.edificio_id) === edificio
                : true;
    const listaAulas = ordenadas.filter(
        (a) =>
            delFiltro(a) &&
            (!textoAula ||
                normalizar(`${a.nombre} ${ubicacionDe(a, facultad)}`).includes(textoAula))
    );
    const nombreAula = (id) =>
        catalogo.find((a) => a.id === id)?.nombre ??
        examen?.aulas_detalle?.find((a) => a.aula_id === id)?.nombre ??
        String(id);
    const edificiosElegidos = [
        ...new Set(
            aulas
                .map(
                    (id) =>
                        catalogo.find((a) => a.id === id)?.edificio_id ??
                        examen?.aulas_detalle?.find((a) => a.aula_id === id)?.edificio_id
                )
                .filter(Boolean)
        ),
    ];
    const fueraDeLaFacultad = aulas.some((id) => {
        const aula = catalogo.find((a) => a.id === id);
        return aula && aula.facultad !== facultad;
    });

    const quitarError = (...campos) =>
        setErrores((previos) =>
            Object.fromEntries(Object.entries(previos).filter(([c]) => !campos.includes(c)))
        );
    const errorDe = (prefijo) =>
        Object.entries(errores).find(([c]) => c === prefijo || c.startsWith(`${prefijo}.`))?.[1];

    const cambiarAsignatura = (valor) => {
        setAsignaturaId(Number(valor));
        setGrupos(null);
        setAulas([]);
        setBuscado('');
        quitarError('asignatura_id', 'grupos', 'aulas', 'fecha');
    };

    const cambiarGrupos = (siguiente) => {
        setGrupos(siguiente);
        setErrores((previos) =>
            Object.fromEntries(Object.entries(previos).filter(([c]) => !c.startsWith('grupos')))
        );
    };

    const cambiarAulas = (siguiente) => {
        setAulas(siguiente);
        setErrores((previos) =>
            Object.fromEntries(Object.entries(previos).filter(([c]) => !c.startsWith('aulas')))
        );
    };

    const continuarExamen = () => {
        const faltas = {};
        if (!fecha) faltas.fecha = 'Obligatorio';
        else if (periodo && (fecha < periodo.fecha_inicio || fecha > periodo.fecha_fin)) {
            faltas.fecha = `Fuera del período ${periodo.codigo}`;
        }
        if (!hora) faltas.hora_inicio = 'Obligatorio';
        if (!duracionValida(duracion)) faltas.duracion_minutos = 'Entre 15 y 480';
        setErrores(faltas);
        if (Object.keys(faltas).length === 0) setPaso(1);
    };

    const guardar = async () => {
        setEnviando(true);
        setRechazo(null);
        setErrores({});
        const cuerpo = {
            asignatura_id: asignaturaId,
            tipo,
            fecha,
            hora_inicio: hora,
            duracion_minutos: Number(duracion),
            normas: normas.trim(),
            normas_marcadas: marcadas,
            grupos: elegidosIds,
            aulas,
        };
        try {
            const { data } = examen
                ? await api.put(`/examenes/${examen.id}`, {
                      ...cuerpo,
                      normas_conservadas: conservadas,
                  })
                : await api.post('/examenes', cuerpo);
            const guardado = data.data;
            setExamen(guardado);
            setMarcadas(
                (guardado.normas_marcadas ?? []).map((n) => n.plantilla_id).filter(Boolean)
            );
            setConservadas(sueltasDe(guardado).map((n) => n.id));
            setGrupos((guardado.grupos_detalle ?? []).map((g) => g.id));
            setAulas((guardado.aulas_detalle ?? []).map((a) => a.aula_id));
            setPaso(3);
            onExamen(guardado);
            avisar(sinPunto(data.message ?? (examen ? 'Examen guardado' : 'Examen registrado')));
        } catch (fallo) {
            const campos = erroresDe(fallo);
            const nombres = Object.keys(campos);
            if (nombres.length > 0) {
                setErrores(campos);
                setPaso(Math.min(...nombres.map(pasoDe)));
            } else {
                setRechazo(mensajeDe(fallo, 'No se pudo guardar'));
            }
        } finally {
            setEnviando(false);
        }
    };

    const guardadas = Object.fromEntries(
        (examen?.normas_marcadas ?? [])
            .filter((n) => n.plantilla_id)
            .map((n) => [n.plantilla_id, n.texto])
    );

    return (
        <>
            <Pasos pasos={PASOS} actual={paso} onIr={setPaso} />

            {paso === 0 && (
                <Tarjeta titulo="1. Examen">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="min-w-0 sm:col-span-2">
                            <Seleccion
                                etiqueta="Asignatura"
                                requerido
                                value={asignaturaId ?? ''}
                                onChange={(e) => cambiarAsignatura(e.target.value)}
                            >
                                {asignaturas.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {[a.nombre, a.codigo].filter(Boolean).join(' · ')}
                                    </option>
                                ))}
                            </Seleccion>
                            {errores.asignatura_id && (
                                <p role="alert" className={ERROR}>
                                    {errores.asignatura_id}
                                </p>
                            )}
                        </div>
                        <div className="min-w-0">
                            <Seleccion
                                etiqueta="Tipo"
                                requerido
                                value={tipo}
                                onChange={(e) => {
                                    setTipo(e.target.value);
                                    quitarError('tipo', 'grupos');
                                }}
                            >
                                {tipos.map((t) => (
                                    <option key={t.valor} value={t.valor}>
                                        {t.etiqueta}
                                    </option>
                                ))}
                            </Seleccion>
                            {errores.tipo && (
                                <p role="alert" className={ERROR}>
                                    {errores.tipo}
                                </p>
                            )}
                        </div>
                        <Campo
                            etiqueta="Fecha"
                            requerido
                            type="date"
                            min={periodo?.fecha_inicio}
                            max={periodo?.fecha_fin}
                            className="min-w-0"
                            value={fecha}
                            onChange={(e) => {
                                setFecha(e.target.value);
                                quitarError('fecha');
                            }}
                            error={errores.fecha}
                        />
                        <Campo
                            etiqueta="Hora de inicio"
                            requerido
                            type="time"
                            className="min-w-0"
                            value={hora}
                            onChange={(e) => {
                                setHora(e.target.value);
                                quitarError('hora_inicio');
                            }}
                            error={errores.hora_inicio}
                        />
                        <Campo
                            etiqueta="Duración"
                            requerido
                            sufijo="min"
                            inputMode="numeric"
                            className="min-w-0"
                            value={duracion}
                            onChange={(e) => {
                                setDuracion(e.target.value);
                                quitarError('duracion_minutos');
                            }}
                            error={errores.duracion_minutos}
                            validar={(v) => (duracionValida(v) ? null : 'Entre 15 y 480')}
                        />
                    </div>
                    <div className="mt-4">
                        <NormasDelExamen
                            marcadas={marcadas}
                            onMarcadas={(siguiente) => {
                                setMarcadas(siguiente);
                                quitarError('normas_marcadas');
                            }}
                            texto={normas}
                            onTexto={(valor) => {
                                setNormas(valor);
                                quitarError('normas');
                            }}
                            sueltas={sueltasDe(examen)}
                            conservadas={conservadas}
                            onConservadas={setConservadas}
                            guardadas={guardadas}
                            error={errores.normas}
                            errorMarcadas={errorDe('normas_marcadas')}
                            onAviso={avisar}
                        />
                    </div>
                    <div className="mt-5 flex justify-end">
                        <Boton type="button" onClick={continuarExamen}>
                            Continuar <ArrowRight className="h-4 w-4" />
                        </Boton>
                    </div>
                </Tarjeta>
            )}

            {paso === 1 && (
                <Tarjeta
                    titulo="2. Grupos"
                    acciones={
                        <Insignia tono="primario">
                            <Users className="h-3.5 w-3.5" /> {plural(inscritos, 'inscrito')}
                        </Insignia>
                    }
                >
                    <EstadoCarga
                        cargando={cargandoGrupos}
                        error={opciones.error}
                        onReintentar={opciones.recargar}
                        filas={4}
                    >
                        <div className="space-y-4">
                            <div>
                                <h3 className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    Mis grupos
                                </h3>
                                {propios.length > 0 ? (
                                    <ul className="divide-y divide-slate-200 rounded-lg border border-slate-200">
                                        {propios.map((g) => (
                                            <FilaGrupo
                                                key={g.id}
                                                g={g}
                                                marcado={elegidosIds.includes(g.id)}
                                                onCambiar={() =>
                                                    cambiarGrupos(alternar(elegidosIds, g.id))
                                                }
                                            />
                                        ))}
                                    </ul>
                                ) : (
                                    <p className={VACIO}>Sin grupos</p>
                                )}
                            </div>
                            <div>
                                <div className="mb-1.5 flex min-h-9 flex-wrap items-center justify-between gap-x-3">
                                    <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                                        Otros grupos de la asignatura ·{' '}
                                        {
                                            otrosTodos.filter((g) => elegidosIds.includes(g.id))
                                                .length
                                        }{' '}
                                        de {otrosTodos.length}
                                    </h3>
                                    {otrosTodos.length > 1 && (
                                        <span className="-mr-2 flex">
                                            <Boton
                                                type="button"
                                                variante="enlace"
                                                tamano="chico"
                                                onClick={() =>
                                                    cambiarGrupos([
                                                        ...new Set([
                                                            ...elegidosIds,
                                                            ...otros.map((g) => g.id),
                                                        ]),
                                                    ])
                                                }
                                            >
                                                Todos
                                            </Boton>
                                            <Boton
                                                type="button"
                                                variante="enlace"
                                                tamano="chico"
                                                onClick={() =>
                                                    cambiarGrupos(
                                                        elegidosIds.filter(
                                                            (id) => !otros.some((g) => g.id === id)
                                                        )
                                                    )
                                                }
                                            >
                                                Ninguno
                                            </Boton>
                                        </span>
                                    )}
                                </div>
                                {muchos && (
                                    <Buscador
                                        valor={buscado}
                                        onCambiar={setBuscado}
                                        placeholder="Grupo o docente"
                                        etiqueta="Buscar grupo"
                                        className="mb-2"
                                    />
                                )}
                                {otros.length > 0 ? (
                                    <div
                                        className={`rounded-lg border border-slate-200 ${muchos ? 'max-h-80 overflow-y-auto' : ''}`}
                                    >
                                        <ul
                                            className={
                                                muchos
                                                    ? 'grid divide-y divide-slate-200 md:grid-cols-2 md:gap-x-px md:divide-y-0 md:bg-slate-200 md:[&>li]:border-b md:[&>li]:border-slate-200 md:[&>li]:bg-white'
                                                    : 'divide-y divide-slate-200'
                                            }
                                        >
                                            {otros.map((g) => (
                                                <FilaGrupo
                                                    key={g.id}
                                                    g={g}
                                                    marcado={elegidosIds.includes(g.id)}
                                                    onCambiar={() =>
                                                        cambiarGrupos(alternar(elegidosIds, g.id))
                                                    }
                                                />
                                            ))}
                                        </ul>
                                    </div>
                                ) : (
                                    <p className={VACIO}>
                                        {otrosTodos.length ? 'Sin resultados' : 'Sin grupos'}
                                    </p>
                                )}
                            </div>
                        </div>
                    </EstadoCarga>
                    {errorDe('grupos') && (
                        <p role="alert" className={ERROR}>
                            {errorDe('grupos')}
                        </p>
                    )}
                    <div className="mt-5 flex justify-between gap-2">
                        <Boton type="button" variante="secundario" onClick={() => setPaso(0)}>
                            Atrás
                        </Boton>
                        <Boton
                            type="button"
                            disabled={cargandoGrupos || elegidosIds.length === 0}
                            onClick={() => setPaso(2)}
                        >
                            Continuar <ArrowRight className="h-4 w-4" />
                        </Boton>
                    </div>
                </Tarjeta>
            )}

            {paso === 2 && (
                <div className="grid gap-6 lg:grid-cols-[1fr_1.1fr]">
                    <Tarjeta
                        titulo="3. Aulas"
                        acciones={
                            <Insignia tono="primario">
                                {plural(aulas.length, 'elegida')} · {plural(inscritos, 'inscrito')}
                            </Insignia>
                        }
                    >
                        <div className="space-y-3">
                            {aulas.length > 0 && (
                                <ul
                                    className="flex max-h-32 flex-wrap gap-1.5 overflow-y-auto"
                                    aria-label="Aulas elegidas"
                                >
                                    {aulas.map((id) => (
                                        <li
                                            key={id}
                                            className="inline-flex items-center rounded-full bg-primary-50 pl-3 text-sm font-medium text-primary-900"
                                        >
                                            {nombreAula(id)}
                                            <button
                                                type="button"
                                                aria-label={`Quitar el aula ${nombreAula(id)}`}
                                                onClick={() => cambiarAulas(alternar(aulas, id))}
                                                className={`flex h-9 w-9 items-center justify-center rounded-full text-primary-700 hover:bg-primary-100 ${FOCO}`}
                                            >
                                                <X className="h-4 w-4" />
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <div className="grid gap-2 sm:grid-cols-2">
                                <Seleccion
                                    aria-label="Edificio"
                                    className="min-w-0"
                                    value={edificio}
                                    onChange={(e) => setEdificio(e.target.value)}
                                >
                                    <option value="">
                                        Todos los edificios ({catalogo.length})
                                    </option>
                                    <option value="sugeridas">
                                        Sugeridas · aulas de los grupos ({sugeridas.length})
                                    </option>
                                    <option value="elegidas">Elegidas ({aulas.length})</option>
                                    {edificiosConAulas.map((e) => (
                                        <option key={e.id} value={String(e.id)}>
                                            {e.nombre}
                                            {e.facultad !== facultad ? ` · ${e.facultad}` : ''} (
                                            {catalogo.filter((a) => a.edificio_id === e.id).length})
                                        </option>
                                    ))}
                                </Seleccion>
                                <Buscador
                                    valor={busqueda}
                                    onCambiar={setBusqueda}
                                    placeholder="Buscar aula"
                                />
                            </div>
                            <EstadoCarga
                                cargando={todasLasAulas.cargando}
                                error={todasLasAulas.error}
                                onReintentar={todasLasAulas.recargar}
                                filas={4}
                            >
                                {listaAulas.length > 0 ? (
                                    <ul
                                        className="max-h-80 divide-y divide-slate-200 overflow-y-auto rounded-lg border border-slate-200"
                                        aria-label="Aulas"
                                    >
                                        {listaAulas.map((a) => (
                                            <FilaAula
                                                key={a.id}
                                                aula={a}
                                                ubicacion={ubicacionDe(a, facultad)}
                                                sugerida={sugeridas.includes(a.id)}
                                                compartida={compartidas[a.id]?.join(', ')}
                                                marcado={aulas.includes(a.id)}
                                                onCambiar={() =>
                                                    cambiarAulas(alternar(aulas, a.id))
                                                }
                                            />
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="rounded-lg border border-slate-200 py-6 text-center text-sm text-slate-600">
                                        Sin resultados
                                    </p>
                                )}
                                <p className="text-xs text-slate-600">
                                    {plural(listaAulas.length, 'aula')}
                                </p>
                            </EstadoCarga>
                        </div>
                        {errorDe('aulas') && (
                            <p role="alert" className={ERROR}>
                                {errorDe('aulas')}
                            </p>
                        )}
                        {rechazo && (
                            <p role="alert" className={ERROR}>
                                {rechazo}
                            </p>
                        )}
                        <div className="mt-5 flex justify-between gap-2">
                            <Boton type="button" variante="secundario" onClick={() => setPaso(1)}>
                                Atrás
                            </Boton>
                            <Boton
                                type="button"
                                disabled={enviando || elegidosIds.length === 0}
                                onClick={guardar}
                            >
                                {examen ? 'Guardar examen' : 'Registrar examen'}
                            </Boton>
                        </div>
                    </Tarjeta>
                    <Tarjeta titulo="Ubicación">
                        <MapaCampus
                            edificios={edificios}
                            caja={caja}
                            facultad={fueraDeLaFacultad ? null : facultad}
                            resaltados={edificiosElegidos}
                            alto="h-56 lg:h-80"
                        />
                    </Tarjeta>
                </div>
            )}

            {paso === 3 && examen && <Resumen examen={examen} recien />}
            {aviso}
        </>
    );
}

Asistente.propTypes = {
    asignaturas: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            codigo: PropTypes.string,
            nombre: PropTypes.string.isRequired,
            facultad: PropTypes.string,
            periodo: PropTypes.string,
        })
    ).isRequired,
    tipos: PropTypes.arrayOf(
        PropTypes.shape({
            valor: PropTypes.string.isRequired,
            etiqueta: PropTypes.string.isRequired,
        })
    ).isRequired,
    periodos: PropTypes.arrayOf(
        PropTypes.shape({
            codigo: PropTypes.string.isRequired,
            fecha_inicio: PropTypes.string,
            fecha_fin: PropTypes.string,
        })
    ).isRequired,
    inicial: FORMA_DETALLE,
    pasoInicial: PropTypes.number.isRequired,
    onExamen: PropTypes.func.isRequired,
};

// Registro y edición de un examen. Con `?examen=3&paso=2` se abre un examen
// ya registrado en el paso indicado (0 es «Examen», 2 es «Aulas»).
export default function RegistrarExamen() {
    const [parametros] = useSearchParams();
    const examenId = Number(parametros.get('examen')) || null;
    const pasoPedido = Math.min(Math.max(Number(parametros.get('paso')) || 0, 0), 2);

    const misGrupos = usarConsulta('/docente/grupos');
    const tipos = usarConsulta('/examenes/tipos');
    const periodos = usarConsulta('/periodos/vigentes');
    const detalle = usarConsulta(examenId ? `/examenes/${examenId}` : null);
    // El examen recién guardado, para el título.
    const [guardado, setGuardado] = useState(null);

    const previo = detalle.datos ?? null;
    const examen = guardado ?? previo;
    const cargando = misGrupos.cargando || tipos.cargando || detalle.cargando;
    const negado = detalle.error && [403, 404].includes(estadoDe(detalle.error));
    const error = misGrupos.error || tipos.error || (negado ? null : detalle.error);

    // Las asignaturas que dicta: una por cada asignatura de sus grupos.
    const asignaturas = [
        ...new Map(
            (misGrupos.datos ?? []).map((g) => [
                g.asignatura.id,
                { ...g.asignatura, facultad: g.facultad, periodo: g.periodo },
            ])
        ).values(),
    ];
    if (previo && !asignaturas.some((a) => a.id === previo.asignatura.id)) {
        asignaturas.push(previo.asignatura);
    }
    asignaturas.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));

    const periodo = misGrupos.meta?.periodo;

    return (
        <div className="space-y-6">
            <Encabezado
                titulo={
                    examen
                        ? `${examen.tipo_texto ?? examen.tipo} · ${examen.asignatura.nombre}`
                        : 'Registrar examen'
                }
                subtitulo={periodo ? `Período ${periodo}` : undefined}
                volver={{ a: '/examenes', texto: 'Exámenes' }}
            />

            {negado ? (
                <Tarjeta>
                    <p role="alert" className="py-6 text-center text-sm text-slate-600">
                        {sinPunto(mensajeDe(detalle.error, 'Examen no encontrado'))}
                    </p>
                </Tarjeta>
            ) : (
                <EstadoCarga
                    cargando={cargando}
                    error={error}
                    vacio={!previo && asignaturas.length === 0}
                    textoVacio="Sin asignaturas"
                    onReintentar={() => {
                        misGrupos.recargar();
                        tipos.recargar();
                        detalle.recargar();
                    }}
                >
                    {previo && previo.propio === false ? (
                        <Resumen examen={previo} />
                    ) : (
                        <Asistente
                            key={previo?.id ?? 'nuevo'}
                            asignaturas={asignaturas}
                            tipos={tipos.datos ?? []}
                            periodos={periodos.datos ?? []}
                            inicial={previo}
                            pasoInicial={previo ? pasoPedido : 0}
                            onExamen={setGuardado}
                        />
                    )}
                </EstadoCarga>
            )}
        </div>
    );
}
