import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { FileSpreadsheet, FileText } from 'lucide-react';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Encabezado from '../../componentes/Encabezado';
import Seleccion from '../../componentes/Seleccion';
import descargar from '../../api/descargar';
import usarConsulta from '../../api/usarConsulta';
import { mensajeDe } from '../../api/errores';
import { useFacultades } from '../../sesion/SesionContexto';

const TIPOS = [
    ['horarios', 'Horarios de ingreso'],
    ['registrados', 'Habilitados y registrados'],
    ['intentos', 'Intentos no autorizados'],
    ['estudiantes', 'Resumen por estudiante'],
    ['incidencias', 'Incidencias del examen'],
    ['asistencia', 'Asistencia al examen'],
    ['ausentes', 'Estudiantes ausentes'],
];

const INDIVIDUALES = new Set(['incidencias', 'asistencia', 'ausentes']);

const COLUMNAS = {
    registrados: [
        ['fecha', 'Fecha'],
        ['examen', 'Examen'],
        ['facultad', 'Facultad'],
        ['docente', 'Docente'],
        ['aula', 'Aula'],
        ['habilitados', 'Habilitados'],
        ['registrados', 'Registrados'],
    ],
    intentos: [
        ['ocurridaEn', 'Fecha y hora'],
        ['codigoUniversitario', 'Código SIS'],
        ['estudiante', 'Estudiante'],
        ['aula', 'Aula'],
        ['examenIntentado', 'Examen intentado'],
        ['examenCorrespondiente', 'Examen correspondiente'],
        ['causa', 'Causa'],
    ],
    estudiantes: [
        ['codigoUniversitario', 'Código SIS'],
        ['nombre', 'Estudiante'],
        ['carrera', 'Carrera'],
        ['materias', 'Materias'],
        ['examenes', 'Exámenes'],
        ['ingresos', 'Ingresos'],
        ['intentos', 'Intentos'],
        ['expulsiones', 'Expulsiones'],
        ['incidencias', 'Incidencias'],
    ],
    incidencias: [
        ['hora', 'Hora'],
        ['aula', 'Aula'],
        ['codigoUniversitario', 'Código SIS'],
        ['estudiante', 'Estudiante'],
        ['tipo', 'Tipo'],
        ['origen', 'Origen'],
        ['causa', 'Causa'],
        ['detalle', 'Detalle'],
        ['registradoPor', 'Registrado por'],
        ['conCodigoDeAula', 'Con código de aula'],
    ],
    asistencia: [
        ['codigoUniversitario', 'Código SIS'],
        ['nombre', 'Estudiante'],
        ['grupo', 'Grupo'],
        ['aula', 'Aula'],
        ['hora', 'Hora'],
        ['medio', 'Medio'],
        ['expulsado', 'Expulsado'],
        ['excepcion', 'Excepción'],
    ],
    ausentes: [
        ['codigoUniversitario', 'Código SIS'],
        ['nombre', 'Estudiante'],
        ['grupo', 'Grupo'],
        ['carrera', 'Carrera'],
        ['aula', 'Aula'],
    ],
};

function mostrar(valor) {
    if (valor === null || valor === undefined || valor === '') return '—';
    if (typeof valor === 'boolean') return valor ? 'Sí' : 'No';
    return String(valor);
}

function filasDelReporte(tipo, datos) {
    if (tipo === 'horarios') {
        return Array.isArray(datos?.tramos) ? datos.tramos : [];
    }
    return Array.isArray(datos) ? datos : [];
}

function nombreExamen(fila) {
    const nombre =
        fila.examen ?? fila.asignatura ?? fila.nombre ?? fila.tipo ?? `Examen ${fila.id}`;
    const fecha = fila.fecha ?? '';
    return `${nombre}${fecha ? ` · ${fecha}` : ''}`;
}

