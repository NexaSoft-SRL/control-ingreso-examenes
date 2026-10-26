import { useEffect, useMemo, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Seleccion, { AreaTexto } from '../../componentes/Seleccion';
import { TIPOS_COMUNES, TIPOS_INCIDENCIA } from './tiposIncidencia';

// Incidencias comunes de la puerta (catálogo cerrado del servidor). Las
// situaciones excepcionales se registran desde el veredicto.
const DETALLE_MAXIMO = 500;

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');
const horaDe = (instante) => String(instante ?? '').slice(11, 16);

export default function IncidenciasManuales({ aulaId, examenes }) {
    const disponibles = useMemo(
        () =>
            examenes.filter((examen) =>
                (examen.aulas ?? []).some((aula) => String(aula.id) === String(aulaId))
            ),
        [examenes, aulaId]
    );

    const [eleccion, setEleccion] = useState('');
    const [tipo, setTipo] = useState('INCIDENTE_GENERAL');
    const [detalle, setDetalle] = useState('');
    const [estudiante, setEstudiante] = useState('');
    const [registros, setRegistros] = useState([]);
    const [mensaje, setMensaje] = useState('');
    const [errores, setErrores] = useState({});
    const [guardando, setGuardando] = useState(false);
    const [actualizando, setActualizando] = useState(false);
    const [version, setVersion] = useState(0);
    const contextoActual = useRef('');
    const montado = useRef(false);

    // Con dos exámenes en el aula, se elige a cuál va la incidencia.
    let examenId = '';
    if (disponibles.some((item) => String(item.id) === eleccion)) examenId = eleccion;
    else if (disponibles.length === 1) examenId = String(disponibles[0].id);

    useEffect(() => {
        setEleccion('');
        setRegistros([]);
        setDetalle('');
        setEstudiante('');
        setMensaje('');
        setErrores({});
    }, [aulaId]);

    useEffect(() => {
        montado.current = true;

        return () => {
            montado.current = false;
        };
    }, []);

    contextoActual.current = `${aulaId}:${examenId}`;

    useEffect(() => {
        setGuardando(false);
    }, [aulaId, examenId]);

    useEffect(() => {
        let vigente = true;
        const controlador = new AbortController();

        setRegistros([]);

        if (!aulaId || !examenId) {
            return () => {
                vigente = false;
                controlador.abort();
            };
        }

        const consultar = async () => {
            setActualizando(true);

            try {
                const respuesta = await api.get(
                    `/aulas/${aulaId}/punto-control/incidencias-manuales`,
                    {
                        params: { examen_id: Number(examenId), limite: 20 },
                        signal: controlador.signal,
                    }
                );

                if (vigente) {
                    setRegistros(Array.isArray(respuesta.data?.data) ? respuesta.data.data : []);
                }
            } catch {
                if (vigente) {
                    setMensaje('No se pudieron consultar las incidencias');
                }
            } finally {
                if (vigente) setActualizando(false);
            }
        };

        consultar();

        return () => {
            vigente = false;
            controlador.abort();
        };
    }, [aulaId, examenId, version]);

    const registrar = async (evento) => {
        evento.preventDefault();

        const texto = detalle.trim();

        if (
            guardando ||
            !examenId ||
            !aulaId ||
            texto.length < 5 ||
            texto.length > DETALLE_MAXIMO
        ) {
            return;
        }

        const codigo = estudiante.trim();

        if (codigo !== '' && !/^[0-9]{5,10}$/.test(codigo)) {
            setErrores({ codigo_universitario: 'Código SIS no válido' });
            return;
        }

        const contextoSolicitud = `${aulaId}:${examenId}`;

        setGuardando(true);
        setMensaje('');
        setErrores({});

        try {
            await api.post(`/aulas/${aulaId}/punto-control/incidencias-manuales`, {
                examen_id: Number(examenId),
                tipo,
                detalle: texto,
                ...(codigo !== '' ? { codigo_universitario: codigo } : {}),
            });

            if (montado.current && contextoActual.current === contextoSolicitud) {
                setDetalle('');
                setEstudiante('');
                setMensaje('Incidencia registrada');
                setVersion((anterior) => anterior + 1);
            }
        } catch (error) {
            if (montado.current && contextoActual.current === contextoSolicitud) {
                const campos = erroresDe(error);
                setErrores(campos);
                if (Object.keys(campos).length === 0) {
                    setMensaje(sinPunto(mensajeDe(error, 'No se pudo registrar la incidencia')));
                }
            }
        } finally {
            if (montado.current && contextoActual.current === contextoSolicitud) {
                setGuardando(false);
            }
        }
    };

    return (
        <section
            aria-label="Incidencias del aula"
            className="space-y-4 rounded-xl border border-slate-200 bg-white p-5"
        >
            <h2 className="text-lg font-semibold text-slate-900">Incidencias del aula</h2>

            <form onSubmit={registrar} className="space-y-3" noValidate>
                {disponibles.length > 1 && (
                    <Seleccion
                        etiqueta="Examen"
                        aria-label="Examen de la incidencia"
                        value={examenId}
                        onChange={(evento) => setEleccion(evento.target.value)}
                    >
                        <option value="">Seleccionar examen</option>
                        {disponibles.map((examen) => (
                            <option key={examen.id} value={String(examen.id)}>
                                {[examen.asignatura?.nombre, examen.tipo_etiqueta ?? examen.tipo]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </option>
                        ))}
                    </Seleccion>
                )}

                <Seleccion
                    etiqueta="Tipo"
                    aria-label="Tipo de incidencia"
                    value={tipo}
                    onChange={(evento) => setTipo(evento.target.value)}
                >
                    {TIPOS_COMUNES.map((clave) => (
                        <option key={clave} value={clave}>
                            {TIPOS_INCIDENCIA[clave]}
                        </option>
                    ))}
                </Seleccion>

                <Campo
                    etiqueta="Código SIS (opcional)"
                    aria-label="Código SIS de la incidencia"
                    value={estudiante}
                    error={errores.codigo_universitario}
                    onChange={(evento) => setEstudiante(evento.target.value)}
                    inputMode="numeric"
                    autoComplete="off"
                />

                <AreaTexto
                    etiqueta="Detalle"
                    requerido
                    aria-label="Detalle de la incidencia"
                    value={detalle}
                    error={errores.detalle}
                    pie={`${detalle.trim().length}/${DETALLE_MAXIMO}`}
                    onChange={(evento) => setDetalle(evento.target.value)}
                    maxLength={DETALLE_MAXIMO}
                    rows={3}
                />

                <Boton
                    type="submit"
                    disabled={
                        guardando ||
                        !examenId ||
                        detalle.trim().length < 5 ||
                        detalle.trim().length > DETALLE_MAXIMO
                    }
                >
                    {guardando ? 'Registrando…' : 'Registrar incidencia'}
                </Boton>
            </form>

            {mensaje && (
                <p role="status" className="text-sm text-slate-800">
                    {mensaje}
                </p>
            )}

            <h3 className="font-medium text-slate-900">Registradas</h3>
            {actualizando && <p className="text-sm text-slate-600">Consultando…</p>}
            {!actualizando && registros.length === 0 && (
                <p className="text-sm text-slate-600">Sin incidencias</p>
            )}
            <ul className="divide-y divide-slate-100">
                {registros.map((registro) => (
                    <li key={registro.incidencia_id} className="space-y-1 py-3 text-sm">
                        <p className="font-semibold text-slate-900">
                            {registro.tipo_etiqueta ??
                                TIPOS_INCIDENCIA[registro.tipo] ??
                                registro.tipo}
                        </p>
                        <p className="whitespace-pre-wrap break-words text-slate-700">
                            {registro.detalle}
                        </p>
                        {registro.estudiante_id && (
                            <p className="text-slate-700">
                                {[
                                    [registro.apellidos, registro.nombres]
                                        .filter(Boolean)
                                        .join(', '),
                                    registro.codigo,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        )}
                        <p className="text-slate-600">
                            {[
                                registro.operador,
                                registro.hora ?? horaDe(registro.reportada_en),
                                registro.atendida_en ? 'Atendida' : 'Pendiente',
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </li>
                ))}
            </ul>
        </section>
    );
}

IncidenciasManuales.propTypes = {
    aulaId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
    examenes: PropTypes.arrayOf(PropTypes.object).isRequired,
};
