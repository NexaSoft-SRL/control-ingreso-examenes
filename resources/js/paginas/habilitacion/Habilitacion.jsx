import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { CheckCircle2, Shuffle, XCircle } from 'lucide-react';
import { api } from '../../api/cliente';
import { codigoDe, erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Chips from '../../componentes/Chips';
import Dato from '../../componentes/Dato';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Seleccion, { AreaTexto } from '../../componentes/Seleccion';
import SelectorExamen from '../../componentes/SelectorExamen';
import Tarjeta from '../../componentes/Tarjeta';
import usarExamen from '../../componentes/usarExamen';
import { fechaCorta, plural } from '../../utiles/texto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const POR_PAGINA = 25;
const MOTIVO_MINIMO = 5;

// Quién registró la inhabilitación y cuándo: «Blanco Coca Leticia · 9 oct 2026, 14:05».
const autoria = (e) => [e.registrada_por, fechaCorta(e.registrada_el)].filter(Boolean).join(' · ');
const MOTIVO_MAXIMO = 1000;
const CONDICION = {
    habilitado: ['exito', 'Habilitado'],
    no: ['advertencia', 'No habilitado'],
    pendiente: ['neutro', 'Sin revisar'],
};
const SIN_FILTROS = { grupo: null, aula: null, condicion: null };
const COLUMNAS = ['Código', 'Estudiante', 'Grupo', 'Condición', 'Aula', 'Motivo'];

// «3 habilitados.» → «3 habilitados».
const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

function Condicion({ estado }) {
    const [tono, texto] = CONDICION[estado] ?? CONDICION.pendiente;
    return <Insignia tono={tono}>{texto}</Insignia>;
}

Condicion.propTypes = { estado: PropTypes.string };

// La lista de un examen: cifras, distribución por aula, filtros, selección
// y lotes. Se monta de nuevo con cada examen (`key`), así el cambio de
// examen limpia la selección, los filtros, la búsqueda y el motivo.
function ListaDelExamen({ examenId }) {
    const ruta = `/examenes/${examenId}/habilitaciones`;
    const lista = usarPaginaServidor({ porPagina: POR_PAGINA, filtros: SIN_FILTROS });
    const { datos, meta, cargando, error, recargar } = usarConsulta(ruta, {
        parametros: lista.parametros,
    });
    // La selección sobrevive al cambio de página: son `estudiante_id`.
    // `todos` es «Seleccionar los N»: el lote viaja con los filtros.
    const [seleccion, setSeleccion] = useState(() => new Set());
    const [todos, setTodos] = useState(false);
    const [motivo, setMotivo] = useState('');
    const [errorMotivo, setErrorMotivo] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();

    const filas = datos ?? [];
    const total = meta?.total ?? 0;
    const { irA } = lista;

    // Tras un lote, la página a la vista puede haber dejado de existir.
    useEffect(() => {
        if (datos && datos.length === 0 && total > 0 && lista.pagina > 1) {
            irA(Math.ceil(total / POR_PAGINA));
        }
    }, [datos, total, lista.pagina, irA]);

    if (cargando || error) {
        if (error && estadoDe(error) === 403) {
            return (
                <Tarjeta>
                    <p role="alert" className="py-6 text-center text-sm text-slate-600">
                        {sinPunto(mensajeDe(error, 'Sin permiso'))}
                    </p>
                </Tarjeta>
            );
        }
        return (
            <Tarjeta sinRelleno>
                <EstadoCarga cargando={cargando} error={error} onReintentar={recargar} />
            </Tarjeta>
        );
    }

    const cifras = meta?.cifras ?? {};
    const aulas = meta?.por_aula ?? [];
    const grupos = meta?.grupos ?? [];
    const condiciones = meta?.condiciones ?? {};
    const { grupo, aula, condicion } = lista.filtros;

    const idsPagina = filas.map((e) => e.estudiante_id);
    const marcado = (id) => todos || seleccion.has(id);
    const paginaMarcada = filas.length > 0 && idsPagina.every(marcado);
    const cantidad = todos ? total : seleccion.size;
    const motivoCorto = motivo.trim().length < MOTIVO_MINIMO;

    const filtrar = (clave, valor) => {
        setTodos(false);
        lista.ponerFiltro(clave, valor);
    };
    const buscar = (texto) => {
        setTodos(false);
        lista.ponerBuscar(texto);
    };
    const alternar = (id) => {
        const despues = new Set(seleccion);
        if (todos) {
            idsPagina.forEach((i) => despues.add(i));
            despues.delete(id);
        } else if (despues.has(id)) {
            despues.delete(id);
        } else {
            despues.add(id);
        }
        setTodos(false);
        setSeleccion(despues);
    };
    const alternarPagina = () => {
        const despues = new Set(seleccion);
        idsPagina.forEach((i) => (paginaMarcada ? despues.delete(i) : despues.add(i)));
        setTodos(false);
        setSeleccion(despues);
    };
    const quitar = () => {
        setTodos(false);
        setSeleccion(new Set());
    };
    const cerrarSeleccion = () => {
        quitar();
        setMotivo('');
        setErrorMotivo(null);
    };

    const rechazo = (fallo, porDefecto) => {
        const campo = erroresDe(fallo).motivo;
        if (campo) {
            setErrorMotivo(campo);
            return;
        }
        if (codigoDe(fallo) === 'SIN_AULAS') {
            avisar('Sin aulas', 'error');
            return;
        }
        avisar(sinPunto(mensajeDe(fallo, porDefecto)), 'error');
    };

    const cambiar = async (habilitado) => {
        if (enviando || cantidad === 0) return;
        const cuerpo = {
            habilitado,
            ...(habilitado ? {} : { motivo: motivo.trim() }),
            ...(todos
                ? { todos: true, filtros: lista.filtrosVigentes }
                : { estudiantes: [...seleccion] }),
        };
        setEnviando(true);
        try {
            const { data } = await api.post(ruta, cuerpo);
            avisar(
                data?.afectados !== undefined
                    ? plural(data.afectados, habilitado ? 'habilitado' : 'inhabilitado')
                    : sinPunto(data?.message)
            );
            cerrarSeleccion();
            await recargar();
        } catch (fallo) {
            rechazo(fallo, 'No se pudo guardar');
        } finally {
            setEnviando(false);
        }
    };
    const inhabilitar = () => {
        if (motivoCorto) {
            setErrorMotivo(`Mínimo ${MOTIVO_MINIMO} caracteres`);
            return;
        }
        cambiar(false);
    };
    const repartir = async () => {
        if (enviando) return;
        setEnviando(true);
        try {
            const { data } = await api.post(`/examenes/${examenId}/reparto`);
            avisar(sinPunto(data?.message) || 'Sin estudiantes por repartir');
            await recargar();
        } catch (fallo) {
            rechazo(fallo, 'No se pudo repartir');
        } finally {
            setEnviando(false);
        }
    };

    const casilla = (e) => (
        <label className="flex min-h-11 min-w-11 cursor-pointer items-center justify-center">
            <input
                type="checkbox"
                className="h-4 w-4"
                aria-label={`Seleccionar a ${e.nombre}`}
                checked={marcado(e.estudiante_id)}
                onChange={() => alternar(e.estudiante_id)}
            />
        </label>
    );
    // «25 seleccionados» y, si hay más páginas, el atajo a todo lo filtrado.
    const resumenSeleccion = (
        <>
            <span className="text-sm font-medium text-slate-700">
                {plural(cantidad, 'seleccionado')}
            </span>
            {!todos && total > filas.length && (
                <Boton variante="enlace" tamano="chico" onClick={() => setTodos(true)}>
                    Seleccionar los {total}
                </Boton>
            )}
            <Boton variante="enlace" tamano="chico" onClick={quitar}>
                Quitar
            </Boton>
        </>
    );

    return (
        <div className={`space-y-6 ${cantidad > 0 ? 'max-md:pb-64' : ''}`}>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Dato
                    etiqueta={`Inscritos · ${plural(grupos.length, 'grupo')}`}
                    valor={cifras.inscritos ?? 0}
                />
                <Dato etiqueta="Habilitados" valor={cifras.habilitados ?? 0} tono="exito" />
                <Dato
                    etiqueta="No habilitados"
                    valor={cifras.no_habilitados ?? 0}
                    tono={cifras.no_habilitados ? 'advertencia' : 'neutro'}
                />
                <Dato etiqueta="Sin revisar" valor={cifras.sin_revisar ?? 0} />
            </div>

            <Tarjeta
                titulo={
                    aulas.length > 0
                        ? `Distribución por aula · ${aulas.length}`
                        : 'Distribución por aula'
                }
                acciones={
                    <>
                        {cifras.sin_aula > 0 && (
                            <Insignia tono="advertencia">{cifras.sin_aula} sin aula</Insignia>
                        )}
                        <Boton
                            variante="secundario"
                            disabled={aulas.length === 0 || enviando}
                            onClick={repartir}
                        >
                            <Shuffle className="h-4 w-4" /> Repartir
                        </Boton>
                    </>
                }
            >
                {aulas.length > 0 ? (
                    <ul className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                        {aulas.map((a) => {
                            const activo = aula === a.aula_id;
                            return (
                                <li key={a.aula_id} className="min-w-0">
                                    <button
                                        type="button"
                                        aria-pressed={activo}
                                        aria-label={`Aula ${a.nombre}, ${plural(a.asignados, 'estudiante')}`}
                                        onClick={() => filtrar('aula', activo ? null : a.aula_id)}
                                        className={`flex w-full min-w-0 items-baseline justify-between gap-2 rounded-lg border px-3 py-2 text-left ${FOCO} ${activo ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500/30' : 'border-slate-200 hover:bg-slate-50'}`}
                                    >
                                        <span className="min-w-0 truncate text-sm font-semibold text-slate-800">
                                            {a.nombre}
                                        </span>
                                        <span className="shrink-0 text-sm tabular-nums text-slate-600">
                                            {a.asignados}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                ) : (
                    <p className="py-4 text-center text-sm text-slate-600">Sin aulas</p>
                )}
            </Tarjeta>

            <Tarjeta titulo="Lista del examen" sinRelleno>
                <div className="space-y-3 p-4 sm:px-5">
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-[11rem_11rem_1fr]">
                        <Seleccion
                            aria-label="Grupo"
                            className="min-w-0"
                            value={grupo ?? ''}
                            onChange={(e) => filtrar('grupo', Number(e.target.value) || null)}
                        >
                            <option value="">Todos los grupos</option>
                            {grupos.map((g) => (
                                <option key={g.id} value={g.id}>
                                    Grupo {g.codigo}
                                    {g.propio ? ' · Grupo propio' : ''}
                                </option>
                            ))}
                        </Seleccion>
                        <Seleccion
                            aria-label="Aula"
                            className="min-w-0"
                            value={aula ?? ''}
                            disabled={aulas.length === 0}
                            onChange={(e) => filtrar('aula', Number(e.target.value) || null)}
                        >
                            <option value="">Todas las aulas</option>
                            {aulas.map((a) => (
                                <option key={a.aula_id} value={a.aula_id}>
                                    Aula {a.nombre}
                                </option>
                            ))}
                        </Seleccion>
                        <Buscador
                            valor={lista.buscar}
                            onCambiar={buscar}
                            placeholder="Nombre, código o documento"
                            etiqueta="Buscar estudiante"
                            className="col-span-2 md:col-span-1"
                        />
                    </div>
                    <Chips
                        etiqueta="Condición"
                        valor={condicion}
                        onCambiar={(valor) => filtrar('condicion', valor)}
                        opciones={[
                            { valor: null, etiqueta: 'Todos', conteo: condiciones.todos ?? 0 },
                            {
                                valor: 'habilitado',
                                etiqueta: 'Habilitados',
                                conteo: condiciones.habilitado ?? 0,
                            },
                            {
                                valor: 'no',
                                etiqueta: 'No habilitados',
                                conteo: condiciones.no ?? 0,
                            },
                            {
                                valor: 'pendiente',
                                etiqueta: 'Sin revisar',
                                conteo: condiciones.pendiente ?? 0,
                            },
                        ]}
                    />
                </div>

                {cantidad > 0 && (
                    <div
                        data-testid="panel-seleccion"
                        className="border-t border-slate-200 bg-slate-50 p-4 max-md:fixed max-md:inset-x-0 max-md:bottom-[calc(4.5rem+env(safe-area-inset-bottom))] max-md:z-10 max-md:bg-white max-md:shadow-[0_-4px_12px_rgba(15,23,42,0.12)] sm:px-5"
                    >
                        <div className="mb-2 flex flex-wrap items-center gap-x-2 md:hidden">
                            {resumenSeleccion}
                        </div>
                        <AreaTexto
                            etiqueta="Motivo de la inhabilitación"
                            requerido
                            rows={2}
                            maxLength={MOTIVO_MAXIMO}
                            value={motivo}
                            error={errorMotivo}
                            pie={`${motivo.length}/${MOTIVO_MAXIMO}`}
                            onChange={(e) => {
                                const texto = e.target.value;
                                setMotivo(texto);
                                setErrorMotivo(
                                    texto.trim().length < MOTIVO_MINIMO
                                        ? `Mínimo ${MOTIVO_MINIMO} caracteres`
                                        : null
                                );
                            }}
                        />
                        <div className="mt-3 flex flex-wrap items-center justify-end gap-2">
                            <div className="mr-auto hidden flex-wrap items-center gap-x-2 md:flex">
                                {resumenSeleccion}
                            </div>
                            <Boton
                                variante="peligroContorno"
                                className="max-md:flex-1"
                                disabled={enviando}
                                onClick={inhabilitar}
                            >
                                <XCircle className="h-4 w-4" /> Inhabilitar
                            </Boton>
                            <Boton
                                className="max-md:flex-1"
                                disabled={enviando}
                                onClick={() => cambiar(true)}
                            >
                                <CheckCircle2 className="h-4 w-4" /> Habilitar
                            </Boton>
                        </div>
                    </div>
                )}

                {filas.length === 0 ? (
                    <p className="border-t border-slate-200 py-10 text-center text-sm text-slate-600">
                        {(cifras.inscritos ?? 0) === 0 ? 'Sin estudiantes' : 'Sin resultados'}
                    </p>
                ) : (
                    <>
                        <div className="border-t border-slate-200 md:hidden">
                            <label className="flex min-h-11 cursor-pointer items-center gap-1 bg-slate-50 pr-4 text-xs font-medium uppercase tracking-wide text-slate-600">
                                <span className="flex min-h-11 min-w-11 items-center justify-center">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4"
                                        checked={paginaMarcada}
                                        onChange={alternarPagina}
                                    />
                                </span>
                                Seleccionar la página
                            </label>
                            <ul className="divide-y divide-slate-200">
                                {filas.map((e) => (
                                    <li
                                        key={e.estudiante_id}
                                        className="flex items-start gap-1 py-2 pr-4"
                                    >
                                        {casilla(e)}
                                        <div className="min-w-0 flex-1 py-1">
                                            <div className="flex items-start justify-between gap-2">
                                                <p className="min-w-0 break-words text-sm font-medium text-slate-800">
                                                    {e.nombre}
                                                </p>
                                                <Condicion estado={e.estado} />
                                            </div>
                                            <p className="text-xs text-slate-600">
                                                <span className="font-mono">{e.codigo}</span> ·
                                                Grupo {e.grupo}
                                                {e.aula ? ` · Aula ${e.aula}` : ''}
                                            </p>
                                            {e.motivo && (
                                                <p className="mt-1 break-words text-xs text-slate-700">
                                                    {e.motivo}
                                                </p>
                                            )}
                                            {autoria(e) && (
                                                <p className="text-xs text-slate-500">
                                                    {autoria(e)}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="hidden overflow-x-auto border-t border-slate-200 md:block">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-600">
                                    <tr>
                                        <th scope="col" className="w-11 pl-2">
                                            <label className="flex min-h-11 min-w-11 cursor-pointer items-center justify-center">
                                                <input
                                                    type="checkbox"
                                                    className="h-4 w-4"
                                                    aria-label="Seleccionar la página"
                                                    checked={paginaMarcada}
                                                    onChange={alternarPagina}
                                                />
                                            </label>
                                        </th>
                                        {COLUMNAS.map((t) => (
                                            <th
                                                key={t}
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                {t}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200">
                                    {filas.map((e) => (
                                        <tr
                                            key={e.estudiante_id}
                                            className={
                                                marcado(e.estudiante_id)
                                                    ? 'bg-primary-50'
                                                    : 'hover:bg-slate-50'
                                            }
                                        >
                                            <td className="pl-2">{casilla(e)}</td>
                                            <td className="px-3 py-2 font-mono text-xs text-slate-700">
                                                {e.codigo}
                                            </td>
                                            <td className="px-3 py-2 font-medium text-slate-800">
                                                {e.nombre}
                                            </td>
                                            <td className="px-3 py-2 text-slate-700">{e.grupo}</td>
                                            <td className="px-3 py-2">
                                                <Condicion estado={e.estado} />
                                            </td>
                                            <td className="px-3 py-2 text-slate-700">
                                                {e.aula ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 text-slate-600">
                                                {e.motivo ?? '—'}
                                                {autoria(e) && (
                                                    <span className="block text-xs text-slate-500">
                                                        {autoria(e)}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Paginacion
                            {...lista.paginacion(meta)}
                            unidad={['estudiante', 'estudiantes']}
                            className="border-t border-slate-200 sm:px-5"
                        />
                    </>
                )}
            </Tarjeta>
            {aviso}
        </div>
    );
}

ListaDelExamen.propTypes = { examenId: PropTypes.number.isRequired };

// Habilitación y distribución del examen elegido (`?examen=`).
export default function Habilitacion() {
    const { datos, cargando, error, recargar } = usarConsulta('/examenes');
    const examenes = datos ?? [];
    const [examen, elegir] = usarExamen(examenes);

    return (
        <div className="space-y-6">
            <Encabezado
                titulo="Habilitación"
                subtitulo={
                    examen
                        ? `${examen.tipo_texto ?? examen.tipo} · ${examen.asignatura?.nombre ?? ''}`
                        : undefined
                }
                volver={{ a: '/examenes', texto: 'Exámenes' }}
            />
            {examen ? (
                <>
                    <SelectorExamen valor={examen.id} onCambiar={elegir} examenes={examenes} />
                    <ListaDelExamen key={examen.id} examenId={examen.id} />
                </>
            ) : (
                <Tarjeta sinRelleno>
                    <EstadoCarga
                        cargando={cargando}
                        error={error}
                        vacio
                        textoVacio="Sin exámenes"
                        onReintentar={recargar}
                    />
                </Tarjeta>
            )}
        </div>
    );
}
