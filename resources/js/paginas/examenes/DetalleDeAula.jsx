import PropTypes from 'prop-types';
import {
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    GraduationCap,
    TriangleAlert,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import usarConsulta from '../../api/usarConsulta';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import { fechaLarga } from '../../utiles/texto';
import solapamientoDe from './solapamientoDe';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// Lo que pasa en un aula el día del examen: sus clases y los exámenes que ya
// tiene, con lo que choca con la hora propuesta. Sirve para decidir si el aula
// conviene antes de elegirla. Los datos son los del calendario de aulas.
export default function DetalleDeAula({
    aula,
    fecha,
    hora,
    duracion,
    elegida,
    onAlternar,
    onCerrar,
}) {
    const agenda = usarConsulta('/calendario/aulas', {
        parametros: { desde: fecha, hasta: fecha, aulas: [aula.id] },
    });
    const delAula = agenda.datos?.aulas?.[aula.id] ?? { clases: [], examenes: [] };

    // Todo lo del día en una sola línea de tiempo, con lo que se solapa marcado.
    const eventos = [
        ...delAula.clases.map((c) => ({
            clave: `clase-${c.inicio}-${c.asignatura}-${c.grupo}`,
            tipo: c.auxiliatura ? 'Auxiliatura' : 'Clase',
            inicio: c.inicio,
            fin: c.fin,
            titulo: c.asignatura,
            detalle: [`Grupo ${c.grupo}`, c.docente].filter(Boolean).join(' · '),
        })),
        ...delAula.examenes.map((e, i) => ({
            clave: `examen-${e.id ?? i}-${e.inicio}`,
            tipo: 'Examen',
            inicio: e.inicio,
            fin: e.fin,
            titulo: `${e.tipo} · ${e.asignatura}`,
            detalle: e.docente ?? '',
        })),
    ]
        .map((e) => ({ ...e, choque: solapamientoDe(hora, duracion, e.inicio, e.fin) }))
        .sort((a, b) => a.inicio.localeCompare(b.inicio));
    const ocupada = eventos.some((e) => e.choque);

    return (
        <Dialogo
            titulo={`Aula ${aula.nombre}`}
            onCerrar={onCerrar}
            ancho="max-w-lg"
            acciones={
                <>
                    <Link
                        to={`/calendario?aula=${aula.id}`}
                        className={`mr-auto inline-flex min-h-11 items-center gap-2 rounded-lg px-1 text-sm font-medium text-primary-700 hover:underline ${FOCO}`}
                    >
                        <CalendarDays className="h-4 w-4" aria-hidden="true" /> Ver la semana
                    </Link>
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cerrar
                    </Boton>
                    {onAlternar && (
                        <Boton
                            type="button"
                            onClick={() => {
                                onAlternar();
                                onCerrar();
                            }}
                        >
                            {elegida ? 'Quitar del examen' : 'Elegir esta aula'}
                        </Boton>
                    )}
                </>
            }
        >
            {aula.ubicacion && <p className="mb-3 text-sm text-slate-600">{aula.ubicacion}</p>}
            <EstadoCarga
                cargando={agenda.cargando}
                error={agenda.error}
                onReintentar={agenda.recargar}
                filas={3}
                className="!p-0"
            >
                <p className="mb-3 text-sm text-slate-600">
                    {fechaLarga(fecha)} · tu examen de {hora} a {finDe(hora, duracion)}
                </p>
                {ocupada ? (
                    <Insignia tono="advertencia">
                        <TriangleAlert className="h-3.5 w-3.5" aria-hidden="true" /> Ocupada a esa
                        hora
                    </Insignia>
                ) : (
                    <Insignia tono="exito">
                        <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" /> Libre a esa hora
                    </Insignia>
                )}

                <h3 className="mb-1.5 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    Ese día en el aula
                </h3>
                {eventos.length > 0 ? (
                    <ul
                        aria-label="Agenda del aula"
                        className="divide-y divide-slate-200 rounded-lg border border-slate-200"
                    >
                        {eventos.map((e) => (
                            <li
                                key={e.clave}
                                className={`flex items-start gap-3 px-3 py-2 ${e.choque ? 'bg-warning-50' : ''}`}
                            >
                                {e.tipo === 'Examen' ? (
                                    <ClipboardList
                                        className="mt-0.5 h-4 w-4 shrink-0 text-slate-500"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <GraduationCap
                                        className="mt-0.5 h-4 w-4 shrink-0 text-slate-500"
                                        aria-hidden="true"
                                    />
                                )}
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium text-slate-900">
                                        {e.inicio}–{e.fin} · {e.tipo}
                                    </span>
                                    <span className="block break-words text-sm text-slate-700">
                                        {e.titulo}
                                    </span>
                                    {e.detalle && (
                                        <span className="block break-words text-xs text-slate-600">
                                            {e.detalle}
                                        </span>
                                    )}
                                    {e.choque && (
                                        <span className="block text-xs font-semibold text-warning-800">
                                            Coincide {e.choque.inicio}–{e.choque.fin} (
                                            {e.choque.minutos} min)
                                        </span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="rounded-lg border border-slate-200 py-4 text-center text-sm text-slate-600">
                        Sin clases ni exámenes ese día
                    </p>
                )}
            </EstadoCarga>
        </Dialogo>
    );
}

// La hora en que termina el examen propuesto.
function finDe(hora, duracion) {
    const [h, m] = String(hora).split(':').map(Number);
    if (Number.isNaN(h) || Number.isNaN(m)) return '';
    const total = h * 60 + m + Number(duracion || 0);
    return `${String(Math.floor(total / 60) % 24).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

DetalleDeAula.propTypes = {
    aula: PropTypes.shape({
        id: PropTypes.number.isRequired,
        nombre: PropTypes.string.isRequired,
        ubicacion: PropTypes.string,
    }).isRequired,
    fecha: PropTypes.string.isRequired,
    hora: PropTypes.string.isRequired,
    duracion: PropTypes.number.isRequired,
    elegida: PropTypes.bool,
    onAlternar: PropTypes.func,
    onCerrar: PropTypes.func.isRequired,
};
