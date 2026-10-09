import PropTypes from 'prop-types';
import { useState } from 'react';
import {
    ArrowRight,
    Check,
    ChevronDown,
    DoorOpen,
    ListChecks,
    Plus,
    QrCode,
    Users,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Chips from '../../componentes/Chips';
import Dialogo from '../../componentes/Dialogo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import { usarSesion } from '../../sesion/SesionContexto';
import { colorDeAsignatura, diaMes, fechaLarga, mesDe, plural } from '../../utiles/texto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const ENLACE = `inline-flex min-h-10 shrink-0 items-center rounded-lg text-sm font-medium hover:underline ${FOCO}`;

// Los pasos del avance. El de códigos QR queda siempre pendiente (HU-20).
const PASOS = [
    {
        clave: 'grupos',
        Icono: Users,
        texto: 'Grupos',
        detalle: (e) => `${e.grupos.length} · ${plural(e.inscritos, 'inscrito')}`,
    },
    {
        clave: 'aulas',
        Icono: DoorOpen,
        texto: 'Aulas',
        detalle: (e) => (e.aulas.length ? e.aulas.join(', ') : 'Sin elegir'),
    },
    {
        clave: 'habilitacion',
        Icono: ListChecks,
        texto: 'Habilitación',
        detalle: (e) => (e.habilitados ? `${e.habilitados} de ${e.inscritos}` : 'Pendiente'),
    },
    { clave: 'qr', Icono: QrCode, texto: 'Códigos QR', detalle: () => 'Pendiente' },
];

const pendiente = (e) => e.estado !== 'Listo';
const tipoDe = (e) => e.tipo_texto ?? e.tipo;

// A dónde lleva la acción siguiente. `emitir_qr` no tiene destino todavía y
// las aulas solo las elige quien registró el examen.
function accionDe(e, puedeHabilitar) {
    const clave = e.accion?.clave;
    if (clave === 'elegir_aulas' && e.propio) {
        return {
            texto: 'Elegir aulas',
            a: `/examenes/nuevo?examen=${e.id}&paso=${e.accion.paso ?? 2}`,
        };
    }
    if (clave === 'habilitar' && puedeHabilitar) {
        const empezada = (e.habilitados ?? 0) + (e.no_habilitados ?? 0) > 0;
        return {
            texto: empezada ? 'Terminar habilitación' : 'Habilitar',
            a: `/habilitacion?examen=${e.id}`,
        };
    }
    return null;
}

const FORMA_EXAMEN = PropTypes.shape({
    id: PropTypes.number.isRequired,
    asignatura: PropTypes.shape({ id: PropTypes.number, nombre: PropTypes.string }).isRequired,
    tipo: PropTypes.string,
    tipo_texto: PropTypes.string,
    fecha: PropTypes.string.isRequired,
    hora: PropTypes.string,
    duracion: PropTypes.number,
    grupos: PropTypes.arrayOf(PropTypes.string).isRequired,
    inscritos: PropTypes.number,
    aulas: PropTypes.arrayOf(PropTypes.string).isRequired,
    habilitados: PropTypes.number,
    no_habilitados: PropTypes.number,
    avance: PropTypes.objectOf(PropTypes.string).isRequired,
    estado: PropTypes.string,
    accion: PropTypes.shape({ clave: PropTypes.string, paso: PropTypes.number }),
    propio: PropTypes.bool,
    registrado_por: PropTypes.string,
    junto_con: PropTypes.number,
});

// Una fila por examen: fecha, asignatura, estado y el paso siguiente. Los
// pasos se despliegan. Con pocos exámenes, los pendientes ya vienen
// desplegados; con muchos, todos plegados para que la lista se pueda recorrer.
function TarjetaExamen({ e, destacado = false, plegado = false, onEliminar }) {
    const { puede } = usarSesion();
    const [abierto, setAbierto] = useState(destacado || (pendiente(e) && !plegado));
    const puedeHabilitar = puede('habilitacion');
    const accion = accionDe(e, puedeHabilitar);
    const nombre = e.asignatura.nombre;
    const titulo = `${tipoDe(e)} · ${nombre}`;
    const hechos = PASOS.filter((p) => e.avance[p.clave] === 'ok').length;
    const [dia, mes] = diaMes(e.fecha).split(' ');
    const aulas =
        e.aulas.length === 0
            ? 'Sin aulas'
            : e.aulas.length > 3
              ? plural(e.aulas.length, 'aula')
              : e.aulas.join(', ');
    const verHabilitacion =
        puedeHabilitar && e.aulas.length > 0 && accion?.a !== `/habilitacion?examen=${e.id}`;

    return (
        <article
            className={`flex min-w-0 flex-col overflow-hidden rounded-xl border bg-white md:flex-row ${destacado ? 'border-primary-500 ring-2 ring-primary-500/30' : 'border-slate-200'}`}
        >
            <div
                className="flex shrink-0 items-center gap-2 border-b border-l-[6px] border-b-slate-200 bg-slate-50 px-4 py-2 text-slate-900 md:w-24 md:flex-col md:justify-center md:gap-0 md:border-b-0 md:border-r md:border-r-slate-200 md:py-3"
                style={{ borderLeftColor: colorDeAsignatura(nombre) }}
            >
                <span className="text-xl font-bold leading-none md:text-2xl">{dia}</span>
                <span className="text-sm font-medium uppercase text-slate-700">{mes}</span>
                <span className="ml-auto text-sm font-medium text-slate-700 md:ml-0">{e.hora}</span>
            </div>
            <div className="min-w-0 flex-1 px-4 py-3 sm:px-5">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <div className="min-w-0 flex-1 basis-64">
                        <h3 className="break-words text-base font-semibold text-slate-900 sm:truncate">
                            {titulo}
                        </h3>
                        <p className="truncate text-sm text-slate-600">
                            {plural(e.grupos.length, 'grupo')} · {plural(e.inscritos, 'inscrito')} ·{' '}
                            {aulas}
                            {e.junto_con > 0 &&
                                ` · junto con ${plural(e.junto_con, 'examen', 'exámenes')}`}{' '}
                            · {hechos} de {PASOS.length} pasos
                        </p>
                    </div>
                    <Insignia tono={pendiente(e) ? 'advertencia' : 'exito'} punto>
                        {e.estado}
                    </Insignia>
                    <div className="ml-auto flex items-center gap-x-3">
                        {accion && (
                            <Link to={accion.a} className={`${ENLACE} gap-1.5 text-primary-700`}>
                                {accion.texto} <ArrowRight className="h-4 w-4" />
                            </Link>
                        )}
                        <button
                            type="button"
                            aria-expanded={abierto}
                            aria-label={`Pasos de ${titulo}`}
                            onClick={() => setAbierto(!abierto)}
                            className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 ${FOCO}`}
                        >
                            <ChevronDown
                                className={`h-4 w-4 transition-transform ${abierto ? 'rotate-180' : ''}`}
                            />
                        </button>
                    </div>
                </div>

                {abierto && (
                    <>
                        <ol className="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-4">
                            {PASOS.map(({ clave, Icono, texto, detalle }) => {
                                const hecho = e.avance[clave] === 'ok';
                                const actual = e.avance[clave] === 'ahora';
                                return (
                                    <li
                                        key={clave}
                                        className={`min-w-0 rounded-lg border p-2.5 ${actual ? 'border-warning-200 bg-warning-50' : 'border-slate-200 bg-white'}`}
                                    >
                                        <span
                                            className={`flex items-center gap-1.5 text-xs font-semibold ${hecho || actual ? 'text-slate-800' : 'text-slate-600'}`}
                                        >
                                            {hecho ? (
                                                <Check
                                                    className="h-3.5 w-3.5 shrink-0 text-slate-600"
                                                    strokeWidth={3}
                                                />
                                            ) : (
                                                <Icono
                                                    className={`h-3.5 w-3.5 shrink-0 ${actual ? 'text-warning-700' : 'text-slate-500'}`}
                                                />
                                            )}
                                            <span className="truncate">{texto}</span>
                                        </span>
                                        <span className="mt-0.5 block truncate text-xs text-slate-600">
                                            {detalle(e)}
                                        </span>
                                    </li>
                                );
                            })}
                        </ol>
                        <div className="mt-2 flex flex-wrap items-center justify-end gap-x-4">
                            <span className="mr-auto min-w-0 truncate text-sm text-slate-600">
                                {fechaLarga(e.fecha)} · {e.duracion} min
                                {e.registrado_por ? ` · ${e.registrado_por}` : ''}
                            </span>
                            {verHabilitacion && (
                                <Link
                                    to={`/habilitacion?examen=${e.id}`}
                                    className={`${ENLACE} text-slate-700`}
                                >
                                    Habilitación
                                </Link>
                            )}
                            {e.propio && (
                                <>
                                    <Link
                                        to={`/examenes/nuevo?examen=${e.id}`}
                                        className={`${ENLACE} text-slate-700`}
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => onEliminar(e)}
                                        className={`${ENLACE} text-danger-700`}
                                    >
                                        Eliminar
                                    </button>
                                </>
                            )}
                        </div>
                    </>
                )}
            </div>
        </article>
    );
}

TarjetaExamen.propTypes = {
    e: FORMA_EXAMEN.isRequired,
    destacado: PropTypes.bool,
    plegado: PropTypes.bool,
    onEliminar: PropTypes.func.isRequired,
};

// Los exámenes del docente en el período, de todas sus asignaturas. El de
// hoy (o el próximo) va primero; el color de la asignatura queda solo en
// el bloque de la fecha. Se filtra por asignatura y, aparte, por pendientes.
export default function Examenes() {
    const { datos, meta, cargando, error, recargar } = usarConsulta('/examenes');
    const [asignatura, setAsignatura] = useState(null);
    const [soloPendientes, setSoloPendientes] = useState(false);
    const [porEliminar, setPorEliminar] = useState(null);
    const [rechazo, setRechazo] = useState(null);
    const [eliminando, setEliminando] = useState(false);
    const [aviso, avisar] = useAviso();

    const examenes = [...(datos ?? [])].sort((a, b) =>
        `${a.fecha}${a.hora}`.localeCompare(`${b.fecha}${b.hora}`)
    );
    const hoy = meta?.hoy ?? null;
    const asignaturas = [
        ...new Map(examenes.map((e) => [e.asignatura.id, e.asignatura])).values(),
    ].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));

    const deLaAsignatura = (e) => asignatura === null || e.asignatura.id === asignatura;
    const visible = (e) => deLaAsignatura(e) && (!soloPendientes || pendiente(e));
    // El de hoy que no terminó o, si no hay, el próximo.
    const primero = examenes.find((e) => !e.rendido && (!hoy || e.fecha >= hoy)) ?? null;
    const destacado = primero && visible(primero) ? primero : null;
    const lista = examenes.filter((e) => visible(e) && e !== destacado);
    const meses = [...new Set(lista.map((e) => e.fecha.slice(0, 7)))];
    const plegado = examenes.length > 4;
    const pendientes = examenes.filter((e) => deLaAsignatura(e) && pendiente(e)).length;

    const opciones = [
        {
            valor: null,
            etiqueta: asignaturas.length > 5 ? 'Todas las asignaturas' : 'Todos',
            conteo: examenes.length,
        },
        ...asignaturas.map((a) => ({
            valor: a.id,
            etiqueta: a.nombre,
            color: colorDeAsignatura(a.nombre),
            conteo: examenes.filter((e) => e.asignatura.id === a.id).length,
        })),
    ];

    const pedirEliminar = (e) => {
        setPorEliminar(e);
        setRechazo(null);
    };

    const eliminar = async () => {
        setEliminando(true);
        setRechazo(null);
        try {
            await api.delete(`/examenes/${porEliminar.id}`);
            setPorEliminar(null);
            avisar('Examen eliminado');
            await recargar();
        } catch (fallo) {
            setRechazo(mensajeDe(fallo, 'No se pudo eliminar'));
        } finally {
            setEliminando(false);
        }
    };

    const subtitulo = datos
        ? [
              meta?.periodo ? `Período ${meta.periodo}` : null,
              plural(examenes.length, 'examen', 'exámenes'),
              plural(asignaturas.length, 'asignatura'),
          ]
              .filter(Boolean)
              .join(' · ')
        : undefined;

    return (
        <div className="space-y-6">
            <Encabezado titulo="Exámenes" subtitulo={subtitulo}>
                <Link
                    to="/examenes/nuevo"
                    className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700 ${FOCO}`}
                >
                    <Plus className="h-4 w-4" strokeWidth={2.5} /> Registrar examen
                </Link>
            </Encabezado>

            <EstadoCarga cargando={cargando} error={error} onReintentar={recargar}>
                <Chips
                    opciones={opciones}
                    valor={asignatura}
                    onCambiar={setAsignatura}
                    etiqueta="Asignatura"
                    extra={
                        <button
                            type="button"
                            aria-pressed={soloPendientes}
                            onClick={() => setSoloPendientes(!soloPendientes)}
                            className={`inline-flex min-h-10 shrink-0 items-center gap-2 rounded-full border px-3.5 text-sm font-medium transition-colors ${FOCO} ${
                                soloPendientes
                                    ? 'border-warning-600 bg-warning-50 text-warning-900'
                                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                            }`}
                        >
                            Pendientes{' '}
                            <span
                                className={soloPendientes ? 'text-warning-700' : 'text-slate-500'}
                            >
                                {pendientes}
                            </span>
                        </button>
                    }
                />

                {destacado && (
                    <section className="space-y-2">
                        <h2 className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                            {destacado.fecha === hoy ? 'Hoy' : 'Próximo'}
                        </h2>
                        <TarjetaExamen e={destacado} destacado onEliminar={pedirEliminar} />
                    </section>
                )}

                {meses.map((mes) => {
                    const delMes = lista.filter((e) => e.fecha.startsWith(mes));
                    return (
                        <section key={mes} className="space-y-2">
                            <h2 className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {mesDe(`${mes}-01`)} · {delMes.length}
                            </h2>
                            {delMes.map((e) => (
                                <TarjetaExamen
                                    key={e.id}
                                    e={e}
                                    plegado={plegado}
                                    onEliminar={pedirEliminar}
                                />
                            ))}
                        </section>
                    );
                })}

                {!destacado && lista.length === 0 && (
                    <p className="py-10 text-center text-sm text-slate-600">Sin exámenes</p>
                )}
            </EstadoCarga>

            <Dialogo
                titulo="Eliminar examen"
                abierto={porEliminar !== null}
                onCerrar={() => setPorEliminar(null)}
                acciones={
                    <>
                        <Boton
                            type="button"
                            variante="secundario"
                            onClick={() => setPorEliminar(null)}
                        >
                            Cancelar
                        </Boton>
                        <Boton
                            type="button"
                            variante="peligro"
                            disabled={eliminando}
                            onClick={eliminar}
                        >
                            Eliminar
                        </Boton>
                    </>
                }
            >
                {porEliminar && (
                    <>
                        <p className="break-words text-sm font-semibold text-slate-900">
                            {tipoDe(porEliminar)} · {porEliminar.asignatura.nombre}
                        </p>
                        <p className="text-sm text-slate-600">
                            {fechaLarga(porEliminar.fecha)}, {porEliminar.hora} ·{' '}
                            {plural(porEliminar.grupos.length, 'grupo')}
                        </p>
                    </>
                )}
                {rechazo && (
                    <p role="alert" className="mt-2 text-sm text-danger-600">
                        {rechazo}
                    </p>
                )}
            </Dialogo>
            {aviso}
        </div>
    );
}
