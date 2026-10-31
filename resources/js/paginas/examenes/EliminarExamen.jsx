import PropTypes from 'prop-types';
import { useState } from 'react';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import { fechaLarga, plural } from '../../utiles/texto';
import { FORMA_EXAMEN, tipoDe } from './avanceDelExamen';

// Confirmación para eliminar un examen, con lo que se pierde. `examen` nulo
// la cierra; `onEliminado` corre tras borrarlo en el servidor.
export default function EliminarExamen({ examen, onCerrar, onEliminado }) {
    const [rechazo, setRechazo] = useState(null);
    const [eliminando, setEliminando] = useState(false);

    const eliminar = async () => {
        setEliminando(true);
        setRechazo(null);
        try {
            await api.delete(`/examenes/${examen.id}`);
            onEliminado();
        } catch (fallo) {
            setRechazo(mensajeDe(fallo, 'No se pudo eliminar'));
        } finally {
            setEliminando(false);
        }
    };

    const cerrar = () => {
        setRechazo(null);
        onCerrar();
    };

    const habilitaciones = (examen?.habilitados ?? 0) + (examen?.no_habilitados ?? 0);
    const perdidas = examen
        ? [
              examen.aulas.length > 0 && plural(examen.aulas.length, 'aula'),
              habilitaciones > 0 && plural(habilitaciones, 'habilitación', 'habilitaciones'),
              examen.qr_emitidos > 0 && plural(examen.qr_emitidos, 'código QR', 'códigos QR'),
              examen.codigos_aula > 0 &&
                  plural(examen.codigos_aula, 'código de aula', 'códigos de aula'),
          ].filter(Boolean)
        : [];

    return (
        <Dialogo
            titulo="Eliminar examen"
            abierto={examen !== null}
            onCerrar={cerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={cerrar}>
                        Cancelar
                    </Boton>
                    <Boton
                        type="button"
                        variante="peligro"
                        cargando={eliminando}
                        onClick={eliminar}
                    >
                        Eliminar
                    </Boton>
                </>
            }
        >
            {examen && (
                <>
                    <p className="break-words text-sm font-semibold text-slate-900">
                        {tipoDe(examen)} · {examen.asignatura.nombre}
                    </p>
                    <p className="text-sm text-slate-600">
                        {fechaLarga(examen.fecha)}, {examen.hora} ·{' '}
                        {plural(examen.grupos.length, 'grupo')}
                    </p>
                    {perdidas.length > 0 && (
                        <p className="mt-3 rounded-lg bg-danger-50 px-3 py-2 text-sm font-medium text-danger-700">
                            Se pierden: {perdidas.join(' · ')}
                        </p>
                    )}
                </>
            )}
            {rechazo && (
                <p role="alert" className="mt-2 text-sm text-danger-600">
                    {rechazo}
                </p>
            )}
        </Dialogo>
    );
}

EliminarExamen.propTypes = {
    examen: FORMA_EXAMEN,
    onCerrar: PropTypes.func.isRequired,
    onEliminado: PropTypes.func.isRequired,
};
