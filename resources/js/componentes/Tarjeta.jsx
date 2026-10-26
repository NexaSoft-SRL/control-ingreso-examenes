import PropTypes from 'prop-types';

// `sinRelleno` deja el contenido a ras del borde, para tablas y listas.
// `icono` (un componente de lucide) acompaña al título.
export default function Tarjeta({
    id,
    titulo,
    icono: Icono = null,
    acciones,
    sinRelleno = false,
    className = '',
    children,
}) {
    return (
        <section
            id={id}
            className={`min-w-0 rounded-xl border border-slate-200 bg-white ${className}`}
        >
            {(titulo || acciones) && (
                <header className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-b border-slate-200 px-5 py-4">
                    {titulo && (
                        <h2 className="flex items-center gap-2.5 text-base font-semibold text-slate-800">
                            {Icono && (
                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                                    <Icono className="h-4 w-4" aria-hidden="true" />
                                </span>
                            )}
                            {titulo}
                        </h2>
                    )}
                    {acciones && <div className="flex shrink-0 items-center gap-2">{acciones}</div>}
                </header>
            )}
            <div className={sinRelleno ? '' : 'p-5'}>{children}</div>
        </section>
    );
}

Tarjeta.propTypes = {
    id: PropTypes.string,
    titulo: PropTypes.node,
    icono: PropTypes.elementType,
    acciones: PropTypes.node,
    sinRelleno: PropTypes.bool,
    className: PropTypes.string,
    children: PropTypes.node,
};
