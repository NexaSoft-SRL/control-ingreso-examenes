import PropTypes from 'prop-types';
import { useState } from 'react';
import { ChevronRight, DownloadCloud, LoaderCircle, RefreshCw, RotateCw } from 'lucide-react';
import { Link } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import { PuntoFacultad } from '../../componentes/FiltroFacultad';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Tarjeta from '../../componentes/Tarjeta';
import { usarFacultades, usarSesion } from '../../sesion/SesionContexto';
import { diaMes, fechaCorta } from '../../utiles/texto';

const FOCO = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600';
const COLUMNAS = 'md:grid-cols-[minmax(0,1.6fr)_minmax(0,1.4fr)_minmax(0,1fr)_9rem]';
const POR_PAGINA = 4;

const miles = (n) => Number(n ?? 0).toLocaleString('es-BO');

const TONO_PERIODO = {
    Vigente: 'exito',
    Cerrado: 'neutro',
    Próximo: 'info',
    'Sin fechas': 'advertencia',
};

const ESTADOS = {
    sin: { tono: 'neutro', texto: () => 'Sin importar' },
    importando: { tono: 'primario', texto: () => 'Importando' },
    importada: {
        tono: 'exito',
        texto: (fecha) => (fecha ? `Importada · ${fechaCorta(fecha)}` : 'Importada'),
    },
    fallo: { tono: 'peligro', texto: () => 'Falló' },
};

const PENDIENTES = [
    {
        clave: 'docentes_sin_cuenta',
        titulo: 'Docentes sin cuenta',
        ruta: '/docentes',
        accion: 'Activar cuentas',
        permiso: 'aulas_docentes',
    },
    {
        clave: 'grupos_sin_lista',
        titulo: 'Grupos sin lista de inscritos',
        ruta: '/estudiantes',
        accion: 'Ir al padrón',
        permiso: 'padron_estudiantes',
    },
];

// '2026-08-10', '2026-12-26' → '10 ago – 26 dic 2026'; el mismo mes, '9 – 31 jul 2026'.
function rango(inicio, fin, conAnio = true) {
    if (!inicio || !fin) return '';
    const [anioI, mesI, diaI] = inicio.slice(0, 10).split('-').map(Number);
    const [anioF, mesF] = fin.slice(0, 10).split('-').map(Number);
    const hasta = conAnio ? fechaCorta(fin.slice(0, 10)) : diaMes(fin);
    if (anioI !== anioF) return `${fechaCorta(inicio.slice(0, 10))} – ${hasta}`;
    return `${mesI === mesF ? diaI : diaMes(inicio)} – ${hasta}`;
}

function subtituloDe(resumen) {
    if (!resumen?.hoy) return null;
    const ventana = resumen.ventana;
    const hoy = `Hoy: ${fechaCorta(resumen.hoy)}`;
    return ventana
        ? `${hoy} · ${ventana.nombre} ${rango(ventana.desde, ventana.hasta, false)}`
        : hoy;
}

// Fechas de un período: la fuente no las trajo y las fija el administrador.
function AjustarFechas({ periodo, onCerrar, onGuardado }) {
    const [inicio, setInicio] = useState(periodo.fecha_inicio ?? '');
    const [fin, setFin] = useState(periodo.fecha_fin ?? '');
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [guardando, setGuardando] = useState(false);

    function guardar(evento) {
        evento.preventDefault();
        const locales = {};
        if (!inicio) locales.fecha_inicio = 'Obligatorio';
        if (!fin) locales.fecha_fin = 'Obligatorio';
        else if (inicio && fin <= inicio) locales.fecha_fin = 'Debe ser posterior al inicio';
        setErrores(locales);
        setGeneral(null);
        if (Object.keys(locales).length > 0) return;

        setGuardando(true);
        api.put(`/periodos/${periodo.id}`, { fecha_inicio: inicio, fecha_fin: fin })
            .then((respuesta) => onGuardado(respuesta.data?.data ?? null))
            .catch((error) => {
                const deCampo = erroresDe(error);
                setErrores(deCampo);
                if (Object.keys(deCampo).length === 0)
                    setGeneral(mensajeDe(error, 'No se pudo guardar'));
                setGuardando(false);
            });
    }

    const cambiar = (campo, poner) => (e) => {
        poner(e.target.value);
        setErrores((previos) => ({ ...previos, [campo]: undefined }));
    };

    return (
        <Dialogo
            titulo={`Fechas del período ${periodo.codigo}`}
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton type="submit" form="ajustar-periodo" disabled={guardando}>
                        {guardando ? 'Guardando' : 'Guardar'}
                    </Boton>
                </>
            }
        >
            <form
                id="ajustar-periodo"
                onSubmit={guardar}
                noValidate
                className="grid gap-4 sm:grid-cols-2"
            >
                <Campo
                    etiqueta="Inicio"
                    type="date"
                    requerido
                    value={inicio}
                    onChange={cambiar('fecha_inicio', setInicio)}
                    error={errores.fecha_inicio}
                />
                <Campo
                    etiqueta="Fin"
                    type="date"
                    requerido
                    value={fin}
                    onChange={cambiar('fecha_fin', setFin)}
                    error={errores.fecha_fin}
                />
                {general && (
                    <p role="alert" className="text-sm text-danger-600 sm:col-span-2">
                        {general}
                    </p>
                )}
            </form>
        </Dialogo>
    );
}

