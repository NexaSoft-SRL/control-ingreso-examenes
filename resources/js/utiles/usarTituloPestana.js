import { useEffect } from 'react';

const SISTEMA = 'Control de exámenes';

// La pestaña del navegador dice en qué pantalla se está: «Habilitación ·
// Control de exámenes». Sin título queda solo el del sistema.
export default function useTituloPestana(titulo) {
    useEffect(() => {
        document.title = titulo ? `${titulo} · ${SISTEMA}` : SISTEMA;
    }, [titulo]);
}
