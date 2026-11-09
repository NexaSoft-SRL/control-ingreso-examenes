import { createContext, useContext } from 'react';

// El acceso abierto con código de aula (ruta 7), para quien controla sin
// cuenta: { aula_id, aula, examen_id, nombre, hasta }. Nulo con sesión normal.
export const AccesoAulaContexto = createContext(null);

export function useAccesoAula() {
    return useContext(AccesoAulaContexto);
}

export { useAccesoAula as usarAccesoAula };
