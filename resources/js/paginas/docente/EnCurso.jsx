import { DoorOpen, Flag, Hourglass, LogIn, Radio, TriangleAlert } from 'lucide-react';
import PropTypes from 'prop-types';
import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api/cliente';
import { estadoDe, mensajeDe } from '../../api/errores';
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
import SinActividad from '../../componentes/SinActividad';
import {
    BotonIncidencia,
    FiltroGravedad,
    gravedadDe,
    ListaIncidencias,
} from '../../componentes/Incidencias';
import Insignia from '../../componentes/Insignia';
import MapaCampus from '../../componentes/MapaCampus';
import SelectorExamen from '../../componentes/SelectorExamen';
import Tarjeta from '../../componentes/Tarjeta';
import usarExamen from '../../componentes/usarExamen';
import { usarSesion } from '../../sesion/SesionContexto';
import { diaMes } from '../../utiles/texto';
import ControlDelExamen from './ControlDelExamen';
import EnCursoAulas, { avanceDe, faltanEn } from './EnCursoAulas';
import Llegadas from './Llegadas';

const INTERVALO = 10000;
// Con más aulas que estas, las tarjetas pasan a una rejilla compacta.
const MAXIMO_TARJETAS = 6;

const ORDENES = {
    avance: ['Menor avance', (a, b) => avanceDe(a) - avanceDe(b)],
    faltan: ['Más pendientes', (a, b) => faltanEn(b) - faltanEn(a)],
    aula: ['Aula', (a, b) => a.nombre.localeCompare(b.nombre, 'es', { numeric: true })],
};

const FILTROS = {
    incidencias: (a) => a.incidencias_sin_atender > 0,
    pendientes: (a) => faltanEn(a) > 0,
    vacias: (a) => a.ingresados === 0,
    propias: (a) => a.a_mi_cargo,
};

// GET /api/en-curso, con la forma que espera el selector.
const comoExamen = (e) => ({
    id: e.examen_id,
    fecha: e.fecha,
    hora: e.hora,
    tipo: e.tipo,
    tipo_texto: e.tipo_texto,
    asignatura: e.asignatura,
    abierto: e.abierto,
    aulas: e.aulas ?? [],
});

// '2026-10-09T16:07:16-04:00' → '16:07:16'.
const horaDe = (instante) => String(instante ?? '').slice(11, 16);

