import PropTypes from 'prop-types';
import { useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import EstadoCarga from '../../componentes/EstadoCarga';
import { AreaTexto } from '../../componentes/Seleccion';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const ICONO = `flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 ${FOCO}`;
export const TEXTO_LIBRE_MAXIMO = 2000;
const PLANTILLA_MAXIMO = 300;

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

// Las normas de un examen: plantillas predefinidas y propias que se marcan,
// más un texto libre. Las plantillas propias se crean, editan y quitan aquí
// (GET/POST/PUT/DELETE /normas/plantillas). `sueltas` son las normas que el
// examen conserva de una plantilla que ya no existe: se pueden desmarcar.
// `guardadas` es el texto con que el examen guardó cada plantilla marcada.
export default function NormasDelExamen({
    marcadas,
    onMarcadas,
    texto,
    onTexto,
    sueltas = [],
    conservadas = [],
    onConservadas,
    guardadas = {},
    error,
    errorMarcadas,
    onAviso,
}) {
    const { datos, cargando, error: errorCarga, recargar } = usarConsulta('/normas/plantillas');
    // `{ id, texto }`: con `id` nulo es una plantilla nueva.
    const [edicion, setEdicion] = useState(null);
    const [errorTexto, setErrorTexto] = useState(null);
    const [porQuitar, setPorQuitar] = useState(null);
    const [rechazo, setRechazo] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const plantillas = datos ?? [];
    const alternar = (lista, id) =>
        lista.includes(id) ? lista.filter((v) => v !== id) : [...lista, id];

    const abrir = (plantilla) => {
        setEdicion(plantilla);
        setErrorTexto(null);
        setRechazo(null);
    };

    const guardar = async () => {
        const limpio = edicion.texto.trim();
        if (!limpio) {
            setErrorTexto('Obligatorio');
            return;
        }
        setEnviando(true);
        setErrorTexto(null);
        setRechazo(null);
        try {
            const { data } = edicion.id
                ? await api.put(`/normas/plantillas/${edicion.id}`, { texto: limpio })
                : await api.post('/normas/plantillas', { texto: limpio });
            const nueva = data?.data?.id;
            if (!edicion.id && nueva && !marcadas.includes(nueva)) {
                onMarcadas([...marcadas, nueva]);
            }
            setEdicion(null);
            onAviso?.(sinPunto(data?.message ?? 'Plantilla guardada'));
            await recargar();
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (campos.texto) setErrorTexto(campos.texto);
            else setRechazo(mensajeDe(fallo, 'No se pudo guardar'));
        } finally {
            setEnviando(false);
        }
    };

    const quitar = async () => {
        setEnviando(true);
        setRechazo(null);
        try {
            await api.delete(`/normas/plantillas/${porQuitar.id}`);
            if (marcadas.includes(porQuitar.id)) {
                onMarcadas(marcadas.filter((id) => id !== porQuitar.id));
            }
            setPorQuitar(null);
            onAviso?.('Plantilla quitada');
            await recargar();
        } catch (fallo) {
            setRechazo(mensajeDe(fallo, 'No se pudo quitar'));
        } finally {
            setEnviando(false);
        }
    };

    return (
        <fieldset className="min-w-0">
            <div className="mb-1.5 flex min-h-9 flex-wrap items-center justify-between gap-x-3">
                <legend className="float-left text-sm font-medium text-slate-700">Normas</legend>
                <Boton
                    type="button"
                    variante="enlace"
                    tamano="chico"
                    className="-mr-2"
                    onClick={() => abrir({ id: null, texto: '' })}
                >
                    <Plus className="h-4 w-4" /> Nueva plantilla
                </Boton>
            </div>

            <div className="rounded-lg border border-slate-200">
                <EstadoCarga
                    cargando={cargando}
                    error={errorCarga}
                    vacio={plantillas.length === 0 && sueltas.length === 0}
                    textoVacio="Sin plantillas"
                    onReintentar={recargar}
                    filas={3}
                >
                    <ul className="divide-y divide-slate-200" aria-label="Normas del examen">
                        {plantillas.map((p) => {
                            const marcada = marcadas.includes(p.id);
                            const guardada = guardadas[p.id];
                            return (
                                <li key={p.id} className="flex min-w-0 items-center gap-1 pr-1">
                                    <label className="flex min-h-11 min-w-0 flex-1 cursor-pointer items-start gap-3 px-4 py-2.5">
                                        <input
                                            type="checkbox"
                                            className="mt-0.5 h-4 w-4 shrink-0"
                                            checked={marcada}
                                            onChange={() => onMarcadas(alternar(marcadas, p.id))}
                                        />
                                        <span className="min-w-0 flex-1 break-words text-sm text-slate-800">
                                            {p.texto}
                                            {marcada && guardada && guardada !== p.texto && (
                                                <span className="block text-xs text-slate-600">
                                                    En el examen: {guardada}
                                                </span>
                                            )}
                                        </span>
                                        {p.propia && (
                                            <span className="shrink-0 text-xs font-medium text-primary-700">
                                                Propia
                                            </span>
                                        )}
                                    </label>
                                    {p.propia && (
                                        <>
                                            <button
                                                type="button"
                                                aria-label={`Editar la plantilla ${p.texto}`}
                                                title="Editar"
                                                onClick={() => abrir({ id: p.id, texto: p.texto })}
                                                className={ICONO}
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                aria-label={`Quitar la plantilla ${p.texto}`}
                                                title="Quitar"
                                                onClick={() => {
                                                    setPorQuitar(p);
                                                    setRechazo(null);
                                                }}
                                                className={ICONO}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </>
                                    )}
                                </li>
                            );
                        })}
                        {sueltas.map((n) => (
                            <li key={`suelta-${n.id}`} className="min-w-0">
                                <label className="flex min-h-11 min-w-0 cursor-pointer items-start gap-3 px-4 py-2.5">
                                    <input
                                        type="checkbox"
                                        className="mt-0.5 h-4 w-4 shrink-0"
                                        checked={conservadas.includes(n.id)}
                                        onChange={() =>
                                            onConservadas?.(alternar(conservadas, n.id))
                                        }
                                    />
                                    <span className="min-w-0 flex-1 break-words text-sm text-slate-800">
                                        {n.texto}
                                    </span>
                                    <span className="shrink-0 text-xs font-medium text-slate-600">
                                        Sin plantilla
                                    </span>
                                </label>
                            </li>
                        ))}
                    </ul>
                </EstadoCarga>
            </div>
            {errorMarcadas && (
                <p role="alert" className="mt-1.5 text-sm text-danger-600">
                    {errorMarcadas}
                </p>
            )}

            <AreaTexto
                className="mt-3"
                aria-label="Otras normas"
                rows={3}
                maxLength={TEXTO_LIBRE_MAXIMO}
                placeholder="Otras normas"
                value={texto}
                onChange={(e) => onTexto(e.target.value)}
                error={error}
                pie={`${texto.length}/${TEXTO_LIBRE_MAXIMO}`}
            />

            <Dialogo
                titulo={edicion?.id ? 'Editar plantilla' : 'Nueva plantilla'}
                abierto={edicion !== null}
                onCerrar={() => setEdicion(null)}
                acciones={
                    <>
                        <Boton type="button" variante="secundario" onClick={() => setEdicion(null)}>
                            Cancelar
                        </Boton>
                        <Boton type="button" disabled={enviando} onClick={guardar}>
                            Guardar
                        </Boton>
                    </>
                }
            >
                {edicion && (
                    <>
                        <AreaTexto
                            etiqueta="Texto"
                            requerido
                            rows={3}
                            maxLength={PLANTILLA_MAXIMO}
                            value={edicion.texto}
                            onChange={(e) => {
                                setEdicion({ ...edicion, texto: e.target.value });
                                setErrorTexto(null);
                            }}
                            error={errorTexto ?? undefined}
                            pie={`${edicion.texto.length}/${PLANTILLA_MAXIMO}`}
                        />
                        {rechazo && (
                            <p role="alert" className="mt-2 text-sm text-danger-600">
                                {rechazo}
                            </p>
                        )}
                    </>
                )}
            </Dialogo>

            <Dialogo
                titulo="Quitar plantilla"
                abierto={porQuitar !== null}
                onCerrar={() => setPorQuitar(null)}
                acciones={
                    <>
                        <Boton
                            type="button"
                            variante="secundario"
                            onClick={() => setPorQuitar(null)}
                        >
                            Cancelar
                        </Boton>
                        <Boton
                            type="button"
                            variante="peligro"
                            disabled={enviando}
                            onClick={quitar}
                        >
                            Quitar
                        </Boton>
                    </>
                }
            >
                <p className="break-words text-sm text-slate-800">{porQuitar?.texto}</p>
                {rechazo && (
                    <p role="alert" className="mt-2 text-sm text-danger-600">
                        {rechazo}
                    </p>
                )}
            </Dialogo>
        </fieldset>
    );
}

NormasDelExamen.propTypes = {
    marcadas: PropTypes.arrayOf(PropTypes.number).isRequired,
    onMarcadas: PropTypes.func.isRequired,
    texto: PropTypes.string.isRequired,
    onTexto: PropTypes.func.isRequired,
    sueltas: PropTypes.arrayOf(
        PropTypes.shape({ id: PropTypes.number.isRequired, texto: PropTypes.string.isRequired })
    ),
    conservadas: PropTypes.arrayOf(PropTypes.number),
    onConservadas: PropTypes.func,
    guardadas: PropTypes.objectOf(PropTypes.string),
    error: PropTypes.string,
    errorMarcadas: PropTypes.string,
    onAviso: PropTypes.func,
};