AjustarFechas.propTypes = {
    periodo: PropTypes.shape({
        id: PropTypes.number.isRequired,
        codigo: PropTypes.string.isRequired,
        fecha_inicio: PropTypes.string,
        fecha_fin: PropTypes.string,
    }).isRequired,
    onCerrar: PropTypes.func.isRequired,
    onGuardado: PropTypes.func.isRequired,
};

function FilaPendiente({ pendiente, cifras, enlazada }) {
    const contenido = (
        <>
            <span className="w-16 shrink-0 text-xl font-semibold tabular-nums text-warning-700">
                {miles(cifras.valor)}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium text-slate-800">
                    {pendiente.titulo}
                </span>
                <span className="block text-xs text-slate-600">de {miles(cifras.de)}</span>
            </span>
            {enlazada && (
                <span className="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-primary-700">
                    <span className="hidden sm:inline">{pendiente.accion}</span>
                    <ChevronRight className="h-4 w-4" aria-hidden="true" />
                </span>
            )}
        </>
    );

    return (
        <li>
            {enlazada ? (
                <Link
                    to={pendiente.ruta}
                    className={`flex min-h-14 items-center gap-3 px-4 py-2 hover:bg-slate-50 ${FOCO} focus-visible:-outline-offset-2`}
                >
                    {contenido}
                </Link>
            ) : (
                <div className="flex min-h-14 items-center gap-3 px-4 py-2">{contenido}</div>
            )}
        </li>
    );
}

FilaPendiente.propTypes = {
    pendiente: PropTypes.shape({
        titulo: PropTypes.string.isRequired,
        ruta: PropTypes.string.isRequired,
        accion: PropTypes.string.isRequired,
    }).isRequired,
    cifras: PropTypes.shape({ valor: PropTypes.number, de: PropTypes.number }).isRequired,
    enlazada: PropTypes.bool.isRequired,
};

