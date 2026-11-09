import PropTypes from 'prop-types';
import { useCallback, useEffect, useState } from 'react';
import {
    CheckCircle2,
    CircleCheck,
    CircleX,
    Hourglass,
    QrCode,
    Shuffle,
    UsersRound,
    XCircle,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { api } from '../../api/cliente';
import { codigoDe, erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Chips from '../../componentes/Chips';
import Dato from '../../componentes/Dato';
import Dialogo from '../../componentes/Dialogo';
import rutaDelExamen from '../examenes/rutaDelExamen';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Seleccion, { AreaTexto } from '../../componentes/Seleccion';
import SelectorExamen from '../../componentes/SelectorExamen';
import Tarjeta from '../../componentes/Tarjeta';
import usarExamen from '../../componentes/usarExamen';
import { usarSesion } from '../../sesion/SesionContexto';
import { fechaCorta, plural } from '../../utiles/texto';

const LOTE_CON_CONFIRMACION = 10;

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const POR_PAGINA = 25;
const MOTIVO_MINIMO = 5;

// Quién registró la inhabilitación y cuándo: «Blanco Coca Leticia · 9 oct 2026, 14:05».
const autoria = (e) => [e.registrada_por, fechaCorta(e.registrada_el)].filter(Boolean).join(' · ');
const MOTIVO_MAXIMO = 1000;
// Todos revisados, al menos un habilitado y todos con aula: ya se pueden
// emitir los códigos QR (la credencial lleva el aula).
const completa = (cifras) =>
    (cifras?.sin_revisar ?? 1) === 0 &&
    (cifras?.habilitados ?? 0) > 0 &&
    (cifras?.sin_aula ?? 0) === 0;
const CONDICION = {
    habilitado: ['exito', 'Habilitado'],
    no: ['peligro', 'No habilitado'],
    pendiente: ['neutro', 'Sin revisar'],
};
const SIN_FILTROS = { grupo: null, aula: null, condicion: null };
const COLUMNAS = ['Código SIS', 'Estudiante', 'Grupo', 'Condición', 'Aula', 'Motivo'];

// «3 habilitados.» → «3 habilitados».
const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

function Condicion({ estado }) {
    const [tono, texto] = CONDICION[estado] ?? CONDICION.pendiente;
    return <Insignia tono={tono}>{texto}</Insignia>;
}

Condicion.propTypes = { estado: PropTypes.string };

// «Aguilar Cossío Mariana» → «AM»: el primer apellido y el último nombre.
const iniciales = (nombre) => {
    const partes = String(nombre ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
    return `${partes[0]?.[0] ?? ''}${partes.length > 1 ? partes[partes.length - 1][0] : ''}`.toUpperCase();
};

// La foto del inscrito en círculo, como en el aula virtual; sin foto, sus
// iniciales. Con foto se toca para verla grande.
function FotoInscrito({ url, nombre, conFoto, onAmpliar }) {
    const [fallo, setFallo] = useState(false);
    const hayFoto = conFoto && !fallo;
    const circulo =
        'flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full ring-2 ring-white';
    if (!hayFoto)
        return (
            <span
                className={`${circulo} bg-slate-200 text-xs font-semibold text-slate-600`}
                title="Sin foto"
                aria-label={`${nombre} sin foto`}
                role="img"
            >
                {iniciales(nombre)}
            </span>
        );
    return (
        <button
            type="button"
            onClick={onAmpliar}
            className={`${circulo} shadow-sm hover:ring-primary-300 ${FOCO}`}
            aria-label={`Ver la foto de ${nombre}`}
        >
            <img
                src={url}
                alt=""
                loading="lazy"
                className="h-full w-full object-cover object-[50%_25%]"
                onError={() => setFallo(true)}
            />
        </button>
    );
}

FotoInscrito.propTypes = {
    url: PropTypes.string.isRequired,
    nombre: PropTypes.string.isRequired,
    conFoto: PropTypes.bool,
    onAmpliar: PropTypes.func.isRequired,
};

// La lista de un examen: cifras, distribución por aula, filtros, selección
// y lotes. Se monta de nuevo con cada examen (`key`), así el cambio de
// examen limpia la selección, los filtros, la búsqueda y el motivo.
export function ListaDelExamen({ examenId, onCifras }) {
    const ruta = `/examenes/${examenId}/habilitaciones`;
    const lista = usarPaginaServidor({ porPagina: POR_PAGINA, filtros: SIN_FILTROS });
    const { datos, meta, cargando, error, recargar } = usarConsulta(ruta, {
        parametros: lista.parametros,
    });
    // La selección sobrevive al cambio de página: son `estudiante_id`.
    // `todos` es «Seleccionar los N»: el lote viaja con los filtros.
    const [seleccion, setSeleccion] = useState(() => new Set());
    const [todos, setTodos] = useState(false);
    // Con «todos», los que se desmarcaron después.
    const [excluidos, setExcluidos] = useState(() => new Set());
    const [motivo, setMotivo] = useState('');
    const [errorMotivo, setErrorMotivo] = useState(null);
    const [enviando, setEnviando] = useState(false);
    // Un lote grande se confirma antes: no se deshace con un clic.
    const [porConfirmar, setPorConfirmar] = useState(null);
    const [ampliada, setAmpliada] = useState(null);
    const urlFoto = (e) => `/api${ruta}/${e.estudiante_id}/foto`;
    const foto = (e) => (
        <FotoInscrito
            url={urlFoto(e)}
            nombre={e.nombre}
            conFoto={Boolean(e.con_foto)}
            onAmpliar={() => setAmpliada(e)}
        />
    );
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

    // Las cifras vigentes suben a la página: de ellas depende el enlace a
    // los códigos QR.
    const cifrasVigentes = meta?.cifras;
    useEffect(() => {
        if (cifrasVigentes) onCifras(cifrasVigentes);
    }, [cifrasVigentes, onCifras]);

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
    const marcado = (id) => (todos ? !excluidos.has(id) : seleccion.has(id));
    const paginaMarcada = filas.length > 0 && idsPagina.every(marcado);
    const cantidad = todos ? total - excluidos.size : seleccion.size;
    const motivoCorto = motivo.trim().length < MOTIVO_MINIMO;

    const seleccionarTodos = () => {
        setTodos(true);
        setExcluidos(new Set());
    };
    const quitar = () => {
        setTodos(false);
        setExcluidos(new Set());
        setSeleccion(new Set());
    };
    const filtrar = (clave, valor) => {
        quitar();
        lista.ponerFiltro(clave, valor);
    };
    const buscar = (texto) => {
        quitar();
        lista.ponerBuscar(texto);
    };
    // Con «todos», desmarcar uno lo excluye del lote: sigue «todos menos él».
    const alternar = (id) => {
        const cambiado = (previo) => {
            const despues = new Set(previo);
            if (despues.has(id)) despues.delete(id);
            else despues.add(id);
            return despues;
        };
        if (todos) setExcluidos(cambiado);
        else setSeleccion(cambiado);
    };
    const alternarPagina = () => {
        if (todos) {
            const despues = new Set(excluidos);
            idsPagina.forEach((i) => (paginaMarcada ? despues.add(i) : despues.delete(i)));
            setExcluidos(despues);
            return;
        }
        const despues = new Set(seleccion);
        idsPagina.forEach((i) => (paginaMarcada ? despues.delete(i) : despues.add(i)));
        setSeleccion(despues);
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

    const pedir = (habilitado) => {
        if (cantidad >= LOTE_CON_CONFIRMACION) setPorConfirmar(habilitado);
        else cambiar(habilitado);
    };

    const cambiar = async (habilitado) => {
        setPorConfirmar(null);
        if (enviando || cantidad === 0) return;
        const cuerpo = {
            habilitado,
            ...(habilitado ? {} : { motivo: motivo.trim() }),
            ...(todos
                ? { todos: true, filtros: lista.filtrosVigentes, excluidos: [...excluidos] }
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
        pedir(false);
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
                <Boton variante="enlace" tamano="chico" onClick={seleccionarTodos}>
                    Seleccionar los {total}
                </Boton>
            )}
            <Boton variante="enlace" tamano="chico" onClick={quitar}>
                Deseleccionar
            </Boton>
        </>
    );

    return (
        <div className={`space-y-6 ${cantidad > 0 ? 'max-md:pb-64' : ''}`}>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Dato
                    etiqueta={`Inscritos · ${plural(grupos.length, 'grupo')}`}
                    icono={UsersRound}
                    valor={cifras.inscritos ?? 0}
                />
                <Dato
                    etiqueta="Habilitados"
                    icono={CircleCheck}
                    valor={cifras.habilitados ?? 0}
                    tono="exito"
                />
                <Dato
                    etiqueta="No habilitados"
                    icono={CircleX}
                    valor={cifras.no_habilitados ?? 0}
                    tono={cifras.no_habilitados ? 'advertencia' : 'neutro'}
                />
                <Dato etiqueta="Sin revisar" icono={Hourglass} valor={cifras.sin_revisar ?? 0} />
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
                            etiqueta="Motivo para inhabilitar"
                            rows={2}
                            maxLength={MOTIVO_MAXIMO}
                            value={motivo}
                            error={errorMotivo}
                            pie={`${motivo.length}/${MOTIVO_MAXIMO}`}
                            onChange={(e) => {
                                // Se valida al inhabilitar, no mientras se escribe.
                                setMotivo(e.target.value);
                                setErrorMotivo(null);
                            }}
                        />
                        <div className="mt-3 flex flex-wrap items-center justify-end gap-2">
                            <div className="mr-auto hidden flex-wrap items-center gap-x-2 md:flex">
                                {resumenSeleccion}
                            </div>
                            <Boton
                                variante="peligroContorno"
                                className="max-md:flex-1"
                                cargando={enviando}
                                onClick={inhabilitar}
                            >
                                <XCircle className="h-4 w-4" /> Inhabilitar
                            </Boton>
                            <Boton
                                className="max-md:flex-1"
                                cargando={enviando}
                                onClick={() => pedir(true)}
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
                                        <span className="py-1">{foto(e)}</span>
                                        <div className="min-w-0 flex-1 py-1 pl-2">
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
                                                <span className="flex items-center gap-3">
                                                    {foto(e)}
                                                    {e.nombre}
                                                </span>
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
            {ampliada && (
                <Dialogo
                    titulo={ampliada.nombre}
                    onCerrar={() => setAmpliada(null)}
                    ancho="max-w-sm"
                >
                    <img
                        src={urlFoto(ampliada)}
                        alt={`Foto de ${ampliada.nombre}`}
                        className="mx-auto aspect-square w-full max-w-64 rounded-xl object-cover object-[50%_25%] shadow-md"
                    />
                    <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt className="text-xs text-slate-500">Código SIS</dt>
                            <dd className="font-mono text-slate-800">{ampliada.codigo}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-slate-500">Grupo</dt>
                            <dd className="text-slate-800">{ampliada.grupo}</dd>
                        </div>
                        <div className="col-span-2">
                            <dt className="mb-1 text-xs text-slate-500">Condición</dt>
                            <dd>
                                <Condicion estado={ampliada.estado} />
                            </dd>
                        </div>
                    </dl>
                </Dialogo>
            )}
            {porConfirmar !== null && (
                <Dialogo
                    titulo={porConfirmar ? 'Habilitar' : 'Inhabilitar'}
                    onCerrar={() => setPorConfirmar(null)}
                    acciones={
                        <>
                            <Boton variante="secundario" onClick={() => setPorConfirmar(null)}>
                                Cancelar
                            </Boton>
                            <Boton
                                variante={porConfirmar ? 'primario' : 'peligro'}
                                onClick={() => cambiar(porConfirmar)}
                                cargando={enviando}
                            >
                                {porConfirmar ? 'Habilitar' : 'Inhabilitar'} {cantidad}
                            </Boton>
                        </>
                    }
                >
                    <p className="text-sm text-slate-700">
                        {plural(cantidad, 'estudiante')}
                        {!porConfirmar && motivo.trim() && ` · ${motivo.trim()}`}
                    </p>
                </Dialogo>
            )}
            {aviso}
        </div>
    );
}

ListaDelExamen.propTypes = {
    examenId: PropTypes.number.isRequired,
    onCifras: PropTypes.func.isRequired,
};

// Habilitación y distribución del examen elegido (`?examen=`).
export default function Habilitacion() {
    const { datos, cargando, error, recargar } = usarConsulta('/examenes');
    const examenes = datos ?? [];
    const [examen, elegir] = usarExamen(examenes);
    const { puede } = usarSesion();
    // Las cifras del examen a la vista, al día tras cada lote.
    const [vigentes, setVigentes] = useState(null);
    const examenId = examen?.id ?? null;
    const anotar = useCallback((cifras) => setVigentes({ examenId, cifras }), [examenId]);
    const cifras = vigentes?.examenId === examenId ? vigentes.cifras : examen;

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
            >
                {examen && puede('codigos_qr') && (
                    <Link
                        to={rutaDelExamen(examen.id, 'qr')}
                        className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium ${FOCO} ${
                            completa(cifras)
                                ? 'bg-primary-600 text-white hover:bg-primary-700'
                                : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                        }`}
                    >
                        <QrCode className="h-4 w-4" /> Emitir códigos QR
                    </Link>
                )}
            </Encabezado>
            {examen ? (
                <>
                    <SelectorExamen valor={examen.id} onCambiar={elegir} examenes={examenes} />
                    <ListaDelExamen key={examen.id} examenId={examen.id} onCifras={anotar} />
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