// El examen mientras se toma: cifras, incidencias, mapa y aulas. Se monta de
// nuevo con cada examen (`key`), así el cambio limpia filtros y orden.
function Tablero({ examen, examenes, elegir, onFinalizado }) {
    const { usuario, puede } = usarSesion();
    const [aviso, avisar] = useAviso();
    const [porFinalizar, setPorFinalizar] = useState(false);
    const [finalizando, setFinalizando] = useState(false);
    const [rechazo, setRechazo] = useState(null);
    const [filtro, setFiltro] = useState(null);
    const [orden, setOrden] = useState('avance');
    const [soloSinAtender, setSoloSinAtender] = useState(true);
    const [gravedad, setGravedad] = useState(null);
    const { edificios, caja } = usarEdificios();

    const vivo = usarSondeo(`/examenes/${examen.id}/en-curso`, { intervalo: INTERVALO });
    const cuerpo = vivo.cuerpo;
    const reportes = usarSondeo(`/examenes/${examen.id}/incidencias`, {
        intervalo: INTERVALO,
        activa: cuerpo !== null,
    });

    const abierto = cuerpo?.abierto ?? false;
    // Las incidencias solo se reportan el día del examen.
    const esHoy = cuerpo !== null && String(cuerpo.hora_servidor).slice(0, 10) === examen.fecha;
    const aulas = useMemo(() => cuerpo?.aulas ?? [], [cuerpo]);
    const cifras = cuerpo?.cifras;
    const denso = aulas.length > MAXIMO_TARJETAS;
    // La hora del último ingreso registrado en el examen, no la de la lectura.
    const ultimoRegistro = cuerpo?.ultimo_ingreso
        ? `Último registro ${horaDe(cuerpo.ultimo_ingreso)}`
        : 'Sin registros';
    const visibles = aulas.filter(filtro ? FILTROS[filtro] : () => true).sort(ORDENES[orden][1]);
    const cuenta = (clave) => aulas.filter(FILTROS[clave]).length;

    const incidencias = reportes.datos ?? [];
    const sinAtender = incidencias.filter((i) => !i.atendida);
    const delEstado = soloSinAtender ? sinAtender : incidencias;
    const deLaGravedad = delEstado.filter((i) => !gravedad || gravedadDe(i) === gravedad);

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

    // Lanza el error para que el formulario muestre los campos.
    const corregir = async (id, cuerpo) => {
        await api.post(`/incidencias/${id}/correccion`, cuerpo);
        avisar('Incidencia corregida');
        reportes.recargar();
    };

    const atender = async (id) => {
        try {
            await api.post(`/incidencias/${id}/atencion`);
            avisar('Incidencia atendida');
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudo atender'), 'error');
        } finally {
            reportes.recargar();
            vivo.recargar();
        }
    };

    // Docente puesto a cargo de un aula en un examen ajeno: el tablero no
    // es suyo, pero sí la lista de su aula.
    const ajeno = estadoDe(vivo.error) === 403;
    // La duración es la prevista: el examen se cierra cuando el docente lo
    // finaliza. `excedido`: ya pasó la hora prevista y sigue abierto.
    const finalizable = abierto && !ajeno && puede('examenes');
    const excedido = Boolean(cuerpo?.excedido);

    const finalizar = async () => {
        setFinalizando(true);
        setRechazo(null);
        try {
            await api.post(`/examenes/${examen.id}/finalizar`);
            setPorFinalizar(false);
            onFinalizado('Examen finalizado');
        } catch (fallo) {
            setRechazo(mensajeDe(fallo, 'No se pudo finalizar'));
        } finally {
            setFinalizando(false);
        }
    };

    return (
        <>
            <Encabezado
                titulo="En curso"
                subtitulo={`${cuerpo?.examen.tipo ?? examen.tipo_texto} · ${cuerpo?.examen.asignatura ?? examen.asignatura}`}
                sinConexion={vivo.sinConexion}
            >
                {cuerpo !== null &&
                    (abierto ? (
                        <Insignia tono="primario" punto>
                            Ingreso abierto · {ultimoRegistro}
                        </Insignia>
                    ) : (
                        <Insignia tono="neutro">
                            {diaMes(examen.fecha)}, {examen.hora}
                            {cuerpo.ultimo_ingreso && ` · ${ultimoRegistro}`}
                        </Insignia>
                    ))}
                {esHoy && aulas.length > 0 && (
                    <BotonIncidencia
                        aulas={aulas.map((a) => ({ id: a.aula_id, nombre: a.nombre }))}
                        por={usuario?.nombre}
                        hora={horaDe(cuerpo.hora_servidor)}
                        registrar={(datos) => api.post(`/examenes/${examen.id}/incidencias`, datos)}
                        alRegistrar={() => {
                            reportes.recargar();
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
                    Pasó la hora prevista de término ({cuerpo.fin}). La puerta sigue abierta hasta
                    que finalice el examen.
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

            {ajeno ? (
                <Tarjeta titulo="Aulas a mi cargo" sinRelleno>
                    <ul className="divide-y divide-slate-200">
                        {examen.aulas.map((a) => (
                            <li key={a.aula_id}>
                                <Link
                                    to={`/en-curso/aula?examen=${examen.id}&aula=${a.aula_id}`}
                                    className="flex min-h-12 items-center px-4 text-sm font-medium text-primary-700 hover:bg-primary-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600"
                                >
                                    Aula {a.nombre}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Tarjeta>
            ) : (
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

                    {puede('examenes') && (
                        <ControlDelExamen
                            examenId={examen.id}
                            abierto={abierto}
                            onCambio={vivo.recargar}
                        />
                    )}

                    <Llegadas
                        llegadas={cuerpo?.llegadas ?? []}
                        inicio={String(examen.hora ?? '').slice(0, 5)}
                    />

                    {incidencias.length > 0 && (
                        <Tarjeta
                            id="incidencias"
                            titulo={`Incidencias · ${reportes.meta?.todas ?? incidencias.length}`}
                            sinRelleno
                            acciones={
                                <Chips
                                    etiqueta="Incidencias"
                                    valor={soloSinAtender}
                                    onCambiar={setSoloSinAtender}
                                    opciones={[
                                        {
                                            valor: true,
                                            etiqueta: 'Sin atender',
                                            conteo: sinAtender.length,
                                        },
                                        {
                                            valor: false,
                                            etiqueta: 'Todas',
                                            conteo: incidencias.length,
                                        },
                                    ]}
                                />
                            }
                        >
                            <div className="border-b border-slate-200 px-4 py-2">
                                <FiltroGravedad
                                    items={delEstado}
                                    valor={gravedad}
                                    onCambiar={setGravedad}
                                />
                            </div>
                            <ListaIncidencias
                                items={deLaGravedad}
                                atender={atender}
                                corregir={corregir}
                                conAula
                                limite={5}
                            />
                        </Tarjeta>
                    )}

                    {aulas.length > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                            <Chips
                                etiqueta="Aulas"
                                icono={DoorOpen}
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
                        <Tarjeta
                            titulo="Aulas del examen"
                            className={`lg:order-last lg:self-start ${denso ? 'max-lg:order-last' : ''}`}
                        >
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
                                conControl={abierto && puede('punto_control')}
                            />
                        )}
                    </div>
                </EstadoCarga>
            )}
            {aviso}
        </>
    );
}

Tablero.propTypes = {
    examen: PropTypes.shape({
        id: PropTypes.number.isRequired,
        fecha: PropTypes.string,
        hora: PropTypes.string,
        tipo: PropTypes.string,
        tipo_texto: PropTypes.string,
        asignatura: PropTypes.string,
        aulas: PropTypes.array,
    }).isRequired,
    examenes: PropTypes.array.isRequired,
    elegir: PropTypes.func.isRequired,
    onFinalizado: PropTypes.func.isRequired,
};

// El examen mientras se toma, para el docente que pasa de un aula a otra
// (HU-22). Solo se elige entre los exámenes abiertos de GET /api/en-curso
// (desde una hora antes del inicio hasta el final, según el servidor); el
// tablero se sondea cada 10 s.
export default function EnCurso() {
    const { datos, cargando, error, recargar } = usarConsulta('/en-curso');
    // Solo lo que se está tomando: lo ya rendido se ve en los reportes.
    const examenes = useMemo(() => (datos ?? []).map(comoExamen).filter((e) => e.abierto), [datos]);
    const [examen, elegir] = usarExamen(examenes);
    const [aviso, avisar] = useAviso();
    // Al finalizar, el examen deja «En curso»: se relee la lista.
    const finalizado = (texto) => {
        avisar(texto);
        recargar();
    };

    if (!examen) {
        return (
            <div className="space-y-6">
                <Encabezado titulo="En curso" />
                <Tarjeta sinRelleno>
                    <EstadoCarga cargando={cargando} error={error} onReintentar={recargar}>
                        <SinActividad
                            Icono={Radio}
                            titulo="Ningún examen en curso"
                            detalle="El seguimiento se abre una hora antes del examen y se cierra al terminar."
                            conReportes
                        />
                    </EstadoCarga>
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
