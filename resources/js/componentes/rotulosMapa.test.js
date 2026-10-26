import { describe, expect, it } from 'vitest';
import { abreviar, acomodarRotulos } from './rotulosMapa';

// Un mapa de `ancho` × `alto` px donde cada lngLat ya es el punto en pantalla.
const mapa = (ancho, alto) => ({
    getContainer: () => ({ getBoundingClientRect: () => ({ width: ancho, height: alto }) }),
    project: ([x, y]) => ({ x, y }),
});

// Como el Marker de MapLibre: un elemento, su punto y su desplazamiento.
function marcador(texto, punto) {
    const elemento = document.createElement('div');
    elemento.className = 'mapa-rotulo';
    elemento.textContent = texto;
    let desplazamiento = [0, 0];
    return {
        punto,
        getElement: () => elemento,
        getLngLat: () => punto,
        setOffset: (d) => {
            desplazamiento = d;
        },
        get desplazamiento() {
            return desplazamiento;
        },
    };
}

// En jsdom no hay medidas: el rótulo mide 8 px por letra y 24 de alto.
const cajaDe = (m) => {
    const elemento = m.getElement();
    const ancho = elemento.textContent.length * 8;
    const x = m.punto[0] + m.desplazamiento[0];
    const y = m.punto[1] + m.desplazamiento[1];
    return { izq: x - ancho / 2, der: x + ancho / 2, arr: y - 12, aba: y + 12 };
};

const visibles = (marcadores) =>
    marcadores.filter((m) => m.getElement().style.visibility !== 'hidden');

const seTapan = (a, b) => a.izq < b.der && b.izq < a.der && a.arr < b.aba && b.arr < a.aba;

function sinSuperponer(marcadores, ancho, alto) {
    const cajas = visibles(marcadores).map(cajaDe);
    cajas.forEach((caja, i) => {
        expect(caja.izq).toBeGreaterThanOrEqual(0);
        expect(caja.der).toBeLessThanOrEqual(ancho);
        expect(caja.arr).toBeGreaterThanOrEqual(0);
        expect(caja.aba).toBeLessThanOrEqual(alto);
        cajas.slice(i + 1).forEach((otra) => expect(seTapan(caja, otra)).toBe(false));
    });
}

describe('rótulos del mapa vivo', () => {
    it('abrevia el rótulo de las aulas a su número', () => {
        expect(abreviar('Aula 612')).toBe('612');
        expect(abreviar('Aula 613, 614, 615, 616')).toBe('613 +3');
        expect(abreviar('Edificio Académico 2')).toBe('Edificio Académico 2');
    });

    it('con lugar deja cada rótulo entero sin tocar a otro', () => {
        const marcadores = [
            marcador('Aula 691B, 691A', [150, 100]),
            marcador('Aula 691E', [160, 110]),
        ];

        acomodarRotulos(mapa(400, 300), marcadores);

        expect(marcadores.map((m) => m.getElement().textContent)).toEqual([
            'Aula 691B, 691A',
            'Aula 691E',
        ]);
        expect(visibles(marcadores)).toHaveLength(2);
        sinSuperponer(marcadores, 400, 300);
    });

    it('a 390 px el aula que no cabe pasa a un globo con su número, no encima de otra', () => {
        // Análisis Numérico: edificios vecinos en un mapa angosto.
        const marcadores = [
            marcador('Aula 606, 608, 608A, 608B, 609, 609A', [150, 150]),
            marcador('Aula 613, 614, 615, 616', [230, 70]),
            marcador('Aula 607', [140, 95]),
            marcador('Aula 612', [228, 72]),
        ];

        acomodarRotulos(mapa(316, 190), marcadores);

        sinSuperponer(marcadores, 316, 190);
        const aula612 = marcadores[3].getElement();
        if (aula612.style.visibility !== 'hidden') {
            expect(['Aula 612', '612']).toContain(aula612.textContent);
        }
        expect(visibles(marcadores).length).toBeGreaterThanOrEqual(3);
    });

    it('el globo guarda el rótulo entero y lo devuelve cuando vuelve a caber', () => {
        const marcadores = [marcador('Aula 613, 614', [60, 40]), marcador('Aula 612', [60, 40])];

        acomodarRotulos(mapa(130, 80), marcadores);

        sinSuperponer(marcadores, 130, 80);
        const globo = marcadores[1].getElement();
        expect(globo.textContent).toBe('612');
        expect(globo).toHaveAttribute('title', 'Aula 612');

        acomodarRotulos(mapa(600, 400), marcadores);

        expect(globo.textContent).toBe('Aula 612');
        expect(globo).not.toHaveAttribute('title');
        sinSuperponer(marcadores, 600, 400);
    });

    it('si ni el globo cabe, lo oculta en vez de taparlo', () => {
        const marcadores = [marcador('Aula 61', [30, 15]), marcador('Aula 62', [30, 15])];

        acomodarRotulos(mapa(60, 30), marcadores);

        expect(marcadores[1].getElement().style.visibility).toBe('hidden');
        sinSuperponer(marcadores, 60, 30);
    });
});
