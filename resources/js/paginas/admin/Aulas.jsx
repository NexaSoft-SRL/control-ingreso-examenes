import PropTypes from 'prop-types';
import { useMemo, useRef, useState } from 'react';
import usarConsulta from '../../api/usarConsulta';
import usarEdificios from '../../api/usarEdificios';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import FiltroFacultad, { PuntoFacultad } from '../../componentes/FiltroFacultad';
import MapaCampus from '../../componentes/MapaCampus';
import Paginacion, { usePaginas } from '../../componentes/Paginacion';
import Tarjeta from '../../componentes/Tarjeta';
import { usarFacultades } from '../../sesion/SesionContexto';
import { normalizar, plural } from '../../utiles/texto';

const POR_PAGINA = 12;
const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600';
const FILA = 'flex min-h-12 w-full items-center gap-3 px-4 py-2 text-left';

const ordenNatural = (a, b) => a.localeCompare(b, 'es', { numeric: true, sensitivity: 'base' });
const esPlantaBaja = (nombre) => normalizar(nombre).startsWith('planta baja');

// Las aulas de un edificio agrupadas por piso, con la planta baja primero.
// Si no se conocen los pisos van en un solo grupo sin nombre, igual que las
// que quedan sin piso en un edificio que sí los tiene.
export function pisosDe(edificio) {
    const aulas = edificio.aulas ?? [];
    const pisos = (edificio.pisos ?? []).filter((p) => (p.aulas ?? []).length > 0);
    const conPiso = new Set(pisos.flatMap((p) => p.aulas));
    const sueltas = aulas.filter((a) => !conPiso.has(a));
    const ordenados = [
        ...pisos.filter((p) => esPlantaBaja(p.nombre)),
        ...pisos.filter((p) => !esPlantaBaja(p.nombre)),
    ];
    return sueltas.length > 0 ? [...ordenados, { nombre: null, aulas: sueltas }] : ordenados;
}

