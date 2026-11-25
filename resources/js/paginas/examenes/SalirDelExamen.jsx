import PropTypes from 'prop-types';
import { useState } from 'react';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import { fechaLarga } from '../../utiles/texto';
import { FORMA_EXAMEN, tipoDe } from './avanceDelExamen';

// Confirmación para que un docente colaborador saque sus grupos de un examen
// que registró otro. `examen` nulo la cierra; `onSalio` corre tras salir.
export default function SalirDelExamen({ examen, onCerrar, onSalio }) {
    const [rechazo, setRechazo] = useState(null);
    const [saliendo, setSaliendo] = useState(false);

    const salir = async () => {
        setSaliendo(true);
        setRechazo(null);
        try {
            await api.post(`/examenes/${examen.id}/salir`);
            onSalio();
        } catch (fallo) {
            setRechazo(mensajeDe(fallo, 'No se pudo salir del examen'));
        } finally {
            setSaliendo(false);
        }
    };

    const cerrar = () => {
        setRechazo(null);
        onCerrar();
    };

    return (
        <Dialogo
            titulo="Salir del examen"
            abierto={examen !== null}
            onCerrar={cerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={cerrar}>
                        Cancelar
                    </Boton>
                    <Boton type="button" variante="peligro" cargando={saliendo} onClick={salir}>
                        Salir
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
                        {fechaLarga(examen.fecha)}, {examen.hora}
                    </p>
                    <p className="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-sm font-medium text-warning-800">
                        Sus grupos dejan este examen y sus estudiantes ya no se habilitan en él
                    </p>
                </>
            )}
            {rechazo && (
                <p
                    role="alert"
                    className="mt-3 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700"
                >
                    {rechazo}
                </p>
            )}
        </Dialogo>
    );
}

SalirDelExamen.propTypes = {
    examen: FORMA_EXAMEN,
    onCerrar: PropTypes.func.isRequired,
    onSalio: PropTypes.func.isRequired,
};
