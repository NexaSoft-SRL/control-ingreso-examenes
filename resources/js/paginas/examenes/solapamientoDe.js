const minutosDe = (texto) => {
    if (typeof texto !== 'string' || !/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/.test(texto)) {
        return null;
    }
    const [horas, minutos] = texto.split(':').map(Number);
    return horas * 60 + minutos;
};

const formatear = (valor) =>
    `${String(Math.floor(valor / 60)).padStart(2, '0')}:${String(valor % 60).padStart(2, '0')}`;

export default function solapamientoDe(hora, duracion, inicio, fin) {
    const desde = minutosDe(hora);
    const existenteDesde = minutosDe(inicio);
    const existenteHasta = minutosDe(fin);

    if (
        desde === null ||
        existenteDesde === null ||
        existenteHasta === null ||
        !Number.isInteger(duracion) ||
        duracion <= 0 ||
        desde + duracion > 1440 ||
        existenteHasta <= existenteDesde
    ) {
        return null;
    }

    const comienzo = Math.max(desde, existenteDesde);
    const termino = Math.min(desde + duracion, existenteHasta);

    if (comienzo >= termino) return null;

    return {
        inicio: formatear(comienzo),
        fin: formatear(termino),
        minutos: termino - comienzo,
    };
}
