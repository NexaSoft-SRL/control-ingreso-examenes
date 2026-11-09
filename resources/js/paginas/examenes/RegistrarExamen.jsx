import PropTypes from 'prop-types';
import { useCallback, useEffect, useState } from 'react';
import {
    ArrowRight,
    Building2,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    Clock,
    DoorOpen,
    Hourglass,
    KeyRound,
    LayoutGrid,
    ListChecks,
    MapPin,
    ScrollText,
    Tag,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarEdificios from '../../api/usarEdificios';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Campo from '../../componentes/Campo';
import Desplegable from '../../componentes/Desplegable';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import MapaCampus from '../../componentes/MapaCampus';
import Pasos from '../../componentes/Pasos';
import Seleccion from '../../componentes/Seleccion';
import SelectorAsignatura from '../../componentes/SelectorAsignatura';
import Tarjeta from '../../componentes/Tarjeta';
import { usarSesion } from '../../sesion/SesionContexto';
import { diaMes, fechaLarga, normalizar, plural } from '../../utiles/texto';
import CodigoDeAula from './CodigoDeAula';
import NormasDelExamen from './NormasDelExamen';
import rutaDelExamen from './rutaDelExamen';
import QuienAtiende from './QuienAtiende';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const PASOS = ['Examen', 'Grupos', 'Aulas', 'Quién atiende', 'Listo'];
const ICONOS_DE_PASO = [ClipboardList, Users, DoorOpen, UserCheck, CheckCircle2];
// Con más grupos que estos, la lista lleva buscador y desplazamiento propio.
const MUCHOS_GRUPOS = 8;
const VACIO = 'rounded-lg border border-slate-200 py-4 text-center text-sm text-slate-600';
const ERROR = 'mt-1.5 text-sm text-danger-600';

// La ventana del calendario académico de cada tipo, como la comprueba el servidor.
const VENTANA_DE_TIPO = {
    PRIMER_PARCIAL: 'primeros_parciales',
    SEGUNDO_PARCIAL: 'segundos_parciales',
    FINAL: 'finales_y_segunda_instancia',
    SEGUNDA_INSTANCIA: 'finales_y_segunda_instancia',
    MESA: 'examenes_de_mesa',
};
const ventanaDe = (periodo, tipo) => {
    const ventana = periodo?.ventanas?.[VENTANA_DE_TIPO[tipo]];
    return Array.isArray(ventana) && ventana.length === 2 ? ventana : null;
};
// La primera fecha posible para el tipo: hoy, salvo que el período o la
// ventana del calendario empiecen después; vacía si la ventana ya terminó.
const fechaSugerida = (periodo, tipo, hoy) => {
    const ventana = ventanaDe(periodo, tipo);
    const desde = [hoy, periodo?.fecha_inicio, ventana?.[0]].filter(Boolean).sort().at(-1);
    const hasta = [periodo?.fecha_fin, ventana?.[1]].filter(Boolean).sort()[0];
    return hasta && desde > hasta ? '' : desde;
};
// La hora de la primera franja de la mañana en la UMSS.
const HORA_SUGERIDA = '08:15';
// '08:30' → 510.
const minutosDe = (hora) => {
    const [h, m] = String(hora).split(':').map(Number);
    return h * 60 + m;
};

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');
const duracionValida = (texto) =>
    /^\d+$/.test(String(texto).trim()) && Number(texto) >= 15 && Number(texto) <= 480;
const alternar = (lista, valor) =>
    lista.includes(valor) ? lista.filter((v) => v !== valor) : [...lista, valor];
// Los rechazos de quién atiende un aula: `aulas.N.encargado_id`, `aulas.N.apoyo`.
const DE_PERSONAL = /^aulas\.\d+\.(encargado_id|apoyo)/;
// En qué paso se corrige cada campo que rechaza el servidor.
const pasoDe = (campo) =>
    campo.startsWith('grupos')
        ? 1
        : DE_PERSONAL.test(campo)
          ? 3
          : campo.startsWith('aulas')
            ? 2
            : 0;
// Quién atiende cada aula de un examen guardado: `{ [aulaId]: { encargado, apoyo } }`.
const personalDe = (examen, yo) =>
    Object.fromEntries(
        (examen?.aulas_detalle ?? []).map((a) => [
            a.aula_id,
            { encargado: a.encargado?.id ?? yo, apoyo: (a.apoyo ?? []).map((p) => p.id) },
        ])
    );
const conCodigoDe = (examen) =>
    (examen?.aulas_detalle ?? []).filter((a) => a.usa_codigo).map((a) => a.aula_id);

function ubicacionDe(aula, facultad) {
    const lugar = [aula.edificio, aula.piso].filter(Boolean).join(' · ') || 'Sin edificio';
    return aula.facultad && aula.facultad !== facultad ? `${lugar} · ${aula.facultad}` : lugar;
}

const FORMA_GRUPO = PropTypes.shape({
    id: PropTypes.number.isRequired,
    codigo: PropTypes.string.isRequired,
    docente: PropTypes.string,
    docente_usuario_id: PropTypes.number,
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
        PropTypes.shape({
            aula_id: PropTypes.number.isRequired,
            nombre: PropTypes.string,
            ubicacion: PropTypes.string,
            edificio_id: PropTypes.number,
            encargado: PropTypes.shape({
                id: PropTypes.number.isRequired,
                nombre: PropTypes.string.isRequired,
                es_docente: PropTypes.bool,
            }),
            apoyo: PropTypes.arrayOf(
                PropTypes.shape({
                    id: PropTypes.number.isRequired,
                    nombre: PropTypes.string.isRequired,
                })
            ),
            usa_codigo: PropTypes.bool,
        })
    ),
});

