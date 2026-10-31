import PropTypes from 'prop-types';
import { useState } from 'react';
import { Camera } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import { fechaCorta } from '../../utiles/texto';

// Lo mismo que valida el servidor: desde hoy hasta un semestre.
const DIAS_MAXIMOS = 180;

const hoyLocal = () => new Date().toLocaleDateString('en-CA');
const sumarDias = (fecha, dias) => {
    const [a, m, d] = fecha.split('-').map(Number);
    return new Date(a, m - 1, d + dias).toLocaleDateString('en-CA');
};

// Hasta qué día suben su foto los inscritos del grupo
// (GET y PUT /docente/grupos/{id}/plazo-foto). Vencido, quien no la subió
// queda inhabilitado en los exámenes del grupo hasta que la suba.
export default function PlazoFotosDelGrupo({ grupoId, onAvisar }) {
    const { datos: plazo, recargar } = usarConsulta(`/docente/grupos/${grupoId}/plazo-foto`);
    const [abierto, setAbierto] = useState(false);

    if (!plazo) return null;

    const faltan = plazo.sin_foto > 0 ? ` · ${plazo.sin_foto} sin foto` : '';
    const [texto, tono] = !plazo.hasta
        ? [`Plazo de fotos${faltan}`, 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50']
        : plazo.vencido
          ? [
                `Fotos vencidas${faltan}`,
                'border-danger-200 bg-danger-50 text-danger-800 hover:bg-danger-100',
            ]
          : [
                `Fotos hasta el ${fechaCorta(plazo.hasta)}${faltan}`,
                'border-primary-200 bg-primary-50 text-primary-800 hover:bg-primary-100',
            ];

    return (
        <>
            <button
                type="button"
                onClick={() => setAbierto(true)}
                className={`inline-flex min-h-10 items-center gap-1.5 whitespace-nowrap rounded-lg border px-3 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600 ${tono}`}
            >
                <Camera className="h-4 w-4 shrink-0" aria-hidden="true" /> {texto}
            </button>
            {abierto && (
                <DialogoPlazo
                    grupoId={grupoId}
                    plazo={plazo}
                    onCerrar={() => setAbierto(false)}
                    onGuardado={(aviso) => {
                        setAbierto(false);
                        onAvisar(aviso);
                        recargar();
                    }}
                />
            )}
        </>
    );
}

PlazoFotosDelGrupo.propTypes = {
    grupoId: PropTypes.number.isRequired,
    onAvisar: PropTypes.func.isRequired,
};

function DialogoPlazo({ grupoId, plazo, onCerrar, onGuardado }) {
    const hoy = hoyLocal();
    const [hasta, setHasta] = useState(
        plazo.hasta && !plazo.vencido ? plazo.hasta : sumarDias(hoy, 14)
    );
    const [error, setError] = useState(null);
    const [rechazo, setRechazo] = useState(null);
    const [enviando, setEnviando] = useState(null);

    async function guardar(valor) {
        if (valor !== null && !valor) return setError('Obligatorio');
        setEnviando(valor === null ? 'quitar' : 'guardar');
        setError(null);
        setRechazo(null);
        try {
            const { data } = await api.put(`/docente/grupos/${grupoId}/plazo-foto`, {
                hasta: valor,
            });
            onGuardado(
                data.data.hasta
                    ? `Fotos hasta el ${fechaCorta(data.data.hasta)}`
                    : 'Plazo de fotos quitado'
            );
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (campos.hasta) setError(campos.hasta);
            else setRechazo(mensajeDe(fallo, 'No se pudo guardar').replace(/\.$/, ''));
        } finally {
            setEnviando(null);
        }
        return undefined;
    }

    return (
        <Dialogo
            titulo="Plazo de fotos del grupo"
            onCerrar={onCerrar}
            acciones={
                <>
                    {plazo.hasta && (
                        <Boton
                            type="button"
                            variante="secundario"
                            className="sm:mr-auto"
                            cargando={enviando === 'quitar'}
                            onClick={() => guardar(null)}
                        >
                            Quitar plazo
                        </Boton>
                    )}
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton
                        type="button"
                        cargando={enviando === 'guardar'}
                        onClick={() => guardar(hasta)}
                    >
                        Guardar
                    </Boton>
                </>
            }
        >
            <form
                className="space-y-3 text-sm"
                onSubmit={(e) => {
                    e.preventDefault();
                    guardar(hasta);
                }}
                noValidate
            >
                <p className="text-slate-700">
                    {plazo.sin_foto} de {plazo.inscritos} sin foto
                </p>
                {plazo.vencido && (
                    <p className="rounded-lg bg-danger-50 px-3 py-2 text-danger-800">
                        Venció el {fechaCorta(plazo.hasta)}
                        {plazo.fijado_por && ` · ${plazo.fijado_por}`}
                    </p>
                )}
                <Campo
                    etiqueta="Suben su foto hasta"
                    requerido
                    type="date"
                    min={hoy}
                    max={sumarDias(hoy, DIAS_MAXIMOS)}
                    value={hasta}
                    error={error}
                    onChange={(e) => setHasta(e.target.value)}
                />
                <p className="text-xs text-slate-600">
                    Vencido: sin foto, no habilitado en los exámenes del grupo
                </p>
                {rechazo && (
                    <p role="alert" className="text-danger-600">
                        {rechazo}
                    </p>
                )}
            </form>
        </Dialogo>
    );
}

DialogoPlazo.propTypes = {
    grupoId: PropTypes.number.isRequired,
    plazo: PropTypes.shape({
        hasta: PropTypes.string,
        fijado_por: PropTypes.string,
        inscritos: PropTypes.number.isRequired,
        sin_foto: PropTypes.number.isRequired,
        vencido: PropTypes.bool.isRequired,
    }).isRequired,
    onCerrar: PropTypes.func.isRequired,
    onGuardado: PropTypes.func.isRequired,
};
