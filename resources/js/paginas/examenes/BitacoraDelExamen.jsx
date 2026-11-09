import PropTypes from 'prop-types';
import { useMemo, useState } from 'react';
import { Ban, ClipboardList, KeyRound, LogIn, TriangleAlert, UserX } from 'lucide-react';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Chips from '../../componentes/Chips';
import EstadoCarga from '../../componentes/EstadoCarga';
import { FiltroGravedad, GRAVEDADES } from '../../componentes/Incidencias';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import { fechaLarga, normalizar, plural } from '../../utiles/texto';
import BotonesDescarga from '../reportes/BotonesDescarga';

// De a cuántos sucesos se muestra la lista.
const DE_A = 100;

// Cada categoría con su rótulo, su ícono y su color.
const CATEGORIAS = {
    ingreso: { texto: 'Ingresos', Icono: LogIn, tono: 'bg-success-100 text-success-700' },
    rechazo: { texto: 'Rechazos', Icono: Ban, tono: 'bg-danger-100 text-danger-700' },
    incidencia: {
        texto: 'Incidencias',
        Icono: TriangleAlert,
        tono: 'bg-warning-100 text-warning-800',
    },
    acceso: { texto: 'Accesos', Icono: KeyRound, tono: 'bg-primary-100 text-primary-700' },
    examen: { texto: 'Examen', Icono: ClipboardList, tono: 'bg-slate-100 text-slate-600' },
    ausente: { texto: 'No se presentaron', Icono: UserX, tono: 'bg-slate-200 text-slate-700' },
};

// Todo lo ocurrido en el examen, en orden (GET /reportes/examenes/{id}/bitacora):
// inicio y fin, ingresos, intentos rechazados, incidencias con su atención y
// su corrección, accesos con código de aula, lo hecho con el examen y sus
// aulas y, al cerrar, el resumen y cada habilitado que no se presentó; arriba,
// quién controló cada aula. Se filtra por tipo, por
// gravedad, por aula y por estudiante, y se descarga como los demás reportes.
// `deLaUniversidad`: la lee el administrador, desde sus reportes.
export default function BitacoraDelExamen({ examenId, fecha, deLaUniversidad = false }) {
    const base = deLaUniversidad ? '/reportes/universidad' : '/reportes';
    const { datos, cuerpo, cargando, error, recargar } = usarConsulta(
        `${base}/examenes/${examenId}/bitacora`
    );
    const [aviso, avisar] = useAviso();
    const [categoria, setCategoria] = useState(null);
    const [gravedad, setGravedad] = useState(null);
    const [aula, setAula] = useState('');
    const [buscado, setBuscado] = useState('');
    const [visibles, setVisibles] = useState(DE_A);

    const sucesos = useMemo(() => datos ?? [], [datos]);
    const controles = cuerpo?.aulas ?? [];
    const aulas = [...new Set(sucesos.map((s) => s.aula).filter(Boolean))].sort();
    const texto = normalizar(buscado.trim());
    const filtrados = sucesos.filter(
        (s) =>
            (categoria === null || s.categoria === categoria) &&
            (gravedad === null || s.gravedad === gravedad) &&
            (!aula || s.aula === aula) &&
            (!texto || normalizar(`${s.estudiante ?? ''} ${s.codigo ?? ''}`).includes(texto))
    );
    const dias = [...new Set(filtrados.slice(0, visibles).map((s) => s.instante.slice(0, 10)))];

    const conGravedad = sucesos.filter((s) => s.gravedad);

    const opciones = [
        { valor: null, etiqueta: 'Todo', conteo: sucesos.length },
        ...Object.entries(CATEGORIAS)
            .map(([clave, c]) => ({
                valor: clave,
                etiqueta: c.texto,
                conteo: sucesos.filter((s) => s.categoria === clave).length,
            }))
            .filter((o) => o.conteo > 0),
    ];

    return (
        <Tarjeta
            titulo="Bitácora del examen"
            icono={ClipboardList}
            sinRelleno
            acciones={
                <BotonesDescarga
                    nombre="Bitácora del examen"
                    ruta={`${base}/bitacora/exportar`}
                    parametros={{ examen: examenId }}
                    archivo={`bitacora_${fecha}`}
                    deshabilitado={sucesos.length === 0}
                    onAvisar={avisar}
                />
            }
        >
            <EstadoCarga
                cargando={cargando}
                error={error}
                vacio={sucesos.length === 0}
                textoVacio="Sin sucesos todavía"
                onReintentar={recargar}
            >
                {controles.length > 0 && (
                    <section
                        aria-label="Control por aula"
                        className="space-y-1.5 border-b border-slate-200 px-4 py-3 sm:px-5"
                    >
                        {controles.map((c) => (
                            <p key={c.aula} className="text-sm text-slate-700">
                                <span className="font-semibold text-slate-900">Aula {c.aula}</span>
                                {' · controló: '}
                                {c.controlo || 'nadie asignado'}
                                <span className="text-xs text-slate-500">
                                    {' · '}
                                    {plural(c.ingresos, 'ingreso')}
                                    {c.primer_ingreso &&
                                        ` · ${c.primer_ingreso} – ${c.ultimo_ingreso}`}
                                </span>
                            </p>
                        ))}
                    </section>
                )}
                <div className="space-y-3 border-b border-slate-200 p-4 sm:px-5">
                    <Chips
                        etiqueta="Tipo de suceso"
                        opciones={opciones}
                        valor={categoria}
                        onCambiar={(v) => {
                            setCategoria(v);
                            setVisibles(DE_A);
                        }}
                    />
                    {conGravedad.length > 0 && (
                        <FiltroGravedad
                            items={conGravedad}
                            valor={gravedad}
                            onCambiar={(v) => {
                                setGravedad(v);
                                setVisibles(DE_A);
                            }}
                        />
                    )}
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <div className="min-w-0 flex-1">
                            <Buscador
                                valor={buscado}
                                onCambiar={(v) => {
                                    setBuscado(v);
                                    setVisibles(DE_A);
                                }}
                                placeholder="Estudiante o código SIS"
                                etiqueta="Buscar estudiante"
                            />
                        </div>
                        {aulas.length > 1 && (
                            <Seleccion
                                aria-label="Aula"
                                className="sm:w-44"
                                value={aula}
                                onChange={(e) => {
                                    setAula(e.target.value);
                                    setVisibles(DE_A);
                                }}
                            >
                                <option value="">Todas las aulas</option>
                                {aulas.map((a) => (
                                    <option key={a} value={a}>
                                        Aula {a}
                                    </option>
                                ))}
                            </Seleccion>
                        )}
                    </div>
                </div>

                {filtrados.length === 0 ? (
                    <p className="py-10 text-center text-sm text-slate-600">Sin coincidencias</p>
                ) : (
                    <div className="px-4 py-3 sm:px-5">
                        {dias.map((dia) => (
                            <section key={dia} aria-label={fechaLarga(dia)}>
                                <h3 className="sticky top-0 z-10 bg-white py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {fechaLarga(dia)}
                                </h3>
                                <ol className="relative ml-4 border-l border-slate-200">
                                    {filtrados
                                        .slice(0, visibles)
                                        .filter((s) => s.instante.startsWith(dia))
                                        .map((s, i) => (
                                            <Suceso key={`${s.instante}-${i}`} suceso={s} />
                                        ))}
                                </ol>
                            </section>
                        ))}
                        {filtrados.length > visibles && (
                            <div className="flex justify-center pt-3">
                                <Boton
                                    type="button"
                                    variante="secundario"
                                    onClick={() => setVisibles((v) => v + DE_A)}
                                >
                                    Mostrar más · quedan {filtrados.length - visibles}
                                </Boton>
                            </div>
                        )}
                        <p className="pt-3 text-xs text-slate-500">
                            {plural(filtrados.length, 'suceso')}
                            {filtrados.length !== sucesos.length && ` de ${sucesos.length}`}
                        </p>
                    </div>
                )}
            </EstadoCarga>
            {aviso}
        </Tarjeta>
    );
}

