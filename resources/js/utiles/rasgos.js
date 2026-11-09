// Medidas sobre los puntos del rostro (MediaPipe Face Landmarker, 478
// puntos normalizados de 0 a 1) para hallar lentes y filtros. Son cuentas
// simples sobre zonas chicas de la imagen: perfiles de luz y color.

// Puntos de la malla que se usan.
export const PUNTOS = {
    ojoDerExterno: 33,
    ojoDerInterno: 133,
    ojoIzqInterno: 362,
    ojoIzqExterno: 263,
    parpadoDerArriba: 159,
    parpadoDerAbajo: 145,
    parpadoIzqArriba: 386,
    parpadoIzqAbajo: 374,
    lineaDelPelo: 10,
    entreCejas: 168,
    puenteNariz: 6,
    puntaNariz: 1,
    mejillaDer: 205,
    mejillaIzq: 425,
};

const entre = (v, a, b) => Math.min(Math.max(v, a), b);

// La imagen en planos: luz (Y) y color (Cb, Cr), como en JPEG.
export function aPlanos(rgba, ancho, alto) {
    const n = ancho * alto;
    const luz = new Float32Array(n);
    const cb = new Float32Array(n);
    const cr = new Float32Array(n);
    for (let i = 0, p = 0; i < n; i++, p += 4) {
        const y = 0.299 * rgba[p] + 0.587 * rgba[p + 1] + 0.114 * rgba[p + 2];
        luz[i] = y;
        cb[i] = 0.564 * (rgba[p + 2] - y);
        cr[i] = 0.713 * (rgba[p] - y);
    }
    return { luz, cb, cr, ancho, alto };
}

// Una caja { x0, y0, x1, y1 } recortada a la imagen, en píxeles enteros.
function dentro(caja, ancho, alto) {
    const x0 = entre(Math.round(caja.x0), 0, ancho - 1);
    const y0 = entre(Math.round(caja.y0), 0, alto - 1);
    return {
        x0,
        y0,
        x1: entre(Math.round(caja.x1), x0 + 1, ancho),
        y1: entre(Math.round(caja.y1), y0 + 1, alto),
    };
}

// Luz y color medios de una caja.
export function colorDe(planos, caja) {
    const { x0, y0, x1, y1 } = dentro(caja, planos.ancho, planos.alto);
    let luz = 0;
    let cb = 0;
    let cr = 0;
    for (let y = y0; y < y1; y++) {
        for (let x = x0; x < x1; x++) {
            const i = y * planos.ancho + x;
            luz += planos.luz[i];
            cb += planos.cb[i];
            cr += planos.cr[i];
        }
    }
    const n = (x1 - x0) * (y1 - y0);
    return { luz: luz / n, cb: cb / n, cr: cr / n };
}

// Diferencia de color (sin contar la luz) entre dos zonas.
export function distanciaColor(a, b) {
    return Math.hypot(a.cb - b.cb, a.cr - b.cr);
}

// Una línea fina en un perfil: cuánto se aparta un valor de sus vecinos a
// `k` pasos cuando queda más oscuro (o más claro) que los dos. Una sombra
// o un degradé no cuentan; el armazón de unos lentes, sí.
export function lineaEnPerfil(perfil, k) {
    let mayor = 0;
    for (let i = k; i < perfil.length - k; i++) {
        const a = perfil[i - k];
        const b = perfil[i + k];
        mayor = Math.max(mayor, Math.min(a, b) - perfil[i], perfil[i] - Math.max(a, b));
    }
    return mayor;
}

// Media de cada fila de una caja de un plano: el perfil de arriba abajo.
export function perfilDeFilas(plano, ancho, alto, caja) {
    const { x0, y0, x1, y1 } = dentro(caja, ancho, alto);
    const perfil = [];
    for (let y = y0; y < y1; y++) {
        let suma = 0;
        for (let x = x0; x < x1; x++) suma += plano[y * ancho + x];
        perfil.push(suma / (x1 - x0));
    }
    return perfil;
}

// La mediana, entre las columnas de la caja, de la línea más marcada de
// cada una: el aro de unos lentes cruza todas las columnas; una arruga o
// una sombra, pocas.
function lineaEnColumnas(plano, ancho, alto, caja, k) {
    const { x0, x1 } = dentro(caja, ancho, alto);
    const valores = [];
    for (let x = x0; x < x1; x++) {
        valores.push(
            lineaEnPerfil(perfilDeFilas(plano, ancho, alto, { ...caja, x0: x, x1: x + 1 }), k)
        );
    }
    valores.sort((a, b) => a - b);
    return valores[Math.floor(valores.length / 2)];
}

