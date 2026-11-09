import { describe, expect, it } from 'vitest';
import { aPlanos, lineaEnPerfil, medirRasgos, PUNTOS } from './rasgos';
import { UMBRALES } from './revisarFoto';

const LADO = 200;
const PIEL = [200, 150, 120];

// Un rostro de mentira: piel lisa de 200 × 200 y los puntos que usa
// medirRasgos en su lugar (ojos a la altura 80, nariz en 120).
function rostro() {
    const rgba = new Uint8ClampedArray(LADO * LADO * 4);
    for (let i = 0; i < LADO * LADO; i++) rgba.set([...PIEL, 255], i * 4);
    const puntos = Array.from({ length: 478 }, () => ({ x: 0.5, y: 0.5 }));
    const poner = (nombre, x, y) => {
        puntos[PUNTOS[nombre]] = { x: x / LADO, y: y / LADO };
    };
    poner('ojoDerExterno', 50, 80);
    poner('ojoDerInterno', 85, 80);
    poner('ojoIzqInterno', 115, 80);
    poner('ojoIzqExterno', 150, 80);
    poner('parpadoDerArriba', 67, 75);
    poner('parpadoDerAbajo', 67, 85);
    poner('parpadoIzqArriba', 133, 75);
    poner('parpadoIzqAbajo', 133, 85);
    poner('lineaDelPelo', 100, 20);
    poner('entreCejas', 100, 72);
    poner('puenteNariz', 100, 85);
    poner('puntaNariz', 100, 120);
    poner('mejillaDer', 65, 125);
    poner('mejillaIzq', 135, 125);
    return { rgba, puntos };
}

function pintar(rgba, x0, y0, x1, y1, color) {
    for (let y = y0; y < y1; y++) {
        for (let x = x0; x < x1; x++) rgba.set([...color, 255], (y * LADO + x) * 4);
    }
}

const medirDe = ({ rgba, puntos }) => medirRasgos(aPlanos(rgba, LADO, LADO), puntos, UMBRALES);

describe('lineaEnPerfil', () => {
    it('un degradé o una sombra suave no son una línea', () => {
        expect(lineaEnPerfil([100, 110, 120, 130, 140, 150, 160], 2)).toBe(0);
        expect(lineaEnPerfil([150, 150, 150, 150, 150, 150, 150], 2)).toBe(0);
    });

    it('una línea fina, oscura o clara, da cuánto se aparta de sus vecinos', () => {
        expect(lineaEnPerfil([150, 150, 150, 40, 150, 150, 150], 2)).toBe(110);
        expect(lineaEnPerfil([120, 120, 120, 220, 125, 125, 125], 2)).toBe(95);
    });
});

describe('medirRasgos', () => {
    it('un rostro limpio no da lentes ni filtros', () => {
        const m = medirDe(rostro());
        expect(m.puente).toBe(0);
        expect(m.puenteColor).toBe(0);
        expect(m.aros).toBe(0);
        expect(m.ojos).toBeCloseTo(1);
        expect(m.luzNariz).toBeCloseTo(1);
        expect(m.colorNariz).toBeCloseTo(0);
        expect(m.frenteAjena).toBe(0);
    });

    it('el puente de unos lentes deja una línea entre los ojos', () => {
        const r = rostro();
        pintar(r.rgba, 80, 70, 120, 72, [30, 30, 30]);
        expect(medirDe(r).puente).toBeGreaterThan(UMBRALES.lentesPuente);
    });

    it('un puente de metal claro se ve en el color aunque no cambie la luz', () => {
        const r = rostro();
        // Gris de la misma luz que la piel: solo cambia el color.
        pintar(r.rgba, 80, 70, 120, 72, [164, 164, 164]);
        const m = medirDe(r);
        expect(m.puente).toBeLessThan(UMBRALES.lentesPuente);
        expect(m.puenteColor).toBeGreaterThan(UMBRALES.lentesPuenteColor);
    });

    it('el aro bajo los ojos', () => {
        const r = rostro();
        pintar(r.rgba, 40, 92, 160, 94, [30, 30, 30]);
        expect(medirDe(r).aros).toBeGreaterThan(UMBRALES.lentesAros);
    });

    it('lentes de sol: los ojos muy oscuros', () => {
        const r = rostro();
        pintar(r.rgba, 45, 68, 155, 92, [20, 20, 25]);
        expect(medirDe(r).ojos).toBeLessThan(UMBRALES.ojosOscuros);
    });

    it('nariz de perrito: la punta de la nariz oscura', () => {
        const r = rostro();
        pintar(r.rgba, 90, 112, 110, 128, [25, 20, 20]);
        const m = medirDe(r);
        expect(m.luzNariz).toBeLessThan(UMBRALES.narizOscura);
        expect(m.colorNariz).toBeGreaterThan(UMBRALES.narizColor);
    });

    it('algo de color vivo en la frente; pelo negro, no', () => {
        const flores = rostro();
        pintar(flores.rgba, 70, 25, 130, 40, [240, 80, 160]);
        expect(medirDe(flores).frenteAjena).toBeGreaterThan(UMBRALES.frenteAjena);
        const flequillo = rostro();
        pintar(flequillo.rgba, 40, 20, 160, 45, [20, 20, 20]);
        expect(medirDe(flequillo).frenteAjena).toBe(0);
    });
});