BitacoraDelExamen.propTypes = {
    examenId: PropTypes.number.isRequired,
    fecha: PropTypes.string.isRequired,
    deLaUniversidad: PropTypes.bool,
};

const TEXTO_GRAVEDAD = {
    MUY_GRAVE: 'text-danger-700',
    GRAVE: 'text-warning-700',
    LEVE: 'text-info-700',
};

function Suceso({ suceso: s }) {
    const { Icono, tono } = CATEGORIAS[s.categoria] ?? CATEGORIAS.examen;
    return (
        <li className="relative pb-4 pl-6 last:pb-1">
            <span
                className={`absolute -left-3.5 top-0 flex h-7 w-7 items-center justify-center rounded-full ring-4 ring-white ${tono}`}
                aria-hidden="true"
            >
                <Icono className="h-3.5 w-3.5" />
            </span>
            <div className="flex flex-wrap items-baseline gap-x-2">
                <span className="font-mono text-xs text-slate-500">{s.instante.slice(11)}</span>
                <span className="text-sm font-semibold text-slate-900">{s.titulo}</span>
                {GRAVEDADES[s.gravedad] && (
                    <span className={`text-xs font-semibold ${TEXTO_GRAVEDAD[s.gravedad]}`}>
                        {GRAVEDADES[s.gravedad].texto}
                    </span>
                )}
                {s.aula && (
                    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">
                        Aula {s.aula}
                    </span>
                )}
            </div>
            {s.estudiante && (
                <p className="text-sm text-slate-800">
                    {s.estudiante}
                    {s.codigo && <span className="text-slate-500"> · {s.codigo}</span>}
                </p>
            )}
            {s.detalle && <p className="text-sm text-slate-600">{s.detalle}</p>}
            {s.por && <p className="text-xs text-slate-500">{s.por}</p>}
        </li>
    );
}

Suceso.propTypes = {
    suceso: PropTypes.shape({
        instante: PropTypes.string.isRequired,
        categoria: PropTypes.string.isRequired,
        titulo: PropTypes.string.isRequired,
        detalle: PropTypes.string,
        aula: PropTypes.string,
        estudiante: PropTypes.string,
        codigo: PropTypes.string,
        por: PropTypes.string,
        gravedad: PropTypes.string,
    }).isRequired,
};
