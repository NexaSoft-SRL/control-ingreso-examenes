import PropTypes from 'prop-types';
import { useMemo, useState } from 'react';
import { DoorOpen, Flag, Hourglass, LogIn, TriangleAlert } from 'lucide-react';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarEdificios from '../../api/usarEdificios';
import usarSondeo from '../../api/usarSondeo';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Chips from '../../componentes/Chips';
import Dato from '../../componentes/Dato';
import Dialogo from '../../componentes/Dialogo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import MapaCampus from '../../componentes/MapaCampus';
import SelectorExamen from '../../componentes/SelectorExamen';
import Tarjeta from '../../componentes/Tarjeta';
import usarExamen from '../../componentes/usarExamen';
import { usarSesion } from '../../sesion/SesionContexto';
import { diaMes } from '../../utiles/texto';
import EnCursoAulas, { avanceDe, horaDe } from './EnCursoAulas';
import { BotonIncidencia } from '../../componentes/Incidencias';
import ControlDelExamen from './ControlDelExamen';
import IncidenciasDelExamen from './IncidenciasDelExamen';
import Llegadas from './Llegadas';

export const INTERVALO = 10000;
// Con más aulas que estas, las tarjetas pasan a una rejilla compacta.
const MAXIMO_TARJETAS = 6;

const ORDENES = {
    avance: ['Menor avance', (a, b) => avanceDe(a) - avanceDe(b)],
    faltan: ['Más pendientes', (a, b) => b.faltan - a.faltan],
    aula: ['Aula', (a, b) => a.nombre.localeCompare(b.nombre, 'es', { numeric: true })],
};

const FILTROS = {
    incidencias: (a) => a.incidencias_sin_atender > 0,
    pendientes: (a) => a.faltan > 0,
    vacias: (a) => a.ingresados === 0,
    propias: (a) => a.a_mi_cargo,
};

