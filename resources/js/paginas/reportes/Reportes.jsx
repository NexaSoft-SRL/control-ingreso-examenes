import { useEffect, useState } from 'react';
import {
    CalendarRange,
    CircleCheck,
    ClipboardList,
    Clock,
    GraduationCap,
    History,
    Search,
    ShieldX,
    X,
} from 'lucide-react';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import FiltroFacultad, { PuntoFacultad } from '../../componentes/FiltroFacultad';
import Paginacion from '../../componentes/Paginacion';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import { usarSesion } from '../../sesion/SesionContexto';
import { fechaCorta } from '../../utiles/texto';
import BitacoraDelExamen from '../examenes/BitacoraDelExamen';
import BotonesDescarga from './BotonesDescarga';

// Reportes de toda la universidad (HU-24). El administrador mira todos los
// exámenes y no modifica registros. Las cifras salen de los exámenes que
// dejan los filtros, o del examen elegido en la lista (`meta.cifras`).
const REPORTES = [
    {
        clave: 'horarios',
        icono: Clock,
        archivo: 'horarios_ingreso',
        nombre: 'Horarios de ingreso',
        cifra: 'ingresos',
        unidad: ['ingreso', 'ingresos'],
    },
    {
        clave: 'registrados',
        icono: ClipboardList,
        archivo: 'registrados_examen',
        nombre: 'Registrados por examen',
        cifra: 'examenes',
        unidad: ['examen', 'exámenes'],
    },
    {
        clave: 'intentos',
        icono: ShieldX,
        archivo: 'intentos_rechazados',
        nombre: 'Intentos rechazados',
        cifra: 'intentos',
        unidad: ['intento', 'intentos'],
    },
    {
        clave: 'estudiantes',
        icono: GraduationCap,
        archivo: 'resumen_estudiantes',
        nombre: 'Resumen por estudiante',
        cifra: 'estudiantes',
        unidad: ['estudiante', 'estudiantes'],
    },
];

const miles = (n) => Number(n ?? 0).toLocaleString('es-BO');
const TH = 'px-4 py-3 font-medium';
const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600';

