import { useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';

// El examen con el que trabaja el docente viaja en la URL (`?examen=3`),
// para que se conserve al pasar de la lista a Habilitación, a Códigos QR o
// a En curso. Sin parámetro (o con uno que no está en la lista), es el
// primero. `examenes` es la lista de GET /api/examenes; mientras carga,
// el examen es nulo.
//
//   const { datos } = usarConsulta('/examenes');
//   const [examen, elegir] = usarExamen(datos ?? []);
export default function useExamen(examenes = []) {
    const [parametros, setParametros] = useSearchParams();
    const id = Number(parametros.get('examen')) || null;
    const examen = examenes.find((e) => e.id === id) ?? examenes[0] ?? null;

    const elegir = useCallback(
        (nuevoId) => {
            setParametros(
                (previos) => {
                    const siguiente = new URLSearchParams(previos);
                    siguiente.set('examen', String(nuevoId));
                    return siguiente;
                },
                { replace: true }
            );
        },
        [setParametros]
    );

    return [examen, elegir];
}
