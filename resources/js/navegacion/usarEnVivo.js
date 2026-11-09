import usarSondeo from '../api/usarSondeo';

// ¿Tiene la cuenta un examen abierto ahora? Abierto es lo que decide el
// servidor: desde una hora antes del inicio hasta el final. Con eso el menú
// marca «En vivo» en «En curso» y el punto de control. Quien toma exámenes lo sabe por
// GET /en-curso; quien solo atiende un aula, por GET /puerta/aulas. Es nulo
// mientras no se sabe (o si la consulta falla).
export default function useEnVivo(cuenta) {
    const permisos = cuenta?.permisos ?? [];
    const toma = permisos.includes('examenes');
    const atiende = permisos.includes('punto_control') || permisos.includes('seguimiento_vivo');
    const ruta = !atiende ? null : toma ? '/en-curso' : '/puerta/aulas';
    const { datos, error } = usarSondeo(ruta, { intervalo: 60000, activa: Boolean(ruta) });

    if (!ruta || error || !Array.isArray(datos)) return null;
    return datos.some((x) => Boolean(toma ? x.abierto : x.abierta));
}
