import { useState } from 'react';
import PropTypes from 'prop-types';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';

// Las fechas en que los estudiantes suben su foto (PUT /api/estudiantes/plazo-fotos).
export default function PlazoFotos({ plazo, onCerrar, onGuardado, avisar }) {
    const [desde, setDesde] = useState(plazo?.desde ?? '');
    const [hasta, setHasta] = useState(plazo?.hasta ?? '');
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);

    async function guardar(e) {
        e?.preventDefault();
        const faltas = {
            desde: desde ? undefined : 'Obligatorio',
            hasta: hasta ? undefined : 'Obligatorio',
        };
        if (faltas.desde || faltas.hasta) return setErrores(faltas);
        if (enviando) return undefined;
        setEnviando(true);
        setErrores({});
        try {
            await api.put('/estudiantes/plazo-fotos', { desde, hasta });
            avisar('Plazo guardado');
            onGuardado();
            onCerrar();
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (Object.keys(campos).length > 0) setErrores(campos);
            else avisar(mensajeDe(fallo, 'No se pudo guardar'), 'error');
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    return (
        <Dialogo
            titulo="Plazo de fotos"
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton onClick={guardar} cargando={enviando}>
                        Guardar
                    </Boton>
                </>
            }
        >
            <form className="grid grid-cols-2 gap-3" onSubmit={guardar} noValidate>
                <Campo
                    etiqueta="Desde"
                    requerido
                    type="date"
                    value={desde}
                    error={errores.desde}
                    onChange={(e) => setDesde(e.target.value)}
                />
                <Campo
                    etiqueta="Hasta"
                    requerido
                    type="date"
                    min={desde || undefined}
                    value={hasta}
                    error={errores.hasta}
                    onChange={(e) => setHasta(e.target.value)}
                />
            </form>
        </Dialogo>
    );
}

PlazoFotos.propTypes = {
    plazo: PropTypes.shape({ desde: PropTypes.string, hasta: PropTypes.string }),
    onCerrar: PropTypes.func.isRequired,
    onGuardado: PropTypes.func.isRequired,
    avisar: PropTypes.func.isRequired,
};
