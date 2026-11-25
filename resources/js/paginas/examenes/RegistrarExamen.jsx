import PropTypes from 'prop-types';
import { useCallback, useEffect, useState } from 'react';
import {
    ArrowRight,
    Building2,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
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
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
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
import IconoAsignatura from '../../componentes/IconoAsignatura';
import SelectorAsignatura, { Detalle } from '../../componentes/SelectorAsignatura';
import Tarjeta from '../../componentes/Tarjeta';
import { usarSesion } from '../../sesion/SesionContexto';
import { colorDeAsignatura, diaMes, fechaLarga, normalizar, plural } from '../../utiles/texto';
import ChoquesDeEstudiantes from './ChoquesDeEstudiantes';
import CodigoDeAula from './CodigoDeAula';
import NormasDelExamen from './NormasDelExamen';
import rutaDelExamen from './rutaDelExamen';
import solapamientoDe from './solapamientoDe';
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
// El horario de clases: un examen empieza y termina dentro de él, de lunes a sábado.
const DESDE = '06:45';
const HASTA = '21:45';
const esDomingo = (fecha) => new Date(`${fecha}T12:00:00`).getDay() === 0;
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
    con_ingresos: PropTypes.bool,
    invitaciones: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            grupo_codigo: PropTypes.string,
            docente: PropTypes.string,
            estado: PropTypes.string.isRequired,
        })
    ),
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

