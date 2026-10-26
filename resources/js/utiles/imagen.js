// Las fotos se reducen en el navegador antes de subirlas: una foto de
// teléfono (3 a 8 MB) queda en unos cientos de KB y no choca con el
// límite de subida del servidor. Sin `createImageBitmap` o sin canvas
// (navegadores viejos, pruebas) se envía el archivo tal cual.

export const MAXIMO_MB = 8;
export const TIPOS_IMAGEN = ['image/jpeg', 'image/png', 'image/webp'];
// Las fotos de iPhone: Safari las sabe leer y se convierten a JPEG aquí;
// donde no, se dice que use JPG o PNG.
const TIPOS_HEIC = ['image/heic', 'image/heif'];

export class ImagenNoValida extends Error {}

export async function reducirImagen(archivo, lado = 1600, calidad = 0.85) {
    const heic = TIPOS_HEIC.includes(archivo.type) || /\.hei[cf]$/i.test(archivo.name);
    if (!TIPOS_IMAGEN.includes(archivo.type) && !heic) {
        throw new ImagenNoValida('Solo JPG, PNG o WebP');
    }
    if (typeof createImageBitmap !== 'function') {
        if (heic) throw new ImagenNoValida('HEIC no compatible aquí · use JPG o PNG');
        return comprobarTamano(archivo);
    }

    let mapa;
    try {
        mapa = await createImageBitmap(archivo);
    } catch {
        throw new ImagenNoValida(
            heic ? 'HEIC no compatible aquí · use JPG o PNG' : 'La imagen no se puede leer'
        );
    }

    const escala = Math.min(1, lado / Math.max(mapa.width, mapa.height));
    const lienzo = document.createElement('canvas');
    lienzo.width = Math.round(mapa.width * escala);
    lienzo.height = Math.round(mapa.height * escala);
    const contexto = lienzo.getContext?.('2d');
    if (!contexto || typeof lienzo.toBlob !== 'function') return comprobarTamano(archivo);

    contexto.fillStyle = '#fff';
    contexto.fillRect(0, 0, lienzo.width, lienzo.height);
    contexto.drawImage(mapa, 0, 0, lienzo.width, lienzo.height);
    mapa.close?.();

    const blob = await new Promise((resolver) => lienzo.toBlob(resolver, 'image/jpeg', calidad));
    if (!blob) return comprobarTamano(archivo);

    const nombre = archivo.name.replace(/\.[^.]+$/, '') + '.jpg';
    return comprobarTamano(new File([blob], nombre, { type: 'image/jpeg' }));
}

function comprobarTamano(archivo) {
    if (archivo.size > MAXIMO_MB * 1024 * 1024) {
        throw new ImagenNoValida(`Máximo ${MAXIMO_MB} MB`);
    }
    return archivo;
}

// Las dimensiones de una imagen ya elegida, o null si no se pueden leer.
export async function dimensionesDe(archivo) {
    if (typeof createImageBitmap !== 'function') return null;
    try {
        const mapa = await createImageBitmap(archivo);
        const medidas = { ancho: mapa.width, alto: mapa.height };
        mapa.close?.();
        return medidas;
    } catch {
        return null;
    }
}
