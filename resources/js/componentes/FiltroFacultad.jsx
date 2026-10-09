import PropTypes from 'prop-types';
import Chips from './Chips';
import { usarFacultades } from '../sesion/SesionContexto';

const SIN_COLOR = '#475569';

// El color de una facultad, a partir de la lista de `usarFacultades()`.
export function colorDeFacultad(sigla, facultades = []) {
    return facultades.find((f) => f.sigla === sigla)?.color ?? SIN_COLOR;
}

// `const colorDe = usarColorDeFacultad(); colorDe('FCyT')`.
export function useColorDeFacultad() {
    const facultades = usarFacultades();
    return (sigla) => colorDeFacultad(sigla, facultades);
}

export { useColorDeFacultad as usarColorDeFacultad };

export function PuntoFacultad({ sigla, conTexto = true }) {
    const colorDe = useColorDeFacultad();
    return (
        <span
            className="inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium text-slate-700"
            title={sigla}
        >
            <span
                className="h-2.5 w-2.5 shrink-0 rounded-full"
                style={{ backgroundColor: colorDe(sigla) }}
            />
            {conTexto && sigla}
        </span>
    );
}

PuntoFacultad.propTypes = { sigla: PropTypes.string.isRequired, conTexto: PropTypes.bool };

// Selector de facultad, con el color de cada una. `valor` nulo es «todas».
// `campo` elige qué se entrega al cambiar: la `sigla` (por defecto) o la
// `clave` que esperan los parámetros `?facultad=` de la API.
export default function FiltroFacultad({ valor, onCambiar, conteo, extra, campo = 'sigla' }) {
    const facultades = usarFacultades();
    const opciones = [
        { valor: null, etiqueta: 'Todas', conteo: conteo?.(null) },
        ...facultades.map((f) => ({
            valor: f[campo],
            etiqueta: f.sigla,
            color: f.color,
            conteo: conteo?.(f.sigla),
        })),
    ];

    return (
        <Chips
            opciones={opciones}
            valor={valor}
            onCambiar={onCambiar}
            etiqueta="Filtrar por facultad"
            extra={extra}
        />
    );
}

FiltroFacultad.propTypes = {
    valor: PropTypes.string,
    onCambiar: PropTypes.func.isRequired,
    conteo: PropTypes.func,
    extra: PropTypes.node,
    campo: PropTypes.oneOf(['sigla', 'clave']),
};
