import PropTypes from 'prop-types';
import { useMemo, useState } from 'react';
import { CheckCircle2 } from 'lucide-react';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarEdificios from '../../api/usarEdificios';
import usarSondeo from '../../api/usarSondeo';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Chips from '../../componentes/Chips';
import Dato from '../../componentes/Dato';
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
import IncidenciaEnCurso from './IncidenciaEnCurso';

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

// 'OTRA_NOVEDAD' → 'Otra novedad'.
function tipoDe(tipo) {
    const texto = String(tipo ?? '')
        .replaceAll('_', ' ')
        .toLocaleLowerCase('es');
    return texto.charAt(0).toLocaleUpperCase('es') + texto.slice(1);
}

function Incidencias({ items, atender, atendiendo }) {
    return (
        <ul className="divide-y divide-slate-200">
            {items.map((i) => (
                <li key={i.id} className="flex flex-wrap items-start justify-between gap-3 p-4">
                    <div className="min-w-0 flex-1 space-y-1">
                        <p className="flex flex-wrap items-center gap-2 text-sm text-slate-600">
                            <Insignia tono={i.atendida ? 'neutro' : 'peligro'}>
                                {tipoDe(i.tipo)}
                            </Insignia>
                            <span className="tabular-nums">
                                Aula {i.aula} · {horaDe(i.reportada_en)}
                            </span>
                        </p>
                        {i.estudiante && (
                            <p className="text-sm font-semibold text-slate-900">{i.estudiante}</p>
                        )}
                        <p className="break-words text-sm text-slate-800">{i.detalle}</p>
                        <p className="text-xs text-slate-600">
                            {i.reportante}
                            {i.rol_reportante && ` · ${i.rol_reportante}`}
                        </p>
                    </div>
                    {i.atendida ? (
                        <p className="flex shrink-0 items-center gap-1.5 text-xs font-medium text-success-700">
                            <CheckCircle2 className="h-4 w-4" />
                            <span>
                                Atendida · {i.atendida_por} · {horaDe(i.atendida_en)}
                            </span>
                        </p>
                    ) : (
                        <Boton
                            type="button"
                            variante="secundario"
                            tamano="chico"
                            disabled={atendiendo === i.id}
                            onClick={() => atender(i.id)}
                            aria-label={`Atender la incidencia del aula ${i.aula}`}
                        >
                            Atender
                        </Boton>
                    )}
                </li>
            ))}
        </ul>
    );
}

Incidencias.propTypes = {
    items: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            tipo: PropTypes.string,
            aula: PropTypes.string,
            detalle: PropTypes.string,
            estudiante: PropTypes.string,
            reportante: PropTypes.string,
            rol_reportante: PropTypes.string,
            reportada_en: PropTypes.string,
            atendida: PropTypes.bool,
            atendida_por: PropTypes.string,
            atendida_en: PropTypes.string,
        })
    ).isRequired,
    atender: PropTypes.func.isRequired,
    atendiendo: PropTypes.number,
};

// El examen mientras se toma: cifras, incidencias, aulas y mapa. Se monta de
// nuevo con cada examen (`key`), así el cambio limpia filtros y orden.
function Tablero({ examen, examenes, elegir }) {
    const { usuario, puede } = usarSesion();
    const [aviso, avisar] = useAviso();
    const [filtro, setFiltro] = useState(null);
    const [orden, setOrden] = useState('avance');
    const [soloSinAtender, setSoloSinAtender] = useState(true);
    const [atendiendo, setAtendiendo] = useState(null);
    const { edificios, caja } = usarEdificios();

    const vivo = usarSondeo(`/en-curso/${examen.id}`, { intervalo: INTERVALO });
    const tablero = vivo.datos;

    const abierto = tablero?.examen.abierto ?? examen.abierto ?? false;
    const aulas = useMemo(() => tablero?.aulas ?? [], [tablero]);
    const incidencias = tablero?.incidencias ?? [];
    const sinAtender = incidencias.filter((i) => !i.atendida);
    const cifras = tablero?.cifras;
    const denso = aulas.length > MAXIMO_TARJETAS;
    const visibles = aulas.filter(filtro ? FILTROS[filtro] : () => true).sort(ORDENES[orden][1]);
    const cuenta = (clave) => aulas.filter(FILTROS[clave]).length;
    // Las incidencias se reportan el día del examen, en las aulas cuya puerta
    // opera la cuenta (la misma regla que la puerta).
    const esHoy = Boolean(tablero) && String(tablero.hora_servidor).slice(0, 10) === examen.fecha;
    const operables = aulas
        .filter((a) => a.puede_operar)
        .map((a) => ({ id: a.aula_id, nombre: a.nombre }));
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

    const atender = async (id) => {
        setAtendiendo(id);
        try {
            await api.patch(`/en-curso/${examen.id}/incidencias/${id}/atendida`);
            avisar('Incidencia atendida');
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudo atender'), 'error');
        } finally {
            setAtendiendo(null);
            vivo.recargar();
        }
    };

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
                        </Insignia>
                    ))}
                {esHoy && puede('punto_control') && operables.length > 0 && (
                    <IncidenciaEnCurso
                        examenId={examen.id}
                        aulas={operables}
                        por={usuario?.nombre}
                        hora={horaDe(tablero.hora_servidor)}
                        alRegistrar={vivo.recargar}
                    />
                )}
            </Encabezado>

            <SelectorExamen valor={examen.id} onCambiar={elegir} examenes={examenes} />

            <EstadoCarga
                cargando={vivo.cargando}
                error={vivo.error}
                onReintentar={vivo.recargar}
                className="rounded-xl border border-slate-200 bg-white"
            >
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Dato
                        etiqueta="Ingresaron"
                        valor={cifras?.ingresaron}
                        tono={abierto ? 'primario' : 'neutro'}
                    />
                    <Dato etiqueta="Faltan" valor={cifras?.faltan} />
                    <Dato etiqueta="Aulas" valor={cifras?.aulas} />
                    <Dato
                        etiqueta="Incidencias sin atender"
                        valor={cifras?.incidencias_sin_atender}
                        tono={cifras?.incidencias_sin_atender ? 'peligro' : 'neutro'}
                    />
                </div>

                {incidencias.length > 0 && (
                    <Tarjeta
                        titulo={`Incidencias · ${incidencias.length}`}
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
                        {soloSinAtender && sinAtender.length === 0 ? (
                            <p className="px-4 py-8 text-center text-sm text-slate-600">
                                Sin incidencias por atender
                            </p>
                        ) : (
                            <Incidencias
                                items={soloSinAtender ? sinAtender : incidencias}
                                atender={atender}
                                atendiendo={atendiendo}
                            />
                        )}
                    </Tarjeta>
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
            {aviso}
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
};

// HU-22: el examen en curso, aula por aula, para el docente que pasa de un
// aula a otra. El selector ofrece los exámenes de hoy y del próximo día con
// examen (GET /api/en-curso); el tablero se consulta cada 10 s. Si el
// ingreso está abierto lo decide el servidor.
export default function EnCurso() {
    const { datos, cargando, error, recargar } = usarConsulta('/en-curso');
    const examenes = useMemo(() => datos ?? [], [datos]);
    const [examen, elegir] = usarExamen(examenes);

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
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <Tablero key={examen.id} examen={examen} examenes={examenes} elegir={elegir} />
        </div>
    );
}
