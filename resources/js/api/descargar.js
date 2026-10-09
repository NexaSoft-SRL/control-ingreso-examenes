import { api } from './cliente';

// El nombre que manda el servidor en Content-Disposition.
export function nombreDeArchivo(cabecera) {
    if (!cabecera) return null;
    const extendido = /filename\*\s*=\s*(?:UTF-8'')?([^;]+)/i.exec(cabecera);
    if (extendido) {
        try {
            return decodeURIComponent(extendido[1].trim().replace(/^"|"$/g, ''));
        } catch {
            return extendido[1].trim();
        }
    }
    const simple = /filename\s*=\s*"?([^";]+)"?/i.exec(cabecera);
    return simple ? simple[1].trim() : null;
}

// Un error de una descarga llega como Blob: se pasa a JSON para que
// `mensajeDe(error)` funcione igual que en las demás peticiones.
async function conCuerpoLegible(error) {
    const datos = error?.response?.data;
    if (datos instanceof Blob) {
        try {
            error.response.data = JSON.parse(await datos.text());
        } catch {
            error.response.data = {};
        }
    }
    return error;
}

// GET de un archivo y descarga con el nombre del servidor. Devuelve el
// nombre, para el aviso «… descargado», cuando el archivo ya llegó.
//
//   const nombre = await descargar('/reportes/asistencia', { parametros: { formato: 'xlsx' } });
export default async function descargar(ruta, { parametros, nombre = 'descarga' } = {}) {
    let respuesta;
    try {
        respuesta = await api.get(ruta, { params: parametros, responseType: 'blob' });
    } catch (error) {
        throw await conCuerpoLegible(error);
    }

    const cabeceras = respuesta.headers ?? {};
    const final = nombreDeArchivo(cabeceras['content-disposition']) ?? nombre;
    const direccion = URL.createObjectURL(respuesta.data);
    const enlace = document.createElement('a');
    enlace.href = direccion;
    enlace.download = final;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
    URL.revokeObjectURL(direccion);

    return final;
}
