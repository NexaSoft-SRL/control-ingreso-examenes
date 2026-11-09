import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    aGris,
    brilloDeFondo,
    brilloMedio,
    deFrente,
    desdeElCentro,
    evaluar,
    MENSAJES,
    medirFoto,
    revisarFoto,
    revisarLuz,
    revisarRostros,
    UMBRALES,
    varianzaLaplaciano,
} from './revisarFoto';

// Un rostro como lo entrega MediaPipe sobre una foto de 300 × 400: caja
// en píxeles y puntos clave normalizados (ojo derecho, ojo izquierdo,
// nariz, boca).
function rostro({ x = 75, y = 120, ancho = 150, alto = 150, nariz = 0.5, ojos = [0.4, 0.6] } = {}) {
    return {
        categories: [{ score: 0.95 }],
        boundingBox: { originX: x, originY: y, width: ancho, height: alto },
        keypoints: [
            { x: ojos[0], y: 0.4 },
            { x: ojos[1], y: 0.4 },
            { x: nariz, y: 0.5 },
            { x: 0.5, y: 0.6 },
        ],
    };
}

function plano(ancho, alto, valor) {
    return new Float32Array(ancho * alto).fill(valor);
}

describe('luz y nitidez', () => {
    it('aGris pasa RGBA a luminancia', () => {
        const gris = aGris(new Uint8ClampedArray([255, 255, 255, 255, 0, 0, 0, 255]), 2, 1);
        expect(gris[0]).toBeCloseTo(255);
        expect(gris[1]).toBe(0);
    });

    it('brilloMedio de toda la imagen o de una zona', () => {
        const gris = plano(10, 10, 200);
        for (let i = 0; i < 50; i++) gris[i] = 0;
        expect(brilloMedio(gris, 10, 10)).toBe(100);
        expect(brilloMedio(gris, 10, 10, { x: 0, y: 5, ancho: 10, alto: 5 })).toBe(200);
    });

    it('el fondo se mide fuera del rostro y el pelo oscuro no lo oscurece', () => {
        // Fondo claro (220) con una franja oscura de pelo junto al rostro.
        const gris = plano(100, 100, 220);
        for (let y = 0; y < 100; y++) {
            for (let x = 15; x < 30; x++) gris[y * 100 + x] = 20;
        }
        const caja = { x: 35, y: 30, ancho: 30, alto: 40 };
        expect(brilloDeFondo(gris, 100, 100, caja)).toBe(220);
        expect(brilloDeFondo(plano(100, 100, 30), 100, 100, caja)).toBe(30);
        // Sin rostro: las esquinas superiores.
        expect(brilloDeFondo(plano(100, 100, 180), 100, 100)).toBe(180);
    });

    it('la varianza del laplaciano baja al desenfocar', () => {
        const nitida = new Float32Array(32 * 32).map((_, i) =>
            (i % 32) % 2 === Math.floor(i / 32) % 2 ? 255 : 0
        );
        const borrosa = new Float32Array(32 * 32).map((_, i) => 100 + (i % 32));
        expect(varianzaLaplaciano(plano(32, 32, 128), 32, 32)).toBe(0);
        expect(varianzaLaplaciano(nitida, 32, 32)).toBeGreaterThan(UMBRALES.nitidezMinima);
        expect(varianzaLaplaciano(borrosa, 32, 32)).toBeLessThan(UMBRALES.nitidezMinima);
    });

    it('revisarLuz avisa foto oscura, fondo oscuro y foto borrosa', () => {
        expect(revisarLuz({ brillo: 130, fondo: 200, nitidez: 200 })).toEqual([]);
        expect(revisarLuz({ brillo: 40, fondo: 30, nitidez: 200 })).toEqual([MENSAJES.oscura]);
        expect(revisarLuz({ brillo: 130, fondo: 40, nitidez: 200 })).toEqual([
            MENSAJES.fondoOscuro,
        ]);
        expect(revisarLuz({ brillo: 130, fondo: 200, nitidez: 10 })).toEqual([MENSAJES.borrosa]);
    });
});

