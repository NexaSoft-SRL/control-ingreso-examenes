import PropTypes from 'prop-types';
import { useState } from 'react';
import { FileSpreadsheet, FileText } from 'lucide-react';
import descargar from '../../api/descargar';
import { mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';

// Los dos botones de un reporte: PDF y hoja de cálculo. La descarga corre
// aparte: solo queda deshabilitado el botón que la pidió, y el aviso sale
// cuando el archivo llegó.
export default function BotonesDescarga({
    nombre,
    ruta,
    parametros,
    archivo,
    deshabilitado = false,
    onAvisar,
    className = '',
}) {
    const [enCurso, setEnCurso] = useState([]);

    async function bajar(formato) {
        setEnCurso((previos) => [...previos, formato]);
        try {
            const final = await descargar(ruta, {
                parametros: { ...parametros, formato },
                nombre: `${archivo}.${formato}`,
            });
            onAvisar(`${final} descargado`);
        } catch (fallo) {
            onAvisar(mensajeDe(fallo, 'No se pudo descargar'), 'error');
        } finally {
            setEnCurso((previos) => previos.filter((f) => f !== formato));
        }
    }

    return (
        <div className={`flex shrink-0 flex-wrap gap-2 ${className}`}>
            <Boton
                type="button"
                variante="secundario"
                disabled={deshabilitado || enCurso.includes('pdf')}
                aria-label={`${nombre} en PDF`}
                onClick={() => bajar('pdf')}
            >
                <FileText className="h-4 w-4" strokeWidth={2} aria-hidden="true" />
                PDF
            </Boton>
            <Boton
                type="button"
                variante="secundario"
                disabled={deshabilitado || enCurso.includes('xlsx')}
                aria-label={`${nombre} en hoja de cálculo`}
                onClick={() => bajar('xlsx')}
            >
                <FileSpreadsheet className="h-4 w-4" strokeWidth={2} aria-hidden="true" />
                <span className="sm:hidden">Hoja</span>
                <span className="hidden sm:inline">Hoja de cálculo</span>
            </Boton>
        </div>
    );
}

BotonesDescarga.propTypes = {
    nombre: PropTypes.string.isRequired,
    ruta: PropTypes.string.isRequired,
    parametros: PropTypes.object,
    archivo: PropTypes.string.isRequired,
    deshabilitado: PropTypes.bool,
    onAvisar: PropTypes.func.isRequired,
    className: PropTypes.string,
};
