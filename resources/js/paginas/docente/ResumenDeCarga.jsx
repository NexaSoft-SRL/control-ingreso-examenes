import PropTypes from 'prop-types';
import { useState } from 'react';
import { AlertTriangle, CheckCheck, Recycle, UserPlus, X, XCircle } from 'lucide-react';
import Boton from '../../componentes/Boton';
import { plural } from '../../utiles/texto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// Las cinco cifras de una carga. Las filas rechazadas y en conflicto se
// pueden desplegar, cada una con su motivo.
function cifrasDe(resultado) {
    const resumen = resultado.resumen ?? {};
    const rechazos = resultado.rechazos ?? [];
    const conflictos = resultado.conflictos ?? [];

    return [
        {
            id: 'nuevos',
            Icono: UserPlus,
            tono: 'text-primary-700 bg-primary-50',
            cantidad: resumen.nuevos ?? 0,
            texto: ['nuevo', 'nuevos'],
            detalle: 'Creados en el padrón',
        },
        {
            id: 'reutilizados',
            Icono: Recycle,
            tono: 'text-success-700 bg-success-50',
            cantidad: resumen.reutilizados ?? 0,
            texto: ['ya en el padrón', 'ya en el padrón'],
            detalle: 'Inscritos al grupo',
        },
        {
            id: 'ya_inscritos',
            Icono: CheckCheck,
            tono: 'text-slate-700 bg-slate-100',
            cantidad: resumen.ya_inscritos ?? 0,
            texto: ['ya inscrito', 'ya inscritos'],
            detalle: 'Sin cambios',
        },
        {
            id: 'rechazados',
            Icono: XCircle,
            tono: 'text-danger-700 bg-danger-50',
            cantidad: resumen.rechazados ?? rechazos.length,
            texto: ['rechazado', 'rechazados'],
            filas: rechazos.map((r) => [`Fila ${r.fila}`, r.motivo]),
        },
        {
            id: 'conflictos',
            Icono: AlertTriangle,
            tono: 'text-warning-700 bg-warning-50',
            cantidad: resumen.conflictos ?? conflictos.length,
            texto: ['en conflicto', 'en conflicto'],
            filas: conflictos.map((c) => [
                [`Fila ${c.fila}`, c.codigo].filter(Boolean).join(' · '),
                c.motivo,
            ]),
        },
    ];
}

export default function ResumenDeCarga({ resultado, onCerrar }) {
    const [abierta, setAbierta] = useState(null);

    return (
        <div className="border-b border-slate-200 p-4 sm:p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
                <p className="min-w-0 text-sm text-slate-700">
                    <strong className="break-all">{resultado.archivo}</strong> ·{' '}
                    {plural(resultado.resumen?.filas ?? 0, 'fila')}
                </p>
                <button
                    type="button"
                    aria-label="Cerrar el resumen de la carga"
                    onClick={onCerrar}
                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 ${FOCO}`}
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
            <ul className="grid gap-2 sm:grid-cols-2">
                {cifrasDe(resultado).map(({ id, Icono, tono, cantidad, texto, detalle, filas }) => {
                    const desplegable = filas && filas.length > 0;
                    return (
                        <li key={id} className="min-w-0 rounded-lg border border-slate-200 p-3">
                            <div className="flex items-center gap-3">
                                <span
                                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${tono}`}
                                >
                                    <Icono className="h-[18px] w-[18px]" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold text-slate-800">
                                        {cantidad} {cantidad === 1 ? texto[0] : texto[1]}
                                    </span>
                                    {detalle && (
                                        <span className="block text-xs text-slate-600">
                                            {detalle}
                                        </span>
                                    )}
                                </span>
                                {desplegable && (
                                    <Boton
                                        type="button"
                                        variante="enlace"
                                        tamano="chico"
                                        className="min-h-10 shrink-0"
                                        aria-expanded={abierta === id}
                                        aria-label={`${abierta === id ? 'Ocultar' : 'Ver'} filas: ${texto[1]}`}
                                        onClick={() => setAbierta(abierta === id ? null : id)}
                                    >
                                        {abierta === id ? 'Ocultar' : 'Ver filas'}
                                    </Boton>
                                )}
                            </div>
                            {desplegable && abierta === id && (
                                <ul className="mt-2 max-h-64 divide-y divide-slate-200 overflow-y-auto border-t border-slate-200 text-xs">
                                    {filas.map(([fila, motivo], i) => (
                                        <li
                                            key={`${fila}-${i}`}
                                            className="flex flex-wrap justify-between gap-x-3 py-2"
                                        >
                                            <span className="font-mono text-slate-800">{fila}</span>
                                            <span className="min-w-0 break-words text-slate-700">
                                                {motivo}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

ResumenDeCarga.propTypes = {
    resultado: PropTypes.shape({
        archivo: PropTypes.string,
        resumen: PropTypes.shape({
            filas: PropTypes.number,
            nuevos: PropTypes.number,
            reutilizados: PropTypes.number,
            ya_inscritos: PropTypes.number,
            rechazados: PropTypes.number,
            conflictos: PropTypes.number,
        }),
        rechazos: PropTypes.arrayOf(
            PropTypes.shape({ fila: PropTypes.number, motivo: PropTypes.string })
        ),
        conflictos: PropTypes.arrayOf(
            PropTypes.shape({
                fila: PropTypes.number,
                codigo: PropTypes.string,
                motivo: PropTypes.string,
            })
        ),
    }).isRequired,
    onCerrar: PropTypes.func.isRequired,
};
