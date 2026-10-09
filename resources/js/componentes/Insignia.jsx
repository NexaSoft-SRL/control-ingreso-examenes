import PropTypes from 'prop-types';

const TONOS = {
    exito: 'bg-success-100 text-success-700',
    peligro: 'bg-danger-100 text-danger-700',
    advertencia: 'bg-warning-100 text-warning-700',
    info: 'bg-info-100 text-info-700',
    primario: 'bg-primary-100 text-primary-700',
    neutro: 'bg-slate-200 text-slate-700',
};

export default function Insignia({ tono = 'neutro', punto = false, children }) {
    return (
        <span
            className={`inline-flex w-fit items-center gap-1.5 self-start whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ${TONOS[tono]}`}
        >
            {punto && <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-current" />}
            {children}
        </span>
    );
}

Insignia.propTypes = {
    tono: PropTypes.oneOf(Object.keys(TONOS)),
    punto: PropTypes.bool,
    children: PropTypes.node,
};
