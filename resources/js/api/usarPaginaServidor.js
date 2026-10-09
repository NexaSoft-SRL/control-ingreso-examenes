import { useCallback, useEffect, useMemo, useState } from 'react';

// Página, tamaño, búsqueda y filtros de una lista que pagina el servidor.
//
//   const lista = usarPaginaServidor({ porPagina: 25, filtros: { facultad: null } });
//   const { datos, meta } = usarConsulta('/docentes', { parametros: lista.parametros });
//   <Buscador valor={lista.buscar} onCambiar={lista.ponerBuscar} />
//   <Paginacion {...lista.paginacion(meta)} unidad={['docente', 'docentes']} />
//
// `parametros` lleva `pagina`, `por_pagina`, `buscar` (300 ms después de la
// última tecla) y los filtros con valor. Cambiar un filtro o la búsqueda
// vuelve a la página 1.
export default function usePaginaServidor({
    porPagina = 25,
    filtros: iniciales = {},
    espera = 300,
} = {}) {
    const [pagina, setPagina] = useState(1);
    const [filtros, setFiltros] = useState(iniciales);
    const [buscar, setBuscar] = useState('');
    const [buscado, setBuscado] = useState('');

    useEffect(() => {
        const texto = buscar.trim();
        if (texto === buscado) return undefined;
        const reloj = setTimeout(() => {
            setBuscado(texto);
            setPagina(1);
        }, espera);
        return () => clearTimeout(reloj);
    }, [buscar, buscado, espera]);

    const ponerFiltro = useCallback((clave, valor) => {
        setFiltros((previos) => ({ ...previos, [clave]: valor }));
        setPagina(1);
    }, []);

    const ponerFiltros = useCallback((nuevos) => {
        setFiltros((previos) => ({ ...previos, ...nuevos }));
        setPagina(1);
    }, []);

    const parametros = useMemo(() => {
        const conValor = Object.fromEntries(
            Object.entries(filtros).filter(
                ([, valor]) =>
                    valor !== undefined && valor !== null && valor !== '' && valor !== false
            )
        );
        return {
            ...conValor,
            ...(buscado ? { buscar: buscado } : {}),
            pagina,
            por_pagina: porPagina,
        };
    }, [filtros, buscado, pagina, porPagina]);

    // Los filtros vigentes sin la página: lo que viaja en `filtros` de un lote.
    const filtrosVigentes = useMemo(() => ({ ...filtros, buscar: buscado }), [filtros, buscado]);

    const paginacion = useCallback(
        (meta) => ({
            total: meta?.total ?? 0,
            pagina: meta?.pagina ?? pagina,
            porPagina: meta?.por_pagina ?? porPagina,
            onCambiar: setPagina,
        }),
        [pagina, porPagina]
    );

    return {
        pagina,
        porPagina,
        filtros,
        buscar,
        parametros,
        filtrosVigentes,
        irA: setPagina,
        ponerFiltro,
        ponerFiltros,
        ponerBuscar: setBuscar,
        paginacion,
    };
}
