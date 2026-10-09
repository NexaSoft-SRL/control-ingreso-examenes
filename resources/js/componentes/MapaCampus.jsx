import PropTypes from 'prop-types';
import { usarFacultades } from '../sesion/SesionContexto';

// Mapa del campus dibujado con los polígonos de los edificios
// (GET /api/edificios, que guarda `usarEdificios()`), proyectados a un SVG.
// Cada facultad conserva su color. No hay mapa base.
//
// `edificios` y `caja` (`meta.caja`) llegan por prop. `facultad` limita el
// mapa a los edificios de una sigla y acerca la vista; sin ella se ve el
// campus completo. `resaltados` marca edificios: cada elemento es el nombre
// de un aula (texto) o el id de un edificio (número). `contorno` marca
// edificios solo con borde (el punto de partida), con la misma regla.
// `rotulos` cambia el texto de un edificio por aula ({ '617': 'Aula 617' }) y
// `rotulosEdificio`, por id de edificio.
export const MAPA = { ancho: 1000, alto: 644 };
const SIN_COLOR = '#475569';
// Lo marcado (las aulas de un examen) va en el color de acción: el rojo de
// la FCyT se leería como peligro.
const MARCADO = '#2563eb';

export function edificioDeAula(edificios, aula, facultad = null) {
    return (
        (edificios ?? []).find(
            (e) => (!facultad || e.facultad === facultad) && (e.aulas ?? []).includes(aula)
        ) ?? null
    );
}

export function pisoDeAula(edificios, aula, facultad = null) {
    return (
        edificioDeAula(edificios, aula, facultad)?.pisos?.find((p) => p.aulas.includes(aula))
            ?.nombre ?? null
    );
}

// Longitud y latitud → lienzo de 1000 × 644 (proyección lineal: a la escala
// de un campus no hace falta otra).
export function proyectar([lon, lat], caja) {
    const [lonMin, lonMax] = caja.lon;
    const [latMin, latMax] = caja.lat;
    const x = ((lon - lonMin) / (lonMax - lonMin || 1)) * MAPA.ancho;
    const y = ((latMax - lat) / (latMax - latMin || 1)) * MAPA.alto;
    return [Math.round(x * 10) / 10, Math.round(y * 10) / 10];
}

function cajaDe(edificios) {
    const puntos = edificios.flatMap((e) => e.poligono ?? []);
    const lons = puntos.map((p) => p[0]);
    const lats = puntos.map((p) => p[1]);
    return {
        lon: [Math.min(...lons), Math.max(...lons)],
        lat: [Math.min(...lats), Math.max(...lats)],
    };
}

function encuadre(edificios) {
    const puntos = edificios.flatMap((e) => e.puntos);
    if (puntos.length === 0) return { x: 0, y: 0, ancho: MAPA.ancho, alto: MAPA.alto };
    const xs = puntos.map((p) => p[0]);
    const ys = puntos.map((p) => p[1]);
    const margen = 40;
    const x = Math.min(...xs) - margen;
    const y = Math.min(...ys) - margen;
    return { x, y, ancho: Math.max(...xs) - x + margen, alto: Math.max(...ys) - y + margen };
}

