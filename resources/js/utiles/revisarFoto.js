// Revisión de la foto del estudiante en el navegador, antes de subirla.
// Todo lo que encuentra es un error que bloquea la foto:
// - con MediaPipe Face Detector (BlazeFace de corto alcance), un solo
//   rostro, grande, centrado y de frente;
// - con MediaPipe Face Landmarker (478 puntos del rostro), sin lentes ni
//   filtros (ver rasgos.js);
// - luz suficiente, fondo claro y liso, y foto nítida.
// Los modelos se cargan la primera vez que hacen falta (import dinámico) y
// se reutilizan; el WebAssembly y los modelos se sirven desde
// /vendor/mediapipe/. Si el detector no carga (sin WebAssembly, sin red), la
// revisión sigue solo con luz y nitidez; si el que no carga es el Face
// Landmarker, sin lentes ni filtros.

import { aPlanos, medirRasgos } from './rasgos';

const RUTA = '/vendor/mediapipe';
// Lado mayor de la copia que se analiza: misma escala para toda foto.
const LADO_ANALISIS = 320;
// Si el detector tarda más (red lenta), esta foto se revisa sin él.
const ESPERA_DETECTOR_MS = 15000;
// Lado mayor de la copia en la que se buscan lentes y filtros.
const LADO_RASGOS = 640;

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
    // Parte mínima de la foto que debe quedar como fondo para juzgarlo: en
    // una foto muy cerrada lo que queda es pelo, no pared.
    fondoMedible: 0.03,
    // Nitidez (ver nitidezDe) sobre el rostro o, sin rostro, la foto:
    // caras nítidas desde 56; desenfocadas 1 px, hasta 31; 1.5 px, hasta 13.
    nitidezMinima: 30,
    // Lentes (ver rasgos.js). Línea horizontal en el puente, entre los
    // ojos, en luz (0 a 255) o en color (Cr): caras sin lentes hasta 3.6 y
    // 2.2; con lentes desde 8.8 (armazón fino transparente, foto real) y
    // 4.0 (metal claro sobre piel clara, que casi no cambia la luz).
    lentesPuente: 6,
    lentesPuenteColor: 3.5,
    // Línea bajo los ojos (aro inferior): sin lentes hasta 36, armazón
    // oscuro desde 120.
    lentesAros: 60,
    // Luz de la zona de los ojos sobre la de las mejillas: sin lentes desde
    // 0.42, lentes de sol hasta 0.19.
    ojosOscuros: 0.3,
    // Filtros. Punta de la nariz contra las mejillas: sin filtro, luz desde
    // 0.67 y color hasta 4.8; nariz de perrito, luz hasta 0.24 y color
    // desde 19.
    narizOscura: 0.45,
    narizColor: 12,
    // Frente: un píxel es ajeno a la piel si su color (Cb, Cr) se aparta
    // más de `colorAjeno` y tiene color propio (no es gris, negro ni
    // blanco, como el pelo canoso o muy oscuro) de al menos `colorVivo`; con
    // más de `frenteAjena` de ellos hay algo encima (sin filtro, 0; con
    // flores, desde 0.08).
    colorAjeno: 45,
    colorVivo: 20,
    frenteAjena: 0.04,
};

