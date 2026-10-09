import { useEffect, useRef } from 'react';
import PropTypes from 'prop-types';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

// Mapa del campus con MapLibre, como en GENDA. Con VITE_CARTO_API_KEY el mapa
// base es el claro de CARTO, con los mismos ajustes de color que GENDA. Sin
// clave CARTO sobreimprime «API KEY REQUIRED», así que se usa OpenFreeMap con
// el estilo Positron, que no pide clave. La atribución debe quedar visible.
const CENTRO_UMSS = [-66.1457, -17.3935];
const CLAVE = import.meta.env.VITE_CARTO_API_KEY;
const ESTILO_SIN_CLAVE = 'https://tiles.openfreemap.org/styles/positron';
const ESTILO_CARTO = {
    version: 8,
    sources: {
        carto: {
            type: 'raster',
            tiles: ['a', 'b', 'c'].map(
                (s) =>
                    `https://${s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png?key=${CLAVE}`
            ),
            tileSize: 256,
            attribution: '© OpenStreetMap · © CARTO',
        },
    },
    layers: [
        {
            id: 'carto',
            type: 'raster',
            source: 'carto',
            paint: {
                'raster-saturation': 0.55,
                'raster-contrast': 0.08,
                'raster-brightness-max': 0.97,
            },
        },
    ],
};
const ESTILO = CLAVE ? ESTILO_CARTO : ESTILO_SIN_CLAVE;

const es = (propiedad) => ['boolean', ['get', propiedad], false];
const encima = ['boolean', ['feature-state', 'encima'], false];

function limites(edificios) {
    const puntos = edificios.flatMap((e) => e.poligono);
    if (puntos.length === 0) return null;
    const lons = puntos.map((p) => p[0]);
    const lats = puntos.map((p) => p[1]);
    return [
        [Math.min(...lons), Math.min(...lats)],
        [Math.max(...lons), Math.max(...lats)],
    ];
}

// El índice es el id del elemento: el estado por elemento de MapLibre pide
// ids numéricos.
function coleccion(edificios) {
    return {
        type: 'FeatureCollection',
        features: edificios.map((e, i) => ({
            type: 'Feature',
            id: i,
            properties: {
                edificio: e.id,
                color: e.color,
                marcado: e.marcado,
                atenuado: e.atenuado,
                contorno: e.contorno,
            },
            geometry: { type: 'Polygon', coordinates: [[...e.poligono, e.poligono[0]]] },
        })),
    };
}