export default function MapaCampus({
    edificios = [],
    caja = null,
    facultad = null,
    resaltados = [],
    contorno = [],
    rotulos = {},
    rotulosEdificio = {},
    tamanoRotulo = 30,
    seleccionado = null,
    onElegir,
    alto = 'h-72',
}) {
    const facultades = usarFacultades();
    const colorDe = (sigla) => facultades.find((f) => f.sigla === sigla)?.color ?? SIN_COLOR;
    const conPoligono = edificios.filter((e) => (e.poligono ?? []).length > 0);
    const limites = caja ?? (conPoligono.length > 0 ? cajaDe(conPoligono) : null);
    const proyectados = limites
        ? conPoligono.map((e) => ({
              ...e,
              puntos: e.poligono.map((p) => proyectar(p, limites)),
              medio: proyectar(e.centro ?? e.poligono[0], limites),
          }))
        : [];
    const visibles = facultad ? proyectados.filter((e) => e.facultad === facultad) : proyectados;
    const vista = encuadre(visibles);
    const idsDe = (lista) =>
        new Set(
            lista
                .map((a) => (typeof a === 'number' ? a : edificioDeAula(visibles, a, facultad)?.id))
                .filter((id) => id !== undefined && id !== null)
        );
    const idsResaltados = idsDe(resaltados);
    const idsContorno = idsDe(contorno);
    const hayResaltados = idsResaltados.size > 0;
    // Rótulo propio por aula («Aula 617», «Aquí»); si no hay, el nombre del edificio.
    const rotuloDe = (e) =>
        rotulosEdificio[e.id] ??
        Object.entries(rotulos).find(([aula]) => (e.aulas ?? []).includes(aula))?.[1] ??
        e.nombre;
    const escala = vista.ancho / 1000;

    return (
        <div
            className={`w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-50 ${alto}`}
        >
            <svg
                viewBox={`${vista.x} ${vista.y} ${vista.ancho} ${vista.alto}`}
                className="h-full w-full"
                role="img"
                aria-label="Mapa de edificios del campus"
            >
                {visibles.map((e) => {
                    const activo = idsResaltados.has(e.id);
                    const elegido = seleccionado === e.id;
                    const color = colorDe(e.facultad);
                    const soloContorno = idsContorno.has(e.id);
                    const atenuado = hayResaltados && !activo && !soloContorno;
                    return (
                        <g
                            key={e.id}
                            onClick={onElegir ? () => onElegir(e) : undefined}
                            className={onElegir ? 'cursor-pointer' : undefined}
                        >
                            <title>
                                {e.nombre} · {e.facultad}
                            </title>
                            <polygon
                                points={e.puntos.map((p) => p.join(',')).join(' ')}
                                fill={
                                    soloContorno
                                        ? '#ffffff'
                                        : atenuado
                                          ? '#e2e8f0'
                                          : activo
                                            ? MARCADO
                                            : color
                                }
                                fillOpacity={
                                    atenuado || soloContorno ? 1 : elegido || activo ? 0.95 : 0.6
                                }
                                stroke={
                                    soloContorno
                                        ? '#0f172a'
                                        : atenuado
                                          ? '#94a3b8'
                                          : elegido
                                            ? '#0f172a'
                                            : activo
                                              ? '#1e3a8a'
                                              : color
                                }
                                strokeDasharray={
                                    soloContorno ? `${8 * escala} ${6 * escala}` : undefined
                                }
                                strokeWidth={
                                    (elegido ? 4 : activo || soloContorno ? 3 : 1.5) * escala
                                }
                            />
                            {(activo || elegido || soloContorno) && (
                                <text
                                    x={Math.min(
                                        Math.max(e.medio[0], vista.x + 170 * escala),
                                        vista.x + vista.ancho - 170 * escala
                                    )}
                                    y={e.medio[1] - (tamanoRotulo + 4) * escala}
                                    textAnchor="middle"
                                    stroke="white"
                                    strokeWidth={(tamanoRotulo / 4) * escala}
                                    paintOrder="stroke"
                                    fontSize={tamanoRotulo * escala}
                                    className="fill-slate-900 font-semibold"
                                >
                                    {rotuloDe(e)}
                                </text>
                            )}
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

const elemento = PropTypes.oneOfType([PropTypes.string, PropTypes.number]);

MapaCampus.propTypes = {
    edificios: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            facultad: PropTypes.string,
            nombre: PropTypes.string,
            poligono: PropTypes.arrayOf(PropTypes.arrayOf(PropTypes.number)),
            centro: PropTypes.arrayOf(PropTypes.number),
            aulas: PropTypes.arrayOf(PropTypes.string),
            pisos: PropTypes.array,
        })
    ),
    caja: PropTypes.shape({
        lon: PropTypes.arrayOf(PropTypes.number).isRequired,
        lat: PropTypes.arrayOf(PropTypes.number).isRequired,
    }),
    facultad: PropTypes.string,
    resaltados: PropTypes.arrayOf(elemento),
    contorno: PropTypes.arrayOf(elemento),
    rotulos: PropTypes.objectOf(PropTypes.string),
    rotulosEdificio: PropTypes.objectOf(PropTypes.string),
    tamanoRotulo: PropTypes.number,
    seleccionado: PropTypes.number,
    onElegir: PropTypes.func,
    alto: PropTypes.string,
};
