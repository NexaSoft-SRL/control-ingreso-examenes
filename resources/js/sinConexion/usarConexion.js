import { useCallback, useEffect, useRef, useState } from 'react';
import { api, fallosDeRedSeguidos, suscribirRed } from '../api/cliente';
import { pendientesTotales } from './almacen';
import { sincronizarTodo } from './flujo';

// Cuántas peticiones seguidas sin respuesta hacen falta para dar la conexión
// por caída (una sola puede ser una consulta lenta).
export const FALLOS_PARA_CAIDA = 2;
// Cada cuánto se mira lo pendiente de este navegador.
const REVISION_MS = 2000;
// Con la conexión caída, cada cuánto se prueba si ya volvió.
const SONDEO_MS = 5000;
// Cuánto se deja a la vista el aviso de que todo volvió.
export const VISIBLE_MS = 4000;

const sinRed = () => typeof navigator !== 'undefined' && navigator.onLine === false;
const hayCaida = () => sinRed() || fallosDeRedSeguidos() >= FALLOS_PARA_CAIDA;

// Estado de la conexión para toda la aplicación.
//   fase       'conectado'    todo en orden: no se muestra nada
//              'sin_conexion' no hay red: lo decidido se guarda en este equipo
//              'enviando'     volvió la red y se envía lo guardado
//              'pendiente'    volvió la red pero quedan lecturas sin enviar
//              'sincronizado' volvió la red y no queda nada por enviar
//   pendientes lecturas decididas sin conexión que aún no llegaron al servidor
//   enviadas   las que se enviaron al volver la red
//
// Hay caída si el navegador dice que no tiene red o si fallan seguidas las
// peticiones sin que el servidor responda (un 401 o un 500 es una respuesta:
// no es caída). Al volver, envía lo pendiente de todas las aulas, esté o no
// abierta la pantalla de la puerta.
export default function useConexion() {
    const [caida, setCaida] = useState(hayCaida);
    const [pendientes, setPendientes] = useState(pendientesTotales);
    const [enviadas, setEnviadas] = useState(0);
    const [fase, setFase] = useState(() => {
        if (hayCaida()) return 'sin_conexion';
        return pendientesTotales() > 0 ? 'pendiente' : 'conectado';
    });
    const anterior = useRef(caida);

    const revisar = useCallback(() => {
        setCaida(hayCaida());
        setPendientes(pendientesTotales());
    }, []);

    // Pregunta al servidor si ya responde: cualquier respuesta (aunque sea un
    // 401) cuenta como conexión.
    const sondear = useCallback(() => {
        api.get('/auth/sesion', { silencioso: true }).catch(() => {});
    }, []);

    // La caída sale del navegador (online/offline) y de las peticiones. Al
    // volver la red del navegador se comprueba de inmediato, sin esperar.
    useEffect(() => {
        const alVolver = () => {
            revisar();
            sondear();
        };
        const baja = suscribirRed(revisar);
        window.addEventListener('online', alVolver);
        window.addEventListener('offline', revisar);
        window.addEventListener('storage', revisar);
        const reloj = setInterval(revisar, REVISION_MS);
        return () => {
            baja();
            window.removeEventListener('online', alVolver);
            window.removeEventListener('offline', revisar);
            window.removeEventListener('storage', revisar);
            clearInterval(reloj);
        };
    }, [revisar, sondear]);

    // Envía todo lo pendiente y dice cómo quedó.
    const enviar = useCallback(
        async (alTerminar) => {
            const aplicadas = await sincronizarTodo(api);
            revisar();
            alTerminar(aplicadas);
        },
        [revisar]
    );

    // Caída → «sin conexión». Vuelta → se envía lo pendiente.
    useEffect(() => {
        const veniaCaida = anterior.current;
        anterior.current = caida;
        if (caida) {
            setFase('sin_conexion');
            return undefined;
        }
        if (!veniaCaida) return undefined;
        let vivo = true;
        setEnviadas(0);
        if (pendientesTotales() === 0) {
            setFase('sincronizado');
            return undefined;
        }
        setFase('enviando');
        enviar((aplicadas) => {
            if (!vivo) return;
            setEnviadas(aplicadas);
            setFase(pendientesTotales() > 0 ? 'pendiente' : 'sincronizado');
        });
        return () => {
            vivo = false;
        };
    }, [caida, enviar]);

    // Lo pendiente que quedó de antes (se cerró la pantalla sin conexión):
    // se intenta enviar al abrir, si hay red.
    useEffect(() => {
        if (hayCaida() || pendientesTotales() === 0) return undefined;
        let vivo = true;
        enviar(() => {
            if (vivo) setFase(pendientesTotales() > 0 ? 'pendiente' : 'conectado');
        });
        return () => {
            vivo = false;
        };
    }, [enviar]);

    // Lo pendiente que se envió por otro lado (el panel de la puerta) cierra
    // el aviso; y «sincronizado» se quita solo a los pocos segundos.
    useEffect(() => {
        if (fase === 'pendiente' && pendientes === 0 && !caida) setFase('sincronizado');
    }, [fase, pendientes, caida]);

    useEffect(() => {
        if (fase !== 'sincronizado') return undefined;
        const reloj = setTimeout(() => setFase('conectado'), VISIBLE_MS);
        return () => clearTimeout(reloj);
    }, [fase]);

    // Con la conexión caída se prueba cada tanto si ya volvió: una pantalla
    // quieta no hace peticiones y no se enteraría.
    useEffect(() => {
        if (!caida) return undefined;
        const reloj = setInterval(sondear, SONDEO_MS);
        return () => clearInterval(reloj);
    }, [caida, sondear]);

    return { fase, pendientes, enviadas };
}
