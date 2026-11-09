// Revisión de la foto del estudiante en el navegador, antes de subirla.
// Con MediaPipe Face Detector (BlazeFace de corto alcance) se exige un solo
// rostro, grande, centrado y de frente: eso da `errores`, que bloquean. La
// luz, el fondo (claro y liso) y la nitidez solo dan `avisos`. El detector se carga la
// primera vez que hace falta (import dinámico) y se reutiliza; el
// WebAssembly y el modelo se sirven desde /vendor/mediapipe/. Si no carga
// (sin WebAssembly, sin red), la revisión sigue solo con luz y nitidez.

const RUTA = '/vendor/mediapipe';
// Lado mayor de la copia que se analiza: misma escala para toda foto.
const LADO_ANALISIS = 320;
// Si el detector tarda más (red lenta), esta foto se revisa sin él.
const ESPERA_DETECTOR_MS = 15000;

export const UMBRALES = {
    // Puntaje mínimo para contar un rostro.
    confianza: 0.5,
    // Ancho del rostro sobre el lado menor de la foto.
    rostroMinimo: 0.25,
    // Distancia del centro del rostro al centro de la foto, en fracción
    // del ancho (horizontal) y del alto (vertical).
    descentradoX: 0.2,
    descentradoY: 0.25,
    // Nariz respecto del punto medio entre los ojos, en fracción de la
    // distancia entre ojos; y distancia entre ojos sobre el ancho del rostro.
    narizMaxima: 0.4,
    ojosMinimo: 0.3,
    // Luminancia media (0 a 255) del rostro o, sin rostro, de toda la foto.
    brilloMinimo: 65,
    // Luminancia del fondo a los lados y encima del rostro (percentil 75).
    fondoMinimo: 85,
    // Fondo liso: un píxel es borde si su laplaciano pasa `bordeFondo`, y
    // el fondo deja de ser liso si los bordes pasan de `texturaMaxima`.
    // Calibrado con fotos reales: paredes lisas hasta 0.13 (con pelo al
    // costado), fondos con dibujo desde 0.18.
    bordeFondo: 30,
    texturaMaxima: 0.15,
    // Varianza del laplaciano sobre el rostro (o la foto) en gris.
    nitidezMinima: 60,
};

export const MENSAJES = {
    sinRostro: 'No se detecta un rostro',
    variosRostros: 'Debe aparecer solo usted',
    pequeno: 'Acérquese: el rostro se ve pequeño',
    descentrado: 'Centre el rostro',
    deLado: 'Mire de frente a la cámara',
    oscura: 'Foto oscura',
    fondoOscuro: 'Use un fondo claro',
    fondoConDibujo: 'Use un fondo liso',
    borrosa: 'Foto borrosa',
};

// Luminancia (BT.601) de los píxeles RGBA de un lienzo.
export function aGris(rgba, ancho, alto) {
    const gris = new Float32Array(ancho * alto);
    for (let i = 0, p = 0; i < gris.length; i++, p += 4) {
        gris[i] = 0.299 * rgba[p] + 0.587 * rgba[p + 1] + 0.114 * rgba[p + 2];
    }
    return gris;
}

function recortar(caja, ancho, alto) {
    const x0 = Math.max(0, Math.floor(caja.x));
    const y0 = Math.max(0, Math.floor(caja.y));
    const x1 = Math.min(ancho, Math.ceil(caja.x + caja.ancho));
    const y1 = Math.min(alto, Math.ceil(caja.y + caja.alto));
    return x1 > x0 && y1 > y0 ? { x0, y0, x1, y1 } : null;
}

// Luminancia media de toda la imagen o, con `caja`, de esa zona.
export function brilloMedio(gris, ancho, alto, caja = null) {
    const zona = caja ? recortar(caja, ancho, alto) : { x0: 0, y0: 0, x1: ancho, y1: alto };
    if (!zona) return brilloMedio(gris, ancho, alto);
    let suma = 0;
    for (let y = zona.y0; y < zona.y1; y++) {
        for (let x = zona.x0; x < zona.x1; x++) suma += gris[y * ancho + x];
    }
    return suma / ((zona.x1 - zona.x0) * (zona.y1 - zona.y0));
}

