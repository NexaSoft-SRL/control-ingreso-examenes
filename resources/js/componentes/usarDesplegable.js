import { useEffect, useId, useRef, useState } from 'react';
import { normalizar } from '../utiles/texto';

// Lo común de las listas desplegables propias (SelectorAsignatura,
// SelectorExamen): abrir y cerrar, la opción activa, el clic fuera, la
// activa siempre a la vista y el teclado de una lista nativa (flechas,
// Inicio, Fin, Enter, Escape y Tab). `opciones` son las visibles, en el orden
// en que se dibujan; `texto(opcion)`, lo que se compara al escribir una letra
// para saltar a la que empieza así (sin `texto`, no salta: hay buscador).
//
//   const d = usarDesplegable({ opciones, elegida, alElegir: (o) => onCambiar(o.id) });
//   <div ref={d.caja}> <button onClick={d.alternar} onKeyDown={d.teclas} …/>
//   {d.abierta && <ul ref={d.lista}> <li id={d.idDe(i)} …/> </ul>} </div>
export default function useDesplegable({
    opciones,
    elegida,
    alElegir,
    texto = null,
    deshabilitada = false,
    alAbrir,
}) {
    const id = useId();
    const caja = useRef(null);
    const lista = useRef(null);
    const [abierta, setAbierta] = useState(false);
    const [activa, setActiva] = useState(0);
    const idDe = (i) => `${id}-${i}`;

    const abrir = () => {
        if (deshabilitada) return;
        alAbrir?.();
        setActiva(Math.max(0, opciones.indexOf(elegida)));
        setAbierta(true);
    };
    const cerrar = () => setAbierta(false);
    const alternar = () => (abierta ? cerrar() : abrir());
    const elegir = (opcion) => {
        if (opcion && opcion !== elegida) alElegir(opcion);
        cerrar();
    };

    // Clic fuera de la lista la cierra.
    useEffect(() => {
        if (!abierta) return undefined;
        const fuera = (e) => {
            if (!caja.current?.contains(e.target)) setAbierta(false);
        };
        document.addEventListener('mousedown', fuera);
        return () => document.removeEventListener('mousedown', fuera);
    }, [abierta]);

    // La opción activa siempre a la vista.
    useEffect(() => {
        if (abierta)
            lista.current
                ?.querySelector(`#${CSS.escape(`${id}-${activa}`)}`)
                ?.scrollIntoView?.({ block: 'nearest' });
    }, [abierta, activa, id]);

    const teclas = (e) => {
        if (!abierta) {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
                e.preventDefault();
                abrir();
            }
            return;
        }
        const ultima = opciones.length - 1;
        const mover = {
            ArrowDown: () => setActiva((i) => Math.min(i + 1, ultima)),
            ArrowUp: () => setActiva((i) => Math.max(i - 1, 0)),
            Home: () => setActiva(0),
            End: () => setActiva(ultima),
            Enter: () => elegir(opciones[activa]),
            Escape: cerrar,
            Tab: cerrar,
        }[e.key];
        if (mover) {
            if (e.key !== 'Tab') e.preventDefault();
            mover();
        } else if (texto && e.key.length === 1) {
            const i = opciones.findIndex((o) => normalizar(texto(o)).startsWith(normalizar(e.key)));
            if (i >= 0) setActiva(i);
        }
    };

    return {
        id,
        caja,
        lista,
        abierta,
        activa,
        setActiva,
        abrir,
        cerrar,
        alternar,
        elegir,
        teclas,
        idDe,
    };
}
