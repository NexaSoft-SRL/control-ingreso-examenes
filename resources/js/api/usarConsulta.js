import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from './cliente';

function limpiar(parametros) {
    return Object.fromEntries(
        Object.entries(parametros ?? {}).filter(
            ([, valor]) => valor !== undefined && valor !== null && valor !== ''
        )
    );
}

const INICIAL = { clave: null, cuerpo: null, error: null, fallos: 0 };

// Lectura de una ruta de la API.
//
//   const { datos, meta, cargando, error, recargar } = usarConsulta('/examenes', { parametros });
//
// `datos` es `cuerpo.data` (o el cuerpo entero si no trae `data`); `meta`,
// `cuerpo.meta`; `cuerpo`, la respuesta completa. Con `ruta` nula o
// `activa: false` no consulta. `cargando` solo es cierto mientras no hay nada
// que mostrar: en una recarga los datos anteriores quedan a la vista y
// `actualizando` lo indica. `error` solo se pone si no quedan datos de esa
// misma consulta; un fallo al recargar suma en `fallos` y no borra nada.
// (La función se define como `use…` para que ESLint le aplique las reglas de
// los hooks; se importa con el nombre del archivo.)
export default function useConsulta(ruta, { parametros, activa = true } = {}) {
    const limpios = limpiar(parametros);
    const textoParametros = JSON.stringify(limpios);
    const clave = ruta && activa ? `${ruta}?${textoParametros}` : null;

    const [estado, setEstado] = useState(INICIAL);
    const [enCurso, setEnCurso] = useState(false);
    const turno = useRef(0);
    const montado = useRef(true);

    useEffect(() => {
        montado.current = true;
        return () => {
            montado.current = false;
        };
    }, []);

    const consultar = useCallback(() => {
        if (!clave) return Promise.resolve(null);
        const mio = ++turno.current;
        setEnCurso(true);

        return api
            .get(ruta, { params: JSON.parse(textoParametros) })
            .then((respuesta) => {
                if (!montado.current || mio !== turno.current) return null;
                setEstado({ clave, cuerpo: respuesta.data, error: null, fallos: 0 });
                return respuesta.data;
            })
            .catch((error) => {
                if (!montado.current || mio !== turno.current) return null;
                setEstado((previo) =>
                    previo.clave === clave && previo.cuerpo !== null
                        ? { ...previo, fallos: previo.fallos + 1 }
                        : { clave, cuerpo: null, error, fallos: previo.fallos + 1 }
                );
                return null;
            })
            .finally(() => {
                if (montado.current && mio === turno.current) setEnCurso(false);
            });
    }, [clave, ruta, textoParametros]);

    useEffect(() => {
        if (!clave) {
            turno.current += 1;
            setEstado(INICIAL);
            setEnCurso(false);
            return;
        }
        consultar();
    }, [clave, consultar]);

    // Al cambiar solo los parámetros (página, filtros) se conserva la lista
    // anterior hasta que llega la nueva; al cambiar de ruta, no.
    const mismaRuta = estado.clave !== null && ruta !== null && estado.clave.startsWith(`${ruta}?`);
    const cuerpo = clave && mismaRuta ? estado.cuerpo : null;
    const error = clave && estado.clave === clave ? estado.error : null;
    const tieneData = cuerpo !== null && typeof cuerpo === 'object' && 'data' in cuerpo;

    return {
        datos: cuerpo === null ? null : tieneData ? cuerpo.data : cuerpo,
        meta: cuerpo?.meta ?? null,
        cuerpo,
        cargando: clave !== null && cuerpo === null && error === null,
        actualizando: enCurso,
        error,
        fallos: estado.fallos,
        recargar: consultar,
    };
}
