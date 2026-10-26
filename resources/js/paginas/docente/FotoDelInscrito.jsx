import PropTypes from 'prop-types';
import { useState } from 'react';
import { Camera } from 'lucide-react';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import { fechaCorta } from '../../utiles/texto';

// Lo mismo que aplica el servidor: un cambio cada 12 meses, habilitado 7 días.
const MESES_DE_BLOQUEO = 12;
const DIAS_HABILITADO = 7;

const hoyLocal = () => new Date().toLocaleDateString('en-CA');
const sumarMeses = (fecha, meses) => {
    const [a, m, d] = fecha.split('-').map(Number);
    const total = a * 12 + (m - 1) + meses;
    return `${Math.floor(total / 12)}-${String((total % 12) + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
};

// La foto del inscrito vista por su docente: si la tiene, desde cuándo, y el
// botón para habilitarle subirla o cambiarla. La foto la sube el estudiante.
export function BotonFoto({ estudiante, onAbrir }) {
    const conFoto = Boolean(estudiante.foto?.subida_en);
    return (
        <button
            type="button"
            aria-label={`Foto de ${estudiante.nombre}`}
            title={conFoto ? 'Con foto' : 'Sin foto'}
            onClick={() => onAbrir(estudiante)}
            className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600 ${conFoto ? 'text-success-700' : 'text-slate-400'}`}
        >
            <Camera className="h-4 w-4" />
        </button>
    );
}

BotonFoto.propTypes = {
    estudiante: PropTypes.shape({
        nombre: PropTypes.string.isRequired,
        foto: PropTypes.shape({ subida_en: PropTypes.string }),
    }).isRequired,
    onAbrir: PropTypes.func.isRequired,
};

export default function FotoDelInscrito({ grupoId, estudiante, onHabilitada, onCerrar }) {
    const [enviando, setEnviando] = useState(false);
    const [rechazo, setRechazo] = useState(null);
    const hoy = hoyLocal();
    const { subida_en: subida = null, habilitada_hasta: hasta = null } = estudiante.foto ?? {};
    const vigente = hasta && hasta >= hoy;
    const desde = subida ? sumarMeses(subida, MESES_DE_BLOQUEO) : null;
    const bloqueada = !vigente && desde && desde > hoy;

    const habilitar = async () => {
        setEnviando(true);
        setRechazo(null);
        try {
            const { data } = await api.post(
                `/docente/grupos/${grupoId}/inscritos/${estudiante.id}/foto`
            );
            onHabilitada(`Foto habilitada hasta el ${fechaCorta(data.hasta)}`);
        } catch (fallo) {
            setRechazo(mensajeDe(fallo, 'No se pudo habilitar').replace(/\.$/, ''));
        } finally {
            setEnviando(false);
        }
    };

    return (
        <Dialogo
            titulo="Foto del estudiante"
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cerrar
                    </Boton>
                    {!vigente && !bloqueada && (
                        <Boton type="button" disabled={enviando} onClick={habilitar}>
                            Habilitar {DIAS_HABILITADO} días
                        </Boton>
                    )}
                </>
            }
        >
            <div className="space-y-3 text-sm">
                <p className="font-semibold text-slate-900">{estudiante.nombre}</p>
                <p className="text-slate-700">
                    {subida ? `Foto del ${fechaCorta(subida)}` : 'Sin foto'}
                </p>
                {vigente && (
                    <p className="rounded-lg bg-primary-50 px-3 py-2 text-primary-800">
                        Habilitado hasta el {fechaCorta(hasta)}
                    </p>
                )}
                {bloqueada && (
                    <p className="rounded-lg bg-slate-100 px-3 py-2 text-slate-700">
                        Cambio habilitable desde el {fechaCorta(desde)}
                    </p>
                )}
                {!vigente && !bloqueada && (
                    <p className="text-slate-600">
                        {subida ? 'Podrá cambiarla' : 'Podrá subirla'} desde su acceso durante{' '}
                        {DIAS_HABILITADO} días.
                    </p>
                )}
                {rechazo && (
                    <p role="alert" className="text-danger-600">
                        {rechazo}
                    </p>
                )}
            </div>
        </Dialogo>
    );
}

FotoDelInscrito.propTypes = {
    grupoId: PropTypes.number.isRequired,
    estudiante: PropTypes.shape({
        id: PropTypes.number.isRequired,
        nombre: PropTypes.string.isRequired,
        foto: PropTypes.shape({
            subida_en: PropTypes.string,
            habilitada_hasta: PropTypes.string,
        }),
    }).isRequired,
    onHabilitada: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};
