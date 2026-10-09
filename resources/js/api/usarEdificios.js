import { useEffect, useState } from 'react';
import { api } from './cliente';

// Los edificios (GET /api/edificios) se piden una vez y quedan en memoria
// para toda la sesión: los usa MapaCampus en varias pantallas.
let memoria = null;
let pendiente = null;

export function olvidarEdificios() {
    memoria = null;
    pendiente = null;
}

function pedir() {
    if (!pendiente) {
        pendiente = api
            .get('/edificios')
            .then((respuesta) => {
                memoria = {
                    edificios: respuesta.data?.data ?? [],
                    caja: respuesta.data?.meta?.caja ?? null,
                };
                return memoria;
            })
            .catch((error) => {
                pendiente = null;
                throw error;
            });
    }
    return pendiente;
}

//   const { edificios, caja, cargando, error } = usarEdificios();
//   <MapaCampus edificios={edificios} caja={caja} facultad="FCyT" />
export default function useEdificios() {
    const [estado, setEstado] = useState(() => ({ ...(memoria ?? {}), error: null }));

    useEffect(() => {
        if (memoria) return undefined;
        let vigente = true;
        pedir()
            .then((datos) => vigente && setEstado({ ...datos, error: null }))
            .catch((error) => vigente && setEstado({ error }));
        return () => {
            vigente = false;
        };
    }, []);

    return {
        edificios: estado.edificios ?? [],
        caja: estado.caja ?? null,
        cargando: !estado.edificios && !estado.error,
        error: estado.error,
    };
}
