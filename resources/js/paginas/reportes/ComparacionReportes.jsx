import { useMemo, useState } from 'react';
import PropTypes from 'prop-types';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import EstadoCarga from '../../componentes/EstadoCarga';
import Tarjeta from '../../componentes/Tarjeta';
import BotonesDescarga from './BotonesDescarga';
import AlertasReportes from './AlertasReportes';
import { fechaCorta } from '../../utiles/texto';

const SIN_EXAMENES = [];

export default function ComparacionReportes() {
    const [avisos, avisar] = useAviso();
    const [periodosElegidos, elegirPeriodos] = useState([]);
    const [examenesElegidos, elegirExamenes] = useState([]);
    const opciones = usarConsulta('/reportes/comparacion/opciones');
    const resultadoPeriodos = usarConsulta('/reportes/comparacion', {
        parametros: { periodos: periodosElegidos },
        activa: periodosElegidos.length >= 2,
    });
    const resultadoExamenes = usarConsulta('/reportes/comparacion', {
        parametros: { examenes: examenesElegidos },
        activa: examenesElegidos.length >= 2,
    });

    const examenes = opciones.datos?.examenes ?? SIN_EXAMENES;
    const periodos = opciones.datos?.periodos ?? [];
    const disponibles = useMemo(
        () =>
            examenes.filter(
                (examen) =>
                    examen.rendido &&
                    (periodosElegidos.length === 0 || periodosElegidos.includes(examen.periodo_id))
            ),
        [examenes, periodosElegidos]
    );
    const deshabilitarExportacion =
        examenesElegidos.length < 2 || resultadoExamenes.cargando || resultadoExamenes.error;

    function alternarPeriodo(id) {
        const siguientes = periodosElegidos.includes(id)
            ? periodosElegidos.filter((periodo) => periodo !== id)
            : [...periodosElegidos, id];
        elegirPeriodos(siguientes);
        elegirExamenes((seleccionados) =>
            seleccionados.filter((examenId) =>
                examenes.some(
                    (examen) =>
                        examen.id === examenId &&
                        (siguientes.length === 0 || siguientes.includes(examen.periodo_id))
                )
            )
        );
    }

    function alternarExamen(id) {
        elegirExamenes((actuales) =>
            actuales.includes(id)
                ? actuales.filter((examen) => examen !== id)
                : actuales.length < 20
                  ? [...actuales, id]
                  : actuales
        );
    }

    return (
        <div className="space-y-4">
            <EstadoCarga
                cargando={opciones.cargando}
                error={opciones.error}
                vacio={periodos.length === 0}
                textoVacio="No hay exámenes para comparar"
                onReintentar={opciones.recargar}
            >
                {periodos.length >= 2 && (
                    <Tarjeta titulo="Comparar períodos">
                        <div className="flex flex-wrap gap-3">
                            {periodos.map((periodo) => (
                                <label
                                    key={periodo.id}
                                    className="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm text-slate-800"
                                >
                                    <input
                                        type="checkbox"
                                        aria-label={`Período ${periodo.codigo}`}
                                        checked={periodosElegidos.includes(periodo.id)}
                                        onChange={() => alternarPeriodo(periodo.id)}
                                        className="h-4 w-4 accent-primary-700"
                                    />
                                    <span>
                                        {periodo.codigo} · {periodo.examenes} exámenes
                                    </span>
                                </label>
                            ))}
                        </div>
                        {periodosElegidos.length >= 2 && (
                            <EstadoCarga
                                cargando={resultadoPeriodos.cargando}
                                error={resultadoPeriodos.error}
                                onReintentar={resultadoPeriodos.recargar}
                                filas={2}
                            >
                                <TablaPeriodos periodos={resultadoPeriodos.datos?.periodos ?? []} />
                            </EstadoCarga>
                        )}
                    </Tarjeta>
                )}

                <Tarjeta titulo="Comparar exámenes">
                    <div className="max-h-72 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                        {disponibles.length === 0 ? (
                            <p className="p-3 text-sm text-slate-600">Sin exámenes rendidos</p>
                        ) : (
                            disponibles.map((examen) => (
                                <label
                                    key={examen.id}
                                    className="flex min-h-11 items-center gap-3 rounded px-2 text-sm text-slate-800 hover:bg-slate-50"
                                >
                                    <input
                                        type="checkbox"
                                        aria-label={`Examen ${examen.asignatura}, ${examen.tipo}, ${fechaCorta(examen.fecha)}`}
                                        checked={examenesElegidos.includes(examen.id)}
                                        disabled={
                                            !examenesElegidos.includes(examen.id) &&
                                            examenesElegidos.length >= 20
                                        }
                                        onChange={() => alternarExamen(examen.id)}
                                        className="h-4 w-4 accent-primary-700"
                                    />
                                    <span className="min-w-0 flex-1 truncate">
                                        {examen.asignatura} · {examen.tipo} ·{' '}
                                        {fechaCorta(examen.fecha)}
                                    </span>
                                    <span className="shrink-0 text-xs text-slate-500">
                                        {examen.periodo} · {examen.asistencia}% asistencia
                                    </span>
                                </label>
                            ))
                        )}
                    </div>
                    {examenesElegidos.length > 0 && (
                        <p className="mt-2 text-xs text-slate-600" aria-live="polite">
                            {examenesElegidos.length} de 20 elegidos
                            {examenesElegidos.length === 1 && ' · elija al menos otro'}
                        </p>
                    )}
                    {examenesElegidos.length >= 2 && (
                        <div className="mt-4 space-y-3">
                            <EstadoCarga
                                cargando={resultadoExamenes.cargando}
                                error={resultadoExamenes.error}
                                onReintentar={resultadoExamenes.recargar}
                                filas={2}
                            >
                                <TablaExamenes examenes={resultadoExamenes.datos?.examenes ?? []} />
                            </EstadoCarga>
                            <BotonesDescarga
                                nombre="Reporte consolidado"
                                ruta="/reportes/consolidado/exportar"
                                parametros={{ examenes: examenesElegidos }}
                                archivo="reportes_consolidados"
                                deshabilitado={Boolean(deshabilitarExportacion)}
                                onAvisar={avisar}
                            />
                        </div>
                    )}
                </Tarjeta>
            </EstadoCarga>
            <AlertasReportes />
            {avisos}
        </div>
    );
}