export default function MapaVivo({ edificios, onElegir, alto }) {
    const contenedor = useRef(null);
    const mapa = useRef(null);
    const rotulos = useRef([]);
    const datos = useRef(edificios);
    const alElegir = useRef(onElegir);
    datos.current = edificios;
    alElegir.current = onElegir;

    const dibujar = (animar) => {
        const m = mapa.current;
        const fuente = m?.getSource('edificios');
        if (!fuente) return;
        const lista = datos.current;
        fuente.setData(coleccion(lista));

        rotulos.current.forEach((r) => r.remove());
        rotulos.current = lista
            .filter((e) => e.rotulo)
            .map((e) => {
                const texto = document.createElement('div');
                texto.className = 'mapa-rotulo';
                texto.textContent = e.rotulo;
                return new maplibregl.Marker({ element: texto, anchor: 'bottom', offset: [0, -6] })
                    .setLngLat(e.centro ?? e.poligono[0])
                    .addTo(m);
            });

        const destacados = lista.filter((e) => e.marcado);
        const caja = limites(destacados.length > 0 ? destacados : lista);
        if (caja) {
            m.fitBounds(caja, {
                padding: 48,
                maxZoom: destacados.length > 0 ? 17.5 : 18,
                duration: animar ? 600 : 0,
            });
        }
    };

    useEffect(() => {
        const m = new maplibregl.Map({
            container: contenedor.current,
            style: ESTILO,
            center: CENTRO_UMSS,
            zoom: 16,
            attributionControl: { compact: true },
        });
        mapa.current = m;
        m.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        const observador = new ResizeObserver(() => m.resize());
        observador.observe(contenedor.current);
        let sobre = null;

        m.on('load', () => {
            m.addSource('edificios', { type: 'geojson', data: coleccion([]) });
            m.addLayer({
                id: 'edificios-relleno',
                type: 'fill',
                source: 'edificios',
                paint: {
                    'fill-color': ['case', es('contorno'), '#ffffff', ['get', 'color']],
                    'fill-opacity': [
                        'case',
                        es('contorno'),
                        0.7,
                        es('marcado'),
                        0.55,
                        ['all', encima, es('atenuado')],
                        0.22,
                        encima,
                        0.42,
                        es('atenuado'),
                        0.06,
                        0.22,
                    ],
                    'fill-opacity-transition': { duration: 150 },
                },
            });
            m.addLayer({
                id: 'edificios-borde',
                type: 'line',
                source: 'edificios',
                filter: ['!', es('contorno')],
                paint: {
                    'line-color': ['get', 'color'],
                    'line-width': [
                        'case',
                        es('marcado'),
                        3.5,
                        ['all', encima, es('atenuado')],
                        1.8,
                        encima,
                        3,
                        1.8,
                    ],
                    'line-opacity': ['case', encima, 1, es('atenuado'), 0.25, 1],
                },
            });
            m.addLayer({
                id: 'edificios-contorno',
                type: 'line',
                source: 'edificios',
                filter: es('contorno'),
                paint: { 'line-color': '#0f172a', 'line-width': 2.5, 'line-dasharray': [2, 1.5] },
            });

            m.on('click', 'edificios-relleno', (evento) => {
                const id = evento.features?.[0]?.properties?.edificio;
                const edificio = datos.current.find((e) => e.id === id);
                if (edificio && alElegir.current) alElegir.current(edificio);
            });
            m.on('mousemove', 'edificios-relleno', (evento) => {
                const id = evento.features?.[0]?.id;
                if (typeof id !== 'number' || id === sobre) return;
                if (alElegir.current) m.getCanvas().style.cursor = 'pointer';
                if (sobre !== null) {
                    m.setFeatureState({ source: 'edificios', id: sobre }, { encima: false });
                }
                sobre = id;
                m.setFeatureState({ source: 'edificios', id }, { encima: true });
            });
            m.on('mouseleave', 'edificios-relleno', () => {
                m.getCanvas().style.cursor = '';
                if (sobre !== null) {
                    m.setFeatureState({ source: 'edificios', id: sobre }, { encima: false });
                }
                sobre = null;
            });

            // La atribución queda plegada tras su botón: abierta tapa un mapa pequeño.
            contenedor.current
                ?.querySelector('.maplibregl-ctrl-attrib')
                ?.classList.remove('maplibregl-compact-show');
            // La atribución queda plegada tras su botón: abierta tapa un mapa pequeño.
            contenedor.current
                ?.querySelector('.maplibregl-ctrl-attrib')
                ?.classList.remove('maplibregl-compact-show');
            dibujar(false);
        });

        return () => {
            observador.disconnect();
            rotulos.current.forEach((r) => r.remove());
            m.remove();
            mapa.current = null;
        };
    }, []);

    const huella = JSON.stringify(
        edificios.map((e) => [e.id, e.color, e.marcado, e.atenuado, e.contorno, e.rotulo])
    );
    useEffect(() => {
        if (mapa.current?.isStyleLoaded()) dibujar(true);
    }, [huella]);

    return (
        <div
            ref={contenedor}
            role="img"
            aria-label="Mapa de edificios del campus"
            className={`w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-50 ${alto}`}
        />
    );
}

MapaVivo.propTypes = {
    edificios: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            poligono: PropTypes.arrayOf(PropTypes.arrayOf(PropTypes.number)).isRequired,
            centro: PropTypes.arrayOf(PropTypes.number),
            color: PropTypes.string.isRequired,
            marcado: PropTypes.bool,
            atenuado: PropTypes.bool,
            contorno: PropTypes.bool,
            rotulo: PropTypes.string,
        })
    ).isRequired,
    onElegir: PropTypes.func,
    alto: PropTypes.string.isRequired,
};
