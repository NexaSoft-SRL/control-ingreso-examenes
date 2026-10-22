import { useEffect } from 'react';
import usarConsulta from './usarConsulta';

export const FALLOS_SIN_CONEXION = 3;

// usarConsulta + recarga periódica (no hay WebSockets). El sondeo se detiene
// con la pestaña oculta y se reanuda con una carga inmediata.
//
//   const { datos, sinConexion } = usarSondeo(`/en-curso/${examen}`, { intervalo: 10000 });
//
// `sinConexion` es cierto tras tres fallos seguidos y se apaga al primer acierto.
export default function useSondeo(ruta, { parametros, intervalo = 10000, activa = true } = {}) {
    const consulta = usarConsulta(ruta, { parametros, activa });
    const { recargar } = consulta;
    const encendido = Boolean(ruta) && activa;

    useEffect(() => {
        if (!encendido) return undefined;
        let reloj = null;

        const arrancar = () => {
            if (reloj === null) reloj = setInterval(recargar, intervalo);
        };
        const detener = () => {
            clearInterval(reloj);
            reloj = null;
        };
        const alCambiarVisibilidad = () => {
            if (document.hidden) {
                detener();
            } else {
                recargar();
                arrancar();
            }
        };

        if (!document.hidden) arrancar();
        document.addEventListener('visibilitychange', alCambiarVisibilidad);
        return () => {
            detener();
            document.removeEventListener('visibilitychange', alCambiarVisibilidad);
        };
    }, [encendido, intervalo, recargar]);

    return { ...consulta, sinConexion: consulta.fallos >= FALLOS_SIN_CONEXION };
}