function TablaPeriodos({ periodos }) {
    return (
        <div className="mt-5 overflow-x-auto">
            <table className="w-full min-w-[38rem] text-left text-sm">
                <caption className="sr-only">Comparación de resultados por período</caption>
                <thead className="border-b border-slate-200 text-xs uppercase text-slate-600">
                    <tr>
                        <th className="py-2 pr-3">Período</th>
                        <th className="px-3 py-2">Exámenes rendidos</th>
                        <th className="px-3 py-2">Asistencia</th>
                        <th className="px-3 py-2">Ausentes</th>
                        <th className="pl-3 py-2">Incidencias</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {periodos.map((periodo) => (
                        <tr key={periodo.id}>
                            <th scope="row" className="py-3 pr-3 font-medium">
                                {periodo.codigo}
                            </th>
                            <td className="px-3 py-3">{periodo.examenes}</td>
                            <td className="px-3 py-3">
                                {periodo.asistencia}% ({periodo.presentes}/{periodo.habilitados})
                            </td>
                            <td className="px-3 py-3">{periodo.ausentes}</td>
                            <td className="pl-3 py-3">{periodo.incidencias}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function TablaExamenes({ examenes }) {
    if (examenes.length === 0) {
        return <p className="text-sm text-slate-600">Mínimo dos exámenes rendidos</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[42rem] text-left text-sm">
                <caption className="sr-only">Comparación de resultados por examen</caption>
                <thead className="border-b border-slate-200 text-xs uppercase text-slate-600">
                    <tr>
                        <th className="py-2 pr-3">Examen</th>
                        <th className="px-3 py-2">Período</th>
                        <th className="px-3 py-2">Asistencia</th>
                        <th className="px-3 py-2">Ausentes</th>
                        <th className="pl-3 py-2">Incidencias</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {examenes.map((examen) => (
                        <tr key={examen.id}>
                            <th scope="row" className="py-3 pr-3 font-medium">
                                {examen.asignatura} · {examen.tipo} · {fechaCorta(examen.fecha)}
                            </th>
                            <td className="px-3 py-3">{examen.periodo}</td>
                            <td className="px-3 py-3">
                                {examen.asistencia}% ({examen.presentes}/{examen.habilitados})
                            </td>
                            <td className="px-3 py-3">{examen.ausentes}</td>
                            <td className="pl-3 py-3">{examen.incidencias}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

TablaPeriodos.propTypes = {
    periodos: PropTypes.arrayOf(PropTypes.object).isRequired,
};

TablaExamenes.propTypes = {
    examenes: PropTypes.arrayOf(PropTypes.object).isRequired,
};