// Inicio del administrador: el estado del período vigente. El período se
// detecta, no se crea; la oferta se importa por facultad.
export default function Periodo() {
    const { puede } = usarSesion();
    const catalogo = usarFacultades();
    const [aviso, avisar] = useAviso();

    const lista = usarPaginaServidor({ porPagina: POR_PAGINA });
    const periodos = usarConsulta('/periodos', { parametros: lista.parametros });
    const resumen = usarConsulta('/periodos/resumen');

    const [detectando, setDetectando] = useState(false);
    const [ajustando, setAjustando] = useState(null);
    // Por sigla: `{ estado: 'importando' }` mientras dura la petición y
    // `{ estado: 'fallo', error }` cuando el servidor la rechaza.
    const [locales, setLocales] = useState({});

    const recargarTodo = () => Promise.all([periodos.recargar(), resumen.recargar()]);

    function detectar() {
        setDetectando(true);
        api.post('/periodos/detectar')
            .then((respuesta) => {
                const { creados = 0, actualizados = 0 } = respuesta.data ?? {};
                avisar(`${creados} nuevos · ${actualizados} actualizados`);
                return recargarTodo();
            })
            .catch((error) => avisar(mensajeDe(error, 'No se pudo detectar'), 'error'))
            .finally(() => setDetectando(false));
    }

    async function importar(facultad) {
        const { sigla } = facultad;
        const clave = catalogo.find((f) => f.sigla === sigla)?.clave ?? sigla.toLowerCase();
        const poner = (valor) => setLocales((previos) => ({ ...previos, [sigla]: valor }));

        poner({ estado: 'importando' });
        try {
            await api.post('/oferta/importaciones', { facultad: clave });
            await recargarTodo();
            poner(undefined);
        } catch (error) {
            if (estadoDe(error) === 409) {
                // Otra importación de la misma facultad sigue en curso.
                avisar(mensajeDe(error, 'Importación en curso'), 'error');
                await resumen.recargar();
                poner(undefined);
                return;
            }
            poner({
                estado: 'fallo',
                error:
                    error?.response?.data?.data?.error ?? mensajeDe(error, 'No se pudo importar'),
            });
            await resumen.recargar();
        }
    }

    function guardado() {
        setAjustando(null);
        avisar('Fechas guardadas');
        void recargarTodo();
    }

    const datos = resumen.datos;
    const principal = datos?.periodo ?? null;
    const facultades = datos?.facultades ?? [];
    const pendientes = PENDIENTES.map((p) => ({
        ...p,
        cifras: datos?.pendientes?.[p.clave],
    })).filter((p) => p.cifras && p.cifras.valor > 0);
    const importadas = facultades.filter(
        (f) => (locales[f.sigla] ?? f.importacion)?.estado === 'importada'
    ).length;

    return (
        <div className="space-y-6">
            <Encabezado titulo="Período académico" subtitulo={subtituloDe(datos)}>
                {datos && (
                    <Insignia tono={principal ? 'exito' : 'neutro'} punto>
                        {principal
                            ? `${principal.codigo} · ${principal.estado}`
                            : 'Sin período vigente'}
                    </Insignia>
                )}
            </Encabezado>

            <Tarjeta
                sinRelleno
                titulo="Períodos detectados"
                acciones={
                    <Boton
                        type="button"
                        variante="secundario"
                        tamano="chico"
                        disabled={detectando}
                        onClick={detectar}
                    >
                        <RefreshCw
                            className={`h-4 w-4 ${detectando ? 'animate-spin' : ''}`}
                            aria-hidden="true"
                        />
                        {detectando ? 'Detectando' : 'Detectar'}
                    </Boton>
                }
            >
                <EstadoCarga
                    cargando={periodos.cargando}
                    error={periodos.error}
                    vacio={periodos.datos?.length === 0}
                    textoVacio="Sin períodos"
                    onReintentar={periodos.recargar}
                    filas={POR_PAGINA}
                >
                    <ul className="divide-y divide-slate-200">
                        {(periodos.datos ?? []).map((p) => (
                            <li
                                key={p.id}
                                className="flex min-h-11 flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3"
                            >
                                <span className="w-14 shrink-0 text-base font-semibold tabular-nums text-slate-900">
                                    {p.codigo}
                                </span>
                                <span className="min-w-0 flex-1 basis-32">
                                    <span className="block text-sm font-medium text-slate-800">
                                        {[p.tipo, rango(p.fecha_inicio, p.fecha_fin)]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </span>
                                    <span className="block truncate text-xs text-slate-600">
                                        {p.nota}
                                    </span>
                                </span>
                                <Insignia tono={TONO_PERIODO[p.estado] ?? 'neutro'} punto>
                                    {p.estado}
                                </Insignia>
                                {p.estado === 'Sin fechas' && (
                                    <Boton
                                        type="button"
                                        variante="secundario"
                                        tamano="chico"
                                        onClick={() => setAjustando(p)}
                                        aria-label={`Ajustar ${p.codigo}`}
                                    >
                                        Ajustar
                                    </Boton>
                                )}
                            </li>
                        ))}
                    </ul>
                    <Paginacion
                        {...lista.paginacion(periodos.meta)}
                        unidad={['período', 'períodos']}
                        className="border-t border-slate-200"
                    />
                </EstadoCarga>
            </Tarjeta>

            <Tarjeta
                sinRelleno
                titulo="Pendientes"
                acciones={
                    pendientes.length > 0 && (
                        <Insignia tono="advertencia">{pendientes.length}</Insignia>
                    )
                }
            >
                <EstadoCarga
                    cargando={resumen.cargando}
                    error={resumen.error}
                    vacio={pendientes.length === 0}
                    textoVacio="Sin pendientes"
                    onReintentar={resumen.recargar}
                    filas={2}
                >
                    <ul className="divide-y divide-slate-200">
                        {pendientes.map((p) => (
                            <FilaPendiente
                                key={p.clave}
                                pendiente={p}
                                cifras={p.cifras}
                                enlazada={puede(p.permiso)}
                            />
                        ))}
                    </ul>
                </EstadoCarga>
            </Tarjeta>

            <Tarjeta
                sinRelleno
                titulo="Oferta académica por facultad"
                acciones={
                    facultades.length > 0 && (
                        <Insignia tono={importadas === facultades.length ? 'exito' : 'advertencia'}>
                            {importadas} de {facultades.length} importadas
                        </Insignia>
                    )
                }
            >
                <EstadoCarga
                    cargando={resumen.cargando}
                    error={resumen.error}
                    vacio={facultades.length === 0}
                    textoVacio="Sin facultades"
                    onReintentar={resumen.recargar}
                    filas={4}
                >
                    <div
                        className={`hidden gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-medium uppercase tracking-wide text-slate-600 md:grid ${COLUMNAS}`}
                    >
                        <span>Facultad</span>
                        <span>Oferta</span>
                        <span>Estado</span>
                        <span className="sr-only">Acción</span>
                    </div>
                    <ul className="divide-y divide-slate-200">
                        {facultades.map((f) => {
                            const imp = locales[f.sigla] ?? f.importacion ?? { estado: 'sin' };
                            const estado = ESTADOS[imp.estado] ?? ESTADOS.sin;
                            const ocupado = locales[f.sigla]?.estado === 'importando';
                            const fallo = imp.estado === 'fallo';
                            const accion = fallo ? 'Reintentar' : 'Importar';
                            return (
                                <li
                                    key={f.sigla}
                                    className={`grid min-h-14 items-center gap-x-4 gap-y-2 px-4 py-3 hover:bg-slate-50 ${COLUMNAS}`}
                                >
                                    <div className="min-w-0">
                                        <PuntoFacultad sigla={f.sigla} />
                                        <p className="truncate text-sm font-medium text-slate-800">
                                            {f.nombre}
                                        </p>
                                    </div>
                                    <p className="min-w-0 text-sm text-slate-600">
                                        {miles(f.carreras)} carreras · {miles(f.grupos)} grupos ·{' '}
                                        {miles(f.aulas)} aulas
                                    </p>
                                    <div className="flex min-w-0 flex-wrap items-center justify-between gap-2 md:contents">
                                        <span aria-live="polite" className="min-w-0">
                                            <Insignia tono={estado.tono} punto>
                                                {estado.texto(imp.fecha)}
                                            </Insignia>
                                            {fallo && imp.error && (
                                                <span className="mt-1 block break-words text-xs text-danger-700">
                                                    {imp.error}
                                                </span>
                                            )}
                                        </span>
                                        <Boton
                                            type="button"
                                            variante={fallo ? 'primario' : 'secundario'}
                                            className="md:w-full"
                                            disabled={ocupado}
                                            onClick={() => importar(f)}
                                            aria-label={`${accion} ${f.sigla}`}
                                        >
                                            {ocupado ? (
                                                <LoaderCircle
                                                    className="h-4 w-4 animate-spin"
                                                    aria-hidden="true"
                                                />
                                            ) : fallo ? (
                                                <RotateCw className="h-4 w-4" aria-hidden="true" />
                                            ) : (
                                                <DownloadCloud
                                                    className="h-4 w-4"
                                                    aria-hidden="true"
                                                />
                                            )}
                                            {ocupado ? 'Importando' : accion}
                                        </Boton>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </EstadoCarga>
            </Tarjeta>

            {ajustando && (
                <AjustarFechas
                    periodo={ajustando}
                    onCerrar={() => setAjustando(null)}
                    onGuardado={guardado}
                />
            )}
            {aviso}
        </div>
    );
}
