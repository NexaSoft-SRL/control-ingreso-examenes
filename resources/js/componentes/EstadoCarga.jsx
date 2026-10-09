import PropTypes from 'prop-types';
import Boton from './Boton';

// Los tres estados comunes de una lista, en el lugar de la lista:
//  - `cargando` (primera carga): esqueleto gris del alto de `filas`.
//  - `error`: «No se pudo cargar» y «Reintentar» (`onReintentar`).
//  - `vacio`: el texto de la pantalla (`textoVacio`).
// Si no aplica ninguno, dibuja `children`.
//
//   <EstadoCarga cargando={cargando} error={error} vacio={datos?.length === 0}
//       textoVacio="Sin exámenes" onReintentar={recargar}>…</EstadoCarga>
export default function EstadoCarga({
    cargando = false,
    error = null,
    vacio = false,
    textoVacio = 'Sin resultados',
    onReintentar,
    filas = 5,
    className = '',
    children = null,
}) {
    if (cargando) {
        return (
            <div
                role="status"
                aria-busy="true"
                aria-label="Cargando"
                className={`animate-pulse space-y-3 p-4 ${className}`}
            >
                {Array.from({ length: filas }, (_, i) => (
                    <div key={i} className="h-10 rounded-lg bg-slate-200" />
                ))}
            </div>
        );
    }

    if (error) {
        return (
            <div
                role="alert"
                className={`flex flex-col items-center gap-3 px-4 py-8 text-center ${className}`}
            >
                <p className="text-sm text-slate-600">No se pudo cargar</p>
                {onReintentar && (
                    <Boton
                        type="button"
                        variante="secundario"
                        tamano="chico"
                        onClick={() => onReintentar()}
                    >
                        Reintentar
                    </Boton>
                )}
            </div>
        );
    }

    if (vacio) {
        return (
            <p className={`px-4 py-8 text-center text-sm text-slate-600 ${className}`}>
                {textoVacio}
            </p>
        );
    }

    return children;
}

EstadoCarga.propTypes = {
    cargando: PropTypes.bool,
    error: PropTypes.any,
    vacio: PropTypes.bool,
    textoVacio: PropTypes.node,
    onReintentar: PropTypes.func,
    filas: PropTypes.number,
    className: PropTypes.string,
    children: PropTypes.node,
};