// El fondo: lo que queda a los lados y encima del rostro (ensanchado para
// dejar fuera las orejas), hasta la altura del mentón; sin rostro, las dos
// esquinas superiores. Devuelve los índices de esos píxeles.
export function zonaDeFondo(ancho, alto, caja = null) {
    let indices = [];
    if (caja) {
        const margenX = caja.ancho * 0.3;
        const izq = caja.x - margenX;
        const der = caja.x + caja.ancho + margenX;
        const arriba = caja.y - caja.alto * 0.5;
        const hasta = Math.min(alto, Math.round(caja.y + caja.alto));
        for (let y = 0; y < hasta; y++) {
            for (let x = 0; x < ancho; x++) {
                if (x >= izq && x <= der && y >= arriba) continue;
                indices.push(y * ancho + x);
            }
        }
    }
    if (indices.length < ancho * alto * 0.03) {
        indices = [];
        const lado = Math.max(1, Math.round(ancho * 0.2));
        const hasta = Math.max(1, Math.round(alto / 3));
        for (let y = 0; y < hasta; y++) {
            for (let x = 0; x < ancho; x++) {
                if (x >= lado && x < ancho - lado) continue;
                indices.push(y * ancho + x);
            }
        }
    }
    return indices;
}

// Luminancia del fondo. Se toma un percentil alto y no la media para que el
// pelo oscuro no cuente como fondo.
export function brilloDeFondo(gris, ancho, alto, caja = null, percentil = 0.75) {
    const valores = zonaDeFondo(ancho, alto, caja).map((i) => gris[i]);
    valores.sort((a, b) => a - b);
    return valores[Math.min(valores.length - 1, Math.floor(valores.length * percentil))];
}

// Cuánto dibujo tiene el fondo: la parte de sus píxeles con un borde
// marcado (laplaciano fuerte). Una pared lisa da casi cero; repisas,
// cortinas o afiches, bastante más. El pelo que asoma junto al rostro
// suma poco porque es una parte chica del fondo.
export function texturaDeFondo(gris, ancho, alto, caja = null, borde = UMBRALES.bordeFondo) {
    let fuertes = 0;
    let n = 0;
    for (const i of zonaDeFondo(ancho, alto, caja)) {
        const x = i % ancho;
        const y = (i - x) / ancho;
        if (x === 0 || y === 0 || x === ancho - 1 || y === alto - 1) continue;
        const l = gris[i - 1] + gris[i + 1] + gris[i - ancho] + gris[i + ancho] - 4 * gris[i];
        if (Math.abs(l) > borde) fuertes++;
        n++;
    }
    return n === 0 ? 0 : fuertes / n;
}

// Varianza del laplaciano (núcleo de 4 vecinos): baja en fotos borrosas.
// Con `caja`, solo dentro de esa zona.
export function varianzaLaplaciano(gris, ancho, alto, caja = null) {
    const zona = (caja && recortar(caja, ancho, alto)) || { x0: 0, y0: 0, x1: ancho, y1: alto };
    const x0 = Math.max(1, zona.x0);
    const y0 = Math.max(1, zona.y0);
    const x1 = Math.min(ancho - 1, zona.x1);
    const y1 = Math.min(alto - 1, zona.y1);
    let suma = 0;
    let cuadrados = 0;
    let n = 0;
    for (let y = y0; y < y1; y++) {
        for (let x = x0; x < x1; x++) {
            const i = y * ancho + x;
            const l = gris[i - 1] + gris[i + 1] + gris[i - ancho] + gris[i + ancho] - 4 * gris[i];
            suma += l;
            cuadrados += l * l;
            n++;
        }
    }
    if (n === 0) return 0;
    const media = suma / n;
    return cuadrados / n - media * media;
}

// Rostros con puntaje suficiente, en el formato de MediaPipe: caja en
// píxeles y puntos clave normalizados (0 ojo derecho, 1 ojo izquierdo,
// 2 nariz, 3 boca).
export function rostrosValidos(detecciones, umbrales = UMBRALES) {
    return (detecciones ?? []).filter(
        (d) => d.boundingBox && (d.categories?.[0]?.score ?? 1) >= umbrales.confianza
    );
}

