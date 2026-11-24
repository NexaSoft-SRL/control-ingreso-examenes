import PropTypes from 'prop-types';
import { useEffect, useMemo, useState } from 'react';
import { CalendarDays, Check, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import usarConsulta from '../../api/usarConsulta';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Dialogo from '../../componentes/Dialogo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import { fechaLarga, normalizar, ubicacionSin } from '../../utiles/texto';
import {
    DIAS,
    FIN,
    INICIO,
    MARCAS,
    PX_HORA,
    bloquesDelDia,
    conCarriles,
    horaDe,
    hoyISO,
    indiceDeDia,
    lunesDe,
    posicionDe,
    rangoDeSemana,
    semanaDe,
    sumarDias,
} from './calendarioDeAulas';
import rutaDelExamen from './rutaDelExamen';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const ALTO = ((FIN - INICIO) / 60) * PX_HORA;
// Cada tipo de bloque, con su color y su nombre.
const TIPOS = {
    clase: {
        nombre: 'Clase',
        tono: 'neutro',
        clases: 'border-slate-300 bg-slate-100 text-slate-700 hover:bg-slate-200',
    },
    auxiliatura: {
        nombre: 'Auxiliatura',
        tono: 'neutro',
        clases: 'border-dashed border-slate-400 bg-white text-slate-600 hover:bg-slate-50',
    },
    examen: {
        nombre: 'Examen',
        tono: 'advertencia',
        clases: 'border-warning-600 bg-warning-100 text-warning-800 hover:bg-warning-200',
    },
    mio: {
        nombre: 'Tu examen',
        tono: 'primario',
        clases: 'border-primary-700 bg-primary-600 text-white hover:bg-primary-700',
    },
};

// En el teléfono se ve un día a la vez.
function useMovil() {
    const consulta = '(max-width: 639px)';
    const [movil, setMovil] = useState(() => window.matchMedia?.(consulta).matches === true);

    useEffect(() => {
        const lista = window.matchMedia?.(consulta);
        if (!lista) return undefined;
        const cambiar = () => setMovil(lista.matches);
        lista.addEventListener?.('change', cambiar);
        return () => lista.removeEventListener?.('change', cambiar);
    }, []);

    return movil;
}

function Bloque({ b, onElegir }) {
    const tipo = TIPOS[b.tipo];
    const largo = b.hasta - b.desde >= 50;
    const alto = b.hasta - b.desde >= 75;

    return (
        <button
            type="button"
            onClick={() => onElegir(b)}
            style={posicionDe(b)}
            aria-label={`${tipo.nombre} · ${b.titulo} · ${b.inicio} a ${b.fin}`}
            className={`absolute overflow-hidden rounded-md border px-1.5 py-1 text-left text-xs leading-tight ${tipo.clases} ${FOCO}`}
        >
            <span className={`block font-semibold ${alto ? 'line-clamp-2' : 'truncate'}`}>
                {b.titulo}
            </span>
            {largo && (
                <span className="block truncate opacity-90">
                    {b.inicio}–{b.fin}
                </span>
            )}
        </button>
    );
}

Bloque.propTypes = {
    b: PropTypes.object.isRequired,
    onElegir: PropTypes.func.isRequired,
};

// Los días en columnas y las horas en filas. Cada hora cae a las :45, como
// los períodos del horario.
function Grilla({ dias, aula, hoy, cabecera = true, onElegir }) {
    const columnas = `3.25rem repeat(${dias.length}, minmax(0, 1fr))`;
    const lineas = {
        backgroundImage: `repeating-linear-gradient(to bottom, transparent 0, transparent ${PX_HORA - 1}px, #e2e8f0 ${PX_HORA - 1}px, #e2e8f0 ${PX_HORA}px)`,
        height: `${ALTO}px`,
    };

    return (
        <div className="overflow-x-auto">
            <div className="min-w-[19rem] sm:min-w-[40rem]">
                <div
                    className={`grid border-b border-slate-200 bg-slate-50 ${cabecera ? '' : 'hidden'}`}
                    style={{ gridTemplateColumns: columnas }}
                >
                    <span />
                    {dias.map((fecha) => {
                        const esHoy = fecha === hoy;
                        return (
                            <div
                                key={fecha}
                                className="flex flex-col items-center gap-0.5 border-l border-slate-200 py-2 text-slate-600"
                            >
                                <span className="text-xs font-medium uppercase">
                                    {DIAS[indiceDeDia(fecha)]}
                                </span>
                                <span
                                    className={`flex h-7 min-w-7 items-center justify-center rounded-full px-1 text-sm font-semibold ${esHoy ? 'bg-primary-600 text-white' : 'text-slate-900'}`}
                                    aria-label={`${fechaLarga(fecha)}${esHoy ? ', hoy' : ''}`}
                                >
                                    {Number(fecha.slice(8))}
                                </span>
                            </div>
                        );
                    })}
                </div>
                <div className="grid" style={{ gridTemplateColumns: columnas }}>
                    <div className="relative" style={{ height: `${ALTO}px` }} aria-hidden="true">
                        {MARCAS.slice(0, -1).map((minutos) => (
                            <span
                                key={minutos}
                                className="absolute right-1.5 -translate-y-1/2 text-[11px] leading-none text-slate-500 first:translate-y-0"
                                style={{ top: `${((minutos - INICIO) / 60) * PX_HORA}px` }}
                            >
                                {horaDe(minutos)}
                            </span>
                        ))}
                    </div>
                    {dias.map((fecha) => (
                        <div
                            key={fecha}
                            className={`relative border-l border-slate-200 ${fecha === hoy ? 'bg-primary-50/50' : ''}`}
                            style={lineas}
                        >
                            {conCarriles(bloquesDelDia(aula, fecha)).map((b) => (
                                <Bloque key={b.clave} b={b} onElegir={onElegir} />
                            ))}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

Grilla.propTypes = {
    dias: PropTypes.arrayOf(PropTypes.string).isRequired,
    aula: PropTypes.object,
    hoy: PropTypes.string.isRequired,
    cabecera: PropTypes.bool,
    onElegir: PropTypes.func.isRequired,
};

function Leyenda() {
    return (
        <ul className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-xs text-slate-600">
            {Object.entries(TIPOS)
                .filter(([clave]) => clave !== 'auxiliatura')
                .map(([clave, tipo]) => (
                    <li key={clave} className="flex items-center gap-1.5">
                        <span
                            className={`h-3 w-3 rounded-sm border ${tipo.clases.split(' ').slice(0, 2).join(' ')}`}
                            aria-hidden="true"
                        />
                        {tipo.nombre}
                    </li>
                ))}
        </ul>
    );
}

function Detalle({ bloque, aula, onCerrar }) {
    const tipo = TIPOS[bloque.tipo];

    return (
        <Dialogo
            titulo={bloque.titulo}
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cerrar
                    </Boton>
                    {bloque.examenId && (
                        <Link
                            to={rutaDelExamen(bloque.examenId)}
                            className={`inline-flex min-h-11 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white hover:bg-primary-700 ${FOCO}`}
                        >
                            Ver examen
                        </Link>
                    )}
                </>
            }
        >
            <div className="space-y-3 text-sm text-slate-700">
                <Insignia tono={tipo.tono}>{tipo.nombre}</Insignia>
                <dl className="grid grid-cols-[5rem_minmax(0,1fr)] gap-x-3 gap-y-1.5">
                    <dt className="text-slate-500">Día</dt>
                    <dd className="font-medium text-slate-900">{fechaLarga(bloque.fecha)}</dd>
                    <dt className="text-slate-500">Hora</dt>
                    <dd className="font-medium text-slate-900">
                        {bloque.inicio} a {bloque.fin}
                    </dd>
                    <dt className="text-slate-500">Aula</dt>
                    <dd className="font-medium text-slate-900">{aula}</dd>
                    <dt className="text-slate-500">Detalle</dt>
                    <dd className="font-medium text-slate-900">{bloque.detalle}</dd>
                    <dt className="text-slate-500">Docente</dt>
                    <dd className="font-medium text-slate-900">
                        {bloque.docente ?? 'Por designar'}
                    </dd>
                </dl>
            </div>
        </Dialogo>
    );
}

Detalle.propTypes = {
    bloque: PropTypes.object.isRequired,
    aula: PropTypes.string.isRequired,
    onCerrar: PropTypes.func.isRequired,
};

// Qué aulas están ocupadas esa semana y a qué hora (GET /api/calendario/aulas),
// para elegir aula antes de registrar el examen. Clases del horario y
// exámenes; solo de consulta. El aula elegida va en `?aula=`.
export default function CalendarioAulas() {
    const hoy = hoyISO();
    const [parametros, setParametros] = useSearchParams();
    const aulaId = Number(parametros.get('aula')) || null;
    const [lunes, setLunes] = useState(() => lunesDe(hoy));
    const [indice, setIndice] = useState(() => indiceDeDia(hoy));
    const [edificio, setEdificio] = useState('');
    const [busqueda, setBusqueda] = useState('');
    const [detalle, setDetalle] = useState(null);
    const movil = useMovil();

    const catalogo = usarConsulta('/aulas');
    const aulas = useMemo(() => catalogo.datos ?? [], [catalogo.datos]);
    const sabado = sumarDias(lunes, 5);
    const consulta = usarConsulta('/calendario/aulas', {
        parametros: { desde: lunes, hasta: sabado, aulas: aulaId ? [aulaId] : undefined },
        activa: Boolean(aulaId),
    });

    const edificios = useMemo(
        () =>
            [
                ...new Map(
                    aulas.filter((a) => a.edificio_id).map((a) => [a.edificio_id, a.edificio])
                ),
            ].sort((a, b) => String(a[1]).localeCompare(String(b[1]), 'es', { numeric: true })),
        [aulas]
    );
    const elegida = aulas.find((a) => a.id === aulaId) ?? null;
    const texto = normalizar(busqueda.trim());
    const posibles = useMemo(
        () =>
            aulas
                .filter(
                    (a) =>
                        a.id === aulaId ||
                        ((edificio === '' ||
                            (edificio === 'sin'
                                ? !a.edificio_id
                                : String(a.edificio_id) === edificio)) &&
                            (!texto ||
                                normalizar(`${a.nombre} ${a.edificio ?? ''}`).includes(texto)))
                )
                .sort((a, b) =>
                    String(a.nombre).localeCompare(String(b.nombre), 'es', { numeric: true })
                ),
        [aulas, aulaId, edificio, texto]
    );

    const elegirAula = (id) => {
        const siguiente = new URLSearchParams(parametros);
        if (id) siguiente.set('aula', id);
        else siguiente.delete('aula');
        setParametros(siguiente, { replace: true });
    };
    const mover = (semanas) => setLunes((actual) => sumarDias(actual, semanas * 7));
    const irAHoy = () => {
        setLunes(lunesDe(hoy));
        setIndice(indiceDeDia(hoy));
    };

    const aula = consulta.datos?.aulas?.[aulaId] ?? null;
    const semana = semanaDe(lunes);
    const dias = movil ? [semana[indice]] : semana;
    const sinNada = aula && aula.clases.length === 0 && aula.examenes.length === 0;
    const nombreAula = elegida
        ? [elegida.nombre, ubicacionSin(elegida.nombre, elegida.edificio)]
              .filter(Boolean)
              .join(' · ')
        : '';

    return (
        <div className="space-y-5">
            <Encabezado titulo="Calendario" subtitulo="Aulas ocupadas por semana">
                <Link
                    to="/examenes/nuevo"
                    className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700 ${FOCO}`}
                >
                    <Plus className="h-4 w-4" strokeWidth={2.5} /> Registrar examen
                </Link>
            </Encabezado>

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <Buscador
                    valor={busqueda}
                    onCambiar={setBusqueda}
                    placeholder="Buscar aula"
                    etiqueta="Buscar aula"
                    className="col-span-2 sm:col-span-1"
                />
                <Seleccion
                    aria-label="Edificio"
                    value={edificio}
                    onChange={(e) => setEdificio(e.target.value)}
                >
                    <option value="">Todos los edificios</option>
                    {edificios.map(([id, nombre]) => (
                        <option key={id} value={String(id)}>
                            {nombre}
                        </option>
                    ))}
                    <option value="sin">Sin edificio</option>
                </Seleccion>
                <Seleccion
                    aria-label="Aula"
                    value={String(aulaId ?? '')}
                    onChange={(e) => elegirAula(e.target.value)}
                >
                    <option value="">Elegir aula</option>
                    {posibles.map((a) => (
                        <option key={a.id} value={String(a.id)}>
                            {[a.nombre, edificio === '' ? ubicacionSin(a.nombre, a.edificio) : '']
                                .filter(Boolean)
                                .join(' · ')}
                        </option>
                    ))}
                </Seleccion>
            </div>

            {!aulaId ? (
                <Tarjeta>
                    <div className="flex flex-col items-center gap-2 py-8 text-center">
                        <span className="flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                            <CalendarDays className="h-6 w-6" aria-hidden="true" />
                        </span>
                        <p className="font-semibold text-slate-900">Elija un aula</p>
                        <p className="text-sm text-slate-600">
                            Vea qué clases y exámenes tiene cada semana
                        </p>
                    </div>
                </Tarjeta>
            ) : (
                <Tarjeta
                    sinRelleno
                    titulo={nombreAula ? `Aula ${nombreAula}` : 'Aula'}
                    icono={CalendarDays}
                    acciones={
                        <div className="flex items-center gap-1">
                            <button
                                type="button"
                                aria-label="Semana anterior"
                                onClick={() => mover(-1)}
                                className={`flex h-11 w-11 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 ${FOCO}`}
                            >
                                <ChevronLeft className="h-5 w-5" aria-hidden="true" />
                            </button>
                            <span className="min-w-[8.5rem] text-center text-sm font-semibold text-slate-900">
                                {rangoDeSemana(lunes)}
                            </span>
                            <button
                                type="button"
                                aria-label="Semana siguiente"
                                onClick={() => mover(1)}
                                className={`flex h-11 w-11 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 ${FOCO}`}
                            >
                                <ChevronRight className="h-5 w-5" aria-hidden="true" />
                            </button>
                            <Boton type="button" variante="secundario" onClick={irAHoy}>
                                Hoy
                            </Boton>
                        </div>
                    }
                >
                    <EstadoCarga
                        cargando={consulta.cargando}
                        error={consulta.error}
                        onReintentar={consulta.recargar}
                        filas={6}
                    >
                        {sinNada && (
                            <p
                                role="status"
                                className="flex items-center gap-2 border-b border-success-200 bg-success-50 px-5 py-2.5 text-sm font-medium text-success-800"
                            >
                                <Check className="h-4 w-4" aria-hidden="true" /> Aula libre esta
                                semana
                            </p>
                        )}
                        {movil && (
                            <div
                                role="group"
                                aria-label="Día"
                                className="grid grid-cols-6 gap-1 border-b border-slate-200 p-2"
                            >
                                {semana.map((fecha, i) => (
                                    <button
                                        key={fecha}
                                        type="button"
                                        aria-pressed={i === indice}
                                        aria-label={fechaLarga(fecha)}
                                        onClick={() => setIndice(i)}
                                        className={`flex min-h-11 flex-col items-center justify-center rounded-lg text-xs font-medium ${FOCO} ${i === indice ? 'bg-primary-600 text-white' : 'text-slate-700 hover:bg-slate-100'}`}
                                    >
                                        <span>{DIAS[i]}</span>
                                        <span className="text-sm font-semibold">
                                            {Number(fecha.slice(8))}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        )}
                        <Grilla
                            dias={dias}
                            aula={aula}
                            hoy={hoy}
                            cabecera={!movil}
                            onElegir={setDetalle}
                        />
                        <Leyenda />
                    </EstadoCarga>
                </Tarjeta>
            )}

            {detalle && (
                <Detalle
                    bloque={detalle}
                    aula={nombreAula || String(aulaId)}
                    onCerrar={() => setDetalle(null)}
                />
            )}
        </div>
    );
}