// Los grupos de UNA asignatura del examen, igual para todas: sus grupos
// propios marcados y, aparte, los de otros docentes a quienes se invita. Con
// varias asignaturas cada una lleva su cabecera con lo elegido y las
// invitaciones empiezan plegadas.
function SeccionGrupos({
    asignatura,
    conCabecera,
    propios,
    otros,
    elegidosIds,
    onCambiar,
    bloqueado = false,
    cargando,
    error,
    onReintentar,
}) {
    const [buscado, setBuscado] = useState('');
    const [abierto, setAbierto] = useState(!conCabecera);
    const texto = normalizar(buscado.trim());
    const visibles = otros.filter(
        (g) =>
            !texto || normalizar(`grupo ${g.codigo} ${g.docente ?? 'sin docente'}`).includes(texto)
    );
    const muchos = otros.length > MUCHOS_GRUPOS;
    const deOtros = otros.filter((g) => elegidosIds.includes(g.id)).length;
    const elegidos = [...propios, ...otros].filter((g) => elegidosIds.includes(g.id));
    const inscritos = elegidos.reduce((suma, g) => suma + (g.inscritos ?? 0), 0);
    const sinGrupos = !cargando && elegidos.length === 0;

    const cuerpo = (
        <EstadoCarga cargando={cargando} error={error} onReintentar={onReintentar} filas={3}>
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
                                    onCambiar={() => onCambiar(alternar(elegidosIds, g.id))}
                                />
                            ))}
                        </ul>
                    ) : (
                        <p className={VACIO}>Sin grupos</p>
                    )}
                </div>
                <div>
                    <div className="mb-1.5 flex min-h-9 flex-wrap items-center justify-between gap-x-3">
                        {conCabecera ? (
                            <button
                                type="button"
                                aria-expanded={abierto}
                                onClick={() => setAbierto((previo) => !previo)}
                                className={`-ml-1 flex min-h-9 items-center gap-1 rounded-lg px-1 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:text-slate-900 ${FOCO}`}
                            >
                                <ChevronDown
                                    className={`h-4 w-4 transition-transform ${abierto ? '' : '-rotate-90'}`}
                                    aria-hidden="true"
                                />
                                Invitar a otros docentes · {deOtros} de {otros.length}
                            </button>
                        ) : (
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Invitar a otros docentes · {deOtros} de {otros.length}
                            </h3>
                        )}
                        {abierto && otros.length > 1 && !bloqueado && (
                            <span className="-mr-2 flex">
                                <Boton
                                    type="button"
                                    variante="enlace"
                                    tamano="chico"
                                    onClick={() =>
                                        onCambiar([
                                            ...new Set([
                                                ...elegidosIds,
                                                ...visibles.map((g) => g.id),
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
                                        onCambiar(
                                            elegidosIds.filter(
                                                (id) => !visibles.some((g) => g.id === id)
                                            )
                                        )
                                    }
                                >
                                    Ninguno
                                </Boton>
                            </span>
                        )}
                    </div>
                    {abierto && (
                        <>
                            {muchos && (
                                <Buscador
                                    valor={buscado}
                                    onCambiar={setBuscado}
                                    placeholder="Grupo o docente"
                                    etiqueta="Buscar grupo"
                                    className="mb-2"
                                />
                            )}
                            {visibles.length > 0 ? (
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
                                        {visibles.map((g) => (
                                            <FilaGrupo
                                                key={g.id}
                                                g={g}
                                                marcado={elegidosIds.includes(g.id)}
                                                bloqueado={bloqueado}
                                                onCambiar={() =>
                                                    onCambiar(alternar(elegidosIds, g.id))
                                                }
                                            />
                                        ))}
                                    </ul>
                                </div>
                            ) : (
                                <p className={VACIO}>
                                    {otros.length ? 'Sin resultados' : 'Sin grupos'}
                                </p>
                            )}
                        </>
                    )}
                </div>
            </div>
        </EstadoCarga>
    );

    if (!conCabecera) return cuerpo;

    return (
        <section
            aria-label={`Grupos de ${asignatura.nombre}`}
            className="overflow-hidden rounded-xl border border-slate-200"
        >
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-slate-200 bg-slate-50 px-4 py-3">
                <IconoAsignatura
                    nombre={asignatura.nombre}
                    color={colorDeAsignatura(asignatura.nombre)}
                    tamano="md"
                />
                <h3 className="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900">
                    {asignatura.nombre}
                </h3>
                {cargando ? null : sinGrupos ? (
                    <Insignia tono="advertencia">Elija al menos un grupo</Insignia>
                ) : (
                    <Insignia tono="primario">
                        {plural(elegidos.length, 'grupo')} · {plural(inscritos, 'inscrito')}
                    </Insignia>
                )}
            </div>
            <div className="p-4">{cuerpo}</div>
        </section>
    );
}

SeccionGrupos.propTypes = {
    asignatura: PropTypes.shape({ nombre: PropTypes.string }),
    conCabecera: PropTypes.bool,
    propios: PropTypes.array.isRequired,
    otros: PropTypes.array.isRequired,
    elegidosIds: PropTypes.arrayOf(PropTypes.number).isRequired,
    onCambiar: PropTypes.func.isRequired,
    bloqueado: PropTypes.bool,
    cargando: PropTypes.bool,
    error: PropTypes.any,
    onReintentar: PropTypes.func,
};

// Los grupos de una asignatura que se suma al examen: busca sus grupos y
// muestra la misma sección que la primera.
function GruposDeExtra({ asignatura, elegidos, onElegidos, onDatos }) {
    const opciones = usarConsulta('/examenes/opciones/grupos', {
        parametros: { asignatura_id: asignatura.id },
    });
    const propios = opciones.datos?.propios ?? [];
    const otros = opciones.datos?.otros ?? [];
    const cargado = Boolean(opciones.datos);
    const ids = elegidos ?? propios.map((g) => g.id);
    useEffect(() => {
        if (cargado) onDatos(asignatura.id, { propios, otros });
        // Solo cuando llegan los grupos de la asignatura.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [cargado, asignatura.id]);

    return (
        <SeccionGrupos
            asignatura={asignatura}
            conCabecera
            propios={propios}
            otros={otros}
            elegidosIds={ids}
            onCambiar={onElegidos}
            cargando={opciones.cargando}
            error={opciones.error}
            onReintentar={opciones.recargar}
        />
    );
}

GruposDeExtra.propTypes = {
    asignatura: PropTypes.shape({ id: PropTypes.number.isRequired, nombre: PropTypes.string })
        .isRequired,
    elegidos: PropTypes.arrayOf(PropTypes.number),
    onElegidos: PropTypes.func.isRequired,
    onDatos: PropTypes.func.isRequired,
};

// `compartida`: otro examen usa el aula a esa hora. Es aviso, no impide.
const textoSolapamiento = (hora, duracion, inicio, fin) => {
    const tramo = solapamientoDe(hora, duracion, inicio, fin);
    return tramo ? ` · Coincidencia ${tramo.inicio}–${tramo.fin} (${tramo.minutos} min)` : '';
};

const CLASES_A_LA_VISTA = 2;

function FilaAula({
    aula,
    ubicacion,
    marcado,
    compartida,
    examenes = [],
    clases = [],
    horaPropuesta,
    duracionPropuesta,
    onCambiar,
    bloqueado = false,
}) {
    const ajenas = clases.filter((clase) => !clase.propio);
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
                {examenes.map((examen, indice) => (
                    <span
                        key={`examen-${indice}`}
                        className="w-full min-w-0 pl-7 text-xs text-warning-800"
                    >
                        {examen.docente ? `Docente: ${examen.docente}` : 'Docente no disponible'}
                        {' · '}
                        {examen.inicio}–{examen.fin}
                        {textoSolapamiento(
                            horaPropuesta,
                            duracionPropuesta,
                            examen.inicio,
                            examen.fin
                        )}
                    </span>
                ))}
                {/* Una línea corta por clase (el detalle, al pasar el puntero);
                    con muchas, las dos primeras y cuántas más. */}
                {ajenas.slice(0, CLASES_A_LA_VISTA).map((clase, indice) => (
                    <span
                        key={`clase-${indice}`}
                        title={`Clase · ${clase.asignatura} · Grupo ${clase.grupo} · ${clase.docente ?? 'Docente no disponible'}`}
                        className="w-full min-w-0 truncate pl-7 text-xs text-warning-800"
                    >
                        Ocupada {clase.inicio}–{clase.fin} · {clase.asignatura}
                        {textoSolapamiento(
                            horaPropuesta,
                            duracionPropuesta,
                            clase.inicio,
                            clase.fin
                        )}
                    </span>
                ))}
                {ajenas.length > CLASES_A_LA_VISTA && (
                    <span className="w-full min-w-0 pl-7 text-xs text-warning-800">
                        y {ajenas.length - CLASES_A_LA_VISTA} clase
                        {ajenas.length - CLASES_A_LA_VISTA === 1 ? '' : 's'} más
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
    horaPropuesta: PropTypes.string.isRequired,
    duracionPropuesta: PropTypes.number.isRequired,
    examenes: PropTypes.arrayOf(
        PropTypes.shape({
            examen: PropTypes.string.isRequired,
            docente: PropTypes.string,
            inicio: PropTypes.string.isRequired,
            fin: PropTypes.string.isRequired,
        })
    ),
    clases: PropTypes.arrayOf(
        PropTypes.shape({
            asignatura: PropTypes.string.isRequired,
            grupo: PropTypes.string.isRequired,
            docente: PropTypes.string,
            inicio: PropTypes.string.isRequired,
            fin: PropTypes.string.isRequired,
            propio: PropTypes.bool.isRequired,
        })
    ),
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
    const navegar = useNavigate();
    const [saliendo, setSaliendo] = useState(null);
    const grupos = examen.grupos_detalle ?? [];
    const invitaciones = examen.invitaciones ?? [];
    // Quien colabora (no registró el examen) puede salirse mientras no haya ingresos.
    const puedeSalir = examen.propio === false && !examen.con_ingresos;

    async function salir() {
        setSaliendo('enviando');
        try {
            await api.post(`/examenes/${examen.id}/salir`);
            navegar('/examenes', { state: { aviso: 'Salió del examen' } });
        } catch (fallo) {
            onAviso(sinPunto(mensajeDe(fallo, 'No se pudo salir del examen')), 'error');
            setSaliendo(null);
        }
    }
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
                    {invitaciones.length > 0 && (
                        <ul
                            aria-label="Invitaciones a otros docentes"
                            className="mt-2 flex flex-wrap gap-2"
                        >
                            {invitaciones.map((i) => (
                                <li key={i.id}>
                                    <Insignia
                                        tono={i.estado === 'rechazada' ? 'peligro' : 'advertencia'}
                                    >
                                        Grupo {i.grupo_codigo} · {i.docente ?? 'Sin docente'} ·{' '}
                                        {i.estado === 'rechazada' ? 'Rechazó' : 'Pendiente'}
                                    </Insignia>
                                </li>
                            ))}
                        </ul>
                    )}
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
                {puedeSalir &&
                    (saliendo === null ? (
                        <Boton
                            type="button"
                            variante="peligroContorno"
                            onClick={() => setSaliendo('confirmar')}
                        >
                            Salir del examen
                        </Boton>
                    ) : (
                        <span
                            role="group"
                            aria-label="Salir del examen"
                            className="flex flex-wrap items-center gap-2"
                        >
                            <span className="text-sm font-medium text-danger-700">
                                Sus grupos dejan este examen
                            </span>
                            <Boton
                                type="button"
                                variante="secundario"
                                disabled={saliendo === 'enviando'}
                                onClick={() => setSaliendo(null)}
                            >
                                Cancelar
                            </Boton>
                            <Boton
                                type="button"
                                variante="peligro"
                                cargando={saliendo === 'enviando'}
                                onClick={salir}
                            >
                                Salir
                            </Boton>
                        </span>
                    ))}
                {examen.propio !== false && puede('examenes') && (
                    <Link
                        to={`/examenes/nuevo?copiar=${examen.id}`}
                        className={`inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 ${FOCO}`}
                    >
                        Otro examen a la misma hora
                    </Link>
                )}
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
    sugerido = {},
    base = null,
}) {
    const { usuario } = usarSesion();
    const yo = { id: usuario.id, nombre: usuario.nombre };
    const [examen, setExamen] = useState(inicial);
    const [paso, setPaso] = useState(pasoInicial);
    const hoy = new Date().toLocaleDateString('en-CA');
    // Con `base` (otro examen a la misma hora) el examen nuevo parte de su
    // tipo, fecha, hora, duración, aulas y personal, y de otra asignatura.
    // Un examen nuevo con varias asignaturas a elegir empieza sin ninguna; con
    // una sola, ya viene elegida.
    const [asignaturaId, setAsignaturaId] = useState(
        inicial?.asignatura.id ?? (asignaturas.length === 1 ? asignaturas[0].id : null)
    );
    const fechaBase = base && base.fecha >= hoy ? base.fecha : undefined;
    // Otras asignaturas que se dan ese mismo día: cada una será su examen, con
    // la misma fecha, hora, aulas y personal. Solo al registrar uno nuevo.
    // `grupos`: null mientras no se toquen (valen sus grupos propios).
    const [extras, setExtras] = useState([]);
    const [gruposDeExtra, setGruposDeExtra] = useState({});
    const [tambien, setTambien] = useState({ hechos: [], fallos: [] });
    const restantes = asignaturas.filter(
        (a) => a.id !== asignaturaId && !extras.some((e) => e.id === a.id)
    );
    // Se puede sumar otra asignatura del mismo día solo al registrar un examen nuevo.
    const puedeSumar = !inicial && !examen && asignaturas.length > 1;
    // Todas las asignaturas del examen se tratan igual: una fila por cada una.
    // (Por dentro la primera es la que lleva los datos del examen.)
    const filasDeAsignatura = [
        asignaturas.find((a) => a.id === asignaturaId),
        ...extras.map((e) => e.asignatura),
    ].filter(Boolean);
    // Elegida una asignatura, ya no se cambia: se quita y se elige otra. La
    // primera en llegar lleva los datos del examen; las demás, sus grupos.
    const sumarAsignatura = (id) => {
        const elegida = asignaturas.find((x) => x.id === id);
        if (!elegida) return;
        if (asignaturaId === null) {
            setAsignaturaId(id);
            if (!bloqueado) ajustarFecha(periodoDe(id), tipo);
            quitarError('asignatura_id');
        } else
            setExtras((previos) => [
                ...previos,
                { id: elegida.id, asignatura: elegida, elegidos: null },
            ]);
    };
    const quitarFila = (id) => {
        if (id !== asignaturaId) {
            setExtras((previos) => previos.filter((e) => e.id !== id));
        } else if (extras.length > 0) {
            cambiarAsignatura(extras[0].id);
        } else {
            setAsignaturaId(null);
            setGrupos(null);
            setAulas([]);
            setPersonal({});
        }
    };
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
            base?.tipo ??
            (tipos.find((t) => !cerrado(t.valor, asignaturas[0]?.id)) ?? tipos[0])?.valor ??
            ''
    );
    // Un examen nuevo empieza con fecha y hora cargadas: el campo vacío de
    // fecha u hora se ve en Safari como si tuviera la de hoy, y no la tiene.
    const [fecha, setFecha] = useState(
        () =>
            inicial?.fecha ??
            fechaBase ??
            sugerido.fecha ??
            fechaSugerida(periodoDe(asignaturas[0]?.id), tipo, hoy)
    );
    const [hora, setHora] = useState(
        inicial?.hora ?? (fechaBase ? base.hora : undefined) ?? sugerido.hora ?? HORA_SUGERIDA
    );
    const [duracion, setDuracion] = useState(
        String(inicial?.duracion ?? (fechaBase ? base.duracion : undefined) ?? '90')
    );
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
    const [aulas, setAulas] = useState(
        () =>
            inicial?.aulas_detalle?.map((a) => a.aula_id) ??
            (fechaBase ? base.aulas_detalle?.map((a) => a.aula_id) : undefined) ??
            (sugerido.aula ? [sugerido.aula] : [])
    );
    const [personal, setPersonal] = useState(() =>
        personalDe(inicial ?? (fechaBase ? base : null), usuario.id)
    );
    // Auxiliares sumados en esta visita: los buscados y los recién creados.
    const [sumados, setSumados] = useState([]);
    const [acceso, setAcceso] = useState(null);
    const [conCodigo, setConCodigo] = useState(() => conCodigoDe(inicial));
    const anotarCodigo = useCallback(
        (id) => setConCodigo((previos) => (previos.includes(id) ? previos : [...previos, id])),
        []
    );
    const [busqueda, setBusqueda] = useState('');
    const [edificio, setEdificio] = useState('');
    const [errores, setErrores] = useState({});
    const [rechazo, setRechazo] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();

    // Con ingresos registrados solo cambian las normas, quién atiende y se
    // suman aulas (un aula inutilizable o llena se reemplaza en pleno
    // examen): lo mismo que acepta el servidor. Las que ya estaban no se quitan.
    const bloqueado = Boolean(examen?.con_ingresos);
    const aulasGuardadas = bloqueado ? (examen?.aulas_detalle ?? []).map((a) => a.aula_id) : [];
    const aulaFija = (id) => aulasGuardadas.includes(id);
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
    const idsDeExtra = (e) => e.elegidos ?? (gruposDeExtra[e.id]?.propios ?? []).map((g) => g.id);
    const inscritosDeExtras = extras.reduce((suma, e) => {
        const { propios: suyos = [], otros: ajenos = [] } = gruposDeExtra[e.id] ?? {};
        return (
            suma +
            [...suyos, ...ajenos]
                .filter((g) => idsDeExtra(e).includes(g.id))
                .reduce((s, g) => s + (g.inscritos ?? 0), 0)
        );
    }, 0);
    const todosLosGrupos = [...elegidosIds, ...extras.flatMap(idsDeExtra)];
    const extraVacio = extras.some(
        (e) => gruposDeExtra[e.id] !== undefined && idsDeExtra(e).length === 0
    );
    const inscritos = elegidos.reduce((s, g) => s + (g.inscritos ?? 0), 0) + inscritosDeExtras;
    const cargandoGrupos = opciones.cargando || opciones.actualizando;

    const todasLasAulas = usarConsulta('/aulas');
    const { edificios, caja } = usarEdificios();
    const enGrupos = paso === 1;
    const enAulas = paso === 2;
    const opcionesAulas = usarConsulta('/examenes/opciones/aulas', {
        parametros: {
            grupos: todosLosGrupos,
            fecha,
            hora_inicio: hora,
            duracion_minutos: Number(duracion),
            examen_id: examen?.id,
        },
        activa:
            enAulas && elegidosIds.length > 0 && Boolean(fecha && hora) && duracionValida(duracion),
    });
    // No certificar disponibilidad con respuestas de otros parámetros
    // ni con una comprobación todavía en curso o fallida.
    const falloDisponibilidad =
        !opcionesAulas.actualizando &&
        (opcionesAulas.error || (opcionesAulas.vigente && opcionesAulas.fallos > 0));
    const disponibilidadComprobada =
        opcionesAulas.vigente && !opcionesAulas.actualizando && !falloDisponibilidad;
    const consultandoDisponibilidad = !disponibilidadComprobada && !falloDisponibilidad;
    const datosDisponibilidad = disponibilidadComprobada ? opcionesAulas.datos : null;

    const sugeridas = datosDisponibilidad?.sugeridas ?? [];
    const clasesALaHora = datosDisponibilidad?.clases ?? {};
    const examenesALaHora = datosDisponibilidad?.examenes ?? {};
    // Libre: información vigente, sin clases ajenas ni otros exámenes.
    const libre = (id) =>
        disponibilidadComprobada &&
        !(clasesALaHora[id] ?? []).some((c) => !c.propio) &&
        !(examenesALaHora[id] ?? []).length;
    const conHora = disponibilidadComprobada;
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
        setExtras((previos) => previos.filter((e) => e.id !== Number(valor)));
        if (!bloqueado) ajustarFecha(periodoDe(Number(valor)), tipo);
        setGrupos(null);
        setAulas([]);
        setPersonal({});
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
        ...(examen?.aulas_detalle ?? base?.aulas_detalle ?? []).flatMap((a) => [
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
    // queda en el pasado, ni en domingo, empieza y termina en horario de
    // clases y cae en la ventana de su tipo.
    const ahora = new Date().toTimeString().slice(0, 5);
    const mueve = !inicial || inicial.fecha !== fecha || inicial.hora !== hora;

    const continuarExamen = () => {
        if (bloqueado) {
            setPaso(1);
            return;
        }
        const faltas = {};
        if (asignaturaId === null) faltas.asignatura_id = 'Elija al menos una asignatura';
        if (!fecha) faltas.fecha = 'Obligatorio';
        else if (mueve && fecha < hoy) faltas.fecha = 'La fecha ya pasó';
        else if (mueve && esDomingo(fecha)) faltas.fecha = 'No hay exámenes los domingos';
        else if (periodo && (fecha < periodo.fecha_inicio || fecha > periodo.fecha_fin)) {
            faltas.fecha = `Fuera del período ${periodo.codigo}`;
        } else if (ventana && (fecha < ventana[0] || fecha > ventana[1])) {
            faltas.fecha = `Fuera del calendario: ${tipoTexto.toLowerCase()} del ${diaMes(ventana[0])} al ${diaMes(ventana[1])}`;
        }
        if (!hora) faltas.hora_inicio = 'Obligatorio';
        else if (mueve && (hora < DESDE || hora > HASTA)) {
            faltas.hora_inicio = `Entre ${DESDE} y ${HASTA}`;
        } else if (mueve && fecha === hoy && hora < ahora) faltas.hora_inicio = 'La hora ya pasó';
        if (!duracionValida(duracion)) faltas.duracion_minutos = 'Entre 15 y 480';
        else if (hora && minutosDe(hora) + Number(duracion) > minutosDe(HASTA)) {
            faltas.duracion_minutos = `Debe terminar a las ${HASTA} como máximo`;
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
            // Las otras asignaturas del día: un examen más por cada una, con lo mismo.
            if (!examen && extras.length > 0) {
                const hechos = [];
                const fallos = [];
                for (const extra of extras) {
                    try {
                        const respuesta = await api.post('/examenes', {
                            ...cuerpo,
                            asignatura_id: extra.id,
                            grupos: idsDeExtra(extra),
                        });
                        hechos.push({
                            id: respuesta.data.data.id,
                            nombre: extra.asignatura.nombre,
                        });
                    } catch (falla) {
                        const motivo = Object.values(erroresDe(falla))[0];
                        fallos.push({
                            nombre: extra.asignatura.nombre,
                            motivo: sinPunto(motivo ?? mensajeDe(falla, 'No se pudo registrar')),
                        });
                    }
                }
                setTambien({ hechos, fallos });
                setExtras([]);
            }
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
                            Con ingresos · solo normas, quién atiende y aulas nuevas
                        </p>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="min-w-0 sm:col-span-2">
                            <span
                                id="asignaturas-etiqueta"
                                className="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                {puedeSumar ? 'Asignaturas' : 'Asignatura'}
                                <span className="ml-0.5 text-danger-600" aria-hidden="true">
                                    *
                                </span>
                            </span>
                            {puedeSumar ? (
                                <ul aria-labelledby="asignaturas-etiqueta" className="space-y-3">
                                    {filasDeAsignatura.map((fila) => (
                                        <li key={fila.id} className="flex items-center gap-2">
                                            <div className="flex min-h-14 min-w-0 flex-1 items-center gap-3 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2">
                                                <IconoAsignatura
                                                    nombre={fila.nombre}
                                                    color={colorDeAsignatura(fila.nombre)}
                                                    tamano="md"
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-semibold text-slate-900">
                                                        {fila.nombre}
                                                    </span>
                                                    <Detalle asignatura={fila} />
                                                </span>
                                            </div>
                                            <button
                                                type="button"
                                                aria-label={`Quitar ${fila.nombre}`}
                                                onClick={() => quitarFila(fila.id)}
                                                className={`flex h-14 w-11 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-500 hover:bg-slate-100 ${FOCO}`}
                                            >
                                                <X className="h-4 w-4" aria-hidden="true" />
                                            </button>
                                        </li>
                                    ))}
                                    {restantes.length > 0 && (
                                        <li>
                                            <SelectorAsignatura
                                                etiqueta="Agregar asignatura"
                                                ocultarEtiqueta
                                                vacio={
                                                    filasDeAsignatura.length > 0
                                                        ? 'Agregar otra asignatura'
                                                        : 'Elegir asignatura'
                                                }
                                                asignaturas={restantes}
                                                valor={null}
                                                onCambiar={sumarAsignatura}
                                            />
                                        </li>
                                    )}
                                </ul>
                            ) : (
                                <SelectorAsignatura
                                    etiqueta="Asignatura"
                                    ocultarEtiqueta
                                    disabled={bloqueado}
                                    asignaturas={asignaturas}
                                    valor={asignaturaId}
                                    onCambiar={cambiarAsignatura}
                                />
                            )}
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
                            min={DESDE}
                            max={HASTA}
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
                            formato="digitos"
                            maxLength={3}
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
                            Continuar: Grupos <ArrowRight className="h-4 w-4" />
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
                    <div className="space-y-4">
                        <SeccionGrupos
                            asignatura={asignatura}
                            conCabecera={extras.length > 0}
                            propios={propios}
                            otros={otrosTodos}
                            elegidosIds={elegidosIds}
                            onCambiar={cambiarGrupos}
                            bloqueado={bloqueado}
                            cargando={cargandoGrupos}
                            error={opciones.error}
                            onReintentar={opciones.recargar}
                        />
                        {extras.map((e) => (
                            <GruposDeExtra
                                key={e.id}
                                asignatura={e.asignatura}
                                elegidos={e.elegidos}
                                onElegidos={(siguiente) =>
                                    setExtras((previos) =>
                                        previos.map((x) =>
                                            x.id === e.id ? { ...x, elegidos: siguiente } : x
                                        )
                                    )
                                }
                                onDatos={(id, datos) =>
                                    setGruposDeExtra((previos) => ({ ...previos, [id]: datos }))
                                }
                            />
                        ))}
                    </div>
                    {errorDe('grupos') && (
                        <p role="alert" className={ERROR}>
                            {errorDe('grupos')}
                        </p>
                    )}
                    <div className="mt-4">
                        <ChoquesDeEstudiantes
                            grupos={todosLosGrupos}
                            fecha={fecha}
                            hora={hora}
                            duracion={Number(duracion)}
                            examenId={examen?.id}
                            activa={enGrupos && !cargandoGrupos}
                            guia="Podrá inhabilitarlos con motivo en Habilitación"
                        />
                    </div>
                    <div className="mt-5 flex flex-wrap items-center justify-between gap-2">
                        <Boton type="button" variante="secundario" onClick={() => setPaso(0)}>
                            Atrás
                        </Boton>
                        {!cargandoGrupos && (elegidosIds.length === 0 || extraVacio) && (
                            <p className="text-sm text-slate-600">
                                {extras.length > 0
                                    ? 'Elija al menos un grupo de cada asignatura'
                                    : 'Elija al menos un grupo'}
                            </p>
                        )}
                        <Boton
                            type="button"
                            disabled={cargandoGrupos || elegidosIds.length === 0 || extraVacio}
                            onClick={() => setPaso(2)}
                        >
                            Continuar: Aulas <ArrowRight className="h-4 w-4" />
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
                            {consultandoDisponibilidad && (
                                <p role="status" className="text-sm text-slate-600">
                                    Comprobando disponibilidad de aulas
                                </p>
                            )}
                            {falloDisponibilidad && (
                                <div
                                    role="alert"
                                    className="flex flex-wrap items-center gap-3 text-sm text-danger-600"
                                >
                                    <span>No se pudo comprobar la disponibilidad de aulas</span>
                                    <button
                                        type="button"
                                        onClick={opcionesAulas.recargar}
                                        className={`font-semibold underline ${FOCO}`}
                                    >
                                        Reintentar disponibilidad
                                    </button>
                                </div>
                            )}

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
                                            {disponibilidadComprobada && !libre(id) && (
                                                <span className="ml-1 text-xs font-semibold text-warning-700">
                                                    · ocupada
                                                </span>
                                            )}
                                            {!aulaFija(id) && (
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
                                                examenes={examenesALaHora[a.id] ?? []}
                                                clases={clasesALaHora[a.id] ?? []}
                                                horaPropuesta={hora}
                                                duracionPropuesta={Number(duracion)}
                                                marcado={aulas.includes(a.id)}
                                                bloqueado={aulaFija(a.id)}
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
                        <div className="mt-5 flex flex-wrap items-center justify-between gap-2">
                            <Boton type="button" variante="secundario" onClick={() => setPaso(1)}>
                                Atrás
                            </Boton>
                            <span className="ml-auto flex flex-wrap items-center justify-end gap-2">
                                {aulas.length === 0 && (
                                    <span className="text-sm text-slate-600">
                                        Elija al menos un aula
                                    </span>
                                )}
                                <Boton
                                    type="button"
                                    disabled={elegidosIds.length === 0 || aulas.length === 0}
                                    onClick={() => setPaso(3)}
                                >
                                    Continuar: Quién atiende <ArrowRight className="h-4 w-4" />
                                </Boton>
                            </span>
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
            {paso === 4 && tambien.hechos.length > 0 && (
                <Tarjeta titulo="También registrados" icono={CheckCircle2}>
                    <ul className="divide-y divide-slate-200">
                        {tambien.hechos.map((e) => (
                            <li key={e.id} className="flex items-center justify-between gap-3 py-2">
                                <span className="min-w-0 truncate text-sm font-medium text-slate-800">
                                    {e.nombre}
                                </span>
                                <Link
                                    to={rutaDelExamen(e.id)}
                                    className={`shrink-0 rounded-lg px-1 text-sm font-medium text-primary-700 hover:underline ${FOCO}`}
                                >
                                    Abrir
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Tarjeta>
            )}
            {paso === 4 && tambien.fallos.length > 0 && (
                <ul
                    role="alert"
                    aria-label="Sin registrar"
                    className="space-y-1 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700"
                >
                    {tambien.fallos.map((f) => (
                        <li key={f.nombre}>
                            <strong>{f.nombre}</strong> no se registró · {f.motivo}
                        </li>
                    ))}
                </ul>
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
    // Lo que trae la dirección desde el calendario de aulas: `?aula=&fecha=&hora=`.
    sugerido: PropTypes.shape({
        aula: PropTypes.number,
        fecha: PropTypes.string,
        hora: PropTypes.string,
    }),
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
    base: FORMA_DETALLE,
};

// Registro y edición de un examen. Con `?examen=3&paso=2` se abre un examen
// ya registrado en el paso indicado (0 es «Examen», 2 es «Aulas», 3 es «Quién
// atiende»).
function RegistrarExamenPagina() {
    const [parametros] = useSearchParams();
    const examenId = Number(parametros.get('examen')) || null;
    const pasoPedido = Math.min(Math.max(Number(parametros.get('paso')) || 0, 0), 3);
    // Un examen nuevo puede abrirse con aula, fecha y hora (desde el calendario).
    // La fecha solo vale si es de hoy en adelante y bien escrita.
    const hoyDeHoy = new Date().toLocaleDateString('en-CA');
    const fechaPedida = parametros.get('fecha');
    const horaPedida = parametros.get('hora');
    const sugerido = {
        aula: Number(parametros.get('aula')) || undefined,
        fecha:
            /^\d{4}-\d{2}-\d{2}$/.test(fechaPedida ?? '') && fechaPedida >= hoyDeHoy
                ? fechaPedida
                : undefined,
        hora: /^([01]\d|2[0-3]):[0-5]\d$/.test(horaPedida ?? '') ? horaPedida : undefined,
    };

    const misGrupos = usarConsulta('/docente/grupos');
    const tipos = usarConsulta('/examenes/tipos');
    const periodos = usarConsulta('/periodos/vigentes');
    const detalle = usarConsulta(examenId ? `/examenes/${examenId}` : null);
    // El examen del que parte «otro examen a la misma hora».
    const copiarId = examenId ? null : Number(parametros.get('copiar')) || null;
    const copia = usarConsulta(copiarId ? `/examenes/${copiarId}` : null);
    // Los exámenes del docente, para copiar las normas a uno nuevo.
    const anteriores = usarConsulta(examenId ? null : '/examenes');
    // El examen recién guardado, para el título.
    const [guardado, setGuardado] = useState(null);
    const [aviso, avisar] = useAviso();

    const previo = detalle.datos ?? null;
    const examen = guardado ?? previo;
    const cargando =
        misGrupos.cargando ||
        tipos.cargando ||
        periodos.cargando ||
        detalle.cargando ||
        copia.cargando;
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
                            base={copia.datos?.propio === false ? null : (copia.datos ?? null)}
                            pasoInicial={previo ? pasoPedido : 0}
                            sugerido={previo ? undefined : sugerido}
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

// Cada examen (y cada «otro a la misma hora») es una página nueva: con la
// dirección cambia la clave y el asistente no arrastra lo del anterior.
export default function RegistrarExamen() {
    const [parametros] = useSearchParams();
    return (
        <RegistrarExamenPagina
            key={`${parametros.get('examen') ?? 'nuevo'}-${parametros.get('copiar') ?? ''}`}
        />
    );
}