export function cajaDe(deteccion) {
    const { originX, originY, width, height } = deteccion.boundingBox;
    return { x: originX, y: originY, ancho: width, alto: height };
}

// Errores de rostro de una foto de `ancho` × `alto` píxeles.
export function revisarRostros(detecciones, ancho, alto, umbrales = UMBRALES) {
    const rostros = rostrosValidos(detecciones, umbrales);
    if (rostros.length === 0) return [MENSAJES.sinRostro];
    if (rostros.length > 1) return [MENSAJES.variosRostros];

    const errores = [];
    const caja = cajaDe(rostros[0]);
    if (caja.ancho / Math.min(ancho, alto) < umbrales.rostroMinimo) {
        errores.push(MENSAJES.pequeno);
    }
    const centroX = (caja.x + caja.ancho / 2) / ancho;
    const centroY = (caja.y + caja.alto / 2) / alto;
    if (
        Math.abs(centroX - 0.5) > umbrales.descentradoX ||
        Math.abs(centroY - 0.5) > umbrales.descentradoY
    ) {
        errores.push(MENSAJES.descentrado);
    }
    if (!deFrente(rostros[0], ancho, umbrales)) errores.push(MENSAJES.deLado);
    return errores;
}

// De frente: los ojos a la misma altura más o menos, separados, y la nariz
// entre ambos, cerca del medio.
export function deFrente(deteccion, ancho, umbrales = UMBRALES) {
    const puntos = deteccion.keypoints ?? [];
    if (puntos.length < 3) return true;
    const [ojoA, ojoB, nariz] = puntos;
    const izq = Math.min(ojoA.x, ojoB.x);
    const der = Math.max(ojoA.x, ojoB.x);
    const entreOjos = der - izq;
    const anchoRostro = deteccion.boundingBox.width / ancho;
    if (entreOjos <= 0 || entreOjos / anchoRostro < umbrales.ojosMinimo) return false;
    if (nariz.x <= izq || nariz.x >= der) return false;
    return Math.abs(nariz.x - (izq + der) / 2) / entreOjos <= umbrales.narizMaxima;
}

// Avisos de luz, fondo y nitidez a partir de las medidas. Solo avisan: la
// foto se puede subir igual.
export function revisarLuz({ brillo, fondo, textura = 0, nitidez }, umbrales = UMBRALES) {
    const avisos = [];
    if (brillo < umbrales.brilloMinimo) avisos.push(MENSAJES.oscura);
    else if (fondo < umbrales.fondoMinimo) avisos.push(MENSAJES.fondoOscuro);
    if (textura > umbrales.texturaMaxima) avisos.push(MENSAJES.fondoConDibujo);
    if (nitidez < umbrales.nitidezMinima) avisos.push(MENSAJES.borrosa);
    return avisos;
}

// Medidas de una imagen ya dibujada en gris, con las detecciones (o
// `null` si no hubo detector).
export function medir(gris, ancho, alto, detecciones) {
    const rostros = detecciones ? rostrosValidos(detecciones) : [];
    const caja = rostros.length === 1 ? cajaDe(rostros[0]) : null;
    return {
        ancho,
        alto,
        detecciones,
        brillo: brilloMedio(gris, ancho, alto, caja),
        fondo: brilloDeFondo(gris, ancho, alto, caja),
        textura: texturaDeFondo(gris, ancho, alto, caja),
        nitidez: varianzaLaplaciano(gris, ancho, alto, caja),
    };
}

export function evaluar(medidas, umbrales = UMBRALES) {
    return {
        errores: medidas.detecciones
            ? revisarRostros(medidas.detecciones, medidas.ancho, medidas.alto, umbrales)
            : [],
        avisos: revisarLuz(medidas, umbrales),
    };
}

let detector = null;