export const MENSAJES = {
    sinRostro: 'No se detecta un rostro',
    variosRostros: 'Debe aparecer solo usted',
    pequeno: 'Acérquese: el rostro se ve pequeño',
    descentrado: 'Centre el rostro',
    deLado: 'Mire de frente a la cámara',
    lentes: 'Quítese los lentes',
    filtros: 'Sin filtros ni accesorios en el rostro',
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
// dejar fuera las orejas), hasta la altura del mentón. Devuelve los índices
// de esos píxeles: pocos o ninguno si la foto es muy cerrada.
export function zonaDeFondo(ancho, alto, caja) {
    const indices = [];
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
    return indices;
}

// Luminancia del fondo. Se toma un percentil alto y no la media para que el
// pelo oscuro no cuente como fondo.
export function brilloDeFondo(gris, ancho, alto, caja, percentil = 0.75) {
    const valores = zonaDeFondo(ancho, alto, caja).map((i) => gris[i]);
    valores.sort((a, b) => a - b);
    return valores[Math.min(valores.length - 1, Math.floor(valores.length * percentil))];
}

// Cuánto dibujo tiene el fondo: la parte de sus píxeles con un borde
// marcado (laplaciano fuerte). Una pared lisa da casi cero; repisas,
// cortinas o afiches, bastante más. El pelo que asoma junto al rostro
// suma poco porque es una parte chica del fondo.
export function texturaDeFondo(gris, ancho, alto, caja, borde = UMBRALES.bordeFondo) {
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

// Nitidez: la varianza del laplaciano llevada a un brillo medio de 128. La
// varianza crece con el cuadrado del brillo, así que sin llevarla una foto
// nítida pero algo oscura pasaría por borrosa.
export function nitidezDe(gris, ancho, alto, caja = null) {
    const brillo = Math.max(1, brilloMedio(gris, ancho, alto, caja));
    return varianzaLaplaciano(gris, ancho, alto, caja) * (128 / brillo) ** 2;
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

// Errores de luz, fondo y nitidez a partir de las medidas. Sin fondo
// medible (`fondo` y `textura` nulos), solo luz y nitidez.
export function revisarLuz({ brillo, fondo = null, textura = null, nitidez }, umbrales = UMBRALES) {
    const errores = [];
    if (brillo < umbrales.brilloMinimo) errores.push(MENSAJES.oscura);
    else if (fondo !== null && fondo < umbrales.fondoMinimo) errores.push(MENSAJES.fondoOscuro);
    if (textura !== null && textura > umbrales.texturaMaxima) errores.push(MENSAJES.fondoConDibujo);
    if (nitidez < umbrales.nitidezMinima) errores.push(MENSAJES.borrosa);
    return errores;
}

// Errores de lentes y filtros a partir de las medidas de rasgos.js. Sin
// medidas (el Face Landmarker no cargó), ninguno; si cargó pero no halló
// los puntos de un rostro que el detector sí vio, algo lo tapa o lo
// deforma: un filtro.
export function revisarRasgos(rasgos, umbrales = UMBRALES) {
    if (!rasgos) return [];
    if (!rasgos.puntos) return [MENSAJES.filtros];
    const errores = [];
    if (
        rasgos.puente > umbrales.lentesPuente ||
        rasgos.puenteColor > umbrales.lentesPuenteColor ||
        rasgos.aros > umbrales.lentesAros ||
        rasgos.ojos < umbrales.ojosOscuros
    ) {
        errores.push(MENSAJES.lentes);
    }
    if (
        rasgos.luzNariz < umbrales.narizOscura ||
        rasgos.colorNariz > umbrales.narizColor ||
        rasgos.frenteAjena > umbrales.frenteAjena
    ) {
        errores.push(MENSAJES.filtros);
    }
    return errores;
}

// Medidas de una imagen ya dibujada en gris, con las detecciones (o
// `null` si no hubo detector). El fondo se mide solo con un rostro y si
// queda bastante fuera de él; si no, `fondo` y `textura` son nulos.
export function medir(gris, ancho, alto, detecciones, umbrales = UMBRALES) {
    const rostros = detecciones ? rostrosValidos(detecciones, umbrales) : [];
    const caja = rostros.length === 1 ? cajaDe(rostros[0]) : null;
    const conFondo =
        caja !== null &&
        zonaDeFondo(ancho, alto, caja).length >= ancho * alto * umbrales.fondoMedible;
    return {
        ancho,
        alto,
        detecciones,
        brillo: brilloMedio(gris, ancho, alto, caja),
        fondo: conFondo ? brilloDeFondo(gris, ancho, alto, caja) : null,
        textura: conFondo ? texturaDeFondo(gris, ancho, alto, caja) : null,
        nitidez: nitidezDe(gris, ancho, alto, caja),
    };
}

// Todo bloquea: `avisos` queda vacío (CampoImagen los admite para otros
// usos).
export function evaluar(medidas, umbrales = UMBRALES) {
    const rostro = medidas.detecciones
        ? revisarRostros(medidas.detecciones, medidas.ancho, medidas.alto, umbrales)
        : [];
    return {
        errores: [
            ...rostro,
            ...revisarRasgos(medidas.rasgos, umbrales),
            ...revisarLuz(medidas, umbrales),
        ],
        avisos: [],
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

let marcador = null;

// El Face Landmarker (478 puntos del rostro), cargado una sola vez.
export function cargarMarcador() {
    marcador ??= (async () => {
        const { FaceLandmarker, FilesetResolver } = await import('@mediapipe/tasks-vision');
        const archivos = await FilesetResolver.forVisionTasks(`${RUTA}/wasm`);
        return FaceLandmarker.createFromOptions(archivos, {
            baseOptions: { modelAssetPath: `${RUTA}/face_landmarker.task`, delegate: 'CPU' },
            runningMode: 'IMAGE',
            numFaces: 1,
        });
    })().catch((fallo) => {
        marcador = null;
        throw fallo;
    });
    return marcador;
}

// Empieza a cargar los modelos sin esperarlos (al abrir la pantalla).
export function precargarDetector() {
    if (typeof WebAssembly === 'undefined' || typeof createImageBitmap !== 'function') return;
    cargarDetector().catch(() => {});
    cargarMarcador().catch(() => {});
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
export async function medirFoto(
    archivo,
    { obtenerDetector = cargarDetector, obtenerMarcador = cargarMarcador } = {}
) {
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
    }
    // Lentes y filtros, solo con un rostro: si no, ya hay un error.
    let rasgos = null;
    try {
        if (obtenerMarcador && rostrosValidos(detecciones).length === 1) {
            rasgos = await rasgosDe(mapa, obtenerMarcador);
        }
    } catch {
        rasgos = null;
    } finally {
        mapa.close?.();
    }
    return { ...medir(gris, ancho, alto, detecciones), rasgos };
}

// Los puntos del rostro y sus medidas, sobre una copia más grande que la de
// análisis (el aro fino de unos lentes mide pocos píxeles). Devuelve
// `{ puntos: null }` si el Face Landmarker cargó pero no halló el rostro, y
// null si no cargó a tiempo.
async function rasgosDe(mapa, obtenerMarcador) {
    const listo = await conEspera(obtenerMarcador(), ESPERA_DETECTOR_MS);
    if (!listo) return null;
    const escala = Math.min(1, LADO_RASGOS / Math.max(mapa.width, mapa.height));
    const lienzo = document.createElement('canvas');
    lienzo.width = Math.max(1, Math.round(mapa.width * escala));
    lienzo.height = Math.max(1, Math.round(mapa.height * escala));
    const contexto = lienzo.getContext('2d', { willReadFrequently: true });
    contexto.drawImage(mapa, 0, 0, lienzo.width, lienzo.height);
    const puntos = listo.detect(lienzo).faceLandmarks?.[0] ?? null;
    if (!puntos) return { puntos: null };
    const { width: ancho, height: alto } = lienzo;
    const planos = aPlanos(contexto.getImageData(0, 0, ancho, alto).data, ancho, alto);
    return { puntos, ...medirRasgos(planos, puntos, UMBRALES) };
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
