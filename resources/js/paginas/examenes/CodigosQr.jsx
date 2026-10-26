import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';
import { useState } from 'react';
import { Megaphone, Printer, QrCode, Send } from 'lucide-react';
import { api } from '../../api/cliente';
import { codigoDe, erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarEdificios from '../../api/usarEdificios';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Dato from '../../componentes/Dato';
import Dialogo from '../../componentes/Dialogo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Paginacion from '../../componentes/Paginacion';
import Seleccion from '../../componentes/Seleccion';
import SelectorExamen from '../../componentes/SelectorExamen';
import Tarjeta from '../../componentes/Tarjeta';
import usarExamen from '../../componentes/usarExamen';
import { fechaCorta, fechaLarga, plural } from '../../utiles/texto';
import CredencialExamen, { EstadoEnvio } from './CredencialExamen';

// La hora del navegador como «AAAA-MM-DD HH:MM»: `toISOString` la daría en UTC.
const ahoraLocal = () => {
    const d = new Date();
    const dos = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())} ${dos(d.getHours())}:${dos(d.getMinutes())}`;
};

const POR_PAGINA = 25;
const SIN_FILTROS = { grupo: null, aula: null, envio: null };
// Con cola sincrónica el servidor envía por tandas (`pendientes`): se repite
// solo mientras avanza y nunca más de estas veces.
const MAXIMO_DE_TANDAS = 30;
const MAXIMO_DE_RECOMENDACIONES = 2000;
const ENVIOS = {
    sin_emitir: 'Sin emitir',
    sin_enviar: 'Sin enviar',
    enviado: 'Enviados',
    fallido: 'Fallidos',
};
const VOLVER = { a: '/examenes', texto: 'Exámenes' };

// «251 códigos emitidos.» → «251 códigos emitidos».
const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');
const tituloDe = (examen) =>
    `${examen.tipo_texto ?? examen.tipo} · ${examen.asignatura?.nombre ?? ''}`;
const lugar = (aula) => (aula ? `Aula ${aula}` : 'Sin aula');

// Los filtros con valor, como los esperan la ruta de envíos y la hoja.
function filtrosCon({ grupo, aula, envio, buscar }) {
    return {
        ...(grupo ? { grupo } : {}),
        ...(aula ? { aula } : {}),
        ...(envio ? { envio } : {}),
        ...(buscar ? { buscar } : {}),
    };
}

// Repite un envío por tandas: devuelve la `tanda` del servidor en cada vuelta
// (así no repite destinatarios) y se detiene cuando `pendientes` llega a cero
// o deja de bajar (los correos están fallando: no se insiste).
async function enTandas(ruta, cuerpo, alAvanzar) {
    let salieron = 0;
    let antes = Infinity;
    let quedan = 0;
    let tanda = null;
    for (let vuelta = 0; vuelta < MAXIMO_DE_TANDAS; vuelta += 1) {
        const { data } = await api.post(ruta, tanda ? { ...cuerpo, tanda } : cuerpo);
        salieron += data?.encolados ?? 0;
        quedan = data?.pendientes ?? 0;
        tanda = data?.tanda ?? null;
        if (quedan === 0 || quedan >= antes) break;
        antes = quedan;
        alAvanzar(quedan);
    }
    return { salieron, quedan };
}

// Los códigos de un examen: emisión en lote, credencial de cada habilitado,
// hoja imprimible, envío por correo y su estado. Se monta de nuevo con cada
// examen (`key`): el cambio limpia filtros, búsqueda y credencial elegida.
function CodigosDelExamen({ examen, selector }) {
    const ruta = `/examenes/${examen.id}/codigos-qr`;
    const lista = usarPaginaServidor({ porPagina: POR_PAGINA, filtros: SIN_FILTROS });
    const { datos, meta, cargando, error, recargar } = usarConsulta(ruta, {
        parametros: lista.parametros,
    });
    const [elegido, setElegido] = useState(null);
    const [dialogo, setDialogo] = useState(false);
    const [ocupado, setOcupado] = useState(null);
    const [quedan, setQuedan] = useState(null);
    const [avisando, setAvisando] = useState(false);
    const [recomendaciones, setRecomendaciones] = useState('');
    const [resultadoAviso, setResultadoAviso] = useState(null);
    const [aviso, avisar] = useAviso();
    const { edificios, caja } = usarEdificios();

    const filas = datos ?? [];
    // La credencial a la vista: la elegida o, si el filtro la dejó fuera, la primera.
    const muestra = filas.find((e) => e.estudiante_id === elegido) ?? filas[0] ?? null;
    const credencial = usarConsulta(muestra ? `${ruta}/${muestra.estudiante_id}` : null);

    const encabezado = (acciones) => (
        <>
            <Encabezado titulo="Códigos QR" subtitulo={tituloDe(examen)} volver={VOLVER}>
                {acciones}
            </Encabezado>
            {selector}
        </>
    );

    if (cargando || error) {
        return (
            <div className="space-y-6">
                {encabezado(null)}
                <Tarjeta sinRelleno>
                    {error && estadoDe(error) === 403 ? (
                        <p role="alert" className="px-4 py-8 text-center text-sm text-slate-600">
                            {sinPunto(mensajeDe(error, 'Sin permiso'))}
                        </p>
                    ) : (
                        <EstadoCarga cargando={cargando} error={error} onReintentar={recargar} />
                    )}
                </Tarjeta>
            </div>
        );
    }

    const habilitados = meta?.habilitados ?? 0;
    const emitidos = meta?.emitidos ?? 0;
    const enviados = meta?.enviados ?? 0;
    const fallidos = meta?.fallidos ?? 0;
    // Con habilitados sin aula no se emite ni se avisa: falta repartir.
    const sinAula = meta?.sin_aula ?? 0;
    const sinEnviar = Math.max(0, emitidos - enviados - fallidos);
    const total = meta?.total ?? 0;
    const { grupo, aula, envio } = lista.filtros;
    const vigentes = filtrosCon(lista.filtrosVigentes);
    const filtrado = Object.keys(vigentes).length > 0;
    // Con filtro, el envío y la impresión son solo de lo filtrado.
    const cuantos = filtrado ? total : emitidos;
    const grupos = meta?.grupos ?? [];
    const aulas = meta?.aulas ?? [];
    const grupoElegido = grupos.find((g) => g.id === grupo);
    const aulaElegida = aulas.find((a) => a.aula_id === aula);
    const destino = [
        grupoElegido && `Grupo ${grupoElegido.codigo}`,
        aulaElegida && `Aula ${aulaElegida.nombre}`,
        envio && ENVIOS[envio],
    ]
        .filter(Boolean)
        .map((parte) => ` · ${parte}`)
        .join('');

    const refrescar = () => Promise.all([recargar(), credencial.recargar()]);
    const rechazo = (fallo, porDefecto) => {
        const codigo = codigoDe(fallo);
        if (codigo === 'SIN_HABILITADOS') return avisar('Sin estudiantes habilitados', 'error');
        if (codigo === 'SIN_EMITIR') return avisar('Sin códigos emitidos', 'error');
        if (codigo === 'SIN_AULA')
            return avisar(sinPunto(mensajeDe(fallo, 'Habilitados sin aula')), 'error');
        return avisar(sinPunto(mensajeDe(fallo, porDefecto)), 'error');
    };

    const emitir = async () => {
        if (ocupado) return;
        setOcupado('emitir');
        try {
            const { data } = await api.post(ruta);
            avisar(sinPunto(data?.message) || 'Códigos emitidos');
            await refrescar();
        } catch (fallo) {
            rechazo(fallo, 'No se pudo emitir');
        } finally {
            setOcupado(null);
        }
    };

    const enviar = async () => {
        if (ocupado) return;
        setOcupado('enviar');
        try {
            const { salieron, quedan: sinSalir } = await enTandas(
                `${ruta}/envios`,
                vigentes,
                setQuedan
            );
            setDialogo(false);
            if (sinSalir > 0) avisar(`${plural(sinSalir, 'código')} sin enviar`, 'error');
            else avisar(plural(salieron, 'código enviado', 'códigos enviados'));
        } catch (fallo) {
            setDialogo(false);
            rechazo(fallo, 'No se pudo enviar');
        } finally {
            setOcupado(null);
            setQuedan(null);
            await refrescar();
        }
    };

    // El aviso del examen (fecha, aula, normas y recomendaciones) a los
    // habilitados del filtro, tengan o no código.
    const enviarAviso = async () => {
        if (ocupado) return;
        setOcupado('aviso');
        try {
            const texto = recomendaciones.trim();
            const { salieron, quedan: sinSalir } = await enTandas(
                `${ruta}/avisos`,
                { ...vigentes, ...(texto ? { recomendaciones: texto } : {}) },
                setQuedan
            );
            setAvisando(false);
            setRecomendaciones('');
            setResultadoAviso({ salieron, sinSalir, cuando: ahoraLocal() });
            if (sinSalir > 0) avisar(`${plural(sinSalir, 'aviso')} sin enviar`, 'error');
            else avisar(plural(salieron, 'aviso enviado', 'avisos enviados'));
        } catch (fallo) {
            const campo = erroresDe(fallo).recomendaciones;
            if (!campo) setAvisando(false);
            rechazo(fallo, 'No se pudo enviar');
        } finally {
            setOcupado(null);
            setQuedan(null);
        }
    };

    // Una sola credencial, por estudiante.
    const enviarUna = async () => {
        if (ocupado || !muestra) return;
        setOcupado('una');
        try {
            await api.post(`${ruta}/${muestra.estudiante_id}/envio`);
            avisar(
                muestra.enviado_en || muestra.envio === 'fallido'
                    ? 'Código reenviado'
                    : 'Código enviado'
            );
        } catch (fallo) {
            rechazo(fallo, 'No se pudo enviar');
        } finally {
            setOcupado(null);
            await refrescar();
        }
    };

    const imprimir = () => {
        const consulta = new URLSearchParams(vigentes).toString();
        window.open(`/api${ruta}/hoja${consulta ? `?${consulta}` : ''}`, '_blank', 'noopener');
    };

    const acciones = (
        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            {emitidos < habilitados && (
                <Boton type="button" disabled={ocupado !== null || sinAula > 0} onClick={emitir}>
                    <QrCode className="h-4 w-4" /> Emitir códigos
                </Boton>
            )}
            <Boton
                type="button"
                variante="secundario"
                disabled={total === 0 || ocupado !== null || sinAula > 0}
                onClick={() => setAvisando(true)}
            >
                <Megaphone className="h-4 w-4" /> Enviar aviso
            </Boton>
            {emitidos > 0 && (
                <>
                    <Boton
                        type="button"
                        variante="secundario"
                        disabled={cuantos === 0 || ocupado !== null}
                        onClick={() => setDialogo(true)}
                    >
                        <Send className="h-4 w-4" /> Enviar por correo
                    </Boton>
                    <Boton
                        type="button"
                        variante={emitidos < habilitados ? 'secundario' : 'primario'}
                        disabled={cuantos === 0}
                        onClick={imprimir}
                    >
                        <Printer className="h-4 w-4" /> Imprimir credenciales
                    </Boton>
                </>
            )}
        </div>
    );

    return (
        <div className="space-y-6">
            {encabezado(habilitados > 0 ? acciones : null)}

            {sinAula > 0 && (
                <p
                    role="status"
                    className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-warning-50 px-4 py-3 text-sm font-medium text-warning-800"
                >
                    {plural(sinAula, 'habilitado sin aula', 'habilitados sin aula')}
                    <Link
                        to={`/habilitacion?examen=${examen.id}`}
                        className="font-semibold text-primary-700 underline-offset-2 hover:underline"
                    >
                        Repartir aulas
                    </Link>
                </p>
            )}

            {habilitados === 0 ? (
                <p className="py-10 text-center text-sm text-slate-600">
                    Sin estudiantes habilitados
                </p>
            ) : (
                <>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <Dato etiqueta="Habilitados" valor={habilitados} />
                        <Dato
                            etiqueta="Emitidos"
                            valor={emitidos}
                            tono={emitidos < habilitados ? 'advertencia' : 'neutro'}
                        />
                        <Dato etiqueta="Enviados" valor={enviados} />
                        <Dato
                            etiqueta="Sin enviar"
                            valor={sinEnviar}
                            tono={sinEnviar > 0 ? 'advertencia' : 'neutro'}
                        />
                        <Dato
                            etiqueta="Fallidos"
                            valor={fallidos}
                            tono={fallidos > 0 ? 'peligro' : 'neutro'}
                        />
                    </div>

                    {resultadoAviso && (
                        <p role="status" className="text-sm text-slate-600">
                            Aviso · {fechaCorta(resultadoAviso.cuando)} ·{' '}
                            {plural(resultadoAviso.salieron, 'enviado')}
                            {resultadoAviso.sinSalir > 0 &&
                                ` · ${resultadoAviso.sinSalir} sin enviar`}
                        </p>
                    )}

                    <div className="grid items-start gap-6 lg:grid-cols-2">
                        <div className="grid min-w-0 gap-6 lg:sticky lg:top-6">
                            {muestra && (
                                <CredencialExamen
                                    credencial={credencial.datos}
                                    cargando={credencial.cargando}
                                    error={credencial.error}
                                    onReintentar={credencial.recargar}
                                    enviadoEn={muestra.enviado_en}
                                    fallido={muestra.envio === 'fallido'}
                                    errorEnvio={muestra.error}
                                    onEnviar={enviarUna}
                                    enviando={ocupado !== null}
                                    edificios={edificios}
                                    caja={caja}
                                />
                            )}
                        </div>

                        <Tarjeta titulo="Estudiantes" sinRelleno className="lg:order-first">
                            <div className="grid grid-cols-2 gap-3 p-4 sm:px-5">
                                <Seleccion
                                    aria-label="Grupo"
                                    className="min-w-0"
                                    value={grupo ?? ''}
                                    onChange={(e) =>
                                        lista.ponerFiltro('grupo', Number(e.target.value) || null)
                                    }
                                >
                                    <option value="">Todos los grupos</option>
                                    {grupos.map((g) => (
                                        <option key={g.id} value={g.id}>
                                            Grupo {g.codigo}
                                        </option>
                                    ))}
                                </Seleccion>
                                <Seleccion
                                    aria-label="Aula"
                                    className="min-w-0"
                                    value={aula ?? ''}
                                    onChange={(e) =>
                                        lista.ponerFiltro('aula', Number(e.target.value) || null)
                                    }
                                >
                                    <option value="">Todas las aulas</option>
                                    {aulas.map((a) => (
                                        <option key={a.aula_id} value={a.aula_id}>
                                            Aula {a.nombre}
                                        </option>
                                    ))}
                                </Seleccion>
                                <Seleccion
                                    aria-label="Envío"
                                    className="col-span-2 min-w-0"
                                    value={envio ?? ''}
                                    onChange={(e) =>
                                        lista.ponerFiltro('envio', e.target.value || null)
                                    }
                                >
                                    <option value="">Todos los envíos</option>
                                    {Object.entries(ENVIOS).map(([valor, texto]) => (
                                        <option key={valor} value={valor}>
                                            {texto}
                                        </option>
                                    ))}
                                </Seleccion>
                                <Buscador
                                    valor={lista.buscar}
                                    onCambiar={lista.ponerBuscar}
                                    placeholder="Nombre o código SIS"
                                    etiqueta="Buscar estudiante"
                                    className="col-span-2"
                                />
                            </div>
                            <ul className="divide-y divide-slate-200 border-t border-slate-200">
                                {filas.map((e) => {
                                    const activo = e.estudiante_id === muestra?.estudiante_id;
                                    return (
                                        <li key={e.estudiante_id}>
                                            <button
                                                type="button"
                                                aria-pressed={activo}
                                                onClick={() => setElegido(e.estudiante_id)}
                                                className={`flex min-h-11 w-full items-center gap-3 px-4 py-2 text-left text-sm focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600 sm:px-5 ${activo ? 'bg-primary-50' : 'hover:bg-slate-50'}`}
                                            >
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate font-medium text-slate-800">
                                                        {e.nombre}
                                                    </span>
                                                    <span className="block text-xs text-slate-600">
                                                        <span className="font-mono">
                                                            {e.codigo}
                                                        </span>{' '}
                                                        · Grupo {e.grupo} · {lugar(e.aula)}
                                                    </span>
                                                </span>
                                                <span className="shrink-0">
                                                    <EstadoEnvio
                                                        emitido={e.emitido}
                                                        enviadoEn={e.enviado_en}
                                                        fallido={e.envio === 'fallido'}
                                                    />
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                                {filas.length === 0 && (
                                    <li className="px-4 py-8 text-center text-sm text-slate-600">
                                        Sin resultados
                                    </li>
                                )}
                            </ul>
                            {total > 0 && (
                                <Paginacion
                                    {...lista.paginacion(meta)}
                                    unidad={['código', 'códigos']}
                                    className="border-t border-slate-200 sm:px-5"
                                />
                            )}
                        </Tarjeta>
                    </div>
                </>
            )}

            <Dialogo
                titulo="Enviar por correo"
                abierto={dialogo}
                onCerrar={() => ocupado === null && setDialogo(false)}
                acciones={
                    <>
                        <Boton
                            type="button"
                            variante="secundario"
                            disabled={ocupado !== null}
                            onClick={() => setDialogo(false)}
                        >
                            Cancelar
                        </Boton>
                        <Boton type="button" disabled={ocupado !== null} onClick={enviar}>
                            <Send className="h-4 w-4" /> Enviar
                        </Boton>
                    </>
                }
            >
                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt className="text-slate-600">Examen</dt>
                    <dd className="min-w-0 font-medium text-slate-800">{tituloDe(examen)}</dd>
                    <dt className="text-slate-600">Destinatarios</dt>
                    <dd className="min-w-0 font-medium text-slate-800">
                        {plural(cuantos, 'estudiante')}
                        {destino}
                    </dd>
                    <dt className="text-slate-600">Correo</dt>
                    <dd className="min-w-0 font-medium text-slate-800">Institucional</dd>
                    {!filtrado && (
                        <>
                            <dt className="text-slate-600">Sin enviar</dt>
                            <dd className="min-w-0 font-medium text-slate-800">
                                {sinEnviar + fallidos}
                            </dd>
                        </>
                    )}
                </dl>
                {ocupado === 'enviar' && (
                    <p role="status" className="mt-3 text-sm text-slate-600">
                        {quedan === null ? 'Enviando…' : `Enviando · quedan ${quedan}`}
                    </p>
                )}
            </Dialogo>

            <Dialogo
                titulo="Aviso por correo"
                abierto={avisando}
                onCerrar={() => ocupado === null && setAvisando(false)}
                acciones={
                    <>
                        <Boton
                            type="button"
                            variante="secundario"
                            disabled={ocupado !== null}
                            onClick={() => setAvisando(false)}
                        >
                            Cancelar
                        </Boton>
                        <Boton
                            type="button"
                            disabled={
                                ocupado !== null ||
                                recomendaciones.length > MAXIMO_DE_RECOMENDACIONES
                            }
                            onClick={enviarAviso}
                        >
                            <Megaphone className="h-4 w-4" /> Enviar aviso
                        </Boton>
                    </>
                }
            >
                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt className="text-slate-600">Examen</dt>
                    <dd className="min-w-0 font-medium text-slate-800">{tituloDe(examen)}</dd>
                    {examen.fecha && (
                        <>
                            <dt className="text-slate-600">Fecha</dt>
                            <dd className="min-w-0 font-medium text-slate-800">
                                {[fechaLarga(examen.fecha), examen.hora]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </dd>
                        </>
                    )}
                    <dt className="text-slate-600">Destinatarios</dt>
                    <dd className="min-w-0 font-medium text-slate-800">
                        {plural(filtrado ? total : habilitados, 'estudiante')}
                        {destino}
                    </dd>
                    <dt className="text-slate-600">Contenido</dt>
                    <dd className="min-w-0 font-medium text-slate-800">Fecha, aula y normas</dd>
                </dl>
                <label className="mt-4 block">
                    <span className="mb-1.5 block text-sm font-medium text-slate-700">
                        Recomendaciones
                    </span>
                    <textarea
                        rows={4}
                        value={recomendaciones}
                        onChange={(e) => setRecomendaciones(e.target.value)}
                        aria-invalid={
                            recomendaciones.length > MAXIMO_DE_RECOMENDACIONES ? 'true' : undefined
                        }
                        className="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-500 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                    />
                </label>
                <p
                    className={`mt-1 text-right text-xs ${recomendaciones.length > MAXIMO_DE_RECOMENDACIONES ? 'text-danger-600' : 'text-slate-500'}`}
                >
                    {recomendaciones.length} / {MAXIMO_DE_RECOMENDACIONES}
                </p>
                {ocupado === 'aviso' && (
                    <p role="status" className="mt-3 text-sm text-slate-600">
                        {quedan === null ? 'Enviando…' : `Enviando · quedan ${quedan}`}
                    </p>
                )}
            </Dialogo>
            {aviso}
        </div>
    );
}

CodigosDelExamen.propTypes = {
    examen: PropTypes.shape({
        id: PropTypes.number.isRequired,
        tipo: PropTypes.string,
        tipo_texto: PropTypes.string,
        fecha: PropTypes.string,
        hora: PropTypes.string,
        asignatura: PropTypes.shape({ nombre: PropTypes.string }),
    }).isRequired,
    selector: PropTypes.node,
};

// Códigos QR del examen elegido (`?examen=`). Cada código es de un
// estudiante y un examen, y la credencial dice dónde rinde.
export default function CodigosQr() {
    const { datos, cargando, error, recargar } = usarConsulta('/examenes');
    const examenes = datos ?? [];
    const [examen, elegir] = usarExamen(examenes);

    if (examen) {
        return (
            <CodigosDelExamen
                key={examen.id}
                examen={examen}
                selector={
                    <SelectorExamen valor={examen.id} onCambiar={elegir} examenes={examenes} />
                }
            />
        );
    }

    return (
        <div className="space-y-6">
            <Encabezado titulo="Códigos QR" volver={VOLVER} />
            <Tarjeta sinRelleno>
                <EstadoCarga
                    cargando={cargando}
                    error={error}
                    vacio
                    textoVacio="Sin exámenes"
                    onReintentar={recargar}
                />
            </Tarjeta>
        </div>
    );
}
