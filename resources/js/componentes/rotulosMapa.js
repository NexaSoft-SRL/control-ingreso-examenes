// Acomoda los rótulos del mapa vivo (marcadores de MapLibre) para que no se
// tapen entre sí ni salgan del mapa. Cada rótulo prueba ocho posiciones
// alrededor de su edificio; si ninguna cabe, pasa a un globo con su número
// («Aula 613, 614, 615» → «613 +2», el texto entero queda en el `title`) y
// prueba otra vez, también sobre el edificio. Si tampoco cabe, se oculta:
// nunca queda encima de otro.

const MARGEN = 4;
const SEPARACION = 6;

// 'Aula 613, 614, 615, 616' → '613 +3'; 'Aula 612' → '612'.
export function abreviar(rotulo) {
    const nombres = String(rotulo ?? '')
        .replace(/^Aula\s+/, '')
        .split(',')
        .map((n) => n.trim())
        .filter(Boolean);
    if (nombres.length === 0) return '';
    return nombres.length > 1 ? `${nombres[0]} +${nombres.length - 1}` : nombres[0];
}

const candidatos = (ancho, alto, conCentro) => [
    [0, -alto - SEPARACION],
    [0, alto + SEPARACION],
    [ancho / 2 + SEPARACION, 0],
    [-ancho / 2 - SEPARACION, 0],
    [ancho / 2 + SEPARACION, -alto - SEPARACION],
    [-ancho / 2 - SEPARACION, -alto - SEPARACION],
    [ancho / 2 + SEPARACION, alto + SEPARACION],
    [-ancho / 2 - SEPARACION, alto + SEPARACION],
    ...(conCentro ? [[0, 0]] : []),
];

const separadas = (caja, otra) =>
    caja.derecha + MARGEN <= otra.izquierda ||
    caja.izquierda - MARGEN >= otra.derecha ||
    caja.abajo + MARGEN <= otra.arriba ||
    caja.arriba - MARGEN >= otra.abajo;

function mostrar(elemento, texto, globo, completo) {
    elemento.textContent = texto;
    elemento.style.visibility = '';
    elemento.style.borderRadius = globo ? '9999px' : '';
    if (globo) elemento.title = completo;
    else elemento.removeAttribute('title');
}

// `mapa` necesita getContainer() y project(lngLat); cada marcador,
// getElement(), getLngLat() y setOffset([x, y]).
export function acomodarRotulos(mapa, marcadores) {
    const { width, height } = mapa.getContainer().getBoundingClientRect();
    const ocupados = [];

    marcadores.forEach((marcador) => {
        const elemento = marcador.getElement();
        elemento.dataset.rotulo ??= elemento.textContent;
        const completo = elemento.dataset.rotulo;
        const corto = abreviar(completo);
        const punto = mapa.project(marcador.getLngLat());
        const formas = [[completo, false]];
        if (corto && corto !== completo) formas.push([corto, true]);

        for (const [texto, globo] of formas) {
            mostrar(elemento, texto, globo, completo);
            const ancho = elemento.offsetWidth || texto.length * 8;
            const alto = elemento.offsetHeight || 24;

            for (const [x, y] of candidatos(ancho, alto, globo)) {
                const caja = {
                    izquierda: punto.x + x - ancho / 2,
                    derecha: punto.x + x + ancho / 2,
                    arriba: punto.y + y - alto / 2,
                    abajo: punto.y + y + alto / 2,
                };
                const dentro =
                    caja.izquierda >= MARGEN &&
                    caja.derecha <= width - MARGEN &&
                    caja.arriba >= MARGEN &&
                    caja.abajo <= height - MARGEN;

                if (dentro && ocupados.every((otra) => separadas(caja, otra))) {
                    marcador.setOffset([x, y]);
                    ocupados.push(caja);
                    return;
                }
            }
        }

        mostrar(elemento, completo, false, completo);
        elemento.style.visibility = 'hidden';
    });
}
