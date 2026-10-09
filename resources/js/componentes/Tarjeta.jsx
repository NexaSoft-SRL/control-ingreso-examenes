import PropTypes from 'prop-types';

// `sinRelleno` deja el contenido a ras del borde, para tablas y listas.
export default function Tarjeta({
    id,
    titulo,
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
                    {titulo && <h2 className="text-base font-semibold text-slate-800">{titulo}</h2>}
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
    acciones: PropTypes.node,
    sinRelleno: PropTypes.bool,
    className: PropTypes.string,
    children: PropTypes.node,
};
