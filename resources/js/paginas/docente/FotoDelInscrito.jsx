import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { Camera, UserRound } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import { fechaCorta } from '../../utiles/texto';

// Lo mismo que aplica el servidor: un cambio cada 12 meses, habilitado
// hasta el día que elige el docente (una semana si no cambia, un mes como mucho).
const MESES_DE_BLOQUEO = 12;
const DIAS_HABILITADO = 7;
const DIAS_MAXIMOS = 30;

const hoyLocal = () => new Date().toLocaleDateString('en-CA');
const sumarMeses = (fecha, meses) => {
    const [a, m, d] = fecha.split('-').map(Number);
    const total = a * 12 + (m - 1) + meses;
    return `${Math.floor(total / 12)}-${String((total % 12) + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
};
const sumarDias = (fecha, dias) => {
    const [a, m, d] = fecha.split('-').map(Number);
    return new Date(a, m - 1, d + dias).toLocaleDateString('en-CA');
};

// La foto del inscrito vista por su docente: si la tiene, desde cuándo, y
// hasta qué día habilitarle subirla o cambiarla (o cambiar ese día, si ya
// está habilitado). La foto la sube el estudiante.
// En la lista, el estado se lee sin abrirla: con foto, habilitado para
// subirla o sin foto.
export function BotonFoto({ estudiante, onAbrir }) {
    const conFoto = Boolean(estudiante.foto?.subida_en);
    const habilitada = (estudiante.foto?.habilitada_hasta ?? '') >= hoyLocal();
    const [texto, tono] = habilitada
        ? ['Habilitada', 'border-primary-200 bg-primary-50 text-primary-800 hover:bg-primary-100']
        : conFoto
          ? ['Con foto', 'border-success-200 bg-success-50 text-success-800 hover:bg-success-100']
          : ['Sin foto', 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'];
    return (
        <button
            type="button"
            aria-label={`Foto de ${estudiante.nombre}`}
            title="Ver la foto o dar plazo para subirla"
            onClick={() => onAbrir(estudiante)}
            className={`inline-flex min-h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-3 text-xs font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600 ${tono}`}
        >
            <Camera className="h-3.5 w-3.5" aria-hidden="true" /> {texto}
        </button>
    );
}

BotonFoto.propTypes = {
    estudiante: PropTypes.shape({
        nombre: PropTypes.string.isRequired,
        foto: PropTypes.shape({
            subida_en: PropTypes.string,
            habilitada_hasta: PropTypes.string,
        }),
    }).isRequired,
    onAbrir: PropTypes.func.isRequired,
};

// La foto del estudiante, tal como la subió (GET .../foto). Sin foto o si no
// carga, queda la silueta.
function Retrato({ grupoId, estudiante, conFoto }) {
    const [url, setUrl] = useState(null);

    useEffect(() => {
        if (!conFoto) return undefined;
        let vigente = true;
        let creada = null;
        api.get(`/docente/grupos/${grupoId}/inscritos/${estudiante.id}/foto`, {
            responseType: 'blob',
        })
            .then((r) => {
                if (!vigente) return;
                creada = URL.createObjectURL(r.data);
                setUrl(creada);
            })
            .catch(() => {});
        return () => {
            vigente = false;
            if (creada) URL.revokeObjectURL(creada);
        };
    }, [grupoId, estudiante.id, conFoto]);

    return url ? (
        <img
            src={url}
            alt={`Foto de ${estudiante.nombre}`}
            className="h-40 w-32 shrink-0 rounded-lg border border-slate-200 object-cover"
        />
    ) : (
        <span className="flex h-40 w-32 shrink-0 flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-500">
            <UserRound className="h-8 w-8 text-slate-400" strokeWidth={1.5} />
            Sin foto
        </span>
    );
}

Retrato.propTypes = {
    grupoId: PropTypes.number.isRequired,
    estudiante: PropTypes.shape({
        id: PropTypes.number.isRequired,
        nombre: PropTypes.string.isRequired,
    }).isRequired,
    conFoto: PropTypes.bool,
};

export default function FotoDelInscrito({ grupoId, estudiante, onHabilitada, onCerrar }) {
    const hoy = hoyLocal();
    const { subida_en: subida = null, habilitada_hasta: actual = null } = estudiante.foto ?? {};
    const vigente = Boolean(actual && actual >= hoy);
    const desde = subida ? sumarMeses(subida, MESES_DE_BLOQUEO) : null;
    const bloqueada = !vigente && desde && desde > hoy;
    const [hasta, setHasta] = useState(vigente ? actual : sumarDias(hoy, DIAS_HABILITADO));
    const [enviando, setEnviando] = useState(false);
    const [rechazo, setRechazo] = useState(null);
    const [error, setError] = useState(null);

    const habilitar = async (e) => {
        e?.preventDefault();
        if (!hasta) return setError('Obligatorio');
        setEnviando(true);
        setRechazo(null);
        setError(null);
        try {
            const { data } = await api.post(
                `/docente/grupos/${grupoId}/inscritos/${estudiante.id}/foto`,
                { hasta }
            );
            onHabilitada(`Foto habilitada hasta el ${fechaCorta(data.hasta)}`);
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (campos.hasta) setError(campos.hasta);
            else setRechazo(mensajeDe(fallo, 'No se pudo habilitar').replace(/\.$/, ''));
        } finally {
            setEnviando(false);
        }
        return undefined;
    };

    return (
        <Dialogo
            titulo={`Foto de ${estudiante.nombre}`}
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cerrar
                    </Boton>
                    {!bloqueada && (
                        <Boton
                            type="button"
                            cargando={enviando}
                            disabled={vigente && hasta === actual}
                            onClick={habilitar}
                        >
                            {vigente ? 'Cambiar plazo' : 'Dar plazo para subir foto'}
                        </Boton>
                    )}
                </>
            }
        >
            <div className="space-y-3 text-sm">
                <div className="flex items-start gap-4">
                    <Retrato grupoId={grupoId} estudiante={estudiante} conFoto={Boolean(subida)} />
                    <div className="min-w-0 flex-1 space-y-2">
                        <p className="font-semibold text-slate-900">{estudiante.nombre}</p>
                        {subida ? (
                            <p className="rounded-lg bg-success-50 px-3 py-2 text-success-800">
                                Con foto · del {fechaCorta(subida)}
                            </p>
                        ) : (
                            <p className="text-slate-700">Aún no subió foto</p>
                        )}
                        {vigente && (
                            <p className="rounded-lg bg-primary-50 px-3 py-2 text-primary-800">
                                Habilitado hasta el {fechaCorta(actual)}
                            </p>
                        )}
                        {bloqueada && (
                            <p className="rounded-lg bg-slate-100 px-3 py-2 text-slate-700">
                                Cambio habilitable desde el {fechaCorta(desde)}
                            </p>
                        )}
                    </div>
                </div>
                {!bloqueada && (
                    <form onSubmit={habilitar} noValidate>
                        <Campo
                            etiqueta={subida ? 'Plazo para cambiarla' : 'Plazo para subirla'}
                            requerido
                            type="date"
                            min={hoy}
                            max={sumarDias(hoy, DIAS_MAXIMOS)}
                            value={hasta}
                            error={error}
                            onChange={(e) => setHasta(e.target.value)}
                        />
                    </form>
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
