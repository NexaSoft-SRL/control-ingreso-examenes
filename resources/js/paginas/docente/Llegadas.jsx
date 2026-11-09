import { useState } from 'react';
import PropTypes from 'prop-types';
import Tarjeta from '../../componentes/Tarjeta';

const ALTO = 'h-28';
// Un rótulo cada quince minutos: con más, se encimarían.
const CADA = 3;

const minutosDe = (hora) => {
    const [h, m] = String(hora ?? '')
        .split(':')
        .map(Number);
    return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
};

// Ritmo de llegada: ingresos por tramo de cinco minutos, con la hora de
// inicio marcada. Con esto el docente decide cuándo empezar y si mueve o
// divide aulas.
export default function Llegadas({ llegadas, inicio }) {
    const [activa, setActiva] = useState(null);
    if (llegadas.length === 0) return null;

    const maximo = Math.max(...llegadas.map((l) => l.ingresos), 1);
    const total = llegadas.reduce((s, l) => s + l.ingresos, 0);
    const minInicio = minutosDe(inicio);
    const posicionInicio = llegadas.findIndex((l) => {
        const desde = minutosDe(l.hora);
        return minInicio !== null && desde <= minInicio && minInicio < desde + 5;
    });
    // La marca va en el minuto exacto, dentro de su tramo.
    const izquierdaInicio =
        posicionInicio >= 0
            ? ((posicionInicio + (minInicio - minutosDe(llegadas[posicionInicio].hora)) / 5) /
                  llegadas.length) *
              100
            : null;
    const hasta = (hora) => {
        const fin = minutosDe(hora) + 5;
        return `${String(Math.floor(fin / 60)).padStart(2, '0')}:${String(fin % 60).padStart(2, '0')}`;
    };

    return (
        <Tarjeta titulo={`Llegadas · ${total}`}>
            <div className="relative">
                <div
                    className={`flex ${ALTO} items-end gap-0.5 border-b border-slate-300`}
                    onMouseLeave={() => setActiva(null)}
                    aria-hidden="true"
                >
                    {llegadas.map((l, i) => (
                        <div
                            key={l.hora}
                            className="relative flex h-full min-w-0 flex-1 items-end"
                            onMouseEnter={() => setActiva(i)}
                        >
                            <span
                                className={`w-full rounded-t ${activa === i ? 'bg-primary-700' : 'bg-primary-500'}`}
                                style={{
                                    height:
                                        l.ingresos === 0
                                            ? 0
                                            : `max(4px, ${(l.ingresos / maximo) * 100}%)`,
                                }}
                            />
                        </div>
                    ))}
                </div>
                {izquierdaInicio !== null && (
                    <span
                        className={`pointer-events-none absolute top-0 ${ALTO} border-l-2 border-dashed border-slate-500`}
                        style={{ left: `${izquierdaInicio}%` }}
                        aria-hidden="true"
                    />
                )}
                {activa !== null && (
                    <div
                        className="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white"
                        style={{ left: `${((activa + 0.5) / llegadas.length) * 100}%` }}
                    >
                        {llegadas[activa].hora}–{hasta(llegadas[activa].hora)} ·{' '}
                        {llegadas[activa].ingresos}
                    </div>
                )}
                <div
                    className="mt-1 flex gap-0.5 text-[11px] tabular-nums text-slate-600"
                    aria-hidden="true"
                >
                    {llegadas.map((l, i) => (
                        <span
                            key={l.hora}
                            className="min-w-0 flex-1 overflow-visible whitespace-nowrap"
                        >
                            {i % CADA === 0 ? l.hora : ''}
                        </span>
                    ))}
                </div>
                {posicionInicio >= 0 && (
                    <p className="mt-2 flex items-center gap-1.5 text-xs text-slate-600">
                        <span className="h-3 border-l-2 border-dashed border-slate-500" /> Inicio{' '}
                        {inicio}
                    </p>
                )}
            </div>
            <table className="sr-only">
                <caption>Llegadas por tramo de cinco minutos</caption>
                <thead>
                    <tr>
                        <th>Desde</th>
                        <th>Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    {llegadas.map((l) => (
                        <tr key={l.hora}>
                            <td>{l.hora}</td>
                            <td>{l.ingresos}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </Tarjeta>
    );
}

Llegadas.propTypes = {
    llegadas: PropTypes.arrayOf(
        PropTypes.shape({
            hora: PropTypes.string.isRequired,
            ingresos: PropTypes.number.isRequired,
        })
    ).isRequired,
    inicio: PropTypes.string,
};
