import { useEffect, useRef, useState } from 'react';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import EstadoCarga from '../../componentes/EstadoCarga';
import Tarjeta from '../../componentes/Tarjeta';

const PREDETERMINADAS = { asistencia_minima: 70, incidencias_minimas: 5 };

export default function AlertasReportes() {
    const consulta = usarConsulta('/reportes/alertas');
    const [avisos, avisar] = useAviso();
    const [preferencias, ponerPreferencias] = useState(PREDETERMINADAS);
    const [guardando, ponerGuardando] = useState(false);
    const preferenciasEditadas = useRef(false);

    useEffect(() => {
        if (consulta.datos?.preferencias && !preferenciasEditadas.current) {
            ponerPreferencias(consulta.datos.preferencias);
        }
    }, [consulta.datos?.preferencias]);

    function cambiarPreferencia(clave, valor) {
        preferenciasEditadas.current = true;
        ponerPreferencias((actuales) => ({ ...actuales, [clave]: valor }));
    }

    async function guardar(evento) {
        evento.preventDefault();
        ponerGuardando(true);

        try {
            const respuesta = await api.put('/reportes/alertas/preferencias', preferencias);
            preferenciasEditadas.current = false;
            ponerPreferencias(respuesta.data.preferencias);
            await consulta.recargar();
            avisar('Umbrales de alerta guardados');
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudieron guardar los umbrales'), 'error');
        } finally {
            ponerGuardando(false);
        }
    }

    async function marcarLeida(id) {
        try {
            await api.post(`/reportes/alertas/${id}/leida`);
            await consulta.recargar();
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudo actualizar la alerta'), 'error');
        }
    }

    return (
        <div className="space-y-4">
            <Tarjeta titulo="Alertas de exámenes">
                <p className="mb-4 text-sm text-slate-600">
                    Al cerrar un examen, te avisaremos si la asistencia queda por debajo del
                    porcentaje o si las incidencias alcanzan los umbrales configurados.
                </p>
                <form onSubmit={guardar} className="grid gap-4 sm:grid-cols-3 sm:items-end">
                    <label className="block text-sm font-medium text-slate-700">
                        Asistencia mínima (%)
                        <input
                            type="number"
                            min="0"
                            max="100"
                            required
                            value={preferencias.asistencia_minima}
                            onChange={(evento) =>
                                cambiarPreferencia('asistencia_minima', evento.target.value)
                            }
                            className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 text-slate-900"
                        />
                    </label>
                    <label className="block text-sm font-medium text-slate-700">
                        Incidencias para alertar
                        <input
                            type="number"
                            min="1"
                            max="999"
                            required
                            value={preferencias.incidencias_minimas}
                            onChange={(evento) =>
                                cambiarPreferencia('incidencias_minimas', evento.target.value)
                            }
                            className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 text-slate-900"
                        />
                    </label>
                    <Boton type="submit" cargando={guardando}>
                        {guardando ? 'Guardando…' : 'Guardar umbrales'}
                    </Boton>
                </form>
            </Tarjeta>

            <Tarjeta titulo="Alertas recientes">
                <EstadoCarga
                    cargando={consulta.cargando}
                    error={consulta.error}
                    onReintentar={consulta.recargar}
                    vacio={(consulta.datos?.alertas ?? []).length === 0}
                    textoVacio="Todavía no tienes alertas"
                    filas={2}
                >
                    <ul className="divide-y divide-slate-200">
                        {(consulta.datos?.alertas ?? []).map((alerta) => (
                            <li
                                key={alerta.id}
                                className={`flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between ${
                                    alerta.leida_en ? 'text-slate-600' : 'text-slate-900'
                                }`}
                            >
                                <div>
                                    <h3 className="font-medium">{alerta.titulo}</h3>
                                    <p className="mt-1 text-sm">{alerta.mensaje}</p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        Correo:{' '}
                                        {alerta.correo_estado === 'ENVIADO'
                                            ? 'enviado'
                                            : alerta.correo_estado === 'FALLIDO'
                                              ? 'no enviado'
                                              : 'pendiente'}
                                    </p>
                                </div>
                                {!alerta.leida_en && (
                                    <Boton
                                        variante="secundario"
                                        type="button"
                                        onClick={() => marcarLeida(alerta.id)}
                                    >
                                        Marcar leída
                                    </Boton>
                                )}
                            </li>
                        ))}
                    </ul>
                </EstadoCarga>
            </Tarjeta>
            {avisos}
        </div>
    );
}
