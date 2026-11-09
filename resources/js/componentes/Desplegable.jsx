import PropTypes from 'prop-types';
import { Check, ChevronDown } from 'lucide-react';
import usarDesplegable from './usarDesplegable';

// Lista desplegable propia, con el aspecto de Campo y Buscador: cada opción
// lleva su ícono y, a la derecha, una cuenta. Las opciones con `grupo` se
// juntan bajo ese rótulo, en el orden en que llegan. Se maneja con el teclado
// como una lista nativa (usarDesplegable).
//
//   <Desplegable etiqueta="Edificio" valor={v} onCambiar={setV}
//       opciones={[{ valor: '', texto: 'Todos', icono: Building2, cuenta: 12 }]} />
export default function Desplegable({ etiqueta, valor, onCambiar, opciones, className = '' }) {
    const elegida = opciones.find((o) => o.valor === valor) ?? opciones[0];
    const d = usarDesplegable({
        opciones,
        elegida,
        alElegir: (o) => onCambiar(o.valor),
        texto: (o) => o.texto,
    });
    const grupos = [...new Set(opciones.map((o) => o.grupo ?? null))];
    const Icono = elegida?.icono;

    const opcion = (o) => {
        const i = opciones.indexOf(o);
        const marcada = o === elegida;
        const IconoOpcion = o.icono;
        return (
            <li
                key={o.valor}
                id={d.idDe(i)}
                role="option"
                aria-selected={marcada}
                onMouseDown={(e) => e.preventDefault()}
                onMouseEnter={() => d.setActiva(i)}
                onClick={() => d.elegir(o)}
                className={`flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm ${
                    marcada
                        ? 'bg-primary-50 font-semibold text-primary-800'
                        : i === d.activa
                          ? 'bg-slate-100 text-slate-900'
                          : 'text-slate-800'
                }`}
            >
                {IconoOpcion && (
                    <span
                        className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-md ${
                            marcada
                                ? 'bg-primary-100 text-primary-700'
                                : 'bg-slate-100 text-slate-500'
                        }`}
                        aria-hidden="true"
                    >
                        <IconoOpcion className="h-4 w-4" />
                    </span>
                )}
                <span className="min-w-0 flex-1 truncate">{o.texto}</span>
                {o.cuenta !== undefined && (
                    <span
                        className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums ${
                            marcada
                                ? 'bg-primary-100 text-primary-700'
                                : 'bg-slate-100 text-slate-600'
                        }`}
                    >
                        {o.cuenta}
                    </span>
                )}
                <Check
                    className={`h-4 w-4 shrink-0 text-primary-600 ${marcada ? '' : 'invisible'}`}
                    aria-hidden="true"
                />
            </li>
        );
    };

    return (
        <div ref={d.caja} className={`relative ${className}`}>
            <button
                type="button"
                aria-haspopup="listbox"
                aria-expanded={d.abierta}
                aria-label={`${etiqueta}: ${elegida?.texto ?? ''}`}
                aria-controls={d.abierta ? `${d.id}-lista` : undefined}
                aria-activedescendant={d.abierta ? d.idDe(d.activa) : undefined}
                onClick={d.alternar}
                onKeyDown={d.teclas}
                className={`flex min-h-11 w-full items-center gap-2.5 rounded-lg border bg-white px-3.5 text-left text-sm text-slate-800 focus:outline-none focus-visible:border-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/40 ${
                    d.abierta ? 'border-primary-500 ring-2 ring-primary-500/40' : 'border-slate-300'
                }`}
            >
                {Icono && (
                    <Icono className="h-4 w-4 shrink-0 text-primary-600" aria-hidden="true" />
                )}
                <span className="min-w-0 flex-1 truncate font-medium">{elegida?.texto}</span>
                {elegida?.cuenta !== undefined && (
                    <span className="shrink-0 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-primary-700">
                        {elegida.cuenta}
                    </span>
                )}
                <ChevronDown
                    className={`h-4 w-4 shrink-0 text-slate-500 transition-transform ${d.abierta ? 'rotate-180' : ''}`}
                    aria-hidden="true"
                />
            </button>

            {d.abierta && (
                <ul
                    ref={d.lista}
                    id={`${d.id}-lista`}
                    role="listbox"
                    aria-label={etiqueta}
                    className="absolute z-30 mt-1.5 max-h-80 w-full min-w-72 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg shadow-primary-900/10"
                >
                    {grupos.map((g) =>
                        g === null ? (
                            opciones.filter((o) => !o.grupo).map(opcion)
                        ) : (
                            <li key={g} role="presentation">
                                <ul role="group" aria-label={g}>
                                    <li
                                        role="presentation"
                                        className="mt-1 border-t border-slate-100 px-2.5 pb-1 pt-2.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                                    >
                                        {g}
                                    </li>
                                    {opciones.filter((o) => o.grupo === g).map(opcion)}
                                </ul>
                            </li>
                        )
                    )}
                </ul>
            )}
        </div>
    );
}

Desplegable.propTypes = {
    etiqueta: PropTypes.string.isRequired,
    valor: PropTypes.string.isRequired,
    onCambiar: PropTypes.func.isRequired,
    opciones: PropTypes.arrayOf(
        PropTypes.shape({
            valor: PropTypes.string.isRequired,
            texto: PropTypes.string.isRequired,
            icono: PropTypes.elementType,
            cuenta: PropTypes.number,
            grupo: PropTypes.string,
        })
    ).isRequired,
    className: PropTypes.string,
};
