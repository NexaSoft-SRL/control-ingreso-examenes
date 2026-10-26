import { act, render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// MapLibre necesita WebGL: se reemplaza por un mapa de 316 × 190 px donde
// cada lngLat ya es el punto en pantalla.
const estado = vi.hoisted(() => ({ marcadores: [], eventos: {} }));

vi.mock('maplibre-gl/dist/maplibre-gl.css', () => ({}));
vi.mock('maplibre-gl', () => {
    class Marker {
        constructor({ element }) {
            this.elemento = element;
            this.desplazamiento = [0, 0];
            estado.marcadores.push(this);
        }
        setLngLat(punto) {
            this.punto = punto;
            return this;
        }
        addTo() {
            return this;
        }
        remove() {
            estado.marcadores = estado.marcadores.filter((m) => m !== this);
        }
        getElement() {
            return this.elemento;
        }
        getLngLat() {
            return this.punto;
        }
        setOffset(d) {
            this.desplazamiento = d;
        }
    }
    class Map {
        constructor() {
            this.fuente = { setData: vi.fn() };
        }
        addControl() {}
        addSource() {}
        addLayer() {}
        getSource() {
            return this.fuente;
        }
        getCanvas() {
            return { style: {} };
        }
        getContainer() {
            return { getBoundingClientRect: () => ({ width: 316, height: 190 }) };
        }
        project([x, y]) {
            return { x, y };
        }
        fitBounds() {}
        isStyleLoaded() {
            return true;
        }
        resize() {}
        remove() {}
        on(evento, capaOFuncion, funcion) {
            if (typeof capaOFuncion === 'function') estado.eventos[evento] = capaOFuncion;
            else estado.eventos[`${evento}:${capaOFuncion}`] = funcion;
        }
    }
    return { default: { Map, Marker, NavigationControl: class {} } };
});

const { default: MapaVivo } = await import('./MapaVivo');

const cuadro = ([x, y]) => [
    [x, y],
    [x + 4, y],
    [x + 4, y + 4],
    [x, y + 4],
];

// Análisis Numérico a 390 px: cuatro edificios vecinos con sus aulas.
const EDIFICIOS = [
    ['Aula 606, 608, 608A, 608B, 609, 609A', [150, 150]],
    ['Aula 613, 614, 615, 616', [230, 70]],
    ['Aula 607', [140, 95]],
    ['Aula 612', [228, 72]],
].map(([rotulo, centro], i) => ({
    id: i + 1,
    poligono: cuadro(centro),
    centro,
    color: '#b91c1c',
    marcado: true,
    rotulo,
}));

const cajaDe = (m) => {
    const ancho = m.getElement().textContent.length * 8;
    const x = m.punto[0] + m.desplazamiento[0];
    const y = m.punto[1] + m.desplazamiento[1];
    return { izq: x - ancho / 2, der: x + ancho / 2, arr: y - 12, aba: y + 12 };
};
const seTapan = (a, b) => a.izq < b.der && b.izq < a.der && a.arr < b.aba && b.arr < a.aba;

beforeEach(() => {
    estado.marcadores = [];
    estado.eventos = {};
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe() {}
            disconnect() {}
        }
    );
    vi.stubGlobal('requestAnimationFrame', (fn) => fn());
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('MapaVivo', () => {
    it('a 390 px ningún rótulo de aula queda encima de otro', () => {
        render(<MapaVivo edificios={EDIFICIOS} alto="h-48" />);
        act(() => estado.eventos.load());

        expect(estado.marcadores).toHaveLength(4);
        const cajas = estado.marcadores
            .filter((m) => m.getElement().style.visibility !== 'hidden')
            .map(cajaDe);
        cajas.forEach((caja, i) =>
            cajas.slice(i + 1).forEach((otra) => expect(seTapan(caja, otra)).toBe(false))
        );

        // El 612 sigue a la vista, entero o en su globo con el rótulo completo.
        const aula612 = estado.marcadores[3].getElement();
        expect(aula612.style.visibility).not.toBe('hidden');
        expect(['Aula 612', '612']).toContain(aula612.textContent);
        if (aula612.textContent === '612') expect(aula612).toHaveAttribute('title', 'Aula 612');
    });

    it('vuelve a acomodar los rótulos al moverse el mapa', () => {
        render(<MapaVivo edificios={EDIFICIOS} alto="h-48" />);
        act(() => estado.eventos.load());
        const antes = estado.marcadores.map((m) => m.getElement().textContent);

        act(() => estado.eventos.moveend());

        expect(estado.marcadores.map((m) => m.getElement().textContent)).toEqual(antes);
    });
});
