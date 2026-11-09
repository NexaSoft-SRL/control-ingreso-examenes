import { useEffect, useMemo } from 'react';
import PropTypes from 'prop-types';
import { X } from 'lucide-react';
import CampoImagen from './CampoImagen';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// Varias fotos como prueba (hasta `maximo`): cada una se toma con la cámara
// de atrás o se elige de un archivo, se ve en miniatura y se quita con la
// equis. `onCambiar(fotos)` recibe la lista entera, ya reducida.
export default function FotosEvidencia({ etiqueta, fotos, onCambiar, maximo = 3, error }) {
    const urls = useMemo(() => fotos.map((foto) => URL.createObjectURL(foto)), [fotos]);
    useEffect(() => () => urls.forEach((url) => URL.revokeObjectURL(url)), [urls]);
    const lleno = fotos.length >= maximo;

    return (
        <div>
            {lleno ? (
                <span className="mb-1.5 block text-sm font-medium text-slate-700">{etiqueta}</span>
            ) : (
                <CampoImagen
                    etiqueta={etiqueta}
                    archivo={null}
                    onCambiar={(foto) => foto && onCambiar([...fotos, foto])}
                    camara="environment"
                    tomarFoto
                    error={error}
                />
            )}
            {fotos.length > 0 && (
                <ul className="mt-2 flex flex-wrap items-center gap-2" aria-label="Fotos elegidas">
                    {urls.map((url, i) => (
                        <li key={url} className="relative">
                            <img
                                src={url}
                                alt={`Foto ${i + 1}`}
                                className="h-20 w-20 rounded-lg border border-slate-200 object-cover"
                            />
                            <button
                                type="button"
                                aria-label={`Quitar la foto ${i + 1}`}
                                onClick={() => onCambiar(fotos.filter((_, j) => j !== i))}
                                className={`absolute -right-2 -top-2 flex h-7 w-7 items-center justify-center rounded-full bg-slate-900/70 text-white hover:bg-slate-900 ${FOCO}`}
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </li>
                    ))}
                    <li className="text-xs tabular-nums text-slate-500">
                        {fotos.length}/{maximo}
                    </li>
                </ul>
            )}
            {lleno && error && (
                <span role="alert" className="mt-1.5 block text-sm text-danger-600">
                    {error}
                </span>
            )}
        </div>
    );
}

FotosEvidencia.propTypes = {
    etiqueta: PropTypes.string.isRequired,
    fotos: PropTypes.arrayOf(PropTypes.object).isRequired,
    onCambiar: PropTypes.func.isRequired,
    maximo: PropTypes.number,
    error: PropTypes.string,
};
