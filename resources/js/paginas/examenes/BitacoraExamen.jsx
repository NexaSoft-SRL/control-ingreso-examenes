import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ChevronDown, FileSpreadsheet, FileText, History } from 'lucide-react';
import { usarSesion } from '../../sesion/SesionContexto';
import Boton from '../../componentes/Boton';
import Encabezado from '../../componentes/Encabezado';
import { useAviso } from '../../componentes/Aviso';
import descargar from '../../api/descargar';
import usarConsulta from '../../api/usarConsulta';
import { mensajeDe } from '../../api/errores';

function fechaDe(texto) {
    return texto?.slice(0, 10) ?? 'Fecha no disponible';
}

function horaDe(texto) {
    return texto?.slice(11, 16) ?? '--:--';
}

function agruparPorFecha(eventos) {
    return eventos.reduce((grupos, evento) => {
        const fecha = fechaDe(evento.ocurrida_en);
        const existente = grupos.find((grupo) => grupo.fecha === fecha);
        if (existente) existente.eventos.push(evento);
        else grupos.push({ fecha, eventos: [evento] });
        return grupos;
    }, []);
}

export default function BitacoraExamen() {
    const [parametrosUrl, setParametrosUrl] = useSearchParams();
    const examenId = parametrosUrl.get('examen') ?? '';
    const [categoria, setCategoria] = useState('');
    const [buscar, setBuscar] = useState('');
    const [aula, setAula] = useState('');
    const [pagina, setPagina] = useState(1);
    const [eventos, setEventos] = useState([]);
    const [descarga, setDescarga] = useState('');
    const [aviso, avisar] = useAviso();
    const { puede } = usarSesion();
    const consultaExamenes = usarConsulta('/reportes/bitacora/examenes');
    const rutaBitacora = examenId
        ? `/reportes/examenes/${encodeURIComponent(examenId)}/bitacora`
        : null;
    const consultaBitacora = usarConsulta(rutaBitacora, {
        parametros: { categoria, buscar, aula, pagina },
    });
    const examenes = consultaExamenes.datos ?? [];
    const cuerpo = consultaBitacora.cuerpo;
    const meta = consultaBitacora.meta ?? {};
    const categorias = cuerpo?.categorias ?? [];
    const aulas = cuerpo?.aulas ?? [];
    const examen = cuerpo?.examen ?? examenes.find((item) => String(item.id) === examenId);
    const grupos = useMemo(() => agruparPorFecha(eventos), [eventos]);

    useEffect(() => {
        setCategoria('');
        setBuscar('');
        setAula('');
        setPagina(1);
        setEventos([]);
    }, [examenId]);

    useEffect(() => {
        setEventos([]);
    }, [categoria, buscar, aula]);

    useEffect(() => {
        if (!Array.isArray(consultaBitacora.datos) || meta.pagina !== pagina) return;
        setEventos((actuales) =>
            pagina === 1 ? consultaBitacora.datos : [...actuales, ...consultaBitacora.datos]
        );
    }, [consultaBitacora.datos, meta.pagina, pagina]);

    function seleccionarExamen(id) {
        setParametrosUrl(id ? { examen: id } : {});
    }

    function cambiarFiltro(setter, valor) {
        setter(valor);
        setPagina(1);
    }

    async function descargarBitacora(formato) {
        setDescarga(formato);
        try {
            const nombre = await descargar(
                `/reportes/examenes/${encodeURIComponent(examenId)}/bitacora/descargas/${formato}`
            );
            avisar(`${nombre} descargada.`);
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudo descargar la bitácora.'), 'error');
        } finally {
            setDescarga('');
        }
    }

    const hayEventos = (meta.total_examen ?? 0) > 0;
    const huboError = consultaBitacora.error;
    const errorExamen = huboError ? mensajeDe(huboError, 'No se pudo cargar la bitácora.') : '';

    return (
        <div className="space-y-5">
            {aviso}
            <Encabezado
                titulo="Bitácora de exámenes"
                subtitulo="Consulta en modo de solo lectura los sucesos registrados durante un examen."
                volver={{
                    a: puede('reportes_universidad') ? '/bitacora-examen' : '/examenes',
                    texto: puede('reportes_universidad') ? 'Volver a selección' : 'Volver',
                }}
            />

            <section className="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <label
                    htmlFor="bitacora-examen"
                    className="mb-2 block text-sm font-medium text-slate-700"
                >
                    Examen
                </label>
                <select
                    id="bitacora-examen"
                    value={examenId}
                    onChange={(evento) => seleccionarExamen(evento.target.value)}
                    className="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                    aria-label="Seleccionar examen"
                >
                    <option value="">Selecciona un examen</option>
                    {examenes.map((item) => (
                        <option key={item.id} value={item.id}>
                            {item.fecha} · {item.tipo} · {item.asignatura}
                        </option>
                    ))}
                </select>
                {consultaExamenes.error && (
                    <p role="alert" className="mt-2 text-sm text-danger-700">
                        {mensajeDe(consultaExamenes.error, 'No se pudieron cargar los exámenes.')}
                    </p>
                )}
                {!consultaExamenes.error && !consultaExamenes.cargando && examenes.length === 0 && (
                    <p className="mt-2 text-sm text-slate-600">No hay exámenes disponibles.</p>
                )}
            </section>

            {!examenId ? (
                <p className="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-600">
                    Selecciona un examen para consultar su bitácora.
                </p>
            ) : (
                <>
                    <section className="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                        <div className="flex flex-col gap-3 xl:flex-row xl:items-end">
                            <label className="min-w-0 flex-1 text-sm font-medium text-slate-700">
                                Buscar estudiante o código SIS
                                <input
                                    type="search"
                                    value={buscar}
                                    onChange={(evento) =>
                                        cambiarFiltro(setBuscar, evento.target.value)
                                    }
                                    placeholder="Nombre o código"
                                    className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm font-normal text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                                />
                            </label>
                            <label className="min-w-0 flex-1 text-sm font-medium text-slate-700">
                                Suceso
                                <select
                                    value={categoria}
                                    onChange={(evento) =>
                                        cambiarFiltro(setCategoria, evento.target.value)
                                    }
                                    className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm font-normal text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                                >
                                    <option value="">Todos los sucesos</option>
                                    {categorias.map((item) => (
                                        <option key={item.valor} value={item.valor}>
                                            {item.etiqueta} ({item.total})
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="min-w-0 flex-1 text-sm font-medium text-slate-700">
                                Aula
                                <select
                                    value={aula}
                                    onChange={(evento) =>
                                        cambiarFiltro(setAula, evento.target.value)
                                    }
                                    className="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm font-normal text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                                >
                                    <option value="">Todas las aulas</option>
                                    {aulas.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.nombre}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <p className="text-sm text-slate-600">
                                {hayEventos
                                    ? `${meta.total ?? 0} de ${meta.total_examen} eventos`
                                    : 'Sin eventos registrados'}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Boton
                                    type="button"
                                    variante="secundario"
                                    tamano="chico"
                                    disabled={!hayEventos || Boolean(descarga)}
                                    cargando={descarga === 'pdf'}
                                    onClick={() => descargarBitacora('pdf')}
                                >
                                    <FileText className="h-4 w-4" aria-hidden="true" />
                                    PDF
                                </Boton>
                                <Boton
                                    type="button"
                                    variante="secundario"
                                    tamano="chico"
                                    disabled={!hayEventos || Boolean(descarga)}
                                    cargando={descarga === 'xlsx'}
                                    onClick={() => descargarBitacora('xlsx')}
                                >
                                    <FileSpreadsheet className="h-4 w-4" aria-hidden="true" />
                                    XLSX
                                </Boton>
                            </div>
                        </div>
                    </section>

                    {consultaBitacora.cargando ? (
                        <div
                            role="status"
                            aria-busy="true"
                            className="rounded-xl bg-white p-8 text-center text-sm text-slate-600"
                        >
                            Cargando bitácora…
                        </div>
                    ) : huboError ? (
                        <div
                            role="alert"
                            className="rounded-xl border border-danger-200 bg-white p-5 text-sm text-danger-700"
                        >
                            <p>{errorExamen}</p>
                            <Boton
                                type="button"
                                variante="secundario"
                                tamano="chico"
                                className="mt-3"
                                onClick={() => consultaBitacora.recargar()}
                            >
                                Reintentar
                            </Boton>
                        </div>
                    ) : !hayEventos ? (
                        <div className="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center">
                            <History
                                className="mx-auto h-8 w-8 text-slate-400"
                                aria-hidden="true"
                            />
                            <p className="mt-2 text-sm font-medium text-slate-700">
                                Este examen todavía no tiene eventos.
                            </p>
                            <p className="mt-1 text-sm text-slate-600">
                                La bitácora aparecerá aquí cuando se registren sucesos.
                            </p>
                        </div>
                    ) : eventos.length === 0 ? (
                        <p className="rounded-xl border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-600">
                            Ningún evento coincide con los filtros seleccionados.
                        </p>
                    ) : (
                        <div className="space-y-5">
                            {grupos.map((grupo) => (
                                <section
                                    key={grupo.fecha}
                                    aria-label={`Eventos del ${grupo.fecha}`}
                                >
                                    <h2 className="mb-3 text-sm font-semibold text-slate-700">
                                        {grupo.fecha}
                                    </h2>
                                    <ol className="space-y-3 border-l-2 border-primary-100 pl-4 sm:pl-5">
                                        {grupo.eventos.map((evento) => (
                                            <li key={evento.id} className="relative min-w-0">
                                                <span
                                                    aria-hidden="true"
                                                    className="absolute -left-[1.42rem] top-4 h-3 w-3 rounded-full border-2 border-primary-600 bg-white sm:-left-[1.67rem]"
                                                />
                                                <article className="min-w-0 rounded-xl border border-slate-200 bg-white p-4">
                                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                        <div className="min-w-0">
                                                            <h3 className="break-words text-sm font-semibold text-slate-900">
                                                                {evento.suceso}
                                                            </h3>
                                                            <p className="mt-1 break-words text-sm text-slate-700">
                                                                {evento.estudiante}
                                                                {evento.codigo
                                                                    ? ` · ${evento.codigo}`
                                                                    : ''}
                                                            </p>
                                                        </div>
                                                        <time
                                                            dateTime={evento.ocurrida_en}
                                                            className="shrink-0 text-sm font-medium tabular-nums text-slate-600"
                                                        >
                                                            {horaDe(evento.ocurrida_en)}
                                                        </time>
                                                    </div>
                                                    <p className="mt-2 break-words text-sm text-slate-600">
                                                        {evento.detalle}
                                                    </p>
                                                    <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-slate-100 pt-2 text-xs text-slate-600">
                                                        <span>
                                                            Aula: {evento.aula ?? 'Sin aula'}
                                                        </span>
                                                        <span>Actor: {evento.actor}</span>
                                                        <span>
                                                            Examen: {examen?.tipo} ·{' '}
                                                            {examen?.asignatura}
                                                        </span>
                                                    </div>
                                                </article>
                                            </li>
                                        ))}
                                    </ol>
                                </section>
                            ))}
                            {pagina < (meta.paginas ?? 1) && (
                                <div className="flex justify-center">
                                    <Boton
                                        type="button"
                                        variante="secundario"
                                        onClick={() => setPagina((actual) => actual + 1)}
                                        cargando={consultaBitacora.actualizando}
                                    >
                                        <ChevronDown className="h-4 w-4" aria-hidden="true" />
                                        Mostrar más
                                    </Boton>
                                </div>
                            )}
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
