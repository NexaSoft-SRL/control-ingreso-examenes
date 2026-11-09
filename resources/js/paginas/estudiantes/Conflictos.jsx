import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { api } from '../../api/cliente';
import { codigoDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import Boton from '../../componentes/Boton';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Tarjeta from '../../componentes/Tarjeta';

const POR_PAGINA = 5;

// Lo que reescribe «Usar carga nueva»: cada dato que cambia, de antes a después.
const cambiosDe = (c) =>
    [
        ['C.I.', c.guardado?.documento, c.nuevo?.documento],
        ['Nombre', c.guardado?.nombre, c.nuevo?.nombre],
    ]
        .filter(([, antes, despues]) => (antes ?? '') !== (despues ?? ''))
        .map(([dato, antes, despues]) => `${dato} ${antes ?? '—'} → ${despues ?? '—'}`);

const MARCA = 'rounded bg-warning-100 px-1 text-inherit';
const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

// Marca lo que cambia entre dos valores: si uno continúa al otro, solo el
// agregado; si no, el valor entero.
export function Diferencia({ valor, otro }) {
    const propio = String(valor ?? '');
    const ajeno = String(otro ?? '');
    if (propio === ajeno) return propio;
    if (ajeno !== '' && propio.startsWith(ajeno)) {
        return (
            <>
                {ajeno} <mark className={MARCA}>{propio.slice(ajeno.length).trim()}</mark>
            </>
        );
    }
    if (propio !== '' && ajeno.startsWith(propio)) return propio;
    return <mark className={MARCA}>{propio}</mark>;
}

Diferencia.propTypes = { valor: PropTypes.string, otro: PropTypes.string };

// Conflictos pendientes del padrón, de a cinco: lo guardado frente a lo que
// trajo la carga, y las dos salidas.
export default function Conflictos({ onCambio, avisar }) {
    const lista = usarPaginaServidor({ porPagina: POR_PAGINA });
    const { datos, meta, cargando, error, recargar } = usarConsulta('/estudiantes/conflictos', {
        parametros: lista.parametros,
    });
    const [enviando, setEnviando] = useState(null);
    const [rechazos, setRechazos] = useState({});
    // «Usar carga nueva» reescribe nombre y documento del padrón: se confirma.
    const [confirmando, setConfirmando] = useState(null);

    // Si al resolver la página a la vista queda vacía, se pide la última.
    const total = meta?.total ?? 0;
    const { irA, pagina } = lista;
    useEffect(() => {
        if (datos && datos.length === 0 && total > 0 && pagina > 1) {
            irA(Math.ceil(total / POR_PAGINA));
        }
    }, [datos, total, pagina, irA]);

    function resolver(conflicto, resolucion) {
        if (enviando !== null) return;
        setConfirmando(null);
        setEnviando(conflicto.id);
        setRechazos((previos) => ({ ...previos, [conflicto.id]: undefined }));
        api.post(`/estudiantes/conflictos/${conflicto.id}/resolucion`, { resolucion })
            .then((respuesta) => {
                avisar(
                    sinPunto(respuesta.data?.message) ||
                        `${conflicto.codigo} ${resolucion === 'USAR_CARGA' ? 'actualizado' : 'sin cambios'}`
                );
                recargar();
                onCambio();
            })
            .catch((rechazo) => {
                const estado = estadoDe(rechazo);
                if (estado === 409 && codigoDe(rechazo) === 'DOCUMENTO_EN_USO') {
                    setRechazos((previos) => ({
                        ...previos,
                        [conflicto.id]: sinPunto(mensajeDe(rechazo, 'Documento en uso')),
                    }));
                    return;
                }
                avisar(sinPunto(mensajeDe(rechazo, 'No se pudo resolver')), 'error');
                if (estado === 409 || estado === 404) {
                    recargar();
                    onCambio();
                }
            })
            .finally(() => setEnviando(null));
    }

    return (
        <div className="space-y-4">
            <EstadoCarga
                cargando={cargando}
                error={error}
                vacio={datos?.length === 0 && total === 0}
                textoVacio="Sin conflictos"
                onReintentar={recargar}
                filas={4}
                className="rounded-xl border border-slate-200 bg-white"
            >
                {(datos ?? []).map((c) => (
                    <Tarjeta
                        key={c.id}
                        titulo={`Código SIS ${c.codigo}`}
                        acciones={<Insignia tono="advertencia">{c.tipo}</Insignia>}
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            {[
                                ['Padrón', c.guardado, c.nuevo],
                                ['Carga nueva', c.nuevo, c.guardado],
                            ].map(([titulo, d, otro]) => (
                                <div
                                    key={titulo}
                                    className="min-w-0 rounded-lg border border-slate-200 p-3 text-sm"
                                >
                                    <p className="text-xs font-medium uppercase tracking-wide text-slate-600">
                                        {titulo}
                                    </p>
                                    <p className="mt-1 break-words font-medium text-slate-800">
                                        <Diferencia valor={d?.nombre} otro={otro?.nombre} />
                                    </p>
                                    <p className="text-slate-700">
                                        C.I.{' '}
                                        <Diferencia valor={d?.documento} otro={otro?.documento} />
                                    </p>
                                    <p
                                        className="mt-1 truncate text-xs text-slate-600"
                                        title={d?.por}
                                    >
                                        {d?.por}
                                    </p>
                                </div>
                            ))}
                        </div>
                        {rechazos[c.id] && (
                            <p role="alert" className="mt-3 text-sm text-danger-600">
                                {rechazos[c.id]}
                            </p>
                        )}
                        <div className="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-4">
                            {c.inscripcion_en_espera && (
                                <span className="mr-auto">
                                    <Insignia tono="neutro" punto>
                                        Inscripción en espera
                                    </Insignia>
                                </span>
                            )}
                            {confirmando === c.id ? (
                                <>
                                    <span className="mr-auto min-w-0 break-words text-sm font-medium text-warning-800">
                                        {cambiosDe(c).join(' · ')}
                                    </span>
                                    <Boton
                                        variante="secundario"
                                        onClick={() => setConfirmando(null)}
                                    >
                                        Cancelar
                                    </Boton>
                                    <Boton
                                        cargando={enviando === c.id}
                                        onClick={() => resolver(c, 'USAR_CARGA')}
                                    >
                                        Reemplazar
                                    </Boton>
                                </>
                            ) : (
                                <>
                                    <Boton
                                        variante="secundario"
                                        disabled={enviando !== null}
                                        cargando={enviando === c.id}
                                        onClick={() => resolver(c, 'MANTENER_PADRON')}
                                    >
                                        Mantener padrón
                                    </Boton>
                                    <Boton
                                        disabled={enviando !== null}
                                        onClick={() => setConfirmando(c.id)}
                                    >
                                        Usar carga nueva
                                    </Boton>
                                </>
                            )}
                        </div>
                    </Tarjeta>
                ))}
                <Paginacion
                    {...lista.paginacion(meta)}
                    unidad={['conflicto', 'conflictos']}
                    className="rounded-xl border border-slate-200 bg-white"
                />
            </EstadoCarga>
        </div>
    );
}

Conflictos.propTypes = {
    onCambio: PropTypes.func.isRequired,
    avisar: PropTypes.func.isRequired,
};
