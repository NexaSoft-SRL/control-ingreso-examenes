import { useEffect, useMemo, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { api } from '../../api/cliente';

export default function IncidenciasManuales({ aulaId, examenes, sugerencia = null }) {
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
    const [guardando, setGuardando] = useState(false);
    const [actualizando, setActualizando] = useState(false);
    const [version, setVersion] = useState(0);
    const contextoActual = useRef('');
    const montado = useRef(false);

    const examenId = disponibles.some((item) => String(item.id) === eleccion)
        ? eleccion
        : disponibles.length === 1
          ? String(disponibles[0].id)
          : '';

    useEffect(() => {
        setEleccion('');
        setRegistros([]);
        setDetalle('');
        setEstudiante('');
        setMensaje('');
    }, [aulaId]);

    const huboSugerencia = useRef(false);

    useEffect(() => {
        if (!sugerencia) {
            if (huboSugerencia.current) {
                huboSugerencia.current = false;
                setEleccion('');
                setTipo('INCIDENTE_GENERAL');
                setDetalle('');
                setEstudiante('');
                setMensaje('');
            }
            return;
        }

        const examenPermitido = disponibles.some(
            (examen) => String(examen.id) === String(sugerencia.examenId)
        );

        if (!examenPermitido) return;

        huboSugerencia.current = true;

        setEleccion(String(sugerencia.examenId));
        setTipo(sugerencia.tipo);
        setEstudiante(sugerencia.codigo);
        setDetalle('');
        setMensaje('Completa el motivo y confirma la situación excepcional.');
    }, [sugerencia, disponibles]);

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
                    setMensaje('No se pudieron consultar las incidencias manuales.');
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

        if (guardando || !examenId || !aulaId || texto.length < 5 || texto.length > 2000) {
            return;
        }

        const codigo = estudiante.trim();

        if (codigo !== '' && !/^[0-9]{5,10}$/.test(codigo)) {
            setMensaje('El código universitario debe tener entre 5 y 10 dígitos.');
            return;
        }

        const contextoSolicitud = `${aulaId}:${examenId}`;

        setGuardando(true);
        setMensaje('');

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
                setMensaje('Incidencia manual registrada.');
                setVersion((anterior) => anterior + 1);
            }
        } catch (error) {
            if (montado.current && contextoActual.current === contextoSolicitud) {
                setMensaje(
                    error?.response?.data?.message ?? 'No se pudo registrar la incidencia manual.'
                );
            }
        } finally {
            if (montado.current && contextoActual.current === contextoSolicitud) {
                setGuardando(false);
            }
        }
    };

    return (
        <section
            aria-label="Incidencias manuales"
            className="space-y-4 rounded-xl border border-slate-200 bg-white p-5"
        >
            <h2 className="text-lg font-semibold">Incidencias manuales</h2>

            <form onSubmit={registrar} className="space-y-3">
                <label className="block text-sm">
                    Examen correspondiente
                    <select
                        aria-label="Examen de incidencia manual"
                        value={examenId}
                        onChange={(evento) => setEleccion(evento.target.value)}
                        className="mt-1 block w-full rounded-lg border p-2"
                    >
                        <option value="">Seleccionar examen</option>
                        {disponibles.map((examen) => (
                            <option key={examen.id} value={String(examen.id)}>
                                {examen.asignatura?.nombre ?? 'Examen'} —{' '}
                                {examen.tipo ?? 'Sin tipo'} (#{examen.id})
                            </option>
                        ))}
                    </select>
                </label>

                <label className="block text-sm">
                    Tipo
                    <select
                        aria-label="Tipo de incidencia manual"
                        value={tipo}
                        onChange={(evento) => setTipo(evento.target.value)}
                        className="mt-1 block w-full rounded-lg border p-2"
                    >
                        <option value="INCIDENTE_GENERAL">Incidente general</option>
                        <option value="EXCEPCION_IDENTIFICACION">
                            Excepción: problema de identificación
                        </option>
                        <option value="EXCEPCION_CAMBIO_AULA">Excepción: cambio de aula</option>
                        <option value="EXCEPCION_NO_HABILITADO">
                            Excepción: estudiante no habilitado
                        </option>
                        <option value="DOCUMENTO_OBSERVADO">Documento observado</option>
                        <option value="CONDUCTA">Conducta</option>
                        <option value="OTRO">Otro</option>
                    </select>
                </label>

                <label className="block text-sm">
                    Código universitario (opcional)
                    <input
                        aria-label="Código universitario para incidencia"
                        value={estudiante}
                        onChange={(evento) => setEstudiante(evento.target.value)}
                        className="mt-1 block w-full rounded-lg border p-2"
                        inputMode="numeric"
                    />
                </label>

                <label className="block text-sm">
                    Detalle
                    <textarea
                        aria-label="Detalle de incidencia manual"
                        value={detalle}
                        onChange={(evento) => setDetalle(evento.target.value)}
                        maxLength={2000}
                        rows={3}
                        className="mt-1 block w-full rounded-lg border p-2"
                    />
                </label>

                <button
                    type="submit"
                    disabled={
                        guardando ||
                        !examenId ||
                        detalle.trim().length < 5 ||
                        detalle.trim().length > 2000
                    }
                    className="rounded-lg bg-slate-900 px-4 py-2 text-white disabled:opacity-50"
                >
                    {guardando ? 'Registrando…' : 'Registrar incidencia manual'}
                </button>
            </form>

            {mensaje && (
                <p role="status" className="text-sm">
                    {mensaje}
                </p>
            )}

            <h3 className="font-medium">Historial manual</h3>
            {actualizando && <p className="text-sm">Consultando…</p>}
            {!actualizando && registros.length === 0 && (
                <p className="text-sm text-slate-600">Sin incidencias manuales.</p>
            )}
            <ul className="divide-y">
                {registros.map((registro) => (
                    <li key={registro.incidencia_id} className="py-3 text-sm">
                        <p className="font-semibold">{registro.tipo}</p>
                        <p className="whitespace-pre-wrap break-words">{registro.detalle}</p>
                        <p>
                            {registro.estudiante_id
                                ? `Estudiante: ${[registro.apellidos, registro.nombres].filter(Boolean).join(', ') || registro.estudiante_id}`
                                : 'Incidencia general'}
                        </p>
                        <p>Operador: {registro.operador ?? registro.reportada_por}</p>
                        <p>Fecha: {registro.reportada_en}</p>
                        <p>{registro.atendida_en ? 'Atendida' : 'Pendiente'}</p>
                    </li>
                ))}
            </ul>
        </section>
    );
}

IncidenciasManuales.propTypes = {
    aulaId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]).isRequired,
    examenes: PropTypes.arrayOf(PropTypes.object).isRequired,
    sugerencia: PropTypes.shape({
        examenId: PropTypes.number.isRequired,
        tipo: PropTypes.string.isRequired,
        codigo: PropTypes.string.isRequired,
        solicitud: PropTypes.number.isRequired,
    }),
};