export default function Reportes() {
    const { puede } = usarSesion();
    const [elegido, setElegido] = useState(null);
    // La bitácora del examen elegido, para auditar un reclamo.
    const [conBitacora, setConBitacora] = useState(false);
    const [aviso, avisar] = useAviso();
    const lista = usarPaginaServidor({
        porPagina: 10,
        filtros: { periodo: null, facultad: null, desde: '', hasta: '' },
    });
    const { periodo, facultad, desde, hasta } = lista.filtros;

    // Quien administra los períodos los ve todos (también los cerrados); el
    // resto, los vigentes.
    const todosLosPeriodos = puede('periodo_oferta');
    const { datos: periodos } = usarConsulta(
        todosLosPeriodos ? '/periodos' : '/periodos/vigentes',
        {
            parametros: todosLosPeriodos ? { por_pagina: 100 } : undefined,
        }
    );

    // Abre en el período vigente principal; «Todos» sigue a mano.
    const vigentes = usarConsulta('/periodos/vigentes');
    const principal = vigentes.cuerpo?.principal ?? null;
    const [periodoInicial, setPeriodoInicial] = useState(false);
    const { ponerFiltro } = lista;
    useEffect(() => {
        if (periodoInicial || !periodos || vigentes.cargando) return;
        const id = periodos.find((p) => p.codigo === principal)?.id ?? null;
        if (id) ponerFiltro('periodo', id);
        setPeriodoInicial(true);
    }, [periodoInicial, periodos, principal, vigentes.cargando, ponerFiltro]);

    const rangoInvalido = Boolean(desde && hasta && hasta < desde);
    const { datos, meta, cargando, error, recargar } = usarConsulta('/reportes/examenes', {
        parametros: { ...lista.parametros, examen: elegido?.id },
        activa: !rangoInvalido && periodoInicial,
    });
    const examenes = datos ?? [];
    const cifras = meta?.cifras ?? null;
    const sinAlcance = !cifras || cifras.examenes === 0;

    // Al cambiar un filtro, el examen elegido puede quedar fuera de la lista.
    const filtrar = (clave, valor) => {
        setElegido(null);
        lista.ponerFiltro(clave, valor);
    };
    const buscar = (texto) => {
        setElegido(null);
        lista.ponerBuscar(texto);
    };
    const alternar = (e) => setElegido(elegido?.id === e.id ? null : e);

    // Lo que viaja en cada descarga: los filtros vigentes y, si hay, el examen.
    const alcance = Object.fromEntries(
        Object.entries({ ...lista.filtrosVigentes, examen: elegido?.id }).filter(([, v]) => v)
    );
    const sufijo = elegido ? `examen${elegido.id}` : (facultad ?? 'umss').toLowerCase();

    return (
        <div className="space-y-6">
            <Encabezado titulo="Reportes">
                <Seleccion
                    aria-label="Período"
                    icono={CalendarRange}
                    className="w-full sm:w-44"
                    value={periodo ?? ''}
                    onChange={(e) => filtrar('periodo', Number(e.target.value) || null)}
                >
                    <option value="">Todos</option>
                    {(periodos ?? []).map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.codigo}
                        </option>
                    ))}
                </Seleccion>
            </Encabezado>

            <FiltroFacultad
                valor={facultad}
                onCambiar={(sigla) => filtrar('facultad', sigla)}
                conteo={(sigla) =>
                    meta?.conteos ? miles(meta.conteos[sigla ?? 'todas']) : undefined
                }
            />

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <Campo
                    etiqueta="Buscar"
                    icono={Search}
                    type="search"
                    className="col-span-2 min-w-0 lg:col-span-1"
                    placeholder="Asignatura o docente"
                    value={lista.buscar}
                    onChange={(e) => buscar(e.target.value)}
                />
                <Campo
                    etiqueta="Desde"
                    type="date"
                    className="min-w-0"
                    value={desde}
                    onChange={(e) => filtrar('desde', e.target.value)}
                />
                <Campo
                    etiqueta="Hasta"
                    type="date"
                    className="min-w-0"
                    value={hasta}
                    error={rangoInvalido ? 'Anterior a «Desde»' : undefined}
                    onChange={(e) => filtrar('hasta', e.target.value)}
                />
            </div>

            {elegido && (
                <div className="flex min-w-0 items-center gap-3 rounded-xl border border-primary-200 bg-primary-50 py-2 pl-4 pr-2">
                    <PuntoFacultad sigla={elegido.facultad ?? ''} conTexto={false} />
                    <p
                        className="min-w-0 flex-1 truncate text-sm text-slate-800"
                        title={`${elegido.asignatura} · ${elegido.docente}`}
                    >
                        <span className="font-medium">{elegido.asignatura}</span> · {elegido.tipo} ·{' '}
                        {fechaCorta(elegido.fecha)}
                    </p>
                    <Boton
                        type="button"
                        variante="secundario"
                        tamano="chico"
                        className="min-h-10 shrink-0"
                        onClick={() => setConBitacora(true)}
                    >
                        <History className="h-4 w-4" aria-hidden="true" /> Bitácora
                    </Boton>
                    <Boton
                        type="button"
                        variante="enlace"
                        tamano="chico"
                        className="min-h-10 shrink-0"
                        onClick={() => setElegido(null)}
                        aria-label="Quitar el examen elegido"
                    >
                        <X className="h-4 w-4" aria-hidden="true" />{' '}
                        <span className="hidden sm:inline">Ver todos</span>
                    </Boton>
                </div>
            )}

            <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {REPORTES.map((r) => {
                    const n = cifras?.[r.cifra] ?? 0;
                    return (
                        <li
                            key={r.clave}
                            className="flex min-w-0 flex-wrap items-end justify-between gap-x-3 gap-y-3 rounded-xl border border-slate-200 bg-white p-4 sm:block sm:p-5"
                        >
                            <div className="min-w-0">
                                <h2 className="flex items-center gap-2 text-sm font-medium text-slate-700">
                                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                                        <r.icono className="h-4 w-4" aria-hidden="true" />
                                    </span>
                                    <span className="truncate">{r.nombre}</span>
                                </h2>
                                <p className="mt-1 text-2xl font-semibold leading-tight text-slate-900">
                                    {cifras ? miles(n) : '—'}{' '}
                                    <span className="text-sm font-normal text-slate-600">
                                        {r.unidad[n === 1 ? 0 : 1]}
                                    </span>
                                </p>
                            </div>
                            <BotonesDescarga
                                nombre={r.nombre}
                                ruta={`/reportes/${r.clave}/exportar`}
                                parametros={alcance}
                                archivo={`${r.archivo}_${sufijo}`}
                                deshabilitado={sinAlcance}
                                onAvisar={avisar}
                                className="sm:mt-4"
                            />
                        </li>
                    );
                })}
            </ul>

            <Tarjeta sinRelleno titulo="Exámenes">
                <EstadoCarga
                    cargando={cargando}
                    error={error}
                    vacio={examenes.length === 0}
                    onReintentar={recargar}
                >
                    <ul className="divide-y divide-slate-200 md:hidden">
                        {examenes.map((e) => (
                            <li key={e.id}>
                                <button
                                    type="button"
                                    aria-pressed={elegido?.id === e.id}
                                    onClick={() => alternar(e)}
                                    className={`block w-full space-y-1 px-4 py-3 text-left ${FOCO} ${elegido?.id === e.id ? 'bg-primary-50' : 'hover:bg-slate-50'}`}
                                >
                                    <span className="flex items-baseline justify-between gap-3">
                                        <span className="min-w-0 truncate text-sm font-medium text-slate-800">
                                            {e.asignatura}
                                        </span>
                                        <span className="shrink-0 text-xs tabular-nums text-slate-600">
                                            {miles(e.ingresados)} de {miles(e.habilitados)}
                                        </span>
                                    </span>
                                    <span className="block truncate text-xs text-slate-600">
                                        {e.tipo} · {e.docente}
                                    </span>
                                    <span className="flex min-w-0 items-center gap-3 text-xs text-slate-600">
                                        {e.facultad && <PuntoFacultad sigla={e.facultad} />}
                                        <span className="min-w-0 truncate">
                                            {fechaCorta(e.fecha)}, {e.hora} · {e.aulas}{' '}
                                            {e.aulas === 1 ? 'aula' : 'aulas'}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                    <table className="hidden w-full table-fixed text-left text-sm md:table">
                        <thead className="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-600">
                            <tr>
                                <th scope="col" className={TH}>
                                    Examen
                                </th>
                                <th scope="col" className={`${TH} w-24`}>
                                    Facultad
                                </th>
                                <th scope="col" className={`${TH} w-44`}>
                                    Fecha
                                </th>
                                <th scope="col" className={`${TH} w-20 text-right`}>
                                    Aulas
                                </th>
                                <th scope="col" className={`${TH} w-32 text-right`}>
                                    Ingresaron
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {examenes.map((e) => {
                                const activo = elegido?.id === e.id;
                                return (
                                    <tr
                                        key={e.id}
                                        onClick={() => alternar(e)}
                                        className={`cursor-pointer ${activo ? 'bg-primary-50 shadow-[inset_3px_0_0_var(--color-primary-600)]' : 'hover:bg-slate-50'}`}
                                    >
                                        <td className="px-4 py-2.5">
                                            <button
                                                type="button"
                                                aria-pressed={activo}
                                                onClick={(ev) => {
                                                    ev.stopPropagation();
                                                    alternar(e);
                                                }}
                                                className={`flex max-w-full items-center gap-1.5 rounded text-left font-medium ${activo ? 'text-primary-800' : 'text-slate-800'} ${FOCO}`}
                                                title={e.asignatura}
                                            >
                                                {activo && (
                                                    <CircleCheck
                                                        className="h-4 w-4 shrink-0 text-primary-600"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                <span className="truncate">{e.asignatura}</span>
                                            </button>
                                            <span
                                                className="block truncate text-xs text-slate-600"
                                                title={e.docente}
                                            >
                                                {e.tipo} · {e.docente}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5">
                                            {e.facultad && <PuntoFacultad sigla={e.facultad} />}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-slate-700">
                                            {fechaCorta(e.fecha)}, {e.hora}
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums text-slate-700">
                                            {e.aulas}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-slate-700">
                                            {miles(e.ingresados)} de {miles(e.habilitados)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    <Paginacion
                        {...lista.paginacion(meta)}
                        unidad={['examen', 'exámenes']}
                        className="border-t border-slate-200"
                    />
                </EstadoCarga>
            </Tarjeta>
            {elegido && conBitacora && (
                <Dialogo
                    titulo={`${elegido.asignatura} · ${elegido.tipo} · ${fechaCorta(elegido.fecha)}`}
                    onCerrar={() => setConBitacora(false)}
                    ancho="max-w-3xl"
                >
                    <BitacoraDelExamen
                        examenId={elegido.id}
                        fecha={elegido.fecha}
                        deLaUniversidad
                    />
                </Dialogo>
            )}
            {aviso}
        </div>
    );
}
