import PropTypes from 'prop-types';
import { useState } from 'react';
import { CheckCircle2, CircleAlert, CircleX, ShieldAlert, Smartphone, WifiOff } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Dato from '../../componentes/Dato';
import Dialogo from '../../componentes/Dialogo';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import { AreaTexto } from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';

const NOTA_MINIMA = 5;
const NOTA_MAXIMA = 1000;

const GRAVEDAD = {
    ALTA: { texto: 'Alta', tono: 'peligro' },
    MEDIA: { texto: 'Media', tono: 'advertencia' },
    BAJA: { texto: 'Baja', tono: 'neutro' },
};

const ESTADO = {
    PENDIENTE: { texto: 'Por resolver', tono: 'advertencia' },
    CONFIRMADA: { texto: 'Confirmada', tono: 'exito' },
    DESCARTADA: { texto: 'Descartada', tono: 'neutro' },
};

// «2026-10-12 08:19:00» → «08:19».
const hora = (texto) => (typeof texto === 'string' ? texto.slice(11, 16) : '—');

// Lo que el docente revisa de un examen donde se usó el modo sin conexión:
// los choques que el servidor detectó al sincronizar, para resolverlos a
// mano, y los puestos que prepararon el modo y no enviaron su cierre.
export default function RevisionSinConexion({ examenId }) {
    const { cuerpo, cargando, error, recargar } = usarConsulta(
        `/examenes/${examenId}/sin-conexion`
    );
    const [aviso, avisar] = useAviso();
    const [resolviendo, setResolviendo] = useState(null);
    const [estado, setEstado] = useState('CONFIRMADA');
    const [nota, setNota] = useState('');
    const [errorNota, setErrorNota] = useState(null);
    const [enviando, setEnviando] = useState(false);

    if (!cuerpo) {
        return (
            <Tarjeta sinRelleno>
                <EstadoCarga cargando={cargando} error={error} onReintentar={recargar} />
            </Tarjeta>
        );
    }

    const { resumen, irregularidades, sesiones, sin_sincronizar: sinSincronizar } = cuerpo;

    const cerrar = () => {
        setResolviendo(null);
        setNota('');
        setErrorNota(null);
        setEstado('CONFIRMADA');
    };

    async function resolver() {
        if (nota.trim().length < NOTA_MINIMA)
            return setErrorNota(`Mínimo ${NOTA_MINIMA} caracteres`);
        if (enviando) return undefined;
        setEnviando(true);
        try {
            await api.post(`/sin-conexion/irregularidades/${resolviendo.id}/resolucion`, {
                estado,
                nota: nota.trim(),
            });
            avisar(estado === 'CONFIRMADA' ? 'Confirmada' : 'Descartada');
            cerrar();
            await recargar();
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (campos.nota) setErrorNota(campos.nota);
            else avisar(mensajeDe(fallo, 'No se pudo resolver'), 'error');
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    if (!cuerpo.usado && sesiones.length === 0) {
        return (
            <Tarjeta titulo="Revisión sin conexión" icono={WifiOff}>
                <p className="py-6 text-center text-sm text-slate-600">
                    No se usó el modo sin conexión en este examen
                    <span className="mt-1 block text-xs text-slate-500">
                        Aquí aparecen los ingresos registrados sin internet
                    </span>
                </p>
            </Tarjeta>
        );
    }

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Dato
                    etiqueta="Por resolver"
                    icono={CircleAlert}
                    valor={resumen.pendientes}
                    tono={resumen.pendientes > 0 ? 'advertencia' : 'neutro'}
                />
                <Dato
                    etiqueta="Gravedad alta"
                    icono={ShieldAlert}
                    valor={resumen.altas}
                    tono={resumen.altas > 0 ? 'peligro' : 'neutro'}
                />
                <Dato
                    etiqueta="Resueltas"
                    icono={CheckCircle2}
                    valor={resumen.resueltas}
                    tono="exito"
                />
                <Dato
                    etiqueta="Puestos sin cierre"
                    icono={Smartphone}
                    valor={resumen.sin_sincronizar}
                    tono={resumen.sin_sincronizar > 0 ? 'advertencia' : 'neutro'}
                />
            </div>

            {sinSincronizar.length > 0 && (
                <div
                    role="alert"
                    className="rounded-xl border border-warning-200 bg-warning-50 p-4"
                >
                    <p className="flex items-center gap-2 text-sm font-semibold text-warning-800">
                        <Smartphone className="h-4 w-4" aria-hidden="true" /> Sin cierre
                    </p>
                    <ul className="mt-2 space-y-1 text-sm text-warning-800">
                        {sinSincronizar.map((s) => (
                            <li key={s.id}>
                                Aula {s.aula} · {s.operador ?? 'Código de aula'} · preparó a las{' '}
                                {hora(s.descargada_en)} · puesto …{s.dispositivo}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <Tarjeta
                titulo={`Irregularidades · ${irregularidades.length}`}
                icono={ShieldAlert}
                sinRelleno
            >
                {irregularidades.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-slate-600">
                        Sin irregularidades
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {irregularidades.map((i) => {
                            const g = GRAVEDAD[i.gravedad] ?? GRAVEDAD.BAJA;
                            const e = ESTADO[i.estado] ?? ESTADO.PENDIENTE;
                            return (
                                <li key={i.id} className="space-y-1.5 px-5 py-4">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Insignia tono={g.tono} punto>
                                            {g.texto}
                                        </Insignia>
                                        <p className="text-sm font-semibold text-slate-900">
                                            {i.tipo_texto}
                                        </p>
                                        <span className="ml-auto">
                                            <Insignia tono={e.tono}>{e.texto}</Insignia>
                                        </span>
                                    </div>
                                    <p className="text-sm text-slate-700">{i.detalle}</p>
                                    <p className="text-xs text-slate-600">
                                        {[
                                            i.estudiante,
                                            i.aula && `Aula ${i.aula}`,
                                            i.datos?.dispositivo &&
                                                `puesto …${i.datos.dispositivo}`,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                    {i.estado === 'PENDIENTE' ? (
                                        <Boton
                                            variante="secundario"
                                            tamano="chico"
                                            onClick={() => setResolviendo(i)}
                                        >
                                            Resolver
                                        </Boton>
                                    ) : (
                                        <p className="text-xs text-slate-600">
                                            {i.resuelta_por ? `${i.resuelta_por} · ` : ''}
                                            {i.nota}
                                        </p>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </Tarjeta>

            <Tarjeta titulo={`Puestos · ${sesiones.length}`} icono={Smartphone} sinRelleno>
                <ul className="divide-y divide-slate-100">
                    {sesiones.map((s) => (
                        <li
                            key={s.id}
                            className="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 text-sm"
                        >
                            <span className="font-medium text-slate-800">Aula {s.aula}</span>
                            <span className="text-slate-600">{s.operador ?? 'Código de aula'}</span>
                            <span className="font-mono text-xs text-slate-500">
                                …{s.dispositivo}
                            </span>
                            <span className="text-xs text-slate-600">
                                {hora(s.descargada_en)}
                                {s.sincronizada_en ? ` → ${hora(s.sincronizada_en)}` : ''} ·{' '}
                                {s.eventos} {s.eventos === 1 ? 'lectura' : 'lecturas'}
                            </span>
                            <span className="ml-auto">
                                <Insignia
                                    tono={s.estado === 'SINCRONIZADA' ? 'exito' : 'advertencia'}
                                >
                                    {s.estado === 'SINCRONIZADA' ? 'Cerrado' : 'Sin cierre'}
                                </Insignia>
                            </span>
                        </li>
                    ))}
                </ul>
            </Tarjeta>

            {resolviendo && (
                <Dialogo
                    titulo={resolviendo.tipo_texto}
                    onCerrar={cerrar}
                    acciones={
                        <>
                            <Boton variante="secundario" onClick={cerrar}>
                                Cancelar
                            </Boton>
                            <Boton onClick={resolver} cargando={enviando}>
                                Guardar
                            </Boton>
                        </>
                    }
                >
                    <div className="space-y-4">
                        <p className="text-sm text-slate-700">{resolviendo.detalle}</p>
                        <div role="group" aria-label="Decisión" className="grid grid-cols-2 gap-2">
                            <Boton
                                variante={estado === 'CONFIRMADA' ? 'primario' : 'secundario'}
                                aria-pressed={estado === 'CONFIRMADA'}
                                onClick={() => setEstado('CONFIRMADA')}
                            >
                                <CheckCircle2 className="h-4 w-4" aria-hidden="true" /> Confirmar
                            </Boton>
                            <Boton
                                variante={estado === 'DESCARTADA' ? 'primario' : 'secundario'}
                                aria-pressed={estado === 'DESCARTADA'}
                                onClick={() => setEstado('DESCARTADA')}
                            >
                                <CircleX className="h-4 w-4" aria-hidden="true" /> Descartar
                            </Boton>
                        </div>
                        <AreaTexto
                            etiqueta="Nota"
                            rows={3}
                            maxLength={NOTA_MAXIMA}
                            value={nota}
                            error={errorNota}
                            pie={`${nota.length}/${NOTA_MAXIMA}`}
                            onChange={(e) => {
                                setNota(e.target.value);
                                setErrorNota(null);
                            }}
                        />
                    </div>
                </Dialogo>
            )}
            {aviso}
        </div>
    );
}

RevisionSinConexion.propTypes = {
    examenId: PropTypes.number.isRequired,
};
