import PropTypes from 'prop-types';
import { areaDeAsignatura } from '../utiles/areas';

// El ícono del área en un cuadrito con el color de la asignatura (o el del
// área si no tiene). `tamano`: 'xs' para filtros y listas densas, 'sm' para filas,
// 'md' para encabezados.
export default function IconoAsignatura({ nombre, color, tamano = 'sm', className = '' }) {
    const { area, Icono, color: deArea } = areaDeAsignatura(nombre);
    const tono = color ?? deArea;
    const caja = { xs: 'h-5 w-5 rounded', sm: 'h-7 w-7 rounded-md', md: 'h-9 w-9 rounded-lg' }[
        tamano
    ];
    const icono = { xs: 'h-3.5 w-3.5', sm: 'h-4 w-4', md: 'h-5 w-5' }[tamano];
    return (
        <span
            className={`flex shrink-0 items-center justify-center ${caja} ${className}`}
            style={{ backgroundColor: `${tono}1f`, color: tono }}
            title={area}
            aria-label={area}
            role="img"
        >
            <Icono className={icono} aria-hidden="true" />
        </span>
    );
}

IconoAsignatura.propTypes = {
    nombre: PropTypes.string,
    color: PropTypes.string,
    tamano: PropTypes.oneOf(['xs', 'sm', 'md']),
    className: PropTypes.string,
};
