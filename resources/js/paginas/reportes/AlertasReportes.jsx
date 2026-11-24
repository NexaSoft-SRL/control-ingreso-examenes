import { useEffect, useRef, useState } from 'react';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import EstadoCarga from '../../componentes/EstadoCarga';
import Tarjeta from '../../componentes/Tarjeta';

const PREDETERMINADAS = { asistencia_minima: 70, incidencias_minimas: 5 };

// Los rangos que acepta el servidor.
const LIMITES = {
    asistencia_minima: [0, 100],
    incidencias_minimas: [1, 999],
};
const errorDe = (clave, valor) => {
    const [minimo, maximo] = LIMITES[clave];
    const numero = Number(valor);
    return /^\d+$/.test(String(valor)) && numero >= minimo && numero <= maximo
        ? null
        : `Entre ${minimo} y ${maximo}`;
};

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

    const errores = {
        asistencia_minima: errorDe('asistencia_minima', preferencias.asistencia_minima),
        incidencias_minimas: errorDe('incidencias_minimas', preferencias.incidencias_minimas),
    };
    const invalido = Boolean(errores.asistencia_minima || errores.incidencias_minimas);

    async function guardar(evento) {
        evento.preventDefault();
        if (invalido || guardando) return;
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
                <form onSubmit={guardar} className="grid gap-4 sm:grid-cols-3 sm:items-end">
                    <Campo
                        etiqueta="Asistencia mínima (%)"
                        formato="digitos"
                        maxLength={3}
                        requerido
                        value={String(preferencias.asistencia_minima)}
                        error={errores.asistencia_minima ?? undefined}
                        onChange={(evento) =>
                            cambiarPreferencia('asistencia_minima', evento.target.value)
                        }
                    />
                    <Campo
                        etiqueta="Incidencias para alertar"
                        formato="digitos"
                        maxLength={3}
                        requerido
                        value={String(preferencias.incidencias_minimas)}
                        error={errores.incidencias_minimas ?? undefined}
                        onChange={(evento) =>
                            cambiarPreferencia('incidencias_minimas', evento.target.value)
                        }
                    />
                    <Boton type="submit" cargando={guardando} disabled={invalido}>
                        Guardar umbrales
                    </Boton>
                </form>
            </Tarjeta>

            <Tarjeta titulo="Alertas recientes">
                <EstadoCarga
                    cargando={consulta.cargando}
                    error={consulta.error}
                    onReintentar={consulta.recargar}
                    vacio={(consulta.datos?.alertas ?? []).length === 0}
                    textoVacio="Sin alertas"
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
