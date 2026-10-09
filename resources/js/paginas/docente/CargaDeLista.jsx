import PropTypes from 'prop-types';
import { useRef, useState } from 'react';
import { Download, FileSpreadsheet, Upload } from 'lucide-react';
import { api } from '../../api/cliente';
import descargar from '../../api/descargar';
import { codigoDe, erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';

const COLUMNAS = {
    codigo_universitario: 'Código universitario',
    documento_identidad: 'Documento de identidad',
    nombres: 'Nombres',
    apellidos: 'Apellidos',
};

const TITULOS = {
    FALTAN_COLUMNAS: 'Faltan columnas',
    ORDEN_DE_COLUMNAS: 'Columnas en otro orden',
    ARCHIVO_NO_CORRESPONDE: 'El archivo no es una lista de inscritos',
    ARCHIVO_VACIO: 'El archivo no tiene filas',
};

const EXTENSIONES = ['csv', 'xlsx'];
const MAXIMO_BYTES = 10 * 1024 * 1024;

const columna = (nombre) => COLUMNAS[nombre] ?? nombre;

// Lo que el formulario puede decir del archivo antes de enviarlo: los mismos
// textos con los que responde el servidor.
function reparoDe(archivo) {
    if (!archivo) return 'El archivo es obligatorio.';
    const extension = archivo.name.includes('.') ? archivo.name.split('.').pop().toLowerCase() : '';
    if (!EXTENSIONES.includes(extension)) return 'El archivo debe ser .csv o .xlsx.';
    if (archivo.size > MAXIMO_BYTES) return 'El archivo no puede superar los 10 MB.';
    return null;
}

// El rechazo de una carga, listo para dibujar.
function rechazoDe(error) {
    const cuerpo = error?.response?.data ?? {};
    const codigo = codigoDe(error);

    if (estadoDe(error) === 422 && TITULOS[codigo]) {
        return {
            titulo: TITULOS[codigo],
            columnas: Array.isArray(cuerpo.columnas) ? cuerpo.columnas : [],
            orden: Array.isArray(cuerpo.orden_esperado) ? cuerpo.orden_esperado : [],
        };
    }

    const titulo = erroresDe(error).archivo ?? mensajeDe(error, 'No se pudo cargar el archivo');
    return { titulo, columnas: [], orden: [] };
}

// Zona de carga de la lista de un grupo: elegir el archivo, cargarlo y
// descargar la plantilla. Un archivo rechazado entero se explica aquí.
export default function CargaDeLista({ grupoId, onCargada, onCancelar, onAvisar }) {
    const entrada = useRef(null);
    const [archivo, setArchivo] = useState(null);
    const [rechazo, setRechazo] = useState(null);
    const [enviando, setEnviando] = useState(false);

    function elegir(evento) {
        const elegido = evento.target.files?.[0] ?? null;
        evento.target.value = '';
        if (!elegido) return;
        const reparo = reparoDe(elegido);
        setArchivo(reparo ? null : elegido);
        setRechazo(reparo ? { titulo: reparo, columnas: [], orden: [] } : null);
    }

    async function cargar(evento) {
        evento.preventDefault();
        const reparo = reparoDe(archivo);
        if (reparo) {
            setRechazo({ titulo: reparo, columnas: [], orden: [] });
            return;
        }

        const formulario = new FormData();
        formulario.append('archivo', archivo);
        setEnviando(true);
        setRechazo(null);
        try {
            const respuesta = await api.post(
                `/docente/grupos/${grupoId}/inscritos/carga`,
                formulario
            );
            setArchivo(null);
            onCargada({ ...respuesta.data, archivo: respuesta.data?.archivo ?? archivo.name });
        } catch (error) {
            setRechazo(rechazoDe(error));
        } finally {
            setEnviando(false);
        }
    }

    async function plantilla() {
        try {
            const nombre = await descargar('/inscritos/plantilla', {
                parametros: { alcance: 'grupo' },
                nombre: 'plantilla_inscritos.xlsx',
            });
            onAvisar(`${nombre} descargado`);
        } catch (error) {
            onAvisar(mensajeDe(error, 'No se pudo descargar'), 'error');
        }
    }

    return (
        <form noValidate onSubmit={cargar} className="border-b border-slate-200 p-4 sm:p-5">
            <div className="rounded-lg border-2 border-dashed border-slate-300 p-4 text-center sm:p-6">
                <FileSpreadsheet className="mx-auto h-8 w-8 text-slate-500" />
                <p className="mt-2 text-sm font-medium text-slate-800">
                    Lista de inscritos de la WebSIS
                </p>
                <p className="mx-auto mt-1 max-w-md text-xs text-slate-600">
                    .xlsx o .csv · código universitario, documento, nombres y apellidos
                </p>

                <input
                    ref={entrada}
                    type="file"
                    accept=".csv,.xlsx"
                    aria-label="Archivo de la lista"
                    className="sr-only"
                    tabIndex={-1}
                    onChange={elegir}
                />

                {archivo && (
                    <p className="mt-3 break-all text-sm font-medium text-slate-800">
                        {archivo.name}
                    </p>
                )}

                {rechazo && (
                    <div
                        role="alert"
                        className="mx-auto mt-3 max-w-md rounded-lg bg-danger-50 px-3 py-2 text-left text-sm text-danger-700"
                    >
                        <p className="font-medium">{rechazo.titulo}</p>
                        {rechazo.columnas.length > 0 && (
                            <p className="mt-1 break-words text-xs">
                                Faltan: {rechazo.columnas.map(columna).join(' · ')}
                            </p>
                        )}
                        {rechazo.orden.length > 0 && (
                            <p className="mt-1 break-words text-xs">
                                Orden esperado: {rechazo.orden.map(columna).join(' · ')}
                            </p>
                        )}
                    </div>
                )}

                <div className="mt-4 flex flex-col justify-center gap-2 sm:flex-row sm:flex-wrap">
                    <Boton
                        type="button"
                        variante="secundario"
                        disabled={enviando}
                        onClick={() => entrada.current?.click()}
                    >
                        <Upload className="h-4 w-4" /> Elegir archivo
                    </Boton>
                    <Boton type="submit" disabled={enviando}>
                        {enviando ? 'Cargando' : 'Cargar lista'}
                    </Boton>
                    <Boton type="button" variante="fantasma" onClick={plantilla}>
                        <Download className="h-4 w-4" /> Descargar plantilla
                    </Boton>
                    {onCancelar && (
                        <Boton
                            type="button"
                            variante="fantasma"
                            disabled={enviando}
                            onClick={onCancelar}
                        >
                            Cancelar
                        </Boton>
                    )}
                </div>
            </div>
        </form>
    );
}

CargaDeLista.propTypes = {
    grupoId: PropTypes.number.isRequired,
    onCargada: PropTypes.func.isRequired,
    onCancelar: PropTypes.func,
    onAvisar: PropTypes.func.isRequired,
};