describe('rostros', () => {
    it('un rostro grande, centrado y de frente pasa', () => {
        expect(revisarRostros([rostro()], 300, 400)).toEqual([]);
    });

    it('sin rostro, o con uno de puntaje bajo', () => {
        expect(revisarRostros([], 300, 400)).toEqual([MENSAJES.sinRostro]);
        const dudoso = { ...rostro(), categories: [{ score: 0.2 }] };
        expect(revisarRostros([dudoso], 300, 400)).toEqual([MENSAJES.sinRostro]);
    });

    it('más de un rostro', () => {
        expect(revisarRostros([rostro(), rostro({ x: 10 })], 300, 400)).toEqual([
            MENSAJES.variosRostros,
        ]);
    });

    it('rostro pequeño', () => {
        expect(revisarRostros([rostro({ x: 130, y: 180, ancho: 40, alto: 40 })], 300, 400)).toEqual(
            [MENSAJES.pequeno]
        );
    });

    it('rostro descentrado', () => {
        expect(revisarRostros([rostro({ x: 0 })], 300, 400)).toEqual([MENSAJES.descentrado]);
        expect(revisarRostros([rostro({ y: 250 })], 300, 400)).toEqual([MENSAJES.descentrado]);
    });

    it('de perfil: la nariz fuera de entre los ojos o los ojos juntos', () => {
        expect(deFrente(rostro(), 300)).toBe(true);
        expect(deFrente(rostro({ nariz: 0.55 }), 300)).toBe(true);
        expect(deFrente(rostro({ nariz: 0.59 }), 300)).toBe(false);
        expect(deFrente(rostro({ nariz: 0.65 }), 300)).toBe(false);
        expect(deFrente(rostro({ ojos: [0.48, 0.53], nariz: 0.5 }), 300)).toBe(false);
        expect(revisarRostros([rostro({ nariz: 0.7 })], 300, 400)).toEqual([MENSAJES.deLado]);
    });

    it('desdeElCentro lleva lo hallado en la mitad central a la foto entera', () => {
        const [d] = desdeElCentro([rostro({ x: 100, y: 200, ancho: 100, alto: 100 })], 300, 400);
        expect(d.boundingBox).toMatchObject({ originX: 125, originY: 200, width: 50, height: 50 });
        expect(d.keypoints[2]).toEqual({ x: 0.5, y: 0.5 });
        expect(revisarRostros([d], 300, 400)).toEqual([MENSAJES.pequeno]);
    });
});

describe('evaluar', () => {
    const luz = { ancho: 300, alto: 400, brillo: 130, fondo: 200, nitidez: 200 };

    it('junta errores de rostro y avisos de luz', () => {
        expect(evaluar({ ...luz, detecciones: [], fondo: 40 })).toEqual({
            errores: [MENSAJES.sinRostro],
            avisos: [MENSAJES.fondoOscuro],
        });
    });

    it('sin detector no hay errores, solo avisos', () => {
        expect(evaluar({ ...luz, detecciones: null, brillo: 30 })).toEqual({
            errores: [],
            avisos: [MENSAJES.oscura],
        });
    });
});

describe('medirFoto y revisarFoto', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    // Un navegador simulado: la foto es un gris plano de 300 × 400.
    function simularNavegador(valor = 200) {
        vi.stubGlobal(
            'createImageBitmap',
            vi.fn(async () => ({ width: 300, height: 400, close: vi.fn() }))
        );
        const contexto = {
            drawImage: vi.fn(),
            getImageData: (_x, _y, w, h) => ({
                data: new Uint8ClampedArray(w * h * 4).fill(valor),
            }),
        };
        const crear = document.createElement.bind(document);
        vi.spyOn(document, 'createElement').mockImplementation((etiqueta) => {
            const elemento = crear(etiqueta);
            if (etiqueta === 'canvas') elemento.getContext = () => contexto;
            return elemento;
        });
    }

    const foto = new File(['jpeg'], 'yo.jpg', { type: 'image/jpeg' });

    it('sin createImageBitmap la foto pasa sin observaciones', async () => {
        expect(await revisarFoto(foto)).toEqual({ errores: [], avisos: [] });
    });

    it('con detector usa sus rostros', async () => {
        simularNavegador();
        const detect = vi.fn(() => ({ detections: [rostro(), rostro({ x: 10 })] }));
        const r = await revisarFoto(foto, { obtenerDetector: async () => ({ detect }) });
        expect(r.errores).toEqual([MENSAJES.variosRostros]);
        expect(detect).toHaveBeenCalledTimes(1);
    });

    it('sin rostro lo busca otra vez en el centro ampliado', async () => {
        simularNavegador();
        const detect = vi
            .fn()
            .mockReturnValueOnce({ detections: [] })
            .mockReturnValueOnce({ detections: [rostro({ x: 80, y: 120, ancho: 80, alto: 80 })] });
        const medidas = await medirFoto(foto, { obtenerDetector: async () => ({ detect }) });
        expect(detect).toHaveBeenCalledTimes(2);
        expect(evaluar(medidas).errores).toEqual([MENSAJES.pequeno]);
    });

    it('si el detector no carga, no bloquea: solo luz y nitidez', async () => {
        simularNavegador(20);
        const r = await revisarFoto(foto, {
            obtenerDetector: () => Promise.reject(new Error('sin red')),
        });
        expect(r.errores).toEqual([]);
        expect(r.avisos).toEqual([MENSAJES.oscura, MENSAJES.borrosa]);
    });
});