// El examen mientras se toma: cifras, incidencias, aulas y mapa. Se monta de
// nuevo con cada examen (`key`), así el cambio limpia filtros y orden.
function Tablero({ examen, examenes, elegir, onFinalizado }) {
    const { usuario, puede } = usarSesion();
    const [porFinalizar, setPorFinalizar] = useState(false);
    const [finalizando, setFinalizando] = useState(false);
    const [rechazo, setRechazo] = useState(null);
    const [filtro, setFiltro] = useState(null);
    const [orden, setOrden] = useState('avance');
    const [reportadas, setReportadas] = useState(0);
    const { edificios, caja } = usarEdificios();

    const vivo = usarSondeo(`/en-curso/${examen.id}`, { intervalo: INTERVALO });
    const tablero = vivo.datos;

    const abierto = tablero?.examen.abierto ?? examen.abierto ?? false;
    const aulas = useMemo(() => tablero?.aulas ?? [], [tablero]);
    const cifras = tablero?.cifras;
    const denso = aulas.length > MAXIMO_TARJETAS;
    const visibles = aulas.filter(filtro ? FILTROS[filtro] : () => true).sort(ORDENES[orden][1]);
    const cuenta = (clave) => aulas.filter(FILTROS[clave]).length;
    // Las incidencias se reportan el día del examen, en cualquiera de las
    // aulas del tablero.
    const esHoy = Boolean(tablero) && String(tablero.hora_servidor).slice(0, 10) === examen.fecha;
    const aulasIncidencia = aulas.map((a) => ({ id: a.aula_id, nombre: a.nombre }));
    // HU-28: la duración es la prevista; el examen se cierra cuando un
    // docente lo finaliza. `excedido`: pasó la hora prevista y sigue abierto.
    const finalizable = abierto && Boolean(tablero?.es_docente) && puede('examenes');
    const excedido = Boolean(tablero?.examen.excedido);

    const finalizar = async () => {
        setFinalizando(true);
        setRechazo(null);
        try {
            await api.post(`/examenes/${examen.id}/finalizar`);
            setPorFinalizar(false);
            vivo.recargar();
            onFinalizado('Examen finalizado');
        } catch (fallo) {
            setRechazo(mensajeDe(fallo, 'No se pudo finalizar'));
        } finally {
            setFinalizando(false);
        }
    };
    const ultimoRegistro = tablero?.ultimo_registro
        ? `Último registro ${horaDe(tablero.ultimo_registro)}`
        : 'Sin registros';

    // El edificio de cada aula, con sus aulas como rótulo.
    const { idsEdificio, rotulosEdificio, facultad } = useMemo(() => {
        const porEdificio = {};
        aulas.forEach((a) => {
            if (a.edificio_id === null || a.edificio_id === undefined) return;
            (porEdificio[a.edificio_id] ??= []).push(a.nombre);
        });
        const ids = Object.keys(porEdificio).map(Number);
        return {
            idsEdificio: ids,
            rotulosEdificio: Object.fromEntries(
                Object.entries(porEdificio).map(([id, nombres]) => [
                    id,
                    `Aula ${nombres.join(', ')}`,
                ])
            ),
            facultad: edificios.find((e) => ids.includes(e.id))?.facultad ?? null,
        };
    }, [aulas, edificios]);

    return (
        <>
            <Encabezado
                titulo="En curso"
                subtitulo={`${examen.tipo_texto} · ${examen.asignatura}`}
                sinConexion={vivo.sinConexion}
            >
                {tablero &&
                    (abierto ? (
                        <Insignia tono="primario" punto>
                            Ingreso abierto · {ultimoRegistro}
                        </Insignia>
                    ) : (
                        <Insignia tono="neutro">
                            {diaMes(examen.fecha)}, {examen.hora}
                            {tablero.ultimo_registro && ` · ${ultimoRegistro}`}
                        </Insignia>
                    ))}
                {esHoy && aulasIncidencia.length > 0 && (
                    <BotonIncidencia
                        aulas={aulasIncidencia}
                        por={usuario?.nombre}
                        hora={horaDe(tablero.hora_servidor)}
                        registrar={(datos) => api.post(`/examenes/${examen.id}/incidencias`, datos)}
                        alRegistrar={() => {
                            setReportadas((n) => n + 1);
                            vivo.recargar();
                        }}
                    />
                )}
                {finalizable && (
                    <Boton
                        type="button"
                        variante={excedido ? 'peligro' : 'secundario'}
                        onClick={() => {
                            setRechazo(null);
                            setPorFinalizar(true);
                        }}
                    >
                        <Flag className="h-4 w-4" /> Finalizar examen
                    </Boton>
                )}
            </Encabezado>

            <SelectorExamen valor={examen.id} onCambiar={elegir} examenes={examenes} />

            {finalizable && excedido && (
                <p
                    role="status"
                    className="flex items-center gap-2 rounded-lg bg-warning-50 px-4 py-3 text-sm font-medium text-warning-800"
                >
                    <TriangleAlert className="h-4 w-4 shrink-0" aria-hidden="true" />
                    Pasó la hora prevista de término ({tablero.examen.fin}). La puerta sigue abierta
                    hasta que finalice el examen.
                </p>
            )}

            <Dialogo
                titulo="Finalizar examen"
                abierto={porFinalizar}
                onCerrar={() => setPorFinalizar(false)}
                acciones={
                    <>
                        <Boton
                            type="button"
                            variante="secundario"
                            onClick={() => setPorFinalizar(false)}
                        >
                            Cancelar
                        </Boton>
                        <Boton
                            type="button"
                            variante="peligro"
                            cargando={finalizando}
                            onClick={finalizar}
                        >
                            Finalizar
                        </Boton>
                    </>
                }
            >
                <p className="text-sm text-slate-700">
                    Se cierra la puerta en todas las aulas: ya no se registran ingresos.
                </p>
                {cifras && (
                    <p className="mt-2 text-sm font-medium text-slate-900">
                        Ingresaron {cifras.ingresaron} · Faltan {cifras.faltan}
                    </p>
                )}
                {rechazo && (
                    <p role="alert" className="mt-2 text-sm text-danger-600">
                        {rechazo}
                    </p>
                )}
            </Dialogo>

            <EstadoCarga
                cargando={vivo.cargando}
                error={vivo.error}
                onReintentar={vivo.recargar}
                className="rounded-xl border border-slate-200 bg-white"
            >
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Dato
                        etiqueta="Ingresaron"
                        icono={LogIn}
                        valor={cifras?.ingresaron}
                        tono={abierto ? 'primario' : 'neutro'}
                    />
                    <Dato etiqueta="Faltan" icono={Hourglass} valor={cifras?.faltan} />
                    <Dato etiqueta="Aulas" icono={DoorOpen} valor={cifras?.aulas} />
                    <Dato
                        etiqueta="Incidencias sin atender"
                        icono={TriangleAlert}
                        valor={cifras?.incidencias_sin_atender}
                        tono={cifras?.incidencias_sin_atender ? 'peligro' : 'neutro'}
                    />
                </div>

                {tablero?.es_docente && (
                    <ControlDelExamen
                        examenId={examen.id}
                        abierto={abierto}
                        onCambio={vivo.recargar}
                    />
                )}

                <Llegadas
                    llegadas={tablero?.llegadas ?? []}
                    inicio={String(examen.hora ?? '').slice(0, 5)}
                />

                {tablero && (
                    <IncidenciasDelExamen
                        key={reportadas}
                        examenId={examen.id}
                        puedeAtender={Boolean(tablero.es_docente)}
                        conAula
                    />
                )}

                {aulas.length > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        <Chips
                            etiqueta="Aulas"
                            valor={filtro}
                            onCambiar={setFiltro}
                            opciones={[
                                { valor: null, etiqueta: 'Todas', conteo: aulas.length },
                                {
                                    valor: 'incidencias',
                                    etiqueta: 'Con incidencias',
                                    conteo: cuenta('incidencias'),
                                },
                                {
                                    valor: 'pendientes',
                                    etiqueta: 'Con pendientes',
                                    conteo: cuenta('pendientes'),
                                },
                                {
                                    valor: 'vacias',
                                    etiqueta: 'Sin ingresos',
                                    conteo: cuenta('vacias'),
                                },
                                {
                                    valor: 'propias',
                                    etiqueta: 'A mi cargo',
                                    conteo: cuenta('propias'),
                                },
                            ]}
                        />
                        <label className="flex min-h-10 items-center gap-2 text-sm text-slate-600">
                            Orden
                            <select
                                className="min-h-10 rounded-lg border border-slate-300 bg-white pl-3 text-sm font-medium text-slate-800 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                                value={orden}
                                onChange={(e) => setOrden(e.target.value)}
                            >
                                {Object.entries(ORDENES).map(([clave, [texto]]) => (
                                    <option key={clave} value={clave}>
                                        {texto}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[1.3fr_1fr]">
                    <Tarjeta titulo="Aulas del examen" className="order-last lg:self-start">
                        <MapaCampus
                            edificios={edificios}
                            caja={caja}
                            facultad={facultad}
                            resaltados={idsEdificio}
                            rotulosEdificio={rotulosEdificio}
                            alto="h-48 lg:h-80"
                        />
                    </Tarjeta>

                    {aulas.length === 0 ? (
                        <p className="rounded-xl border border-slate-200 bg-white py-10 text-center text-sm text-slate-600">
                            Sin aulas
                        </p>
                    ) : visibles.length === 0 ? (
                        <p className="self-start rounded-xl border border-slate-200 bg-white py-10 text-center text-sm text-slate-600">
                            Sin resultados
                        </p>
                    ) : (
                        <EnCursoAulas
                            aulas={visibles}
                            examenId={examen.id}
                            denso={denso}
                            abierto={abierto}
                            conLista={puede('seguimiento_vivo')}
                            conControl={abierto && puede('punto_control')}
                        />
                    )}
                </div>
            </EstadoCarga>
        </>
    );
}

Tablero.propTypes = {
    examen: PropTypes.shape({
        id: PropTypes.number.isRequired,
        fecha: PropTypes.string,
        hora: PropTypes.string,
        tipo_texto: PropTypes.string,
        asignatura: PropTypes.string,
        abierto: PropTypes.bool,
    }).isRequired,
    examenes: PropTypes.array.isRequired,
    elegir: PropTypes.func.isRequired,
    onFinalizado: PropTypes.func.isRequired,
};

// HU-22: el examen en curso, aula por aula, para el docente que pasa de un
// aula a otra. El selector ofrece los exámenes de hoy y del próximo día con
// examen (GET /api/en-curso); el tablero se consulta cada 10 s. Si el
// ingreso está abierto lo decide el servidor.
export default function EnCurso() {
    const { datos, cargando, error, recargar } = usarConsulta('/en-curso');
    const examenes = useMemo(() => datos ?? [], [datos]);
    const [examen, elegir] = usarExamen(examenes);
    const [aviso, avisar] = useAviso();
    // Al finalizar, el examen deja de estar en curso: se relee la lista.
    const finalizado = (texto) => {
        avisar(texto);
        recargar();
    };

    if (!examen) {
        return (
            <div className="space-y-6">
                <Encabezado titulo="En curso" />
                <Tarjeta sinRelleno>
                    <EstadoCarga
                        cargando={cargando}
                        error={error}
                        vacio={datos?.length === 0}
                        textoVacio="Sin exámenes"
                        onReintentar={recargar}
                    />
                </Tarjeta>
                {aviso}
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <Tablero
                key={examen.id}
                examen={examen}
                examenes={examenes}
                elegir={elegir}
                onFinalizado={finalizado}
            />
            {aviso}
        </div>
    );
}
