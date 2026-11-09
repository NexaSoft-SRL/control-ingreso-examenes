import { cajaDe, medirFoto, rostrosValidos } from './revisarFoto';

// La foto del estudiante se guarda cuadrada y con el rostro al centro: así
// se ve igual en el círculo de las listas y en grande en la puerta. El
// recorte sigue al rostro que encuentra el detector; sin rostro (o sin
// detector), se toma el cuadrado central, algo hacia arriba si es vertical.

// El lado del cuadrado, en anchos de rostro: entran el cabello y los hombros.
const LADO_EN_ROSTROS = 2.6;
// Lado mínimo del recorte en píxeles de la foto: un rostro pequeño no se
// amplía a una imagen de baja resolución (la revisión dirá «Acérquese»).
const LADO_MINIMO = 400;
// Lado de la imagen que se guarda.
const LADO_FINAL = 960;

const entre = (valor, minimo, maximo) => Math.min(Math.max(valor, minimo), maximo);

// El cuadrado a recortar de una foto de `ancho` × `alto`, con `caja` (el
// rostro en píxeles de esa foto) o sin ella. Devuelve { x, y, lado }.
export function cuadroAlrededor(caja, ancho, alto) {
    const menor = Math.min(ancho, alto);
    if (!caja) {
        const lado = menor;
        return {
            x: Math.round((ancho - lado) / 2),
            y: Math.round(alto > ancho ? (alto - lado) * 0.3 : 0),
            lado,
        };
    }
    const lado = Math.round(
        entre(
            Math.max(caja.ancho, caja.alto) * LADO_EN_ROSTROS,
            Math.min(LADO_MINIMO, menor),
            menor
        )
    );
    // El rostro al centro: la caja del detector va de las cejas al mentón,
    // así que arriba entra el cabello y abajo el cuello y los hombros.
    const centroX = caja.x + caja.ancho / 2;
    const centroY = caja.y + caja.alto / 2;
    return {
        x: Math.round(entre(centroX - lado / 2, 0, ancho - lado)),
        y: Math.round(entre(centroY - lado / 2, 0, alto - lado)),
        lado,
    };
}

// El rostro único de la foto, en fracciones (0 a 1) de su ancho y alto, o
// null si no hay exactamente uno.
async function rostroDe(archivo, opciones) {
    const medidas = await medirFoto(archivo, opciones);
    const rostros = rostrosValidos(medidas?.detecciones);
    if (rostros.length !== 1) return null;
    const caja = cajaDe(rostros[0]);
    return {
        x: caja.x / medidas.ancho,
        y: caja.y / medidas.alto,
        ancho: caja.ancho / medidas.ancho,
        alto: caja.alto / medidas.alto,
    };
}

// La foto recortada en cuadrado alrededor del rostro, en JPEG. Si el
// navegador no puede dibujarla, la devuelve tal cual: el servidor la acepta
// igual y las listas la recortan al mostrarla.
export async function encuadrarFoto(archivo, opciones) {
    if (typeof createImageBitmap !== 'function') return archivo;
    let mapa;
    try {
        mapa = await createImageBitmap(archivo);
    } catch {
        return archivo;
    }
    try {
        let rostro = null;
        try {
            rostro = await rostroDe(archivo, opciones);
        } catch {
            rostro = null;
        }
        const { width: ancho, height: alto } = mapa;
        const caja = rostro && {
            x: rostro.x * ancho,
            y: rostro.y * alto,
            ancho: rostro.ancho * ancho,
            alto: rostro.alto * alto,
        };
        const { x, y, lado } = cuadroAlrededor(caja, ancho, alto);
        const final = Math.min(lado, LADO_FINAL);
        const lienzo = document.createElement('canvas');
        lienzo.width = final;
        lienzo.height = final;
        const contexto = lienzo.getContext?.('2d');
        if (!contexto || typeof lienzo.toBlob !== 'function') return archivo;
        contexto.fillStyle = '#fff';
        contexto.fillRect(0, 0, final, final);
        contexto.drawImage(mapa, x, y, lado, lado, 0, 0, final, final);
        const blob = await new Promise((resolver) => lienzo.toBlob(resolver, 'image/jpeg', 0.9));
        if (!blob) return archivo;
        const nombre = (archivo.name ?? 'foto').replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], nombre, { type: 'image/jpeg' });
    } finally {
        mapa.close?.();
    }
}
