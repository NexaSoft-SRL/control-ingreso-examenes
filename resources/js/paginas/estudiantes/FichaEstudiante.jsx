import PropTypes from 'prop-types';
import { ShieldCheck, UserRound } from 'lucide-react';
import usarConsulta from '../../api/usarConsulta';
import Dialogo from '../../componentes/Dialogo';
import EstadoCarga from '../../componentes/EstadoCarga';
import { PuntoFacultad } from '../../componentes/FiltroFacultad';
import Insignia from '../../componentes/Insignia';

// De dónde salió el dato del estudiante: «Verificado» si lo cargó la
// administración, «Docente» si llegó en la lista de un grupo.
export function Origen({ origen }) {
    return origen === 'Verificado' ? (
        <Insignia tono="exito">
            <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" /> Verificado
        </Insignia>
    ) : (
        <Insignia tono="neutro">
            <UserRound className="h-3.5 w-3.5" aria-hidden="true" /> {origen ?? 'Docente'}
        </Insignia>
    );
}

Origen.propTypes = { origen: PropTypes.string };

function Fila({ etiqueta, children }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-medium text-slate-600">{etiqueta}</dt>
            <dd className="mt-0.5 break-words text-sm text-slate-800">{children}</dd>
        </div>
    );
}

Fila.propTypes = { etiqueta: PropTypes.string.isRequired, children: PropTypes.node };

// Ficha de un estudiante: sus datos, su origen y las materias que lleva en
// los períodos vigentes, con el grupo y el docente de cada una.
export default function FichaEstudiante({ estudiante, onCerrar }) {
    const { datos, cargando, error, recargar } = usarConsulta(`/estudiantes/${estudiante.id}`);
    const ficha = datos?.id === estudiante.id ? datos : null;
    const materias = ficha?.materias ?? [];

    return (
        <Dialogo titulo={estudiante.nombre} onCerrar={onCerrar} ancho="max-w-lg">
            <EstadoCarga
                cargando={cargando || (!ficha && !error)}
                error={error}
                onReintentar={recargar}
                filas={4}
                className="!p-0"
            >
                {ficha && (
                    <div className="space-y-5">
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3">
                            <Fila etiqueta="Código">
                                <span className="font-mono text-xs">{ficha.codigo}</span>
                            </Fila>
                            <Fila etiqueta="Documento">{ficha.documento}</Fila>
                            <Fila etiqueta="Facultad">
                                {ficha.facultad ? <PuntoFacultad sigla={ficha.facultad} /> : '—'}
                            </Fila>
                            <Fila etiqueta="Origen">
                                <Origen origen={ficha.origen} />
                            </Fila>
                            <div className="col-span-2">
                                <Fila etiqueta="Carrera">{ficha.carrera ?? '—'}</Fila>
                            </div>
                            <div className="col-span-2">
                                <Fila etiqueta="Correo">{ficha.correo ?? '—'}</Fila>
                            </div>
                        </dl>

                        <section aria-label="Materias">
                            <h3 className="flex items-center justify-between gap-3 text-xs font-medium uppercase tracking-wide text-slate-600">
                                Materias
                                <span className="normal-case tracking-normal">
                                    {materias.length}
                                </span>
                            </h3>
                            {materias.length === 0 ? (
                                <p className="mt-2 rounded-lg border border-slate-200 px-4 py-6 text-center text-sm text-slate-600">
                                    Sin materias
                                </p>
                            ) : (
                                <ul className="mt-2 divide-y divide-slate-200 rounded-lg border border-slate-200">
                                    {materias.map((m) => (
                                        <li
                                            key={`${m.grupo_id ?? `${m.asignatura.codigo}-${m.grupo}`}-${m.periodo}`}
                                            className="px-3 py-2.5"
                                        >
                                            <p className="break-words text-sm font-medium text-slate-800">
                                                {m.asignatura.nombre}
                                            </p>
                                            <p className="mt-0.5 text-xs text-slate-600">
                                                <span className="font-mono">
                                                    {m.asignatura.codigo}
                                                </span>{' '}
                                                · Grupo {m.grupo} · {m.periodo}
                                            </p>
                                            <p className="mt-0.5 break-words text-sm text-slate-700">
                                                {m.docente ?? 'Por designar'}
                                            </p>
                                            {m.via && (
                                                <p className="mt-0.5 text-xs text-slate-600">
                                                    Vía: {m.via}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                )}
            </EstadoCarga>
        </Dialogo>
    );
}

FichaEstudiante.propTypes = {
    estudiante: PropTypes.shape({
        id: PropTypes.number.isRequired,
        nombre: PropTypes.string.isRequired,
    }).isRequired,
    onCerrar: PropTypes.func.isRequired,
};
