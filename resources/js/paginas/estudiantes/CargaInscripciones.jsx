import PropTypes from 'prop-types';
import { useState } from 'react';
import { Download, Upload } from 'lucide-react';
import { api } from '../../api/cliente';
import descargar from '../../api/descargar';
import { codigoDe, erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Dato from '../../componentes/Dato';
import Dialogo from '../../componentes/Dialogo';
import Seleccion from '../../componentes/Seleccion';
import { usarFacultades } from '../../sesion/SesionContexto';

const ARCHIVO = /\.(csv|xlsx)$/i;
const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');
const miles = (n) => (typeof n === 'number' ? n.toLocaleString('es-BO') : '—');

const CIFRAS = [
    ['filas', 'Filas'],
    ['nuevos', 'Nuevos'],
    ['reutilizados', 'Reutilizados'],
    ['ya_inscritos', 'Ya inscritos'],
    ['rechazados', 'Rechazados'],
    ['conflictos', 'En conflicto'],
];

// Lo que devuelve una carga: las seis cifras y, fila por fila, lo que se
// rechazó y lo que quedó en conflicto.
function Resultado({ resultado }) {
    const resumen = resultado.resumen ?? {};
    const rechazos = resultado.rechazos ?? [];
    const conflictos = resultado.conflictos ?? [];

    return (
        <div className="space-y-4">
            {resultado.archivo && (
                <p className="break-all text-sm font-medium text-slate-800">{resultado.archivo}</p>
            )}
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {CIFRAS.map(([clave, etiqueta]) => (
                    <Dato
                        key={clave}
                        etiqueta={etiqueta}
                        valor={miles(resumen[clave])}
                        tono={
                            resumen[clave] > 0 && clave === 'rechazados'
                                ? 'peligro'
                                : resumen[clave] > 0 && clave === 'conflictos'
                                  ? 'advertencia'
                                  : 'neutro'
                        }
                    />
                ))}
            </div>
            {rechazos.length > 0 && (
                <section aria-label="Filas rechazadas">
                    <h3 className="text-xs font-medium uppercase tracking-wide text-slate-600">
                        Filas rechazadas
                    </h3>
                    <ul className="mt-2 max-h-56 divide-y divide-slate-200 overflow-y-auto rounded-lg border border-slate-200 text-sm">
                        {rechazos.map((r) => (
                            <li key={`${r.fila}-${r.motivo}`} className="flex gap-3 px-3 py-2">
                                <span className="shrink-0 font-medium tabular-nums text-slate-800">
                                    Fila {r.fila}
                                </span>
                                <span className="min-w-0 break-words text-slate-700">
                                    {sinPunto(r.motivo)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
            {conflictos.length > 0 && (
                <section aria-label="Filas en conflicto">
                    <h3 className="text-xs font-medium uppercase tracking-wide text-slate-600">
                        Filas en conflicto
                    </h3>
                    <ul className="mt-2 max-h-56 divide-y divide-slate-200 overflow-y-auto rounded-lg border border-slate-200 text-sm">
                        {conflictos.map((c) => (
                            <li key={`${c.fila}-${c.codigo}`} className="flex gap-3 px-3 py-2">
                                <span className="shrink-0 font-medium tabular-nums text-slate-800">
                                    Fila {c.fila}
                                </span>
                                <span className="min-w-0 break-words text-slate-700">
                                    <span className="font-mono text-xs">{c.codigo}</span> ·{' '}
                                    {sinPunto(c.motivo)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}

Resultado.propTypes = {
    resultado: PropTypes.shape({
        archivo: PropTypes.string,
        resumen: PropTypes.object,
        rechazos: PropTypes.array,
        conflictos: PropTypes.array,
    }).isRequired,
};

// Diálogo de «Cargar inscripciones»: facultad y archivo; después, el
// resultado. Un archivo que no es una lista se rechaza entero, con su motivo
// y las columnas esperadas.
export default function CargaInscripciones({
    facultadInicial = null,
    onCerrar,
    onCargada,
    onVerConflictos,
    avisar,
}) {
    const facultades = usarFacultades();
    const [facultad, setFacultad] = useState(facultadInicial ?? facultades[0]?.clave ?? '');
    const [archivo, setArchivo] = useState(null);
    const [enviado, setEnviado] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [errores, setErrores] = useState({});
    const [rechazo, setRechazo] = useState(null);
    const [general, setGeneral] = useState(null);
    const [resultado, setResultado] = useState(null);

    const errorLocal = archivo
        ? ARCHIVO.test(archivo.name)
            ? null
            : 'Solo .csv o .xlsx'
        : enviado
          ? 'Obligatorio'
          : null;
    const errorArchivo = errorLocal ?? (rechazo ? null : (errores.archivo ?? null));
    const errorFacultad = facultad === '' && enviado ? 'Obligatorio' : (errores.facultad ?? null);

    function limpiar() {
        setErrores({});
        setRechazo(null);
        setGeneral(null);
    }

    function cargar(evento) {
        evento?.preventDefault();
        if (enviando) return;
        setEnviado(true);
        if (!archivo || !ARCHIVO.test(archivo.name) || facultad === '') return;

        const cuerpo = new FormData();
        cuerpo.append('facultad', facultad);
        cuerpo.append('archivo', archivo);

        limpiar();
        setEnviando(true);
        api.post('/estudiantes/cargas', cuerpo)
            .then((respuesta) => {
                setResultado(respuesta.data ?? {});
                onCargada(respuesta.data ?? {});
            })
            .catch((fallo) => {
                const datos = fallo?.response?.data ?? {};
                if (estadoDe(fallo) === 422 && codigoDe(fallo)) {
                    setRechazo({
                        mensaje: sinPunto(mensajeDe(fallo, 'Archivo rechazado')),
                        faltan: Array.isArray(datos.columnas) ? datos.columnas : [],
                        orden: Array.isArray(datos.orden_esperado) ? datos.orden_esperado : [],
                    });
                    return;
                }
                const porCampo = erroresDe(fallo);
                if (porCampo.archivo || porCampo.facultad) {
                    setErrores({
                        archivo: porCampo.archivo && sinPunto(porCampo.archivo),
                        facultad: porCampo.facultad && sinPunto(porCampo.facultad),
                    });
                    return;
                }
                setGeneral(sinPunto(mensajeDe(fallo, 'No se pudo cargar')));
            })
            .finally(() => setEnviando(false));
    }

    function plantilla() {
        descargar('/inscritos/plantilla', {
            parametros: { alcance: 'facultad' },
            nombre: 'plantilla_inscritos.xlsx',
        })
            .then((nombre) => avisar(`${nombre} descargado`))
            .catch((fallo) => avisar(sinPunto(mensajeDe(fallo, 'No se pudo descargar')), 'error'));
    }

    if (resultado) {
        const hayConflictos = (resultado.conflictos ?? []).length > 0;
        return (
            <Dialogo
                titulo="Resultado de la carga"
                onCerrar={onCerrar}
                ancho="max-w-lg"
                acciones={
                    <>
                        {hayConflictos && (
                            <Boton variante="secundario" onClick={onVerConflictos}>
                                Ver conflictos
                            </Boton>
                        )}
                        <Boton onClick={onCerrar}>Cerrar</Boton>
                    </>
                }
            >
                <Resultado resultado={resultado} />
            </Dialogo>
        );
    }

    return (
        <Dialogo
            titulo="Carga de inscripciones"
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton onClick={cargar} disabled={enviando}>
                        <Upload className="h-4 w-4" aria-hidden="true" />{' '}
                        {enviando ? 'Cargando' : 'Cargar'}
                    </Boton>
                </>
            }
        >
            <form className="space-y-4" onSubmit={cargar} noValidate>
                <div>
                    <Seleccion
                        etiqueta="Facultad"
                        requerido
                        value={facultad}
                        aria-invalid={errorFacultad ? 'true' : undefined}
                        onChange={(e) => {
                            setFacultad(e.target.value);
                            limpiar();
                        }}
                    >
                        {facultades.map((f) => (
                            <option key={f.clave} value={f.clave}>
                                {f.sigla} · {f.nombre}
                            </option>
                        ))}
                    </Seleccion>
                    {errorFacultad && (
                        <span className="mt-1.5 block text-sm text-danger-600">
                            {errorFacultad}
                        </span>
                    )}
                </div>
                <label className="block">
                    <span className="mb-1.5 block text-sm font-medium text-slate-700">
                        Archivo
                        <span className="ml-0.5 text-danger-600" aria-hidden="true">
                            *
                        </span>
                    </span>
                    <input
                        type="file"
                        accept=".csv,.xlsx"
                        aria-required="true"
                        aria-invalid={errorArchivo || rechazo ? 'true' : undefined}
                        onChange={(e) => {
                            setArchivo(e.target.files?.[0] ?? null);
                            limpiar();
                        }}
                        className={`block w-full min-w-0 rounded-lg border bg-white text-sm text-slate-700 file:mr-3 file:min-h-11 file:border-0 file:bg-slate-100 file:px-4 file:text-sm file:font-medium file:text-slate-700 ${FOCO} ${
                            errorArchivo || rechazo ? 'border-danger-600' : 'border-slate-300'
                        }`}
                    />
                    {errorArchivo ? (
                        <span className="mt-1.5 block text-sm text-danger-600">{errorArchivo}</span>
                    ) : (
                        !rechazo && (
                            <span className="mt-1.5 block text-xs text-slate-500">
                                .csv o .xlsx
                            </span>
                        )
                    )}
                </label>

                {rechazo && (
                    <div
                        role="alert"
                        className="space-y-2 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm"
                    >
                        <p className="font-medium text-danger-700">{rechazo.mensaje}</p>
                        {rechazo.faltan.length > 0 && (
                            <p className="break-words text-slate-800">
                                Faltan:{' '}
                                <span className="font-mono text-xs">
                                    {rechazo.faltan.join(', ')}
                                </span>
                            </p>
                        )}
                        {rechazo.orden.length > 0 && (
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wide text-slate-600">
                                    Orden esperado
                                </p>
                                <ol
                                    aria-label="Orden esperado"
                                    className="mt-1 flex flex-wrap gap-1.5"
                                >
                                    {rechazo.orden.map((columna, i) => (
                                        <li
                                            key={columna}
                                            className="max-w-full break-all rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-xs text-slate-800"
                                        >
                                            {i + 1}. {columna}
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        )}
                    </div>
                )}

                {general && (
                    <p role="alert" className="text-sm text-danger-600">
                        {general}
                    </p>
                )}

                <Boton type="button" variante="enlace" tamano="chico" onClick={plantilla}>
                    <Download className="h-4 w-4" aria-hidden="true" /> Plantilla
                </Boton>
            </form>
        </Dialogo>
    );
}

CargaInscripciones.propTypes = {
    facultadInicial: PropTypes.string,
    onCerrar: PropTypes.func.isRequired,
    onCargada: PropTypes.func.isRequired,
    onVerConflictos: PropTypes.func.isRequired,
    avisar: PropTypes.func.isRequired,
};
