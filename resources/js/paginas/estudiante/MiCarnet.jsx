import { useState } from 'react';
import PropTypes from 'prop-types';
import { AlertTriangle, IdCard, Save } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Insignia from '../../componentes/Insignia';
import Tarjeta from '../../componentes/Tarjeta';

// El carnet lo escribe el estudiante, una sola vez: es obligatorio para que
// lo habiliten en un examen y despues no se modifica.
export default function MiCarnet({ conCarnet, alGuardar }) {
    const [documento, setDocumento] = useState('');
    const [confirmo, setConfirmo] = useState(false);
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);

    if (conCarnet) {
        return (
            <Tarjeta titulo="Carnet" icono={IdCard} className="mb-4">
                <Insignia tono="exito" punto>
                    Registrado
                </Insignia>
            </Tarjeta>
        );
    }

    async function guardar(e) {
        e.preventDefault();
        if (documento.trim() === '') return setErrores({ documento: 'Obligatorio' });
        if (!confirmo) return setErrores({ confirmo: 'Confirme que revisó su carnet' });
        if (enviando) return undefined;
        setEnviando(true);
        setErrores({});
        setGeneral(null);
        try {
            await api.post('/estudiante/carnet', { documento: documento.trim(), confirmo: true });
            await alGuardar();
        } catch (error) {
            if (estadoDe(error) === 422) setErrores(erroresDe(error));
            else setGeneral(mensajeDe(error, 'No se pudo guardar'));
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    return (
        <Tarjeta titulo="Carnet" icono={IdCard} className="mb-4">
            <form className="space-y-4" noValidate onSubmit={guardar}>
                <p
                    role="alert"
                    className="flex items-start gap-2 rounded-lg bg-warning-50 px-3 py-2 text-sm font-medium text-warning-800"
                >
                    <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                    Obligatorio para dar un examen · después no se puede cambiar
                </p>
                {general && (
                    <p
                        role="alert"
                        className="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700"
                    >
                        {general}
                    </p>
                )}
                <Campo
                    etiqueta="Carnet"
                    iconoEtiqueta={IdCard}
                    placeholder="5003748"
                    value={documento}
                    error={errores.documento}
                    onChange={(e) => {
                        setDocumento(e.target.value);
                        setErrores({});
                    }}
                />
                <label className="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-slate-800">
                    <input
                        type="checkbox"
                        className="h-4 w-4 rounded border-slate-300"
                        checked={confirmo}
                        onChange={(e) => {
                            setConfirmo(e.target.checked);
                            setErrores({});
                        }}
                    />
                    Revisé que mi carnet es correcto
                </label>
                {errores.confirmo && (
                    <p role="alert" className="-mt-2 text-sm text-danger-700">
                        {errores.confirmo}
                    </p>
                )}
                <Boton type="submit" className="w-full" cargando={enviando}>
                    {!enviando && <Save className="h-4 w-4" aria-hidden="true" />}
                    Guardar carnet
                </Boton>
            </form>
        </Tarjeta>
    );
}

MiCarnet.propTypes = {
    conCarnet: PropTypes.bool.isRequired,
    alGuardar: PropTypes.func.isRequired,
};
