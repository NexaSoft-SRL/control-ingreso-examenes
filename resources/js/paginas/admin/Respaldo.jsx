import { useCallback, useEffect, useRef, useState } from 'react';
import { Download, RotateCcw } from 'lucide-react';
import { api } from '../../api/cliente';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Chips from '../../componentes/Chips';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import SinPermiso from '../../componentes/SinPermiso';
import { usarSesion } from '../../sesion/SesionContexto';
import { fechaCorta, plural } from '../../utiles/texto';

const POR_PAGINA = 15;

export function formatearTamano(bytes, fallback = null) {
    if (bytes === null || bytes === undefined) {
        return fallback || '—';
    }
    const num = Number(bytes);
    if (isNaN(num) || num <= 0) return fallback || '—';
    if (num < 1024) return `${num} B`;
    const kb = num / 1024;
    if (kb < 1024) {
        return `${kb.toLocaleString('es-BO', { maximumFractionDigits: 1 })} KB`;
    }
    const mb = kb / 1024;
    if (mb < 1024) {
        return `${mb.toLocaleString('es-BO', { maximumFractionDigits: 1 })} MB`;
    }
    const gb = mb / 1024;
    return `${gb.toLocaleString('es-BO', { maximumFractionDigits: 1 })} GB`;
}

function tonoEstado(estado) {
    const normal = String(estado ?? '').toLowerCase();
    switch (normal) {
        case 'correcto':
            return 'exito';
        case 'fallido':
            return 'peligro';
        case 'restaurado':
            return 'info';
        case 'incompatible':
            return 'advertencia';
        default:
            return 'neutro';
    }
}

