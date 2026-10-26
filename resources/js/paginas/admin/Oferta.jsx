import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ChevronDown, ChevronRight, UserX } from 'lucide-react';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import Campo from '../../componentes/Campo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import IconoAsignatura from '../../componentes/IconoAsignatura';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import { usarFacultades } from '../../sesion/SesionContexto';

const POR_PAGINA = 15;
const GRUPOS_POR_PAGINA = 10;
const TH = 'px-4 py-3 font-medium';
const miles = (n) => Number(n ?? 0).toLocaleString('es-BO');
const plural = (n, uno, varios) => `${miles(n)} ${n === 1 ? uno : varios}`;

const ORDINALES = ['primer', 'segundo', 'tercer', 'cuarto', 'quinto', 'sexto', 'septimo'];

// «Preparatorio», «Primer año»… y «Semestre 1»… «Semestre 10», en el orden
// del plan, no en el alfabético.
function pesoDeNivel(nivel) {
    const texto = nivel.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const numero = Number(/\d+/.exec(texto)?.[0] ?? 0);
    if (texto.startsWith('semestre')) return 200 + numero;
    const ordinal = ORDINALES.findIndex((o) => texto.startsWith(o));
    if (ordinal >= 0) return 100 + ordinal;
    return numero;
}

export const ordenarNiveles = (niveles) =>
    [...niveles].sort((a, b) => pesoDeNivel(a) - pesoDeNivel(b) || a.localeCompare(b, 'es'));

// Los períodos vigentes que le tocan a la carrera elegida: el anual para las
// carreras anuales y los demás para las semestrales.
function periodosDe(vigentes, carrera) {
    if (!carrera) return vigentes;
    const anual = carrera.regimen === 'Anual';
    const suyos = vigentes.filter((p) => (p.tipo === 'Anual') === anual);
    return suyos.length > 0 ? suyos : vigentes;
}

function Docente({ nombre }) {
    return (
        nombre ?? (
            <span className="inline-flex items-center gap-1 text-warning-700">
                <UserX className="h-3.5 w-3.5" aria-hidden="true" /> Por designar
            </span>
        )
    );
}

Docente.propTypes = { nombre: PropTypes.string };

function Horarios({ horarios }) {
    return (horarios ?? []).map((h) => (
        <span key={`${h.dia}${h.hora}${h.aula}`} className="mr-3 inline-block whitespace-nowrap">
            {h.dia} {h.hora}
            {h.aula && (
                <>
                    {' '}
                    · <strong className="font-semibold text-slate-800">{h.aula}</strong>
                </>
            )}
        </span>
    ));
}

