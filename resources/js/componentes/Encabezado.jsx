import PropTypes from 'prop-types';
import { ArrowLeft } from 'lucide-react';
import { Link } from 'react-router-dom';
import Insignia from './Insignia';

// Encabezado de página: título, una línea de datos debajo y, a la
// derecha, la acción principal. `volver` agrega el enlace a la pantalla
// de la que se viene. `sinConexion` (del sondeo) pone la insignia.
export default function Encabezado({ titulo, subtitulo, volver, sinConexion = false, children }) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                {volver && (
                    <Link
                        to={volver.a}
                        className="-ml-1 mb-1 inline-flex min-h-10 items-center gap-1 rounded-lg px-1 text-sm font-medium text-primary-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                    >
                        <ArrowLeft className="h-4 w-4" /> {volver.texto}
                    </Link>
                )}
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 className="text-xl font-semibold text-slate-900 sm:text-2xl">{titulo}</h1>
                    {sinConexion && (
                        <Insignia tono="peligro" punto>
                            Sin conexión
                        </Insignia>
                    )}
                </div>
                {subtitulo && <p className="mt-0.5 text-sm text-slate-600">{subtitulo}</p>}
            </div>
            {children && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">{children}</div>
            )}
        </div>
    );
}

Encabezado.propTypes = {
    titulo: PropTypes.node.isRequired,
    subtitulo: PropTypes.node,
    volver: PropTypes.shape({
        a: PropTypes.oneOfType([PropTypes.string, PropTypes.object]).isRequired,
        texto: PropTypes.string.isRequired,
    }),
    sinConexion: PropTypes.bool,
    children: PropTypes.node,
};
