import { useState } from 'react';
import PropTypes from 'prop-types';
import {
    CalendarRange,
    ChartColumn,
    CircleCheck,
    CircleX,
    History,
    LogIn,
    UserX,
} from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { useAviso } from '../../componentes/Aviso';
import Chips from '../../componentes/Chips';
import Dato from '../../componentes/Dato';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import { FiltroGravedad, gravedadDe, ListaIncidencias } from '../../componentes/Incidencias';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import usarExamen from '../../componentes/usarExamen';
import { usarSesion } from '../../sesion/SesionContexto';
import { colorDeAsignatura, diaMes, fechaCorta, plural } from '../../utiles/texto';
import BotonesDescarga from './BotonesDescarga';
import BitacoraDelExamen from '../examenes/BitacoraDelExamen';
import IconoAsignatura from '../../componentes/IconoAsignatura';

const POR_PAGINA = 8;
// El examen elegido y sus cifras salen de una sola lectura con
// el máximo que admite la ruta.
const TODOS = 100;
const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600';

// Reportes del docente (HU-23): arriba sus exámenes del período, por
// asignatura, donde se elige uno (`?examen=`); debajo, en dos pestañas
// (`?vista=bitacora`), su asistencia, ausentes e incidencias, los tres
// descargables, o su bitácora: la línea de tiempo de todo lo ocurrido. Un examen que todavía no se
// tomó no tiene cifras. La tarjeta «Incidencias» lista lo reportado por las
// personas; el archivo trae además los rechazos de la puerta.
export default function ReportesDocente() {
    const { puede } = usarSesion();
    const [aviso, avisar] = useAviso();
    const lista = usarPaginaServidor({
        porPagina: POR_PAGINA,
        filtros: { periodo: null, asignatura: null },
    });
    const { periodo, asignatura } = lista.filtros;

    const { datos: periodos, cuerpo: vigentes } = usarConsulta('/periodos/vigentes');
    const todos = usarConsulta('/reportes/mis-examenes', {
        parametros: { periodo, por_pagina: TODOS },
    });
    const pagina = usarConsulta('/reportes/mis-examenes', { parametros: lista.parametros });

    const [examen, elegir] = usarExamen(todos.datos ?? []);
    const [parametros, setParametros] = useSearchParams();
    const vista = parametros.get('vista') === 'bitacora' ? 'bitacora' : 'reportes';
    const elegirVista = (clave) =>
        setParametros(
            (previos) => {
                const siguiente = new URLSearchParams(previos);
                if (clave === 'bitacora') siguiente.set('vista', 'bitacora');
                else siguiente.delete('vista');
                return siguiente;
            },
            { replace: true }
        );
    const rendido = Boolean(examen?.rendido);
    const cifra = (valor) => (rendido ? valor : '—');

    const conIncidencias = puede('seguimiento_vivo');
    const incidencias = usarConsulta(examen ? `/examenes/${examen.id}/incidencias` : null, {
        parametros: { estado: 'todas' },
        activa: conIncidencias && rendido,
    });

    const variosPeriodos = (periodos ?? []).length > 1;
    const codigoPeriodo = periodo
        ? periodos?.find((p) => p.id === periodo)?.codigo
        : variosPeriodos
          ? null
          : (periodos?.[0]?.codigo ?? vigentes?.principal);
    const subtitulo = [
        codigoPeriodo && `Período ${codigoPeriodo}`,
        todos.meta && plural(todos.meta.examenes, 'examen', 'exámenes'),
        todos.meta && plural(todos.meta.rendidos, 'rendido'),
    ]
        .filter(Boolean)
        .join(' · ');

    const reportes = examen
        ? [
              {
                  clave: 'asistencia',
                  nombre: 'Asistencia por examen',
                  dato: `${cifra(examen.presentes)} de ${examen.habilitados} · ${cifra(examen.asistencia)} %`,
              },
              {
                  clave: 'ausentes',
                  nombre: 'Estudiantes ausentes',
                  dato: `${cifra(examen.ausentes)} de ${examen.habilitados}`,
              },
              {
                  clave: 'incidencias',
                  nombre: 'Incidencias del examen',
                  dato:
                      rendido && examen.incidencias != null
                          ? `${plural(examen.incidencias, 'registro')} · con intentos rechazados`
                          : '—',
              },
          ]
        : [];

    return (
        <div className="space-y-6">
            <Encabezado titulo="Reportes" subtitulo={subtitulo || undefined}>
                {variosPeriodos && (
                    <Seleccion
                        aria-label="Período"
                        icono={CalendarRange}
                        className="w-full sm:w-44"
                        value={periodo ?? ''}
                        onChange={(e) =>
                            lista.ponerFiltros({
                                periodo: Number(e.target.value) || null,
                                asignatura: null,
                            })
                        }
                    >
                        <option value="">Vigentes</option>
                        {periodos.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.codigo}
                            </option>
                        ))}
                    </Seleccion>
                )}
            </Encabezado>

            <EstadoCarga
                cargando={todos.cargando}
                error={todos.error}
                vacio={!examen}
                textoVacio="Sin exámenes"
                onReintentar={todos.recargar}
                className="rounded-xl border border-slate-200 bg-white"
            >
                {examen && (
                    <>
                        <Tarjeta titulo="Exámenes del período" sinRelleno>
                            <div className="p-4 sm:px-5">
                                <Chips
                                    etiqueta="Asignatura"
                                    valor={asignatura}
                                    onCambiar={(id) => lista.ponerFiltro('asignatura', id)}
                                    opciones={[
                                        {
                                            valor: null,
                                            etiqueta: 'Todas',
                                            conteo: todos.meta?.examenes,
                                        },
                                        ...(todos.meta?.asignaturas ?? []).map((a) => ({
                                            valor: a.id,
                                            etiqueta: a.nombre,
                                            color: colorDeAsignatura(a.nombre),
                                            conteo: a.examenes,
                                        })),
                                    ]}
                                />
                            </div>
                            <EstadoCarga
                                cargando={pagina.cargando}
                                error={pagina.error}
                                vacio={pagina.datos?.length === 0}
                                onReintentar={pagina.recargar}
                                className="border-t border-slate-200"
                            >
                                <ul className="divide-y divide-slate-200 border-t border-slate-200">
                                    {(pagina.datos ?? []).map((e) => {
                                        const activo = e.id === examen.id;
                                        return (
                                            <li key={e.id}>
                                                <button
                                                    type="button"
                                                    aria-pressed={activo}
                                                    onClick={() => elegir(e.id)}
                                                    className={`flex min-h-14 w-full items-center gap-3 px-4 py-2 text-left sm:px-5 ${FOCO} ${activo ? 'bg-primary-50' : 'hover:bg-slate-50'}`}
                                                >
                                                    <IconoAsignatura
                                                        nombre={e.asignatura.nombre}
                                                        color={colorDeAsignatura(
                                                            e.asignatura.nombre
                                                        )}
                                                    />
                                                    <span className="w-12 shrink-0 text-sm font-medium tabular-nums text-slate-700">
                                                        {diaMes(e.fecha)}
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block truncate text-sm font-medium text-slate-900">
                                                            {e.asignatura.nombre}
                                                        </span>
                                                        <span className="block truncate text-xs text-slate-600">
                                                            {e.tipo} · {e.hora}
                                                        </span>
                                                    </span>
                                                    {e.rendido ? (
                                                        <span className="shrink-0 text-right">
                                                            <span className="block text-sm font-medium tabular-nums text-slate-800">
                                                                {e.asistencia} %
                                                            </span>
                                                            <span className="block text-xs tabular-nums text-slate-600">
                                                                {e.presentes} de {e.habilitados}
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <span className="flex shrink-0">
                                                            <Insignia tono="neutro">
                                                                Sin rendir
                                                            </Insignia>
                                                        </span>
                                                    )}
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                                <Paginacion
                                    {...lista.paginacion(pagina.meta)}
                                    unidad={['examen', 'exámenes']}
                                    className="border-t border-slate-200 sm:px-5"
                                />
                            </EstadoCarga>
                        </Tarjeta>

                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 pt-2">
                            <IconoAsignatura
                                nombre={examen.asignatura.nombre}
                                color={colorDeAsignatura(examen.asignatura.nombre)}
                                tamano="md"
                            />
                            <h2 className="min-w-0 text-lg font-semibold text-slate-900">
                                {examen.tipo} · {examen.asignatura.nombre}
                                <span className="font-normal text-slate-600">
                                    {` · ${fechaCorta(examen.fecha)}`}
                                </span>
                            </h2>
                            {!rendido && <Insignia tono="neutro">Sin rendir</Insignia>}
                        </div>

                        <div
                            role="tablist"
                            aria-label="Del examen"
                            className="flex gap-1 border-b border-slate-200"
                        >
                            {[
                                ['reportes', 'Reportes', ChartColumn],
                                ['bitacora', 'Bitácora', History],
                            ].map(([clave, texto, Icono]) => (
                                <button
                                    key={clave}
                                    type="button"
                                    role="tab"
                                    aria-selected={vista === clave}
                                    onClick={() => elegirVista(clave)}
                                    className={`-mb-px flex min-h-11 items-center gap-2 border-b-2 px-4 text-sm font-medium ${FOCO} ${
                                        vista === clave
                                            ? 'border-primary-600 text-primary-700'
                                            : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                                    }`}
                                >
                                    <Icono className="h-4 w-4" aria-hidden="true" />
                                    {texto}
                                </button>
                            ))}
                        </div>

                        {vista === 'bitacora' ? (
                            <BitacoraDelExamen
                                key={examen.id}
                                examenId={examen.id}
                                fecha={examen.fecha}
                            />
                        ) : (
                            <>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <Dato
                                        etiqueta="Ingresaron"
                                        icono={LogIn}
                                        valor={cifra(examen.presentes)}
                                        tono={rendido ? 'exito' : 'neutro'}
                                    />
                                    <Dato
                                        etiqueta="Habilitados"
                                        icono={CircleCheck}
                                        valor={examen.habilitados}
                                    />
                                    <Dato
                                        etiqueta="Ausentes"
                                        icono={UserX}
                                        valor={cifra(examen.ausentes)}
                                    />
                                    <Dato
                                        etiqueta="No habilitados"
                                        icono={CircleX}
                                        valor={examen.no_habilitados}
                                        tono={examen.no_habilitados ? 'advertencia' : 'neutro'}
                                    />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {reportes.map((r) => (
                                        <Tarjeta key={r.clave}>
                                            <h2 className="text-base font-semibold text-slate-800">
                                                {r.nombre}
                                            </h2>
                                            <p className="mt-1 text-sm text-slate-600">{r.dato}</p>
                                            <BotonesDescarga
                                                nombre={r.nombre}
                                                ruta={`/reportes/${r.clave}/exportar`}
                                                parametros={{ examen: examen.id }}
                                                archivo={`${r.clave}_${examen.fecha}`}
                                                deshabilitado={!rendido}
                                                onAvisar={avisar}
                                                className="mt-4"
                                            />
                                        </Tarjeta>
                                    ))}
                                </div>

                                {conIncidencias && rendido && (
                                    <Tarjeta
                                        sinRelleno
                                        titulo="Incidencias reportadas"
                                        acciones={
                                            incidencias.meta && (
                                                <span className="text-sm tabular-nums text-slate-600">
                                                    {incidencias.meta.todas}
                                                </span>
                                            )
                                        }
                                    >
                                        <EstadoCarga
                                            cargando={incidencias.cargando}
                                            error={incidencias.error}
                                            onReintentar={incidencias.recargar}
                                            filas={2}
                                        >
                                            <IncidenciasPorGravedad
                                                key={examen.id}
                                                items={incidencias.datos ?? []}
                                            />
                                        </EstadoCarga>
                                    </Tarjeta>
                                )}
                            </>
                        )}
                    </>
                )}
            </EstadoCarga>
            {aviso}
        </div>
    );
}

// Las incidencias del examen con su filtro por gravedad.
function IncidenciasPorGravedad({ items }) {
    const [gravedad, setGravedad] = useState(null);
    return (
        <>
            {items.length > 0 && (
                <div className="border-b border-slate-200 px-4 py-2">
                    <FiltroGravedad items={items} valor={gravedad} onCambiar={setGravedad} />
                </div>
            )}
            <ListaIncidencias
                items={items.filter((i) => !gravedad || gravedadDe(i) === gravedad)}
                conAula
                limite={5}
            />
        </>
    );
}

IncidenciasPorGravedad.propTypes = {
    items: PropTypes.arrayOf(PropTypes.object).isRequired,
};