// Grupos de una asignatura en la facultad, de a diez: una asignatura de
// servicio llega a tener treinta. Con búsqueda por docente el servidor pone
// primero los que coinciden. Siguen a los filtros: al cambiar período,
// facultad o búsqueda se vuelven a pedir desde la página 1.
function Grupos({ asignatura, periodo, facultad, buscar }) {
    const lista = usarPaginaServidor({ porPagina: GRUPOS_POR_PAGINA });
    const { irA } = lista;

    useEffect(() => {
        irA(1);
    }, [irA, buscar, periodo, facultad]);

    const { datos, meta, cargando, error, recargar } = usarConsulta(
        `/oferta/asignaturas/${asignatura.id}/grupos`,
        { parametros: { ...lista.parametros, periodo, facultad, buscar } }
    );
    const grupos = datos ?? [];

    return (
        <div className="border-t border-slate-200 bg-slate-50/60">
            <EstadoCarga
                cargando={cargando}
                error={error}
                vacio={datos?.length === 0}
                textoVacio="Sin grupos"
                onReintentar={recargar}
                filas={Math.min(asignatura.grupos || 1, 3)}
            >
                <ul className="divide-y divide-slate-200 sm:hidden">
                    {grupos.map((g) => (
                        <li key={g.id} className="space-y-1 px-4 py-3 text-sm">
                            <p className="flex items-baseline justify-between gap-3">
                                <span className="font-medium text-slate-800">Grupo {g.codigo}</span>
                                <span className="shrink-0 text-xs text-slate-600">
                                    {plural(g.inscritos, 'inscrito', 'inscritos')}
                                </span>
                            </p>
                            <p className="break-words text-slate-700">
                                <Docente nombre={g.docente} />
                            </p>
                            <p className="text-xs leading-5 text-slate-600">
                                <Horarios horarios={g.horarios} />
                            </p>
                        </li>
                    ))}
                </ul>
                <div className="hidden overflow-x-auto sm:block">
                    <table className="w-full text-left text-sm">
                        <thead className="text-xs font-medium uppercase tracking-wide text-slate-600">
                            <tr>
                                <th scope="col" className={TH}>
                                    Grupo
                                </th>
                                <th scope="col" className={TH}>
                                    Docente
                                </th>
                                <th scope="col" className={TH}>
                                    Horario y aula
                                </th>
                                <th scope="col" className={`${TH} text-right`}>
                                    Inscritos
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {grupos.map((g) => (
                                <tr key={g.id} className="hover:bg-slate-50">
                                    <td className="px-4 py-2.5 font-medium text-slate-800">
                                        {g.codigo}
                                    </td>
                                    <td className="break-words px-4 py-2.5 text-slate-700">
                                        <Docente nombre={g.docente} />
                                    </td>
                                    <td className="px-4 py-2.5 text-slate-600">
                                        <Horarios horarios={g.horarios} />
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums text-slate-700">
                                        {miles(g.inscritos)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {(meta?.total ?? 0) > GRUPOS_POR_PAGINA && (
                    <Paginacion
                        {...lista.paginacion(meta)}
                        unidad={['grupo', 'grupos']}
                        className="border-t border-slate-200"
                    />
                )}
            </EstadoCarga>
        </div>
    );
}

Grupos.propTypes = {
    asignatura: PropTypes.shape({
        id: PropTypes.number.isRequired,
        grupos: PropTypes.number,
    }).isRequired,
    periodo: PropTypes.string,
    facultad: PropTypes.string,
    buscar: PropTypes.string,
};

// La oferta académica importada (HU-17): asignaturas de un período (por
// defecto, los vigentes) con sus grupos, docentes, horarios y aulas. Los
// períodos cerrados siguen consultables. Solo consulta; la oferta se corrige
// volviendo a importar (pantalla Período).
export default function Oferta() {
    const facultades = usarFacultades();
    // «Grupos sin lista» del período llega con `?sin_lista=1`.
    const [parametrosUrl] = useSearchParams();
    const lista = usarPaginaServidor({
        porPagina: POR_PAGINA,
        filtros: {
            periodo: '',
            facultad: null,
            carrera: '',
            nivel: '',
            sin_lista: parametrosUrl.get('sin_lista') === '1' ? 1 : null,
        },
    });
    const [abierta, setAbierta] = useState(null);

    // Se abre en la primera facultad del catálogo, como llega de la sesión.
    const facultad = lista.filtros.facultad ?? facultades[0]?.clave ?? null;
    const { periodo, carrera, nivel, sin_lista: sinLista } = lista.filtros;
    const parametros = { ...lista.parametros, facultad };

    const vigentes = usarConsulta('/periodos/vigentes');
    const detectados = usarConsulta('/periodos', { parametros: { por_pagina: 100 } });
    const carreras = usarConsulta('/oferta/carreras', {
        parametros: { facultad },
        activa: facultad !== null,
    });
    const { datos, meta, cargando, error, recargar } = usarConsulta('/oferta/asignaturas', {
        parametros,
        activa: facultad !== null,
    });
    // Los niveles que hay en lo filtrado (sin el nivel mismo).
    const consultaNiveles = usarConsulta('/oferta/niveles', {
        parametros: { periodo, facultad, carrera, buscar: parametros.buscar },
        activa: facultad !== null,
    });

    const sigla = facultades.find((f) => f.clave === facultad)?.sigla ?? null;
    const elegida = (carreras.datos ?? []).find((c) => String(c.id) === String(carrera)) ?? null;
    const pedido = (detectados.datos ?? []).find((p) => String(p.id) === String(periodo));
    const periodos = (pedido ? [pedido] : periodosDe(vigentes.datos ?? [], elegida)).map(
        (p) => p.codigo
    );
    const niveles = ordenarNiveles(consultaNiveles.datos ?? []);
    const subtitulo = [
        periodos.length > 0 &&
            `${periodos.length === 1 ? 'Período' : 'Períodos'} ${periodos.join(' y ')}`,
        sigla,
        elegida?.nombre,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <div className="space-y-6">
            <Encabezado titulo="Oferta" subtitulo={subtitulo || undefined}>
                <Insignia tono="neutro">Importado · solo lectura</Insignia>
            </Encabezado>

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-[9.5rem_7rem_minmax(0,1.4fr)_9.5rem_minmax(0,1fr)]">
                <Seleccion
                    etiqueta="Período"
                    className="col-span-2 min-w-0 sm:col-span-1"
                    value={periodo}
                    onChange={(e) => lista.ponerFiltros({ periodo: e.target.value, nivel: '' })}
                >
                    <option value="">Vigentes</option>
                    {(detectados.datos ?? []).map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.estado === 'Vigente' ? p.codigo : `${p.codigo} · ${p.estado}`}
                        </option>
                    ))}
                </Seleccion>
                <Seleccion
                    etiqueta="Facultad"
                    className="min-w-0"
                    value={facultad ?? ''}
                    onChange={(e) =>
                        lista.ponerFiltros({ facultad: e.target.value, carrera: '', nivel: '' })
                    }
                >
                    {facultades.map((f) => (
                        <option key={f.clave} value={f.clave}>
                            {f.sigla}
                        </option>
                    ))}
                </Seleccion>
                <Seleccion
                    etiqueta="Carrera"
                    className="order-last col-span-2 min-w-0 sm:order-none sm:col-span-1"
                    value={carrera}
                    onChange={(e) => {
                        lista.ponerFiltros({ carrera: e.target.value, nivel: '' });
                        setAbierta(null);
                    }}
                >
                    <option value="">Todas</option>
                    {(carreras.datos ?? []).map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.nombre}
                        </option>
                    ))}
                </Seleccion>
                <Seleccion
                    etiqueta="Nivel"
                    className="min-w-0"
                    value={nivel}
                    onChange={(e) => lista.ponerFiltro('nivel', e.target.value)}
                >
                    <option value="">Todos</option>
                    {niveles.map((n) => (
                        <option key={n} value={n}>
                            {n}
                        </option>
                    ))}
                </Seleccion>
                <Campo
                    etiqueta="Buscar"
                    type="search"
                    className="order-last col-span-2 min-w-0 lg:order-none lg:col-span-1"
                    placeholder="Asignatura, código o docente"
                    maxLength={100}
                    value={lista.buscar}
                    onChange={(e) => lista.ponerBuscar(e.target.value)}
                />
            </div>

            <Tarjeta
                sinRelleno
                titulo="Asignaturas"
                acciones={
                    <span className="flex items-center gap-3">
                        {meta && (
                            <span className="text-xs text-slate-600">
                                {plural(meta.total_grupos ?? 0, 'grupo', 'grupos')}
                            </span>
                        )}
                        <button
                            type="button"
                            aria-pressed={Boolean(sinLista)}
                            onClick={() => {
                                lista.ponerFiltro('sin_lista', sinLista ? null : 1);
                                setAbierta(null);
                            }}
                            className={`inline-flex min-h-9 items-center rounded-full border px-3 text-xs font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 ${sinLista ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'}`}
                        >
                            Con grupos sin lista
                        </button>
                    </span>
                }
            >
                <EstadoCarga
                    cargando={cargando || facultad === null}
                    error={error}
                    vacio={datos?.length === 0}
                    textoVacio="Sin resultados"
                    onReintentar={recargar}
                    filas={8}
                >
                    <ul className="divide-y divide-slate-200">
                        {(datos ?? []).map((a) => {
                            const abiertaEsta = abierta === a.id;
                            const Flecha = abiertaEsta ? ChevronDown : ChevronRight;
                            return (
                                <li key={a.id}>
                                    <button
                                        type="button"
                                        aria-expanded={abiertaEsta}
                                        onClick={() => setAbierta(abiertaEsta ? null : a.id)}
                                        className={`flex min-h-14 w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600 ${abiertaEsta ? 'bg-slate-50' : ''}`}
                                    >
                                        <Flecha
                                            className="h-4 w-4 shrink-0 text-slate-600"
                                            aria-hidden="true"
                                        />
                                        <IconoAsignatura nombre={a.nombre} />
                                        <span className="min-w-0 flex-1">
                                            <span
                                                className="block truncate text-sm font-medium text-slate-800"
                                                title={a.nombre}
                                            >
                                                {a.nombre}
                                            </span>
                                            <span className="block truncate text-xs text-slate-600">
                                                {[a.codigo, a.nivel].filter(Boolean).join(' · ')}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-right text-xs tabular-nums text-slate-600">
                                            {plural(a.grupos, 'grupo', 'grupos')}
                                            <br />
                                            {plural(a.inscritos, 'inscrito', 'inscritos')}
                                        </span>
                                    </button>
                                    {abiertaEsta && (
                                        <Grupos
                                            asignatura={a}
                                            periodo={periodo}
                                            facultad={facultad}
                                            buscar={parametros.buscar}
                                        />
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                    <Paginacion
                        {...lista.paginacion(meta)}
                        unidad={['asignatura', 'asignaturas']}
                        className="border-t border-slate-200"
                    />
                </EstadoCarga>
            </Tarjeta>
        </div>
    );
}
