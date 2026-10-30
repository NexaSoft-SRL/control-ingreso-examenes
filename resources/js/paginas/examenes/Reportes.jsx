import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { FileSpreadsheet, FileText } from 'lucide-react';
import PropTypes from 'prop-types';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Chips from '../../componentes/Chips';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import { useSesion } from '../../sesion/SesionContexto';
import descargar from '../../api/descargar';
import useConsulta from '../../api/usarConsulta';
import { mensajeDe } from '../../api/errores';

function cambiarParametros(parametros, cambios) {
    const nuevos = new URLSearchParams(parametros);
    Object.entries(cambios).forEach(([clave, valor]) => {
        if (valor === null || valor === undefined || valor === '') nuevos.delete(clave);
        else nuevos.set(clave, String(valor));
    });
    return nuevos;
}

function nombreEstudiante(fila) {
    return fila.estudiante || 'Sin identificar';
}

function BotonesDescarga({ tipo, titulo, descargasEnCurso, onDescargar, habilitado }) {
    return (
        <div className="flex flex-wrap gap-2">
            {[
                { formato: 'pdf', texto: 'PDF', Icono: FileText },
                { formato: 'xlsx', texto: 'Hoja', Icono: FileSpreadsheet },
            ].map(({ formato, texto, Icono }) => {
                const claveDescarga = `${tipo}:${formato}`;
                return (
                    <Boton
                        key={formato}
                        type="button"
                        variante="secundario"
                        tamano="chico"
                        disabled={!habilitado}
                        cargando={descargasEnCurso.includes(claveDescarga)}
                        onClick={() => onDescargar(tipo, formato)}
                        aria-label={`Descargar ${titulo} ${texto}`}
                    >
                        <Icono className="h-4 w-4" aria-hidden="true" />
                        {texto}
                    </Boton>
                );
            })}
        </div>
    );
}

BotonesDescarga.propTypes = {
    tipo: PropTypes.string.isRequired,
    titulo: PropTypes.string.isRequired,
    descargasEnCurso: PropTypes.arrayOf(PropTypes.string).isRequired,
    onDescargar: PropTypes.func.isRequired,
    habilitado: PropTypes.bool.isRequired,
};

