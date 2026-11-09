import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { Copy, KeyRound, RefreshCw } from 'lucide-react';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import EstadoCarga from '../../componentes/EstadoCarga';
import { fechaLarga } from '../../utiles/texto';

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

// «2026-10-12 08:15» → «lunes 12 de octubre, 08:15».
function momento(texto) {
    const [fecha, hora] = String(texto ?? '').split(' ');
    return [fechaLarga(fecha), hora].filter(Boolean).join(', ');
}

// El código con el que entra al punto de control quien atiende el aula sin
// cuenta (rutas 52 y 53). Consultarlo no lo crea: sin código se ofrece
// «Generar»; con código, «Copiar» y «Regenerar», que deja sin efecto el
// anterior. Se monta solo con un aula elegida.
export default function CodigoDeAula({ examenId, aula, onCerrar, onAviso, onGenerado }) {
    const ruta = `/examenes/${examenId}/aulas/${aula.id}/codigo`;
    const consulta = usarConsulta(ruta);
    const [generado, setGenerado] = useState(null);
    const [enviando, setEnviando] = useState(false);
    // Regenerar deja fuera a quien entró con el código actual: se confirma.
    const [confirmando, setConfirmando] = useState(false);
    const acceso = generado ?? consulta.datos ?? null;
    const hay = Boolean(acceso?.codigo);

    // El aula pasa a tener código cuando lo tiene de verdad.
    useEffect(() => {
        if (hay) onGenerado?.(aula.id);
    }, [hay, aula.id, onGenerado]);

    // El mismo POST genera el primero y reemplaza el vigente.
    const generar = async () => {
        setConfirmando(false);
        setEnviando(true);
        try {
            const { data } = await api.post(ruta);
            setGenerado(data.data);
            onAviso(sinPunto(data.message ?? (hay ? 'Código regenerado' : 'Código generado')));
        } catch (fallo) {
            onAviso(
                sinPunto(mensajeDe(fallo, hay ? 'No se pudo regenerar' : 'No se pudo generar')),
                'error'
            );
        } finally {
            setEnviando(false);
        }
    };

    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(acceso.codigo);
            onAviso('Código copiado');
        } catch {
            onAviso('No se pudo copiar', 'error');
        }
    };

    return (
        <Dialogo
            titulo={`Código de acceso · Aula ${aula.nombre}`}
            onCerrar={onCerrar}
            acciones={
                hay && confirmando ? (
                    <>
                        <Boton
                            type="button"
                            variante="secundario"
                            onClick={() => setConfirmando(false)}
                        >
                            Cancelar
                        </Boton>
                        <Boton
                            type="button"
                            variante="peligro"
                            cargando={enviando}
                            onClick={generar}
                        >
                            <RefreshCw className="h-4 w-4" /> Regenerar
                        </Boton>
                    </>
                ) : hay ? (
                    <>
                        <Boton
                            type="button"
                            variante="secundario"
                            onClick={() => setConfirmando(true)}
                        >
                            <RefreshCw className="h-4 w-4" /> Regenerar
                        </Boton>
                        <Boton type="button" onClick={copiar}>
                            <Copy className="h-4 w-4" /> Copiar
                        </Boton>
                    </>
                ) : (
                    <Boton type="button" disabled={!acceso || enviando} onClick={generar}>
                        <KeyRound className="h-4 w-4" /> Generar
                    </Boton>
                )
            }
        >
            <EstadoCarga
                cargando={consulta.cargando}
                error={generado ? null : consulta.error}
                onReintentar={consulta.recargar}
                filas={2}
            >
                {acceso && (
                    <div className="text-center">
                        {hay ? (
                            <p
                                aria-label="Código"
                                className="font-mono text-4xl font-semibold tracking-[0.2em] text-slate-900"
                            >
                                {acceso.codigo}
                            </p>
                        ) : (
                            <p className="text-base font-medium text-slate-600">Sin código</p>
                        )}
                        <p className="mt-2 break-words text-sm text-slate-600">
                            {[acceso.encargado, momento(acceso.examen)].filter(Boolean).join(' · ')}
                        </p>
                        {hay && (
                            <p className="mt-2 text-xs text-slate-500">
                                Cada acceso se autoriza en «En curso»
                            </p>
                        )}
                        {confirmando && (
                            <p
                                role="alert"
                                className="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-sm font-medium text-warning-800"
                            >
                                El código actual deja de abrir la puerta y se cortan sus accesos
                            </p>
                        )}
                    </div>
                )}
            </EstadoCarga>
        </Dialogo>
    );
}

CodigoDeAula.propTypes = {
    examenId: PropTypes.number.isRequired,
    aula: PropTypes.shape({ id: PropTypes.number.isRequired, nombre: PropTypes.string.isRequired })
        .isRequired,
    onCerrar: PropTypes.func.isRequired,
    onAviso: PropTypes.func.isRequired,
    onGenerado: PropTypes.func,
};