// Aulas y edificios importados (GET /api/edificios y GET /api/aulas, que
// devuelven el catálogo entero): la página filtra y pagina en memoria. La
// vista es del campus completo y se acota por facultad; cada facultad conserva
// su color. La lista de edificios elige lo mismo que el mapa, también con el
// teclado. Es solo de consulta.
function Vista({ onReintentar }) {
    const facultades = usarFacultades();
    const mapa = usarEdificios();
    const consulta = usarConsulta('/aulas');

    const [facultad, setFacultad] = useState(null);
    const [busqueda, setBusqueda] = useState('');
    const [edificioId, setEdificioId] = useState(null);
    const [aula, setAula] = useState(null);
    const [lista, setLista] = useState('edificios');
    const detalle = useRef(null);

    const edificios = mapa.edificios;
    const todas = useMemo(
        () => [...(consulta.datos ?? [])].sort((a, b) => ordenNatural(a.nombre, b.nombre)),
        [consulta.datos]
    );

    const cargando = mapa.cargando || consulta.cargando;
    const error = mapa.error || consulta.error;

    const actual = facultades.find((f) => f.sigla === facultad);
    const deLaFacultad = facultad ? edificios.filter((e) => e.facultad === facultad) : edificios;
    const aulasDeLaFacultad = facultad ? todas.filter((a) => a.facultad === facultad) : todas;
    const texto = normalizar(busqueda.trim());
    const coincide = (valor) => normalizar(valor).includes(texto);
    const listados = texto
        ? deLaFacultad.filter((e) => coincide(e.nombre) || (e.aulas ?? []).some(coincide))
        : deLaFacultad;
    const aulas = texto
        ? aulasDeLaFacultad.filter((a) => coincide(a.nombre) || coincide(a.edificio))
        : aulasDeLaFacultad;
    const clave = `${facultad}|${texto}`;
    const paginasEdificios = usePaginas(listados, POR_PAGINA, clave);
    const paginasAulas = usePaginas(aulas, POR_PAGINA, clave);
    const elegido = deLaFacultad.find((e) => e.id === edificioId) ?? null;
    const pisos = elegido ? pisosDe(elegido) : [];

    // Desde la lista de aulas queda marcada también el aula dentro del edificio.
    function elegir(e, desplazar, nombreAula = null) {
        setEdificioId(e?.id ?? null);
        setAula(nombreAula);
        if (e && desplazar && window.matchMedia?.('(max-width: 1023px)').matches) {
            requestAnimationFrame(() =>
                detalle.current?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
            );
        }
    }

    if (cargando || error) {
        return (
            <div className="space-y-6">
                <Encabezado titulo="Aulas y mapa" />
                <Tarjeta sinRelleno>
                    <EstadoCarga cargando={cargando} error={error} onReintentar={onReintentar} />
                </Tarjeta>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <Encabezado
                titulo="Aulas y mapa"
                subtitulo={`${actual ? actual.nombre : 'Campus completo'} · ${plural(deLaFacultad.length, 'edificio')} · ${plural(aulasDeLaFacultad.length, 'aula')}`}
            />

            <FiltroFacultad
                valor={facultad}
                onCambiar={(sigla) => {
                    setFacultad(sigla);
                    setEdificioId(null);
                    setAula(null);
                }}
                conteo={(sigla) => todas.filter((a) => !sigla || a.facultad === sigla).length}
            />

            <div className="grid gap-6 lg:grid-cols-[1.35fr_1fr]">
                <div className="min-w-0 space-y-6">
                    <Tarjeta titulo={facultad ? `Edificios de ${facultad}` : 'Campus'}>
                        <MapaCampus
                            alto="h-72 sm:h-96"
                            edificios={edificios}
                            caja={mapa.caja}
                            facultad={facultad}
                            seleccionado={elegido?.id ?? null}
                            onElegir={(e) => elegir(e, false)}
                        />
                        <ul
                            aria-label="Facultades"
                            className="mt-3 grid gap-x-4 gap-y-1.5 sm:grid-cols-2"
                        >
                            {facultades
                                .filter((f) => !facultad || f.sigla === facultad)
                                .map((f) => (
                                    <li
                                        key={f.sigla}
                                        className="flex min-w-0 items-start gap-1.5 text-xs text-slate-600"
                                    >
                                        <span
                                            className="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                                            style={{ backgroundColor: f.color }}
                                        />
                                        <span className="min-w-0">
                                            <span className="font-medium text-slate-800">
                                                {f.sigla}
                                            </span>{' '}
                                            · {f.nombre}
                                        </span>
                                    </li>
                                ))}
                        </ul>
                    </Tarjeta>

                    {elegido && (
                        <div ref={detalle} className="scroll-mt-4" data-testid="detalle-edificio">
                            <Tarjeta
                                titulo={elegido.nombre}
                                acciones={
                                    <Boton
                                        variante="enlace"
                                        tamano="chico"
                                        onClick={() => elegir(null, false)}
                                    >
                                        Quitar selección
                                    </Boton>
                                }
                            >
                                <div className="mb-4 flex items-center gap-3">
                                    <PuntoFacultad sigla={elegido.facultad} />
                                    <span className="text-xs text-slate-600">
                                        {plural((elegido.aulas ?? []).length, 'aula')}
                                    </span>
                                </div>
                                <div className="space-y-4">
                                    {pisos.map((piso) => (
                                        <div key={piso.nombre ?? 'aulas'}>
                                            {piso.nombre && (
                                                <p className="mb-2 text-xs font-medium uppercase tracking-wide text-slate-600">
                                                    {piso.nombre}
                                                </p>
                                            )}
                                            <ul
                                                aria-label={piso.nombre ?? 'Aulas'}
                                                className="grid grid-cols-[repeat(auto-fill,minmax(5.5rem,1fr))] gap-2"
                                            >
                                                {piso.aulas.map((a) => (
                                                    <li
                                                        key={a}
                                                        aria-current={a === aula || undefined}
                                                        className={`truncate rounded-lg border px-2 py-1.5 text-center text-sm font-medium ${a === aula ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-200 bg-slate-50 text-slate-800'}`}
                                                        title={a}
                                                    >
                                                        {a}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}
                                    {pisos.length === 0 && (
                                        <p className="py-4 text-center text-sm text-slate-600">
                                            Sin aulas
                                        </p>
                                    )}
                                </div>
                            </Tarjeta>
                        </div>
                    )}
                </div>

                <Tarjeta sinRelleno className="self-start">
                    <div className="space-y-3 border-b border-slate-200 p-4">
                        <div
                            role="tablist"
                            aria-label="Lista"
                            className="flex w-fit max-w-full gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 text-sm font-medium"
                        >
                            {[
                                ['edificios', 'Edificios', listados.length],
                                ['aulas', 'Aulas', aulas.length],
                            ].map(([id, etiqueta, n]) => (
                                <button
                                    key={id}
                                    type="button"
                                    role="tab"
                                    aria-selected={lista === id}
                                    onClick={() => setLista(id)}
                                    className={`min-h-10 whitespace-nowrap rounded-md px-3.5 ${FOCO} ${lista === id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
                                >
                                    {etiqueta}{' '}
                                    <span className="font-normal text-slate-500">{n}</span>
                                </button>
                            ))}
                        </div>
                        <Campo
                            type="search"
                            placeholder="Edificio o aula"
                            aria-label="Buscar edificio o aula"
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                        />
                    </div>
                    {lista === 'edificios' ? (
                        <>
                            <ul aria-label="Edificios" className="divide-y divide-slate-200">
                                {paginasEdificios.visibles.map((e) => {
                                    const activo = elegido?.id === e.id;
                                    const suyas = e.aulas ?? [];
                                    const coinciden = texto ? suyas.filter(coincide) : [];
                                    return (
                                        <li key={e.id}>
                                            <button
                                                type="button"
                                                aria-pressed={activo}
                                                onClick={() => elegir(activo ? null : e, !activo)}
                                                className={`${FILA} ${FOCO} ${activo ? 'bg-primary-50' : 'hover:bg-slate-50'}`}
                                            >
                                                <PuntoFacultad
                                                    sigla={e.facultad}
                                                    conTexto={false}
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span
                                                        className="block truncate text-sm font-medium text-slate-800"
                                                        title={e.nombre}
                                                    >
                                                        {e.nombre}
                                                    </span>
                                                    <span className="block truncate text-xs text-slate-600">
                                                        {coinciden.length > 0
                                                            ? coinciden.join(' · ')
                                                            : e.facultad}
                                                    </span>
                                                </span>
                                                <span className="shrink-0 text-right text-xs text-slate-600">
                                                    {plural(suyas.length, 'aula')}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                                {listados.length === 0 && (
                                    <li className="px-4 py-8 text-center text-sm text-slate-600">
                                        Sin resultados
                                    </li>
                                )}
                            </ul>
                            {listados.length > 0 && (
                                <Paginacion
                                    {...paginasEdificios.paginacion}
                                    unidad={['edificio', 'edificios']}
                                    className="border-t border-slate-200"
                                />
                            )}
                        </>
                    ) : (
                        <>
                            <ul aria-label="Aulas" className="divide-y divide-slate-200">
                                {paginasAulas.visibles.map((a) => {
                                    const suEdificio =
                                        edificios.find((e) => e.id === a.edificio_id) ?? null;
                                    const activo =
                                        suEdificio !== null &&
                                        elegido?.id === suEdificio.id &&
                                        (!aula || aula === a.nombre);
                                    const contenido = (
                                        <>
                                            <PuntoFacultad
                                                sigla={a.facultad ?? ''}
                                                conTexto={false}
                                            />
                                            <span
                                                className="w-20 shrink-0 truncate text-sm font-semibold text-slate-800"
                                                title={a.nombre}
                                            >
                                                {a.nombre}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span
                                                    className={`block truncate text-sm ${a.edificio ? 'text-slate-700' : 'text-slate-500'}`}
                                                    title={a.edificio ?? undefined}
                                                >
                                                    {a.edificio ?? 'Sin edificio'}
                                                </span>
                                                <span className="block truncate text-xs text-slate-600">
                                                    {[a.facultad, a.piso]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </span>
                                        </>
                                    );
                                    return (
                                        <li key={a.id}>
                                            {suEdificio ? (
                                                <button
                                                    type="button"
                                                    aria-pressed={activo}
                                                    onClick={() =>
                                                        elegir(suEdificio, true, a.nombre)
                                                    }
                                                    className={`${FILA} ${FOCO} ${activo ? 'bg-primary-50' : 'hover:bg-slate-50'}`}
                                                >
                                                    {contenido}
                                                </button>
                                            ) : (
                                                <div className={FILA}>{contenido}</div>
                                            )}
                                        </li>
                                    );
                                })}
                                {aulas.length === 0 && (
                                    <li className="px-4 py-8 text-center text-sm text-slate-600">
                                        Sin resultados
                                    </li>
                                )}
                            </ul>
                            {aulas.length > 0 && (
                                <Paginacion
                                    {...paginasAulas.paginacion}
                                    unidad={['aula', 'aulas']}
                                    className="border-t border-slate-200"
                                />
                            )}
                        </>
                    )}
                </Tarjeta>
            </div>
        </div>
    );
}

Vista.propTypes = { onReintentar: PropTypes.func.isRequired };

// «Reintentar» vuelve a montar la vista: así se piden otra vez los edificios,
// que `usarEdificios` guarda en memoria y no sabe recargar.
export default function Aulas() {
    const [intento, setIntento] = useState(0);
    return <Vista key={intento} onReintentar={() => setIntento((n) => n + 1)} />;
}