function TablaMuestra({ titulo, filas, columnas, vacio }) {
    return (
        <section className="min-w-0 rounded-xl border border-slate-200 bg-white">
            <h3 className="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-900">
                {titulo}
            </h3>
            {filas.length === 0 ? (
                <p className="px-4 py-5 text-sm text-slate-600">{vacio}</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[34rem] text-left text-sm">
                        <thead className="bg-slate-50 text-xs font-medium text-slate-600">
                            <tr>
                                {columnas.map((columna) => (
                                    <th key={columna.clave} className="px-3 py-2.5">
                                        {columna.titulo}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {filas.map((fila, indice) => (
                                <tr key={`${fila.codigo ?? fila.hora ?? 'fila'}-${indice}`}>
                                    {columnas.map((columna) => (
                                        <td
                                            key={columna.clave}
                                            className="max-w-64 px-3 py-2.5 text-slate-700"
                                        >
                                            {columna.formato
                                                ? columna.formato(fila)
                                                : (fila[columna.clave] ?? '—')}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

TablaMuestra.propTypes = {
    titulo: PropTypes.string.isRequired,
    filas: PropTypes.arrayOf(PropTypes.object).isRequired,
    columnas: PropTypes.arrayOf(
        PropTypes.shape({
            clave: PropTypes.string.isRequired,
            titulo: PropTypes.string.isRequired,
            formato: PropTypes.func,
        })
    ).isRequired,
    vacio: PropTypes.string.isRequired,
};

function DetalleReporte({
    reporte,
    buscando,
    onDescargar,
    descargasEnCurso,
    puedeVerIncidencias,
    incidencias,
    totalIncidencias,
    cargandoIncidencias,
    errorIncidencias,
    recargarIncidencias,
    mostrandoTodasIncidencias,
    cambiarVisibilidadIncidencias,
    paginaIncidencias,
    cambiarPaginaIncidencias,
}) {
    const tarjetas = [
        {
            clave: 'asistencia',
            titulo: 'Asistencia por examen',
            cantidad: reporte.rendido ? `${reporte.ingresaron} de ${reporte.habilitados}` : '—',
            porcentaje: reporte.rendido ? `${reporte.asistencia_porcentaje}%` : null,
        },
        {
            clave: 'ausentes',
            titulo: 'Estudiantes ausentes',
            cantidad: reporte.rendido ? `${reporte.ausentes} de ${reporte.habilitados}` : '—',
            porcentaje: null,
        },
    ];

    if (puedeVerIncidencias && reporte.rendido) {
        tarjetas.push({
            clave: 'incidencias',
            titulo: 'Incidencias del examen',
            cantidad: `${totalIncidencias} registros`,
            porcentaje: null,
        });
    }

    return (
        <section className="mt-6 space-y-4" aria-label="Detalle del examen">
            <div className="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <h2 className="text-base font-semibold text-slate-900">
                            {reporte.tipo} · {reporte.asignatura.nombre}
                        </h2>
                        <p className="mt-1 text-sm text-slate-600">
                            {reporte.fecha} · {reporte.hora} · Período {reporte.periodo}
                        </p>
                    </div>
                    <Insignia tono={reporte.rendido ? 'exito' : 'neutro'}>
                        {reporte.rendido ? 'Rendido' : 'Sin rendir'}
                    </Insignia>
                </div>
                <dl className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-lg bg-emerald-50 p-3">
                        <dt className="text-xs text-slate-600">Ingresaron</dt>
                        <dd className="mt-1 text-lg font-semibold text-slate-900">
                            {reporte.rendido ? reporte.ingresaron : '—'}
                        </dd>
                    </div>
                    <div className="rounded-lg bg-sky-50 p-3">
                        <dt className="text-xs text-slate-600">Habilitados</dt>
                        <dd className="mt-1 text-lg font-semibold text-slate-900">
                            {reporte.habilitados}
                        </dd>
                    </div>
                    <div className="rounded-lg bg-amber-50 p-3">
                        <dt className="text-xs text-slate-600">Ausentes</dt>
                        <dd className="mt-1 text-lg font-semibold text-slate-900">
                            {reporte.rendido ? reporte.ausentes : '—'}
                        </dd>
                    </div>
                    <div className="rounded-lg bg-rose-50 p-3">
                        <dt className="text-xs text-slate-600">No habilitados</dt>
                        <dd className="mt-1 text-lg font-semibold text-slate-900">
                            {reporte.no_habilitados}
                        </dd>
                    </div>
                </dl>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                {tarjetas.map(({ clave, titulo, cantidad, porcentaje }) => (
                    <section
                        key={clave}
                        className="min-w-0 rounded-xl border border-slate-200 bg-white p-4"
                    >
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">{titulo}</h3>
                                <p className="mt-1 text-xs text-slate-600">
                                    {cantidad}
                                    {porcentaje ? ` · ${porcentaje}` : ''}
                                </p>
                            </div>
                            <span className="rounded-md bg-primary-50 p-2 text-primary-700">
                                <FileText className="h-4 w-4" aria-hidden="true" />
                            </span>
                        </div>
                        {clave === 'incidencias' && (
                            <div className="mt-3 border-t border-slate-100 pt-3">
                                {errorIncidencias ? (
                                    <div role="alert" className="text-xs text-danger-700">
                                        <p>
                                            {mensajeDe(
                                                errorIncidencias,
                                                'No se pudieron cargar las incidencias.'
                                            )}
                                        </p>
                                        <Boton
                                            type="button"
                                            variante="secundario"
                                            tamano="chico"
                                            className="mt-2"
                                            onClick={recargarIncidencias}
                                        >
                                            Reintentar
                                        </Boton>
                                    </div>
                                ) : cargandoIncidencias ? (
                                    <p role="status" className="text-xs text-slate-600">
                                        Cargando incidencias…
                                    </p>
                                ) : totalIncidencias === 0 ? (
                                    <p className="text-xs text-slate-600">Sin incidencias.</p>
                                ) : (
                                    <>
                                        <ul className="space-y-3">
                                            {(mostrandoTodasIncidencias
                                                ? incidencias
                                                : incidencias.slice(0, 5)
                                            ).map((fila, indice) => (
                                                <li
                                                    key={`${fila.ocurrida_en}-${indice}`}
                                                    className="text-xs text-slate-700"
                                                >
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">
                                                            {fila.hora} · {fila.tipo}
                                                        </span>
                                                        <Insignia
                                                            tono={
                                                                fila.gravedad === 'Grave'
                                                                    ? 'peligro'
                                                                    : fila.gravedad === 'Media'
                                                                      ? 'advertencia'
                                                                      : 'neutro'
                                                            }
                                                        >
                                                            {fila.gravedad}
                                                        </Insignia>
                                                    </div>
                                                    <p className="mt-1 text-slate-600">
                                                        {fila.aula} · {nombreEstudiante(fila)}
                                                        {fila.codigo ? ` · ${fila.codigo}` : ''}
                                                    </p>
                                                    <p className="mt-1 break-words">
                                                        {fila.detalle}
                                                    </p>
                                                    <p className="mt-1 text-slate-600">
                                                        {fila.atencion}
                                                    </p>
                                                </li>
                                            ))}
                                        </ul>
                                        {totalIncidencias > 5 && (
                                            <button
                                                type="button"
                                                className="mt-3 text-xs font-medium text-primary-700 underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                                                onClick={() =>
                                                    cambiarVisibilidadIncidencias(
                                                        !mostrandoTodasIncidencias
                                                    )
                                                }
                                            >
                                                {mostrandoTodasIncidencias
                                                    ? 'Ver menos'
                                                    : `Ver todas · ${totalIncidencias}`}
                                            </button>
                                        )}
                                        {mostrandoTodasIncidencias && (
                                            <Paginacion
                                                total={totalIncidencias}
                                                pagina={paginaIncidencias}
                                                porPagina={8}
                                                onCambiar={cambiarPaginaIncidencias}
                                                unidad={['incidencia', 'incidencias']}
                                                className="mt-2 px-0"
                                            />
                                        )}
                                    </>
                                )}
                            </div>
                        )}
                        <div className="mt-4">
                            <BotonesDescarga
                                tipo={clave}
                                titulo={titulo}
                                descargasEnCurso={descargasEnCurso}
                                onDescargar={onDescargar}
                                habilitado={reporte.rendido}
                            />
                        </div>
                    </section>
                ))}
            </div>

            {(!puedeVerIncidencias || !reporte.rendido) && (
                <div className="flex flex-wrap items-center gap-3">
                    <p className="text-sm font-medium text-slate-700">
                        Descargar incidencias del examen
                    </p>
                    <BotonesDescarga
                        tipo="incidencias"
                        titulo="Incidencias del examen"
                        descargasEnCurso={descargasEnCurso}
                        onDescargar={onDescargar}
                        habilitado={reporte.rendido}
                    />
                </div>
            )}

            {reporte.rendido && (
                <>
                    <TablaMuestra
                        titulo="Asistencia registrada"
                        filas={reporte.muestras.asistencia}
                        columnas={[
                            { clave: 'hora', titulo: 'Hora' },
                            { clave: 'codigo', titulo: 'Código SIS' },
                            { clave: 'estudiante', titulo: 'Estudiante' },
                            { clave: 'grupo', titulo: 'Grupo' },
                            { clave: 'aula', titulo: 'Aula' },
                            { clave: 'medio', titulo: 'Medio' },
                            { clave: 'situacion', titulo: 'Situación' },
                        ]}
                        vacio="Aún no hay ingresos registrados."
                    />
                    <TablaMuestra
                        titulo="Estudiantes ausentes"
                        filas={reporte.muestras.ausentes}
                        columnas={[
                            { clave: 'estudiante', titulo: 'Apellidos y nombres' },
                            { clave: 'codigo', titulo: 'Código SIS' },
                            { clave: 'grupo', titulo: 'Grupo' },
                            { clave: 'carrera', titulo: 'Carrera' },
                            { clave: 'aula', titulo: 'Aula asignada' },
                        ]}
                        vacio="No hay estudiantes habilitados ausentes."
                    />
                </>
            )}
            {buscando && <p className="text-xs text-slate-500">Actualizando reportes…</p>}
        </section>
    );
}

DetalleReporte.propTypes = {
    reporte: PropTypes.shape({
        tipo: PropTypes.string.isRequired,
        fecha: PropTypes.string.isRequired,
        hora: PropTypes.string.isRequired,
        periodo: PropTypes.string.isRequired,
        rendido: PropTypes.bool.isRequired,
        asignatura: PropTypes.shape({ nombre: PropTypes.string.isRequired }).isRequired,
        ingresaron: PropTypes.number.isRequired,
        habilitados: PropTypes.number.isRequired,
        ausentes: PropTypes.number.isRequired,
        no_habilitados: PropTypes.number.isRequired,
        incidencias: PropTypes.number.isRequired,
        asistencia_porcentaje: PropTypes.number.isRequired,
        muestras: PropTypes.shape({
            asistencia: PropTypes.arrayOf(PropTypes.object).isRequired,
            ausentes: PropTypes.arrayOf(PropTypes.object).isRequired,
            incidencias: PropTypes.arrayOf(PropTypes.object).isRequired,
        }).isRequired,
        totales: PropTypes.shape({ incidencias: PropTypes.number.isRequired }).isRequired,
    }).isRequired,
    buscando: PropTypes.bool.isRequired,
    onDescargar: PropTypes.func.isRequired,
    descargasEnCurso: PropTypes.arrayOf(PropTypes.string).isRequired,
    puedeVerIncidencias: PropTypes.bool.isRequired,
    incidencias: PropTypes.arrayOf(PropTypes.object).isRequired,
    totalIncidencias: PropTypes.number.isRequired,
    cargandoIncidencias: PropTypes.bool.isRequired,
    errorIncidencias: PropTypes.object,
    recargarIncidencias: PropTypes.func.isRequired,
    mostrandoTodasIncidencias: PropTypes.bool.isRequired,
    cambiarVisibilidadIncidencias: PropTypes.func.isRequired,
    paginaIncidencias: PropTypes.number.isRequired,
    cambiarPaginaIncidencias: PropTypes.func.isRequired,
};

export default function Reportes() {
    const [parametros, setParametros] = useSearchParams();
    const { puede } = useSesion();
    const periodo = parametros.get('periodo') ? Number(parametros.get('periodo')) : null;
    const asignatura = parametros.get('asignatura') ? Number(parametros.get('asignatura')) : null;
    const pagina = Math.max(1, Number(parametros.get('pagina') || 1));
    const examenId = parametros.get('examen');
    const consultaLista = useConsulta('/reportes', {
        parametros: {
            ...(periodo ? { periodo } : {}),
            ...(asignatura ? { asignatura } : {}),
            pagina,
        },
    });
    const consultaDetalle = useConsulta(
        examenId ? `/reportes/examenes/${encodeURIComponent(examenId)}` : null
    );
    const [paginaIncidencias, setPaginaIncidencias] = useState(1);
    const [mostrandoTodasIncidencias, setMostrandoTodasIncidencias] = useState(false);
    useEffect(() => {
        setPaginaIncidencias(1);
        setMostrandoTodasIncidencias(false);
    }, [examenId]);
    const [aviso, avisar] = useAviso();
    const [descargasEnCurso, setDescargasEnCurso] = useState([]);
    const examenes = consultaLista.datos ?? [];
    const meta = consultaLista.meta ?? {};
    const opciones = consultaLista.cuerpo?.asignaturas ?? [];
    const periodos = consultaLista.cuerpo?.periodos ?? [];
    const reporte = consultaDetalle.datos;
    const puedeVerIncidencias = puede('seguimiento_vivo');
    const rutaIncidencias =
        examenId && reporte?.rendido && puedeVerIncidencias
            ? `/reportes/examenes/${encodeURIComponent(examenId)}/incidencias`
            : null;
    const consultaIncidencias = useConsulta(rutaIncidencias, {
        parametros: { pagina: paginaIncidencias },
    });
    const incidencias = consultaIncidencias.datos ?? [];
    const totalIncidencias = consultaIncidencias.meta?.total ?? reporte?.incidencias ?? 0;
    const periodoEtiqueta =
        periodos.length === 0
            ? null
            : periodo !== null
              ? (meta.periodo ?? periodos.find((item) => item.id === periodo)?.codigo)
              : periodos.length === 1
                ? periodos[0].codigo
                : 'Vigentes';
    const resumenPeriodo = consultaLista.cargando
        ? 'Cargando reportes…'
        : consultaLista.error && !consultaLista.cuerpo
          ? 'No se pudo cargar el resumen.'
          : `${periodoEtiqueta ? `Período ${periodoEtiqueta} · ` : 'Sin períodos vigentes · '}${
                meta.total ?? 0
            } ${meta.total === 1 ? 'examen' : 'exámenes'} · ${meta.rendidos ?? 0} ${
                meta.rendidos === 1 ? 'rendido' : 'rendidos'
            }`;

    function filtrar(cambios) {
        setParametros((actuales) =>
            cambiarParametros(actuales, { ...cambios, pagina: 1, examen: null })
        );
    }

    async function descargarReporte(tipo, formato) {
        const clave = `${tipo}:${formato}`;
        setDescargasEnCurso((actuales) =>
            actuales.includes(clave) ? actuales : [...actuales, clave]
        );
        try {
            const nombre = await descargar(
                `/reportes/examenes/${encodeURIComponent(examenId)}/descargas/${tipo}/${formato}`
            );
            avisar(`${nombre} descargado.`);
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudo descargar.'), 'error');
        } finally {
            setDescargasEnCurso((actuales) => actuales.filter((elemento) => elemento !== clave));
        }
    }

    return (
        <>
            <Encabezado
                titulo="Reportes"
                subtitulo="Descarga la asistencia, los ausentes y las incidencias de tus exámenes."
            />
            <section className="mt-5 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-base font-semibold text-slate-900">
                            Exámenes del período
                        </h2>
                        <p className="mt-1 text-xs text-slate-600">{resumenPeriodo}</p>
                    </div>
                    {periodos.length > 1 && (
                        <Chips
                            etiqueta="Período"
                            valor={periodo}
                            onCambiar={(valor) => filtrar({ periodo: valor, asignatura: null })}
                            opciones={[
                                { valor: null, etiqueta: 'Vigentes' },
                                ...periodos.map((item) => ({
                                    valor: item.id,
                                    etiqueta: item.codigo,
                                })),
                            ]}
                        />
                    )}
                </div>

                {opciones.length > 1 && (
                    <div className="mt-4">
                        <Chips
                            etiqueta="Asignatura"
                            valor={asignatura}
                            onCambiar={(valor) => filtrar({ asignatura: valor })}
                            opciones={[
                                { valor: null, etiqueta: 'Todas' },
                                ...opciones.map((item) => ({
                                    valor: item.id,
                                    etiqueta: item.nombre,
                                })),
                            ]}
                        />
                    </div>
                )}

                <EstadoCarga
                    cargando={consultaLista.cargando}
                    vacio={!consultaLista.error && examenes.length === 0}
                    textoVacio={
                        periodos.length === 0
                            ? 'No hay períodos vigentes.'
                            : 'Sin exámenes en este período.'
                    }
                    onReintentar={consultaLista.recargar}
                    className="mt-3"
                >
                    {consultaLista.error ? (
                        <div role="alert" className="px-4 py-8 text-center text-sm text-danger-700">
                            <p>{mensajeDe(consultaLista.error, 'No se pudo cargar.')}</p>
                            <Boton
                                type="button"
                                variante="secundario"
                                tamano="chico"
                                className="mt-3"
                                onClick={() => consultaLista.recargar()}
                            >
                                Reintentar
                            </Boton>
                        </div>
                    ) : (
                        <>
                            <ul className="mt-4 space-y-2">
                                {examenes.map((examen) => {
                                    const activo = String(examen.id) === examenId;
                                    return (
                                        <li key={examen.id}>
                                            <button
                                                type="button"
                                                aria-pressed={activo}
                                                onClick={() =>
                                                    setParametros((actuales) =>
                                                        cambiarParametros(actuales, {
                                                            examen: activo ? null : examen.id,
                                                        })
                                                    )
                                                }
                                                className={`flex min-h-[4.5rem] w-full flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-3 text-left transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 ${
                                                    activo
                                                        ? 'border-primary-500 bg-primary-50'
                                                        : 'border-slate-200 bg-white hover:bg-slate-50'
                                                }`}
                                            >
                                                <span className="flex min-w-0 items-center gap-3">
                                                    <span
                                                        className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-fuchsia-50 text-fuchsia-700"
                                                        aria-hidden="true"
                                                    >
                                                        <FileText className="h-4 w-4" />
                                                    </span>
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-semibold text-slate-900">
                                                            {examen.asignatura.nombre}
                                                        </span>
                                                        <span className="mt-0.5 block text-xs text-slate-600">
                                                            {examen.fecha} · {examen.tipo} ·{' '}
                                                            {examen.hora}
                                                        </span>
                                                    </span>
                                                </span>
                                                <span className="flex shrink-0 items-center gap-3">
                                                    {examen.rendido ? (
                                                        <span className="text-right">
                                                            <span className="block text-sm font-semibold text-slate-900">
                                                                {examen.asistencia_porcentaje}%
                                                            </span>
                                                            <span className="block text-xs text-slate-600">
                                                                {examen.ingresaron} de{' '}
                                                                {examen.habilitados}
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        <Insignia tono="neutro">
                                                            Sin rendir
                                                        </Insignia>
                                                    )}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                            <Paginacion
                                total={meta.total ?? 0}
                                pagina={meta.pagina ?? pagina}
                                porPagina={meta.por_pagina ?? 8}
                                onCambiar={(nueva) =>
                                    setParametros((actuales) =>
                                        cambiarParametros(actuales, { pagina: nueva, examen: null })
                                    )
                                }
                                unidad={['examen', 'exámenes']}
                                className="mt-3 px-0"
                            />
                        </>
                    )}
                </EstadoCarga>
            </section>

            {examenId && (
                <EstadoCarga
                    cargando={consultaDetalle.cargando}
                    error={consultaDetalle.error}
                    vacio={!consultaDetalle.error && !reporte}
                    textoVacio="No se encontró el examen."
                    onReintentar={consultaDetalle.recargar}
                >
                    {reporte && (
                        <DetalleReporte
                            reporte={reporte}
                            buscando={consultaDetalle.actualizando}
                            onDescargar={descargarReporte}
                            descargasEnCurso={descargasEnCurso}
                            puedeVerIncidencias={puedeVerIncidencias}
                            incidencias={incidencias}
                            totalIncidencias={totalIncidencias}
                            cargandoIncidencias={consultaIncidencias.cargando}
                            errorIncidencias={consultaIncidencias.error}
                            recargarIncidencias={consultaIncidencias.recargar}
                            mostrandoTodasIncidencias={mostrandoTodasIncidencias}
                            cambiarVisibilidadIncidencias={(mostrar) => {
                                setMostrandoTodasIncidencias(mostrar);
                                if (mostrar) setPaginaIncidencias(1);
                            }}
                            paginaIncidencias={paginaIncidencias}
                            cambiarPaginaIncidencias={setPaginaIncidencias}
                        />
                    )}
                    {consultaDetalle.error && (
                        <p role="alert" className="mt-4 text-sm text-danger-700">
                            {mensajeDe(consultaDetalle.error, 'No se pudo cargar el reporte.')}
                        </p>
                    )}
                </EstadoCarga>
            )}

            {aviso}
        </>
    );
}