export default function ReportesUniversidad() {
    const [url, setUrl] = useSearchParams();
    const facultades = useFacultades();
    const [aviso, avisar] = useAviso();
    const [descargando, setDescargando] = useState('');
    const [paginaExamenes, setPaginaExamenes] = useState(1);

    const tipo = TIPOS.some(([clave]) => clave === url.get('tipo')) ? url.get('tipo') : 'horarios';

    const examen = url.get('examen') ?? '';
    const periodo = url.get('periodo') ?? '';
    const facultad = url.get('facultad') ?? '';
    const buscar = url.get('buscar') ?? '';
    const desde = url.get('desde') ?? '';
    const hasta = url.get('hasta') ?? '';

    const parametros = {
        ...(examen ? { examen } : {}),
        ...(periodo ? { periodo } : {}),
        ...(facultad ? { facultad } : {}),
        ...(buscar ? { buscar } : {}),
        ...(desde ? { desde } : {}),
        ...(hasta ? { hasta } : {}),
    };

    const consultaExamenes = usarConsulta('/reportes/universidad', {
        parametros: {
            ...parametros,
            pagina: paginaExamenes,
            por_pagina: 25,
        },
    });

    const necesitaExamen = INDIVIDUALES.has(tipo);
    const totalPaginas = consultaExamenes.meta?.paginas ?? 1;
    const rutaReporte = necesitaExamen && !examen ? null : `/reportes/universidad/${tipo}`;

    const consultaReporte = usarConsulta(rutaReporte, { parametros });

    const examenes = Array.isArray(consultaExamenes.datos) ? consultaExamenes.datos : [];

    const datos = consultaReporte.datos;
    const filas = filasDelReporte(tipo, datos);
    const columnas =
        tipo === 'horarios'
            ? [
                  ['tramo', 'Tramo horario'],
                  ['cantidad', 'Ingresos'],
              ]
            : (COLUMNAS[tipo] ?? []);

    function cambiar(cambios) {
        if (
            ['periodo', 'facultad', 'buscar', 'desde', 'hasta'].some((clave) =>
                Object.prototype.hasOwnProperty.call(cambios, clave)
            )
        ) {
            setPaginaExamenes(1);
        }

        setUrl(
            (actual) => {
                const nuevos = new URLSearchParams(actual);
                Object.entries(cambios).forEach(([clave, valor]) => {
                    if (valor === '' || valor === null) nuevos.delete(clave);
                    else nuevos.set(clave, String(valor));
                });
                return nuevos;
            },
            { replace: true }
        );
    }

    async function descargarArchivo(formato) {
        const clave = `${tipo}:${formato}`;
        if (descargando || (necesitaExamen && !examen)) return;
        setDescargando(clave);
        try {
            const nombre = await descargar(`/reportes/universidad/descargas/${tipo}/${formato}`, {
                parametros,
            });
            avisar(`${nombre} descargado.`);
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudo descargar el reporte.'), 'error');
        } finally {
            setDescargando('');
        }
    }

    return (
        <>
            <Encabezado
                titulo="Reportes de la universidad"
                subtitulo="Consulta ingresos, asistencia, ausencias e incidencias, y exporta PDF o XLSX."
            />

            <section className="mt-5 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Seleccion
                        etiqueta="Tipo de reporte"
                        value={tipo}
                        onChange={(evento) => cambiar({ tipo: evento.target.value })}
                    >
                        {TIPOS.map(([clave, titulo]) => (
                            <option key={clave} value={clave}>
                                {titulo}
                            </option>
                        ))}
                    </Seleccion>

                    <Seleccion
                        etiqueta="Examen"
                        value={examen}
                        onChange={(evento) => cambiar({ examen: evento.target.value })}
                    >
                        <option value="">Todos los exámenes</option>
                        {examen && !examenes.some((fila) => String(fila.id) === examen) && (
                            <option value={examen}>Examen {examen}</option>
                        )}
                        {examenes.map((fila) => (
                            <option key={fila.id} value={fila.id}>
                                {nombreExamen(fila)}
                            </option>
                        ))}
                    </Seleccion>

                    <div className="flex items-end gap-2">
                        <Boton
                            variante="secundario"
                            disabled={paginaExamenes <= 1 || consultaExamenes.actualizando}
                            onClick={() => setPaginaExamenes((actual) => Math.max(1, actual - 1))}
                        >
                            Anterior
                        </Boton>
                        <span className="self-center text-sm text-slate-600">
                            Página {paginaExamenes} de {totalPaginas}
                        </span>
                        <Boton
                            variante="secundario"
                            disabled={
                                paginaExamenes >= totalPaginas || consultaExamenes.actualizando
                            }
                            onClick={() => setPaginaExamenes((actual) => actual + 1)}
                        >
                            Siguiente
                        </Boton>
                    </div>

                    <Seleccion
                        etiqueta="Facultad"
                        value={facultad}
                        onChange={(evento) =>
                            cambiar({ facultad: evento.target.value, examen: '' })
                        }
                    >
                        <option value="">Todas las facultades</option>
                        {facultades.map((fila) => (
                            <option key={fila.clave} value={fila.clave}>
                                {fila.sigla || fila.nombre || fila.clave}
                            </option>
                        ))}
                    </Seleccion>

                    <label className="block text-sm font-medium text-slate-700">
                        Período (ID)
                        <input
                            type="number"
                            min="1"
                            value={periodo}
                            onChange={(evento) =>
                                cambiar({ periodo: evento.target.value, examen: '' })
                            }
                            className="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>

                    <label className="block text-sm font-medium text-slate-700">
                        Desde
                        <input
                            type="date"
                            value={desde}
                            onChange={(evento) =>
                                cambiar({ desde: evento.target.value, examen: '' })
                            }
                            className="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>

                    <label className="block text-sm font-medium text-slate-700">
                        Hasta
                        <input
                            type="date"
                            value={hasta}
                            min={desde || undefined}
                            onChange={(evento) =>
                                cambiar({ hasta: evento.target.value, examen: '' })
                            }
                            className="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>

                    <label className="block text-sm font-medium text-slate-700 md:col-span-2 xl:col-span-3">
                        Buscar
                        <input
                            type="search"
                            maxLength={100}
                            value={buscar}
                            onChange={(evento) =>
                                cambiar({ buscar: evento.target.value, examen: '' })
                            }
                            placeholder="Buscar en el alcance del reporte"
                            className="mt-1.5 min-h-11 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                </div>
            </section>

            <section className="mt-5 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="font-semibold text-slate-900">
                        {TIPOS.find(([clave]) => clave === tipo)?.[1]}
                    </h2>
                    <div className="flex flex-wrap gap-2">
                        <Boton
                            variante="secundario"
                            disabled={necesitaExamen && !examen}
                            cargando={descargando === `${tipo}:pdf`}
                            onClick={() => descargarArchivo('pdf')}
                        >
                            <FileText className="h-4 w-4" />
                            PDF
                        </Boton>
                        <Boton
                            variante="secundario"
                            disabled={necesitaExamen && !examen}
                            cargando={descargando === `${tipo}:xlsx`}
                            onClick={() => descargarArchivo('xlsx')}
                        >
                            <FileSpreadsheet className="h-4 w-4" />
                            XLSX
                        </Boton>
                    </div>
                </div>

                {necesitaExamen && !examen ? (
                    <p className="mt-5 text-sm text-slate-600">
                        Selecciona un examen para consultar este reporte.
                    </p>
                ) : consultaReporte.cargando ? (
                    <div className="mt-5" role="status">
                        Cargando reporte…
                    </div>
                ) : consultaReporte.error ? (
                    <p role="alert" className="mt-5 text-sm text-danger-700">
                        {mensajeDe(consultaReporte.error, 'No se pudo cargar el reporte.')}
                    </p>
                ) : (
                    <>
                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="bg-slate-100">
                                    <tr>
                                        {columnas.map(([clave, titulo]) => (
                                            <th key={clave} className="px-3 py-2 font-semibold">
                                                {titulo}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200">
                                    {filas.map((fila, indice) => (
                                        <tr key={indice}>
                                            {columnas.map(([clave]) => (
                                                <td key={clave} className="px-3 py-2">
                                                    {mostrar(fila[clave])}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {filas.length === 0 && (
                            <p className="mt-4 text-sm text-slate-600">
                                No hay resultados para los filtros seleccionados.
                            </p>
                        )}
                        {tipo === 'horarios' && datos && (
                            <p className="mt-4 text-sm font-semibold">
                                Total de ingresos: {mostrar(datos.total)}
                            </p>
                        )}
                    </>
                )}
                {consultaExamenes.error && (
                    <p role="alert" className="mt-4 text-sm text-danger-700">
                        No se pudo cargar la selección de exámenes.
                    </p>
                )}
            </section>

            {aviso}
        </>
    );
}
