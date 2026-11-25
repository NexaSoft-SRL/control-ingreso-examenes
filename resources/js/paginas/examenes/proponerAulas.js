// Propone `cantidad` aulas libres para un examen: primero las de los grupos
// (sugeridas) y las de la facultad; la primera ancla el edificio y el resto
// se busca, en lo posible, en ese mismo edificio para que queden juntas.
// `libre(id)` dice si el aula está libre a la hora del examen.
export default function proponerAulas({ aulas, libre, sugeridas = [], facultad, cantidad }) {
    const rango = (a) => (sugeridas.includes(a.id) ? 0 : 1) * 2 + (a.facultad === facultad ? 0 : 1);
    const libres = aulas
        .filter((a) => libre(a.id))
        .sort(
            (a, b) =>
                rango(a) - rango(b) ||
                String(a.nombre).localeCompare(String(b.nombre), 'es', { numeric: true })
        );
    if (libres.length === 0 || cantidad < 1) return [];

    const [ancla, ...resto] = libres;
    const juntas = (a) => (a.edificio_id && a.edificio_id === ancla.edificio_id ? 0 : 1);
    // El orden es estable: dentro de cada grupo se conserva el de `libres`.
    resto.sort((a, b) => juntas(a) - juntas(b));
    return [ancla, ...resto].slice(0, cantidad).map((a) => a.id);
}