export default function Respaldo() {
    const { puede } = usarSesion();
    const tienePermiso = puede('respaldo_restauracion');

    const [respaldos, setRespaldos] = useState([]);
    const [ultimoCorrecto, setUltimoCorrecto] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [errorCarga, setErrorCarga] = useState(false);
    const [respaldando, setRespaldando] = useState(false);
    const [filtro, setFiltro] = useState('todos');
    const [pagina, setPagina] = useState(1);
    const [total, setTotal] = useState(0);
    const [conteos, setConteos] = useState({
        todos: 0,
        programados: 0,
        puntuales: 0,
        fallidos: 0,
    });
    const [totalSistema, setTotalSistema] = useState(0);

    const [aviso, avisar] = useAviso();
    const ultimaConsulta = useRef(0);

    const cargarHistorial = useCallback(
        async (filtroActual = filtro, paginaActual = pagina) => {
            if (!tienePermiso) return;

            const idConsulta = ++ultimaConsulta.current;
            setCargando(true);
            setErrorCarga(false);

            try {
                const params = {
                    filtro: filtroActual !== 'todos' ? filtroActual : undefined,
                    pagina: paginaActual,
                    por_pagina: POR_PAGINA,
                };

                const respuesta = await api.get('/respaldos', { params });
                if (idConsulta !== ultimaConsulta.current) return;

                const cuerpo = respuesta.data ?? {};
                const datos = Array.isArray(cuerpo)
                    ? cuerpo
                    : Array.isArray(cuerpo.data)
                      ? cuerpo.data
                      : [];

                setRespaldos(datos);

                const meta = cuerpo.meta ?? {};
                const totalCalculado = meta.total ?? cuerpo.total ?? datos.length;
                setTotal(totalCalculado);

                // Conteos globales
                if (cuerpo.conteos) {
                    setConteos(cuerpo.conteos);
                    setTotalSistema(cuerpo.conteos.todos ?? totalCalculado);
                } else {
                    // Si no vienen conteos, estimar o preservar
                    setConteos((previos) => {
                        const nuevo = { ...previos };
                        if (filtroActual === 'todos') {
                            nuevo.todos = totalCalculado;
                            setTotalSistema(totalCalculado);
                        }
                        return nuevo;
                    });
                }

                // Último correcto
                const ultimoApi = cuerpo.ultimo ?? cuerpo.ultimo_correcto;
                if (ultimoApi) {
                    setUltimoCorrecto(ultimoApi);
                } else if (cuerpo.ultimo === null) {
                    setUltimoCorrecto(null);
                } else {
                    const primerCorrecto = datos.find(
                        (r) =>
                            r.estado?.toLowerCase() === 'correcto' ||
                            r.estado?.toLowerCase() === 'restaurado'
                    );
                    if (primerCorrecto) {
                        setUltimoCorrecto(
                            primerCorrecto.fecha ?? primerCorrecto.created_at ?? null
                        );
                    }
                }
            } catch (error) {
                if (idConsulta !== ultimaConsulta.current) return;
                const mensaje = error?.response?.data?.message;
                if (mensaje === 'El filtro no existe.') {
                    avisar(mensaje, 'error');
                } else {
                    setErrorCarga(true);
                }
            } finally {
                if (idConsulta === ultimaConsulta.current) {
                    setCargando(false);
                }
            }
        },
        [tienePermiso, filtro, pagina, avisar]
    );

    useEffect(() => {
        if (tienePermiso) {
            cargarHistorial(filtro, pagina);
        }
    }, [tienePermiso, filtro, pagina, cargarHistorial]);

    if (!tienePermiso) {
        return <SinPermiso />;
    }

    async function manejarRespaldar() {
        if (respaldando) return;
        setRespaldando(true);

        try {
            const respuesta = await api.post('/respaldos');
            const nuevo = respuesta.data?.data ?? respuesta.data;

            avisar('Respaldo generado');

            // Criterio 3: salir de un filtro que lo ocultaría
            if (filtro === 'programados' || filtro === 'fallidos') {
                setFiltro('todos');
                setPagina(1);
            } else {
                setPagina(1);
            }

            if (nuevo && typeof nuevo === 'object') {
                setRespaldos((anteriores) => [nuevo, ...anteriores]);
                const fechaNuevo = nuevo.fecha ?? nuevo.created_at;
                if (fechaNuevo && nuevo.estado?.toLowerCase() !== 'fallido') {
                    setUltimoCorrecto(fechaNuevo);
                }
                setConteos((c) => ({
                    ...c,
                    todos: (c.todos ?? 0) + 1,
                    puntuales: (c.puntuales ?? 0) + 1,
                }));
                setTotal((t) => t + 1);
                setTotalSistema((t) => t + 1);
            } else {
                await cargarHistorial(filtro, 1);
            }
        } catch {
            avisar('No se pudo generar el respaldo', 'error');
            await cargarHistorial(filtro, pagina);
        } finally {
            setRespaldando(false);
        }
    }

    function cambiarFiltro(nuevoFiltro) {
        setFiltro(nuevoFiltro);
        setPagina(1);
    }

    const opcionesChips = [
        { valor: 'todos', etiqueta: 'Todos', conteo: conteos.todos },
        { valor: 'programados', etiqueta: 'Programados', conteo: conteos.programados },
        { valor: 'puntuales', etiqueta: 'Puntuales', conteo: conteos.puntuales },
        { valor: 'fallidos', etiqueta: 'Fallidos', conteo: conteos.fallidos },
    ];

    const subtitulo = (() => {
        if (totalSistema === 0 && respaldos.length === 0 && !ultimoCorrecto) {
            return 'Sin respaldos';
        }
        if (ultimoCorrecto) {
            return `Diario, 03:00 · último: ${fechaCorta(ultimoCorrecto)}`;
        }
        return 'Diario, 03:00';
    })();

    const esVacioTotal = totalSistema === 0 && respaldos.length === 0;

    return (
        <div className="flex flex-col gap-6 p-4 sm:p-6">
            <Encabezado titulo="Respaldo" subtitulo={subtitulo}>
                <Boton
                    type="button"
                    onClick={manejarRespaldar}
                    cargando={respaldando}
                    disabled={respaldando}
                >
                    {respaldando ? 'Respaldando' : 'Respaldar ahora'}
                </Boton>
            </Encabezado>

            {/* Fichas de filtro */}
            {!esVacioTotal && !errorCarga && (
                <Chips
                    opciones={opcionesChips}
                    valor={filtro}
                    onCambiar={cambiarFiltro}
                    etiqueta="Filtros del historial"
                />
            )}

            {cargando ? (
                <EstadoCarga cargando />
            ) : errorCarga ? (
                <div
                    role="alert"
                    className="flex flex-col items-center gap-3 rounded-xl border border-slate-200 bg-white p-8 text-center"
                >
                    <p className="text-sm font-medium text-slate-800">No se pudo cargar</p>
                    <Boton
                        type="button"
                        variante="secundario"
                        onClick={() => cargarHistorial(filtro, pagina)}
                    >
                        Reintentar
                    </Boton>
                </div>
            ) : esVacioTotal ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
                    <p className="text-sm text-slate-500">Sin respaldos</p>
                </div>
            ) : respaldos.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
                    <p className="text-sm text-slate-500">Sin resultados</p>
                </div>
            ) : (
                <section className="flex flex-col gap-4">
                    <h2 className="text-lg font-semibold text-slate-900">Historial</h2>

                    {/* Tabla para escritorio */}
                    <div className="hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
                        <table className="w-full text-left text-sm text-slate-700">
                            <thead className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th scope="col" className="px-4 py-3">
                                        Fecha
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Tipo
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Tamaño
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Estado
                                    </th>
                                    <th scope="col" className="px-4 py-3 text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {respaldos.map((respaldo) => {
                                    const fecha = fechaCorta(respaldo.fecha ?? respaldo.created_at);
                                    const tipo =
                                        respaldo.tipo?.toLowerCase() === 'programado'
                                            ? 'Programado'
                                            : 'Puntual';
                                    const esFallido = respaldo.estado?.toLowerCase() === 'fallido';
                                    const tamano = esFallido
                                        ? '—'
                                        : formatearTamano(respaldo.tamano_bytes, respaldo.tamano);
                                    const estadoEtiqueta =
                                        respaldo.estado?.charAt(0).toUpperCase() +
                                        respaldo.estado?.slice(1).toLowerCase();

                                    return (
                                        <tr key={respaldo.id} className="hover:bg-slate-50/80">
                                            <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                                                {fecha}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-slate-600">
                                                {tipo}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-slate-600">
                                                {tamano}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                <Insignia tono={tonoEstado(respaldo.estado)}>
                                                    {estadoEtiqueta}
                                                </Insignia>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right">
                                                {esFallido ? (
                                                    <span className="text-slate-400">—</span>
                                                ) : (
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Boton
                                                            type="button"
                                                            tamano="chico"
                                                            variante="secundario"
                                                            onClick={() => {}}
                                                        >
                                                            <Download className="h-3.5 w-3.5" />
                                                            Descargar
                                                        </Boton>
                                                        <Boton
                                                            type="button"
                                                            tamano="chico"
                                                            variante="secundario"
                                                            onClick={() => {}}
                                                        >
                                                            <RotateCcw className="h-3.5 w-3.5" />
                                                            Restaurar
                                                        </Boton>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Lista para móvil */}
                    <div className="flex flex-col gap-3 md:hidden">
                        {respaldos.map((respaldo) => {
                            const fecha = fechaCorta(respaldo.fecha ?? respaldo.created_at);
                            const tipo =
                                respaldo.tipo?.toLowerCase() === 'programado'
                                    ? 'Programado'
                                    : 'Puntual';
                            const esFallido = respaldo.estado?.toLowerCase() === 'fallido';
                            const tamano = esFallido
                                ? '—'
                                : formatearTamano(respaldo.tamano_bytes, respaldo.tamano);
                            const estadoEtiqueta =
                                respaldo.estado?.charAt(0).toUpperCase() +
                                respaldo.estado?.slice(1).toLowerCase();

                            return (
                                <div
                                    key={respaldo.id}
                                    className="flex flex-col gap-2.5 rounded-xl border border-slate-200 bg-white p-4 shadow-xs"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-semibold text-slate-900">
                                            {fecha}
                                        </span>
                                        <Insignia tono={tonoEstado(respaldo.estado)}>
                                            {estadoEtiqueta}
                                        </Insignia>
                                    </div>
                                    <p className="text-xs text-slate-600">
                                        {tipo} · {tamano}
                                    </p>
                                    {respaldo.error && (
                                        <p className="text-xs text-danger-600">{respaldo.error}</p>
                                    )}
                                    {!esFallido && (
                                        <div className="mt-1 flex items-center gap-2 border-t border-slate-100 pt-2.5">
                                            <Boton
                                                type="button"
                                                tamano="chico"
                                                variante="secundario"
                                                className="flex-1"
                                                onClick={() => {}}
                                            >
                                                <Download className="h-3.5 w-3.5" />
                                                Descargar
                                            </Boton>
                                            <Boton
                                                type="button"
                                                tamano="chico"
                                                variante="secundario"
                                                className="flex-1"
                                                onClick={() => {}}
                                            >
                                                <RotateCcw className="h-3.5 w-3.5" />
                                                Restaurar
                                            </Boton>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {/* Conteo y Paginación */}
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <span className="text-sm text-slate-600">{plural(total, 'respaldo')}</span>
                        {total > POR_PAGINA && (
                            <Paginacion
                                total={total}
                                pagina={pagina}
                                porPagina={POR_PAGINA}
                                onCambiar={setPagina}
                                unidad={['respaldo', 'respaldos']}
                            />
                        )}
                    </div>
                </section>
            )}

            {aviso}
        </div>
    );
}
