import { useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { Camera, CheckCircle2, IdCard, XCircle } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';

// Veredictos que asientan una incidencia a la que se le puede sumar la foto
// de quien intenta entrar sin corresponderle.
const CON_FOTO = ['no_habilitado', 'ya_ingreso', 'no_corresponde'];
// Veredictos que identifican a un estudiante de un examen del aula.
const CON_CARNET = ['admitido', 'admitido_excepcion', 'no_habilitado', 'ya_ingreso', 'otra_aula'];

// La foto de quien intenta entrar sin corresponderle, como evidencia de su
// incidencia (hasta tres).
function FotoDelIntruso({ aulaId, incidenciaId }) {
    const entrada = useRef(null);
    const [fotos, setFotos] = useState(0);
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);

    async function subir(e) {
        const archivo = e.target.files?.[0];
        e.target.value = '';
        if (!archivo || enviando) return;
        setEnviando(true);
        setError(null);
        try {
            const formulario = new FormData();
            formulario.append('foto', archivo);
            const { data } = await api.post(
                `/puerta/aulas/${aulaId}/incidencias/${incidenciaId}/foto`,
                formulario
            );
            setFotos(data?.evidencias ?? fotos + 1);
        } catch (fallo) {
            setError(erroresDe(fallo).foto ?? mensajeDe(fallo, 'No se pudo guardar la foto'));
        } finally {
            setEnviando(false);
        }
    }

    return (
        <div className="mt-3">
            <input
                ref={entrada}
                type="file"
                accept="image/*"
                capture="environment"
                className="sr-only"
                aria-label="Foto del intruso"
                tabIndex={-1}
                onChange={subir}
            />
            <Boton
                type="button"
                variante="secundario"
                className="min-h-12 w-full"
                cargando={enviando}
                disabled={fotos >= 3}
                onClick={() => entrada.current?.click()}
            >
                {!enviando && <Camera className="h-4 w-4" aria-hidden="true" />} Foto del intruso
                {fotos > 0 && ` · ${fotos}/3`}
            </Boton>
            {error && (
                <p role="alert" className="mt-1 text-sm text-danger-700">
                    {error}
                </p>
            )}
        </div>
    );
}

FotoDelIntruso.propTypes = {
    aulaId: PropTypes.number.isRequired,
    incidenciaId: PropTypes.number.isRequired,
};

// Lo que quien controla puede hacer despues de identificar a alguien:
// pedirle el carnet y compararlo con el que escribio el estudiante, y
// fotografiar a quien intenta entrar sin corresponderle.
export default function ControlDelIdentificado({ aulaId, veredicto }) {
    const { estudiante, examen } = veredicto;
    const clave = veredicto.veredicto;
    const [pidiendo, setPidiendo] = useState(false);
    const [documento, setDocumento] = useState('');
    const [error, setError] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [resultado, setResultado] = useState(null);

    const incidencia = resultado?.incidencia?.id ?? veredicto.incidencia?.id ?? null;
    const puedeFoto =
        incidencia !== null && (resultado?.coincide === false || CON_FOTO.includes(clave));
    const puedeCarnet = Boolean(estudiante?.id && examen?.id) && CON_CARNET.includes(clave);

    async function comparar(e) {
        e.preventDefault();
        if (documento.trim() === '') return setError('Escriba el carnet');
        if (documento.trim().length < 4) return setError('Solo números, de 4 a 10 dígitos');
        if (enviando) return undefined;
        setEnviando(true);
        setError(null);
        try {
            const { data } = await api.post(`/puerta/aulas/${aulaId}/carnet`, {
                estudiante_id: estudiante.id,
                examen_id: examen.id,
                documento: documento.trim(),
            });
            setResultado(data);
            setPidiendo(false);
            setDocumento('');
        } catch (fallo) {
            setError(erroresDe(fallo).documento ?? mensajeDe(fallo, 'No se pudo comparar'));
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    if (!puedeCarnet && !puedeFoto) return null;

    return (
        <div className="mt-3">
            {resultado?.coincide === true && (
                <p
                    role="status"
                    className="flex items-center gap-2 rounded-lg bg-success-50 px-3 py-2 text-sm font-medium text-success-800"
                >
                    <CheckCircle2 className="h-4 w-4 shrink-0" aria-hidden="true" /> Carnet coincide
                </p>
            )}
            {resultado?.coincide === false && (
                <p
                    role="alert"
                    className="flex items-center gap-2 rounded-lg bg-danger-50 px-3 py-2 text-sm font-medium text-danger-800"
                >
                    <XCircle className="h-4 w-4 shrink-0" aria-hidden="true" /> No coincide ·
                    suplantación registrada
                </p>
            )}
            {resultado?.sin_carnet && (
                <p
                    role="status"
                    className="rounded-lg bg-warning-50 px-3 py-2 text-sm font-medium text-warning-800"
                >
                    Sin carnet registrado · no hay con qué comparar
                </p>
            )}

            {puedeCarnet && !pidiendo && resultado === null && (
                <Boton
                    type="button"
                    variante="secundario"
                    className="min-h-12 w-full"
                    onClick={() => setPidiendo(true)}
                >
                    <IdCard className="h-4 w-4" aria-hidden="true" /> Verificar carnet
                </Boton>
            )}
            {pidiendo && (
                <form
                    className="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4"
                    onSubmit={comparar}
                    noValidate
                >
                    <Campo
                        etiqueta="Carnet que presenta"
                        iconoEtiqueta={IdCard}
                        placeholder="Ej.: 5003748"
                        formato="documento"
                        maxLength={10}
                        enterKeyHint="done"
                        autoFocus
                        value={documento}
                        error={error}
                        onChange={(e) => {
                            setDocumento(e.target.value);
                            setError(null);
                        }}
                    />
                    <div className="flex gap-2">
                        <Boton
                            type="button"
                            variante="secundario"
                            className="min-h-12 flex-1"
                            onClick={() => {
                                setPidiendo(false);
                                setError(null);
                            }}
                        >
                            Cancelar
                        </Boton>
                        <Boton type="submit" className="min-h-12 flex-1" cargando={enviando}>
                            Comparar carnet
                        </Boton>
                    </div>
                </form>
            )}

            {puedeFoto && <FotoDelIntruso aulaId={aulaId} incidenciaId={incidencia} />}
        </div>
    );
}

ControlDelIdentificado.propTypes = {
    aulaId: PropTypes.number.isRequired,
    veredicto: PropTypes.shape({
        veredicto: PropTypes.string.isRequired,
        estudiante: PropTypes.shape({ id: PropTypes.number }),
        examen: PropTypes.shape({ id: PropTypes.number }),
        incidencia: PropTypes.shape({ id: PropTypes.number }),
    }).isRequired,
};
