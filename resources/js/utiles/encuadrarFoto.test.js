import { describe, expect, it } from 'vitest';
import { cuadroAlrededor } from './encuadrarFoto';

describe('cuadroAlrededor', () => {
    it('sin rostro toma el cuadrado central; si es vertical, algo hacia arriba', () => {
        expect(cuadroAlrededor(null, 1200, 1600)).toEqual({ x: 0, y: 120, lado: 1200 });
        expect(cuadroAlrededor(null, 1600, 900)).toEqual({ x: 350, y: 0, lado: 900 });
    });

    it('con rostro lo deja al centro con margen para el cabello y los hombros', () => {
        // Rostro de 300 × 300 en el medio de una foto de 1200 × 1600.
        const cuadro = cuadroAlrededor({ x: 450, y: 500, ancho: 300, alto: 300 }, 1200, 1600);
        expect(cuadro.lado).toBe(780);
        expect(cuadro.x).toBe(210);
        expect(cuadro.y + cuadro.lado / 2).toBe(650);
    });

    it('no se sale de la foto ni pasa su lado menor', () => {
        const cerca = cuadroAlrededor({ x: 10, y: 10, ancho: 200, alto: 200 }, 1000, 1000);
        expect(cerca).toEqual({ x: 0, y: 0, lado: 520 });
        const grande = cuadroAlrededor({ x: 100, y: 100, ancho: 700, alto: 800 }, 900, 1200);
        expect(grande.lado).toBe(900);
        expect(grande.x).toBe(0);
    });

    it('un rostro pequeño no se amplía a un recorte diminuto', () => {
        expect(cuadroAlrededor({ x: 600, y: 600, ancho: 60, alto: 60 }, 1600, 1600).lado).toBe(400);
    });
});