function FilaGrupo({ g, marcado, onCambiar, bloqueado = false }) {
    return (
        <li>
            <label
                className={`flex min-h-11 items-center gap-3 px-4 py-2 ${bloqueado ? '' : 'cursor-pointer'}`}
            >
                <input
                    type="checkbox"
                    className="h-4 w-4 shrink-0"
                    checked={marcado}
                    disabled={bloqueado}
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
    bloqueado: PropTypes.bool,
};

// `compartida`: otro examen usa el aula a esa hora. Es aviso, no impide.
function FilaAula({ aula, ubicacion, marcado, compartida, onCambiar, bloqueado = false }) {
    return (
        <li>
            <label
                className={`flex min-h-11 flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 ${bloqueado ? '' : 'cursor-pointer'} ${marcado ? 'bg-primary-50' : ''}`}
            >
                <input
                    type="checkbox"
                    className="h-4 w-4 shrink-0"
                    checked={marcado}
                    disabled={bloqueado}
                    onChange={onCambiar}
                    aria-label={`Aula ${aula.nombre}`}
                />
                <span className="w-16 shrink-0 truncate text-sm font-semibold text-slate-800">
                    {aula.nombre}
                </span>
                <span className="min-w-0 flex-1 truncate text-xs text-slate-600">{ubicacion}</span>
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
    compartida: PropTypes.string,
    onCambiar: PropTypes.func.isRequired,
    bloqueado: PropTypes.bool,
};

// El examen tal como quedó guardado: lo que se ve tras registrar y lo que ve
// el docente de un grupo incluido, que no lo modifica.
function Resumen({ examen, recien = false, onAviso, onElegirAulas }) {
    const { puede } = usarSesion();
    const [acceso, setAcceso] = useState(null);
    const [generados, setGenerados] = useState([]);
    const anotar = useCallback(
        (id) => setGenerados((previos) => (previos.includes(id) ? previos : [...previos, id])),
        []
    );
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
                            <Insignia
                                tono={examen.estado === 'Listo' ? 'exito' : 'advertencia'}
                                punto
                            >
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
                    {aulas.length > 0 && (
                        <div className="mt-1 flex items-start gap-2 text-sm text-slate-700">
                            <UserCheck className="mt-2.5 h-4 w-4 shrink-0 text-slate-500" />
                            <ul aria-label="Quién atiende" className="min-w-0 flex-1">
                                {aulas.map((a) => (
                                    <li key={a.aula_id} className="flex min-w-0 items-center gap-2">
                                        <span className="min-w-0 flex-1 break-words">
                                            <span className="font-medium">{a.nombre}</span>
                                            {a.encargado ? ` · ${a.encargado.nombre}` : ''}
                                            {(a.apoyo ?? []).length > 0 && (
                                                <span className="text-slate-600">
                                                    {' '}
                                                    · {a.apoyo.map((p) => p.nombre).join(', ')}
                                                </span>
                                            )}
                                        </span>
                                        <button
                                            type="button"
                                            aria-label={`Código de acceso del aula ${a.nombre}`}
                                            title="Código de acceso"
                                            onClick={() =>
                                                setAcceso({ id: a.aula_id, nombre: a.nombre })
                                            }
                                            className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg hover:bg-slate-100 ${a.usa_codigo || generados.includes(a.aula_id) ? 'text-primary-700' : 'text-slate-600'} ${FOCO}`}
                                        >
                                            <KeyRound className="h-4 w-4" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
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
                {aulas.length === 0 && onElegirAulas && (
                    <Boton type="button" onClick={onElegirAulas}>
                        Elegir aulas <ArrowRight className="h-4 w-4" />
                    </Boton>
                )}
                {aulas.length > 0 && puede('habilitacion') && (
                    <Link
                        to={rutaDelExamen(examen.id, 'habilitacion')}
                        className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700 ${FOCO}`}
                    >
                        Habilitar estudiantes <ArrowRight className="h-4 w-4" />
                    </Link>
                )}
            </div>
            {acceso && (
                <CodigoDeAula
                    key={acceso.id}
                    examenId={examen.id}
                    aula={acceso}
                    onCerrar={() => setAcceso(null)}
                    onAviso={onAviso}
                    onGenerado={anotar}
                />
            )}
        </Tarjeta>
    );
}

Resumen.propTypes = {
    examen: FORMA_DETALLE.isRequired,
    recien: PropTypes.bool,
    onAviso: PropTypes.func.isRequired,
    onElegirAulas: PropTypes.func,
};

// Las normas del examen que quedaron sin plantilla (la plantilla se quitó).
const sueltasDe = (examen) => (examen?.normas_marcadas ?? []).filter((n) => !n.plantilla_id);

// De qué examen copiar las normas de uno nuevo: el último propio de la
// asignatura o, si no tiene, el último propio de cualquiera.
function fuenteDeNormas(examenes, asignaturaId) {
    const propios = examenes
        .filter((e) => e.propio)
        .sort((a, b) => `${b.fecha}${b.hora}`.localeCompare(`${a.fecha}${a.hora}`));
    return propios.find((e) => e.asignatura?.id === asignaturaId) ?? propios[0] ?? null;
}

// El asistente: examen y normas, grupos, aulas, quién atiende y resumen. Todo
// se guarda de una vez al final (POST o PUT /examenes).
function Asistente({
    asignaturas,
    tipos,
    periodos,
    inicial,
    pasoInicial,
    onExamen,
    anteriores = [],
}) {
    const { usuario } = usarSesion();
    const yo = { id: usuario.id, nombre: usuario.nombre };
    const [examen, setExamen] = useState(inicial);
    const [paso, setPaso] = useState(pasoInicial);
    const [asignaturaId, setAsignaturaId] = useState(
        inicial?.asignatura.id ?? asignaturas[0]?.id ?? null
    );
    const hoy = new Date().toLocaleDateString('en-CA');
    const periodoDe = (id) =>
        periodos.find((p) => p.codigo === asignaturas.find((a) => a.id === id)?.periodo) ?? null;
    // Un tipo cuya ventana ya terminó no se ofrece para un examen nuevo.
    const cerrado = (valor, id) => {
        const ventana = ventanaDe(periodoDe(id), valor);
        return valor !== inicial?.tipo && ventana !== null && ventana[1] < hoy;
    };
    const [tipo, setTipo] = useState(
        () =>
            inicial?.tipo ??
            (tipos.find((t) => !cerrado(t.valor, asignaturas[0]?.id)) ?? tipos[0])?.valor ??
            ''
    );
    // Un examen nuevo empieza con fecha y hora cargadas: el campo vacío de
    // fecha u hora se ve en Safari como si tuviera la de hoy, y no la tiene.
    const [fecha, setFecha] = useState(
        () => inicial?.fecha ?? fechaSugerida(periodoDe(asignaturas[0]?.id), tipo, hoy)
    );
    const [hora, setHora] = useState(inicial?.hora ?? HORA_SUGERIDA);
    const [duracion, setDuracion] = useState(String(inicial?.duracion ?? '90'));
    const [normas, setNormas] = useState(inicial?.normas ?? '');
    const [marcadas, setMarcadas] = useState(() =>
        (inicial?.normas_marcadas ?? []).map((n) => n.plantilla_id).filter(Boolean)
    );
    const [conservadas, setConservadas] = useState(() => sueltasDe(inicial).map((n) => n.id));
    // Un examen nuevo empieza con las normas del último examen propio de su
    // asignatura o, si no hay, del último que registró el docente: suelen
    // repetirse de un examen a otro. Se copian hasta que el docente las toca.
    const [normasTocadas, setNormasTocadas] = useState(false);
    const [copiadasDe, setCopiadasDe] = useState(null);
    const fuente = inicial || normasTocadas ? null : fuenteDeNormas(anteriores, asignaturaId);
    const fuenteId = fuente?.id ?? null;
    useEffect(() => {
        if (fuenteId === null) return undefined;
        let vigente = true;
        api.get(`/examenes/${fuenteId}`)
            .then(({ data }) => {
                const previo = data?.data ?? data;
                if (!vigente || !previo) return;
                setMarcadas(
                    (previo.normas_marcadas ?? []).map((n) => n.plantilla_id).filter(Boolean)
                );
                // Las que no tienen plantilla (la borraron) pasan al texto libre.
                setNormas(
                    [...sueltasDe(previo).map((n) => n.texto), previo.normas]
                        .filter(Boolean)
                        .join('\n')
                );
                setCopiadasDe(fuenteId);
            })
            .catch(() => {});
        return () => {
            vigente = false;
        };
    }, [fuenteId]);
    const copiadas = anteriores.find((e) => e.id === copiadasDe && copiadasDe !== null) ?? null;
    // `null`: todavía no se tocó; valen los grupos propios de la asignatura.
    const [grupos, setGrupos] = useState(() => inicial?.grupos_detalle?.map((g) => g.id) ?? null);
    const [aulas, setAulas] = useState(() => inicial?.aulas_detalle?.map((a) => a.aula_id) ?? []);
    const [personal, setPersonal] = useState(() => personalDe(inicial, usuario.id));
    // Auxiliares sumados en esta visita: los buscados y los recién creados.
    const [sumados, setSumados] = useState([]);
    const [acceso, setAcceso] = useState(null);
    const [conCodigo, setConCodigo] = useState(() => conCodigoDe(inicial));
    const anotarCodigo = useCallback(
        (id) => setConCodigo((previos) => (previos.includes(id) ? previos : [...previos, id])),
        []
    );
    const [buscado, setBuscado] = useState('');
    const [busqueda, setBusqueda] = useState('');
    const [edificio, setEdificio] = useState('');
    const [errores, setErrores] = useState({});
    const [rechazo, setRechazo] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();

    // Con ingresos registrados solo cambian las normas y quién atiende: lo
    // mismo que acepta el servidor.
    const bloqueado = Boolean(examen?.con_ingresos);
    const asignatura = asignaturas.find((a) => a.id === asignaturaId) ?? null;
    const periodo = periodos.find((p) => p.codigo === asignatura?.periodo) ?? null;
    const ventana = ventanaDe(periodo, tipo);
    const tipoTexto = tipos.find((t) => t.valor === tipo)?.etiqueta ?? '';

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
    const clasesALaHora = opcionesAulas.datos?.clases ?? {};
    const examenesALaHora = opcionesAulas.datos?.examenes ?? {};
    // Libre: sin clases de otros grupos ni otros exámenes a esa hora.
    const libre = (id) =>
        !(clasesALaHora[id] ?? []).some((c) => !c.propio) && !(examenesALaHora[id] ?? []).length;
    const conHora = Boolean(opcionesAulas.datos);
    const facultad = asignatura?.facultad ?? null;
    // Solo las aulas de la facultad de la asignatura; las sugeridas y las ya
    // elegidas se ven aunque sean de otra.
    const catalogo = (todasLasAulas.datos ?? []).filter(
        (a) =>
            !facultad || a.facultad === facultad || sugeridas.includes(a.id) || aulas.includes(a.id)
    );
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
        edificio === 'elegidas'
            ? aulas.includes(a.id)
            : edificio === 'libres'
              ? libre(a.id)
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

    // Al cambiar de asignatura o de tipo, una fecha que queda fuera de la
    // nueva ventana pasa a la primera posible; la que sigue valiendo se respeta.
    const ajustarFecha = (periodoNuevo, tipoNuevo) => {
        const sugerida = fechaSugerida(periodoNuevo, tipoNuevo, hoy);
        const ventanaNueva = ventanaDe(periodoNuevo, tipoNuevo);
        const hasta = [periodoNuevo?.fecha_fin, ventanaNueva?.[1]].filter(Boolean).sort()[0];
        if (!fecha || fecha < sugerida || (hasta && fecha > hasta)) setFecha(sugerida);
    };

    const cambiarAsignatura = (valor) => {
        setAsignaturaId(Number(valor));
        if (!bloqueado) ajustarFecha(periodoDe(Number(valor)), tipo);
        setGrupos(null);
        setAulas([]);
        setPersonal({});
        setBuscado('');
        quitarError('asignatura_id', 'grupos', 'aulas', 'fecha');
    };

    const cambiarGrupos = (siguiente) => {
        setGrupos(siguiente);
        // El docente de un grupo que sale deja las aulas que tenía a cargo.
        const cuentas = (lista) => lista.map((g) => g.docente_usuario_id).filter(Boolean);
        const siguen = cuentas(todos.filter((g) => siguiente.includes(g.id)));
        const salen = cuentas(todos).filter((id) => id !== yo.id && !siguen.includes(id));
        if (salen.length > 0) {
            setPersonal((previo) =>
                Object.fromEntries(
                    Object.entries(previo).map(([aula, p]) => [
                        aula,
                        salen.includes(p.encargado) ? { ...p, encargado: yo.id } : p,
                    ])
                )
            );
        }
        setErrores((previos) =>
            Object.fromEntries(Object.entries(previos).filter(([c]) => !c.startsWith('grupos')))
        );
    };

    const de = (id) => personal[id] ?? { encargado: yo.id, apoyo: [] };
    const cambiarPersonal = (siguiente) => {
        setPersonal(siguiente);
        setErrores((previos) =>
            Object.fromEntries(Object.entries(previos).filter(([c]) => !DE_PERSONAL.test(c)))
        );
    };
    const sumar = (persona) =>
        setSumados((previos) =>
            previos.some((p) => p.id === persona.id) ? previos : [...previos, persona]
        );
    // El auxiliar que sale de la lista deja también las aulas donde estaba.
    const quitarAuxiliar = (id) => {
        setSumados((previos) => previos.filter((p) => p.id !== id));
        setPersonal((previo) =>
            Object.fromEntries(
                Object.entries(previo).map(([aula, p]) => [
                    aula,
                    {
                        encargado: p.encargado === id ? yo.id : p.encargado,
                        apoyo: p.apoyo.filter((x) => x !== id),
                    },
                ])
            )
        );
    };
    const guardadas = (examen?.aulas_detalle ?? []).map((a) => a.aula_id);
    // Quienes pueden atender sin ser de la cuenta: los docentes de los grupos
    // del examen (con cuenta) y quienes ya atienden el examen guardado.
    const conocidos = [
        ...elegidos
            .filter((g) => g.docente_usuario_id && g.docente_usuario_id !== yo.id)
            .map((g) => ({ id: g.docente_usuario_id, nombre: g.docente, es_docente: true })),
        ...(examen?.aulas_detalle ?? []).flatMap((a) => [
            ...(a.encargado ? [a.encargado] : []),
            ...(a.apoyo ?? []).map((p) => ({ ...p, es_docente: false })),
        ]),
    ];
    const aulasElegidas = aulas.map((id) => {
        const aula = catalogo.find((a) => a.id === id);
        return {
            id,
            nombre: nombreAula(id),
            ubicacion: aula
                ? ubicacionDe(aula, facultad)
                : (examen?.aulas_detalle?.find((a) => a.aula_id === id)?.ubicacion ?? ''),
        };
    });

    const cambiarAulas = (siguiente) => {
        setAulas(siguiente);
        setErrores((previos) =>
            Object.fromEntries(Object.entries(previos).filter(([c]) => !c.startsWith('aulas')))
        );
    };

    // Lo que el servidor también rechaza: un examen nuevo o movido no
    // queda en el pasado, empieza en horario de clases, termina antes de
    // medianoche y cae en la ventana de su tipo.
    const ahora = new Date().toTimeString().slice(0, 5);
    const mueve = !inicial || inicial.fecha !== fecha || inicial.hora !== hora;

    const continuarExamen = () => {
        if (bloqueado) {
            setPaso(1);
            return;
        }
        const faltas = {};
        if (!fecha) faltas.fecha = 'Obligatorio';
        else if (mueve && fecha < hoy) faltas.fecha = 'La fecha ya pasó';
        else if (periodo && (fecha < periodo.fecha_inicio || fecha > periodo.fecha_fin)) {
            faltas.fecha = `Fuera del período ${periodo.codigo}`;
        } else if (ventana && (fecha < ventana[0] || fecha > ventana[1])) {
            faltas.fecha = `Fuera del calendario: ${tipoTexto.toLowerCase()} del ${diaMes(ventana[0])} al ${diaMes(ventana[1])}`;
        }
        if (!hora) faltas.hora_inicio = 'Obligatorio';
        else if (mueve && (hora < '06:00' || hora > '22:00')) {
            faltas.hora_inicio = 'Entre 06:00 y 22:00';
        } else if (mueve && fecha === hoy && hora < ahora) faltas.hora_inicio = 'La hora ya pasó';
        if (!duracionValida(duracion)) faltas.duracion_minutos = 'Entre 15 y 480';
        else if (hora && minutosDe(hora) + Number(duracion) > 24 * 60) {
            faltas.duracion_minutos = 'Terminaría después de medianoche';
        }
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
            aulas: aulas.map((id) => ({
                aula_id: id,
                encargado_id: de(id).encargado,
                apoyo: de(id).apoyo,
            })),
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
            setPersonal(personalDe(guardado, usuario.id));
            setConCodigo(conCodigoDe(guardado));
            setPaso(4);
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

    const normasGuardadas = Object.fromEntries(
        (examen?.normas_marcadas ?? [])
            .filter((n) => n.plantilla_id)
            .map((n) => [n.plantilla_id, n.texto])
    );

    return (
        <>
            <Pasos
                pasos={PASOS}
                iconos={ICONOS_DE_PASO}
                actual={paso}
                onIr={setPaso}
                completos={[
                    Boolean(fecha && hora) && duracionValida(duracion),
                    elegidosIds.length > 0,
                    aulas.length > 0,
                    aulas.length > 0,
                    Boolean(examen),
                ]}
            />

            {paso === 0 && (
                <Tarjeta titulo="1. Examen" icono={ClipboardList}>
                    {bloqueado && (
                        <p className="mb-4 rounded-lg bg-warning-50 px-3 py-2 text-sm font-medium text-warning-800">
                            Con ingresos · solo normas y quién atiende
                        </p>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="min-w-0 sm:col-span-2">
                            <SelectorAsignatura
                                etiqueta="Asignatura"
                                requerido
                                disabled={bloqueado}
                                asignaturas={asignaturas}
                                valor={asignaturaId}
                                onCambiar={cambiarAsignatura}
                            />
                            {errores.asignatura_id && (
                                <p role="alert" className={ERROR}>
                                    {errores.asignatura_id}
                                </p>
                            )}
                        </div>
                        <div className="min-w-0">
                            <Seleccion
                                etiqueta="Tipo"
                                icono={Tag}
                                requerido
                                disabled={bloqueado}
                                value={tipo}
                                onChange={(e) => {
                                    setTipo(e.target.value);
                                    ajustarFecha(periodo, e.target.value);
                                    quitarError('tipo', 'grupos', 'fecha');
                                }}
                            >
                                {tipos.map((t) => (
                                    <option
                                        key={t.valor}
                                        value={t.valor}
                                        disabled={cerrado(t.valor, asignaturaId)}
                                    >
                                        {t.etiqueta}
                                        {cerrado(t.valor, asignaturaId)
                                            ? ' · calendario cerrado'
                                            : ''}
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
                            icono={CalendarDays}
                            requerido
                            disabled={bloqueado}
                            type="date"
                            min={[hoy, periodo?.fecha_inicio, ventana?.[0]]
                                .filter(Boolean)
                                .sort()
                                .at(-1)}
                            max={[periodo?.fecha_fin, ventana?.[1]].filter(Boolean).sort()[0]}
                            ayuda={
                                ventana
                                    ? `Calendario: ${diaMes(ventana[0])} – ${diaMes(ventana[1])}`
                                    : undefined
                            }
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
                            icono={Clock}
                            requerido
                            disabled={bloqueado}
                            type="time"
                            min="06:00"
                            max="22:00"
                            className="min-w-0"
                            value={hora}
                            onChange={(e) => {
                                setHora(e.target.value);
                                quitarError('hora_inicio', 'duracion_minutos');
                            }}
                            error={errores.hora_inicio}
                        />
                        <Campo
                            etiqueta="Duración"
                            icono={Hourglass}
                            requerido
                            disabled={bloqueado}
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
                                setNormasTocadas(true);
                                quitarError('normas_marcadas');
                            }}
                            texto={normas}
                            onTexto={(valor) => {
                                setNormas(valor);
                                setNormasTocadas(true);
                                quitarError('normas');
                            }}
                            copiadasDe={
                                copiadas
                                    ? `${copiadas.tipo_texto ?? copiadas.tipo} · ${copiadas.asignatura.nombre} · ${diaMes(copiadas.fecha)}`
                                    : null
                            }
                            sueltas={sueltasDe(examen)}
                            conservadas={conservadas}
                            onConservadas={setConservadas}
                            guardadas={normasGuardadas}
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
                    icono={Users}
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
                                                bloqueado={bloqueado}
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
                                    {otrosTodos.length > 1 && !bloqueado && (
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
                                                    bloqueado={bloqueado}
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
                        icono={DoorOpen}
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
                                            {!bloqueado && (
                                                <button
                                                    type="button"
                                                    aria-label={`Quitar el aula ${nombreAula(id)}`}
                                                    onClick={() =>
                                                        cambiarAulas(alternar(aulas, id))
                                                    }
                                                    className={`flex h-9 w-9 items-center justify-center rounded-full text-primary-700 hover:bg-primary-100 ${FOCO}`}
                                                >
                                                    <X className="h-4 w-4" />
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <div className="grid gap-2 sm:grid-cols-[3fr_2fr]">
                                <Desplegable
                                    etiqueta="Edificio"
                                    className="min-w-0"
                                    valor={edificio}
                                    onCambiar={setEdificio}
                                    opciones={[
                                        {
                                            valor: '',
                                            texto: 'Todos los edificios',
                                            icono: LayoutGrid,
                                            cuenta: catalogo.length,
                                        },
                                        {
                                            valor: 'elegidas',
                                            texto: 'Elegidas',
                                            icono: ListChecks,
                                            cuenta: aulas.length,
                                        },
                                        ...(conHora
                                            ? [
                                                  {
                                                      valor: 'libres',
                                                      texto: 'Libres a esa hora',
                                                      icono: CheckCircle2,
                                                      cuenta: catalogo.filter((a) => libre(a.id))
                                                          .length,
                                                  },
                                              ]
                                            : []),
                                        ...edificiosConAulas.map((e) => ({
                                            valor: String(e.id),
                                            texto:
                                                e.facultad !== facultad
                                                    ? `${e.nombre} · ${e.facultad}`
                                                    : e.nombre,
                                            icono: Building2,
                                            cuenta: catalogo.filter((a) => a.edificio_id === e.id)
                                                .length,
                                            grupo: 'Edificios',
                                        })),
                                    ]}
                                />
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
                                                compartida={examenesALaHora[a.id]
                                                    ?.map((x) => x.examen)
                                                    .join(', ')}
                                                marcado={aulas.includes(a.id)}
                                                bloqueado={bloqueado}
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
                        <div className="mt-5 flex justify-between gap-2">
                            <Boton type="button" variante="secundario" onClick={() => setPaso(1)}>
                                Atrás
                            </Boton>
                            <Boton
                                type="button"
                                disabled={elegidosIds.length === 0}
                                onClick={() => setPaso(3)}
                            >
                                Continuar <ArrowRight className="h-4 w-4" />
                            </Boton>
                        </div>
                    </Tarjeta>
                    <Tarjeta titulo="Ubicación" icono={MapPin}>
                        <MapaCampus
                            edificios={edificios}
                            caja={caja}
                            facultad={fueraDeLaFacultad ? null : facultad}
                            resaltados={edificiosElegidos}
                            seleccionado={Number(edificio) || null}
                            onElegir={(e) =>
                                setEdificio((previo) =>
                                    previo === String(e.id) ? '' : String(e.id)
                                )
                            }
                            alto="h-56 lg:h-80"
                        />
                    </Tarjeta>
                </div>
            )}

            {paso === 3 && (
                <QuienAtiende
                    aulas={aulasElegidas}
                    personal={Object.fromEntries(aulas.map((id) => [id, de(id)]))}
                    onPersonal={cambiarPersonal}
                    yo={yo}
                    conocidos={conocidos}
                    sumados={sumados}
                    onSumar={sumar}
                    onQuitar={quitarAuxiliar}
                    errores={errores}
                    conCodigo={examen ? guardadas : []}
                    usanCodigo={conCodigo}
                    onCodigo={setAcceso}
                    onAviso={avisar}
                >
                    {rechazo && (
                        <p role="alert" className={ERROR}>
                            {rechazo}
                        </p>
                    )}
                    <div className="mt-5 flex justify-between gap-2">
                        <Boton type="button" variante="secundario" onClick={() => setPaso(2)}>
                            Atrás
                        </Boton>
                        <Boton
                            type="button"
                            disabled={enviando || elegidosIds.length === 0}
                            cargando={enviando}
                            onClick={guardar}
                        >
                            {examen ? 'Guardar examen' : 'Registrar examen'}
                        </Boton>
                    </div>
                </QuienAtiende>
            )}

            {paso === 4 && examen && (
                <Resumen examen={examen} recien onAviso={avisar} onElegirAulas={() => setPaso(2)} />
            )}
            {acceso && examen && (
                <CodigoDeAula
                    key={acceso.id}
                    examenId={examen.id}
                    aula={acceso}
                    onCerrar={() => setAcceso(null)}
                    onAviso={avisar}
                    onGenerado={anotarCodigo}
                />
            )}
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
            grupos: PropTypes.arrayOf(PropTypes.string),
            inscritos: PropTypes.number,
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
            ventanas: PropTypes.object,
        })
    ).isRequired,
    inicial: FORMA_DETALLE,
    pasoInicial: PropTypes.number.isRequired,
    onExamen: PropTypes.func.isRequired,
    anteriores: PropTypes.array,
};

// Registro y edición de un examen. Con `?examen=3&paso=2` se abre un examen
// ya registrado en el paso indicado (0 es «Examen», 2 es «Aulas», 3 es «Quién
// atiende»).
export default function RegistrarExamen() {
    const [parametros] = useSearchParams();
    const examenId = Number(parametros.get('examen')) || null;
    const pasoPedido = Math.min(Math.max(Number(parametros.get('paso')) || 0, 0), 3);

    const misGrupos = usarConsulta('/docente/grupos');
    const tipos = usarConsulta('/examenes/tipos');
    const periodos = usarConsulta('/periodos/vigentes');
    const detalle = usarConsulta(examenId ? `/examenes/${examenId}` : null);
    // Los exámenes del docente, para copiar las normas a uno nuevo.
    const anteriores = usarConsulta(examenId ? null : '/examenes');
    // El examen recién guardado, para el título.
    const [guardado, setGuardado] = useState(null);
    const [aviso, avisar] = useAviso();

    const previo = detalle.datos ?? null;
    const examen = guardado ?? previo;
    const cargando = misGrupos.cargando || tipos.cargando || periodos.cargando || detalle.cargando;
    const negado = detalle.error && [403, 404].includes(estadoDe(detalle.error));
    const error = misGrupos.error || tipos.error || (negado ? null : detalle.error);

    // Las asignaturas que dicta: una por cada asignatura de sus grupos, con
    // los códigos de esos grupos y la suma de sus inscritos.
    const porAsignatura = new Map();
    for (const g of misGrupos.datos ?? []) {
        const previa = porAsignatura.get(g.asignatura.id) ?? {
            ...g.asignatura,
            facultad: g.facultad,
            periodo: g.periodo,
            grupos: [],
            inscritos: 0,
        };
        previa.grupos.push(g.codigo);
        previa.inscritos += g.inscritos ?? 0;
        porAsignatura.set(g.asignatura.id, previa);
    }
    const asignaturas = [...porAsignatura.values()];
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
                        <Resumen examen={previo} onAviso={avisar} />
                    ) : (
                        <Asistente
                            key={previo?.id ?? 'nuevo'}
                            asignaturas={asignaturas}
                            tipos={tipos.datos ?? []}
                            periodos={periodos.datos ?? []}
                            inicial={previo}
                            anteriores={anteriores.datos ?? []}
                            pasoInicial={previo ? pasoPedido : 0}
                            onExamen={(guardadoAhora) => {
                                setGuardado(guardadoAhora);
                                // La dirección queda en el examen: recargar no lo pierde.
                                if (!examenId) {
                                    window.history.replaceState(
                                        window.history.state,
                                        '',
                                        `${window.location.pathname}?examen=${guardadoAhora.id}`
                                    );
                                }
                            }}
                        />
                    )}
                </EstadoCarga>
            )}
            {aviso}
        </div>
    );
}