// Medidas de lentes y filtros sobre `planos` (ver aPlanos) con los
// `puntos` del rostro. Un píxel de la frente es ajeno a la piel si su color
// se aparta de ella más de `colorAjeno` y es vivo (más de `colorVivo`).
export function medirRasgos(planos, puntos, { colorAjeno, colorVivo }) {
    const { ancho, alto } = planos;
    const p = (i) => ({ x: puntos[i].x * ancho, y: puntos[i].y * alto });
    const P = PUNTOS;
    const [dExt, dInt, iInt, iExt] = [
        P.ojoDerExterno,
        P.ojoDerInterno,
        P.ojoIzqInterno,
        P.ojoIzqExterno,
    ].map(p);
    // Escalas del rostro: separación entre ojos y largo de un ojo.
    const entreOjos = Math.hypot(iInt.x - dInt.x, iInt.y - dInt.y);
    const ojo =
        (Math.hypot(dInt.x - dExt.x, dInt.y - dExt.y) +
            Math.hypot(iExt.x - iInt.x, iExt.y - iInt.y)) /
        2;
    // Distancia a los vecinos al buscar líneas: más que el grosor de un aro.
    const k = Math.max(2, Math.round(ojo * 0.08));
    const cuadro = (c, r) => ({ x0: c.x - r, x1: c.x + r, y0: c.y - r, y1: c.y + r });

    // La piel de referencia: las dos mejillas, al lado de la nariz (bajo
    // donde llegan los lentes).
    const [m1, m2] = [p(P.mejillaDer), p(P.mejillaIzq)].map((c) =>
        colorDe(planos, cuadro(c, entreOjos * 0.15))
    );
    const piel = { luz: (m1.luz + m2.luz) / 2, cb: (m1.cb + m2.cb) / 2, cr: (m1.cr + m2.cr) / 2 };
    const relativa = (luz) => luz / Math.max(1, piel.luz);

    // Puente de los lentes: una franja angosta entre los ojos, desde algo
    // sobre las cejas hasta el puente de la nariz. Ahí la piel es lisa; un
    // armazón deja una línea horizontal, oscura (o clara si es de metal y
    // brilla) y de otro color que la piel.
    const centroX = (dInt.x + iInt.x) / 2;
    const franja = {
        x0: centroX - entreOjos * 0.2,
        x1: centroX + entreOjos * 0.2,
        y0: p(P.entreCejas).y - entreOjos * 0.45,
        y1: p(P.puenteNariz).y + entreOjos * 0.1,
    };
    const puente = lineaEnPerfil(perfilDeFilas(planos.luz, ancho, alto, franja), k);
    const puenteColor = lineaEnPerfil(perfilDeFilas(planos.cr, ancho, alto, franja), k);

    // Aros: bajo cada ojo, entre el párpado y la mejilla, una línea que
    // cruza el ancho del ojo.
    const bajoOjo = (externo, interno, parpado) => {
        const izq = Math.min(externo.x, interno.x);
        const der = Math.max(externo.x, interno.x);
        const margen = (der - izq) * 0.2;
        return lineaEnColumnas(
            planos.luz,
            ancho,
            alto,
            {
                x0: izq + margen,
                x1: der - margen,
                y0: parpado.y + ojo * 0.15,
                y1: parpado.y + ojo * 0.9,
            },
            k
        );
    };
    const aros =
        (bajoOjo(dExt, dInt, p(P.parpadoDerAbajo)) + bajoOjo(iExt, iInt, p(P.parpadoIzqAbajo))) / 2;

    // Ojos: la zona de cada ojo, contra la piel. Con lentes de sol queda muy
    // oscura.
    const zonaOjo = (externo, interno, arriba, abajo) =>
        colorDe(planos, {
            x0: Math.min(externo.x, interno.x),
            x1: Math.max(externo.x, interno.x),
            y0: arriba.y - ojo * 0.15,
            y1: abajo.y + ojo * 0.15,
        }).luz;
    const ojos = relativa(
        (zonaOjo(dExt, dInt, p(P.parpadoDerArriba), p(P.parpadoDerAbajo)) +
            zonaOjo(iExt, iInt, p(P.parpadoIzqArriba), p(P.parpadoIzqAbajo))) /
            2
    );

    // Punta de la nariz: un filtro de perrito la tapa con una mancha oscura.
    const nariz = colorDe(planos, cuadro(p(P.puntaNariz), entreOjos * 0.15));

    // Frente: parte de sus píxeles con un color muy distinto de la piel
    // y vivo (flores, corazones, stickers). El pelo castaño de un flequillo
    // se parece a la piel; el negro o canoso no tiene color propio.
    const frente = dentro(
        {
            x0: centroX - entreOjos * 1.2,
            x1: centroX + entreOjos * 1.2,
            y0: p(P.lineaDelPelo).y,
            y1: p(P.entreCejas).y - entreOjos * 0.2,
        },
        ancho,
        alto
    );
    let ajenos = 0;
    for (let y = frente.y0; y < frente.y1; y++) {
        for (let x = frente.x0; x < frente.x1; x++) {
            const i = y * ancho + x;
            const cb = planos.cb[i];
            const cr = planos.cr[i];
            if (
                Math.hypot(cb - piel.cb, cr - piel.cr) > colorAjeno &&
                Math.hypot(cb, cr) > colorVivo
            ) {
                ajenos++;
            }
        }
    }

    return {
        puente,
        puenteColor,
        aros,
        ojos,
        luzNariz: relativa(nariz.luz),
        colorNariz: distanciaColor(nariz, piel),
        frenteAjena: ajenos / ((frente.x1 - frente.x0) * (frente.y1 - frente.y0)),
    };
}