// El detector, cargado una sola vez. Si falla, se reintenta la próxima.
export function cargarDetector() {
    detector ??= (async () => {
        const { FaceDetector, FilesetResolver } = await import('@mediapipe/tasks-vision');
        const archivos = await FilesetResolver.forVisionTasks(`${RUTA}/wasm`);
        return FaceDetector.createFromOptions(archivos, {
            baseOptions: {
                modelAssetPath: `${RUTA}/blaze_face_short_range.tflite`,
                delegate: 'CPU',
            },
            runningMode: 'IMAGE',
            minDetectionConfidence: UMBRALES.confianza,
        });
    })().catch((fallo) => {
        detector = null;
        throw fallo;
    });
    return detector;
}

// Empieza a cargar el detector sin esperarlo (al abrir la pantalla).
export function precargarDetector() {
    if (typeof WebAssembly === 'undefined' || typeof createImageBitmap !== 'function') return;
    cargarDetector().catch(() => {});
}

async function conEspera(promesa, ms) {
    let reloj;
    const vencida = new Promise((resolver) => {
        reloj = setTimeout(() => resolver(null), ms);
    });
    try {
        return await Promise.race([promesa, vencida]);
    } finally {
        clearTimeout(reloj);
    }
}

// Dibuja la foto a escala de análisis y la mide. Devuelve null si el
// navegador no puede leerla (sin createImageBitmap o sin canvas).
export async function medirFoto(archivo, { obtenerDetector = cargarDetector } = {}) {
    if (typeof createImageBitmap !== 'function') return null;
    let mapa;
    try {
        mapa = await createImageBitmap(archivo);
    } catch {
        return null;
    }
    const escala = Math.min(1, LADO_ANALISIS / Math.max(mapa.width, mapa.height));
    const lienzo = document.createElement('canvas');
    lienzo.width = Math.max(1, Math.round(mapa.width * escala));
    lienzo.height = Math.max(1, Math.round(mapa.height * escala));
    const contexto = lienzo.getContext?.('2d', { willReadFrequently: true });
    if (!contexto) {
        mapa.close?.();
        return null;
    }
    contexto.drawImage(mapa, 0, 0, lienzo.width, lienzo.height);
    const { width: ancho, height: alto } = lienzo;
    const gris = aGris(contexto.getImageData(0, 0, ancho, alto).data, ancho, alto);

    let detecciones = null;
    try {
        const listo =
            typeof WebAssembly === 'undefined'
                ? null
                : await conEspera(obtenerDetector(), ESPERA_DETECTOR_MS);
        detecciones = listo ? listo.detect(lienzo).detections : null;
        if (detecciones?.length === 0) {
            // Un rostro muy pequeño no se detecta en la foto entera: se
            // busca en la mitad central ampliada para decir «Acérquese».
            const { width: w, height: h } = mapa;
            contexto.drawImage(mapa, w / 4, h / 4, w / 2, h / 2, 0, 0, ancho, alto);
            detecciones = desdeElCentro(listo.detect(lienzo).detections, ancho, alto);
        }
    } catch {
        detecciones = null;
    } finally {
        mapa.close?.();
    }
    return medir(gris, ancho, alto, detecciones);
}

// Lleva las detecciones hechas sobre la mitad central ampliada al doble a
// las coordenadas de la foto entera.
export function desdeElCentro(detecciones, ancho, alto) {
    return detecciones.map((d) => ({
        ...d,
        boundingBox: d.boundingBox && {
            ...d.boundingBox,
            originX: ancho / 4 + d.boundingBox.originX / 2,
            originY: alto / 4 + d.boundingBox.originY / 2,
            width: d.boundingBox.width / 2,
            height: d.boundingBox.height / 2,
        },
        keypoints: (d.keypoints ?? []).map((k) => ({ ...k, x: 0.25 + k.x / 2, y: 0.25 + k.y / 2 })),
    }));
}

// La revisión completa: `{ errores, avisos }`. Nunca lanza: si algo falla,
// la foto pasa sin observaciones.
export async function revisarFoto(archivo, opciones) {
    try {
        const medidas = await medirFoto(archivo, opciones);
        return medidas ? evaluar(medidas) : { errores: [], avisos: [] };
    } catch {
        return { errores: [], avisos: [] };
    }
}
