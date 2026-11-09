import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { Camera, ChevronRight, ListChecks, Loader2, ScanLine, X } from 'lucide-react';
import { api } from '../../api/cliente';
import { codigoDe, erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarSondeo from '../../api/usarSondeo';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import EstadoCarga from '../../componentes/EstadoCarga';
import SinActividad from '../../componentes/SinActividad';
import { BotonIncidencia } from '../../componentes/Incidencias';
import Insignia from '../../componentes/Insignia';
import LectorQr from '../../componentes/LectorQr';
import { usarAccesoAula } from '../../sesion/AccesoAulaContexto';
import { usarSesion } from '../../sesion/SesionContexto';
import { colorDeAsignatura, diaMes, normalizar, ubicacionSin } from '../../utiles/texto';
import { senal } from '../../utiles/senal';
import IngresosDelAula from './IngresosDelAula';
import VeredictoPuerta, { VEREDICTOS } from './VeredictoPuerta';
import IconoAsignatura from '../../componentes/IconoAsignatura';

const FOCO = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600';
const SONDEO_MS = 10000;
const ESPERA_BUSQUEDA_MS = 300;
// Código universitario, o C.I. con su complemento («5003748-1A»).
const IDENTIFICADOR = /^\d{5,10}([\s-][0-9A-Za-z]{1,3}|[A-Za-z][0-9A-Za-z]{0,2})?$/;

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');
// '2026-10-12T08:21:07-04:00' → '08:21'.
const horaDe = (iso) => String(iso ?? '').slice(11, 16);

const formaAula = PropTypes.shape({
    aula_id: PropTypes.number.isRequired,
    nombre: PropTypes.string.isRequired,
    ubicacion: PropTypes.string,
    examenes: PropTypes.array,
    abierta: PropTypes.bool,
});

// Las normas de un examen en la puerta: primero las marcadas, una por línea,
// y después el texto libre con sus saltos.
function NormasDelExamen({ examen }) {
    const marcadas = examen.normas_marcadas ?? [];
    if (marcadas.length === 0 && !examen.normas) {
        return <span className="mt-1 block text-xs text-slate-700">Sin normas</span>;
    }
    return (
        <span className="mt-1 block text-xs text-slate-700">
            {marcadas.length > 0 && (
                <span role="list" aria-label="Normas marcadas" className="block">
                    {marcadas.map((norma, i) => (
                        <span key={`${i}-${norma}`} role="listitem" className="flex gap-1.5">
                            <span aria-hidden="true">·</span>
                            <span className="min-w-0 break-words">{norma}</span>
                        </span>
                    ))}
                </span>
            )}
            {examen.normas && (
                <span className={`block whitespace-pre-line ${marcadas.length > 0 ? 'mt-1' : ''}`}>
                    {examen.normas}
                </span>
            )}
        </span>
    );
}

NormasDelExamen.propTypes = {
    examen: PropTypes.shape({
        normas: PropTypes.string,
        normas_marcadas: PropTypes.arrayOf(PropTypes.string),
    }).isRequired,
};

// Las aulas que la persona atiende hoy (ruta 62), para pasar de una a otra.
function DialogoAulas({ abierto, aulas, actual, onElegir, onCerrar }) {
    const [buscada, setBuscada] = useState('');
    const texto = normalizar(buscada.trim());
    const visibles = aulas.filter((a) => normalizar(a.nombre).includes(texto));
    const cerrar = () => {
        setBuscada('');
        onCerrar();
    };

    return (
        <Dialogo titulo="Aula" abierto={abierto} onCerrar={cerrar}>
            {aulas.length > 6 && (
                <Buscador
                    valor={buscada}
                    onCambiar={setBuscada}
                    placeholder="Aula"
                    etiqueta="Buscar aula"
                    className="mb-2"
                />
            )}
            <ul className="divide-y divide-slate-100">
                {visibles.map((a) => (
                    <li key={a.aula_id}>
                        <button
                            type="button"
                            onClick={() => {
                                setBuscada('');
                                onElegir(a.aula_id);
                            }}
                            aria-current={a.aula_id === actual || undefined}
                            className={`-mx-2 flex min-h-14 w-[calc(100%+1rem)] items-center gap-3 rounded-lg px-2 text-left hover:bg-slate-50 ${FOCO}`}
                        >
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold text-slate-900">
                                    Aula {a.nombre}
                                </span>
                                <span className="block truncate text-xs text-slate-600">
                                    {ubicacionSin(a.nombre, a.ubicacion)}
                                </span>
                            </span>
                            {a.aula_id === actual ? (
                                <Insignia tono="primario">Actual</Insignia>
                            ) : (
                                !a.abierta && <Insignia tono="neutro">Sin examen</Insignia>
                            )}
                        </button>
                    </li>
                ))}
                {visibles.length === 0 && (
                    <li className="py-6 text-center text-sm text-slate-600">Sin resultados</li>
                )}
            </ul>
        </Dialogo>
    );
}

DialogoAulas.propTypes = {
    abierto: PropTypes.bool.isRequired,
    aulas: PropTypes.arrayOf(formaAula).isRequired,
    actual: PropTypes.number,
    onElegir: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};

// La puerta de un aula. Trabaja por aula y hora, no por examen: el aula
// puede tener más de un examen a la vez y, al identificar al estudiante, el
// servidor resuelve a cuál pertenece. Se monta de nuevo al cambiar de aula.
function Puesto({ resumen, aulas, hoy, elegirAula }) {
    const { pathname } = useLocation();
    const { usuario, puede } = usarSesion();
    const acceso = usarAccesoAula();
    const [aviso, avisar] = useAviso();
    const esDocente = pathname === '/control';
    const operador = acceso ? (acceso.nombre ?? 'Código de aula') : (usuario?.nombre ?? '');
    const ruta = `/puerta/aulas/${resumen.aula_id}`;

    const [camara, setCamara] = useState(false);
    const [sinCamara, setSinCamara] = useState(false);
    const [verificando, setVerificando] = useState(false);
    const [veredicto, setVeredicto] = useState(null);
    const [manualAbierto, setManualAbierto] = useState(false);
    const [manual, setManual] = useState('');
    const [errorManual, setErrorManual] = useState(null);
    const [normasAbiertas, setNormasAbiertas] = useState(false);
    const [cambiando, setCambiando] = useState(false);
    const [todos, setTodos] = useState(false);
    const [buscar, setBuscar] = useState('');
    const [buscado, setBuscado] = useState('');

    useEffect(() => {
        const texto = buscar.trim();
        if (texto === buscado) return undefined;
        const reloj = setTimeout(() => setBuscado(texto), ESPERA_BUSQUEDA_MS);
        return () => clearTimeout(reloj);
    }, [buscar, buscado]);

    const vivo = usarSondeo(ruta, {
        parametros: todos ? { ingresos: 'todos', buscar: buscado } : {},
        intervalo: SONDEO_MS,
    });
    const puerta = vivo.cuerpo;
    const aula = puerta?.aula ?? { id: resumen.aula_id, nombre: resumen.nombre };
    const examenes = puerta?.examenes ?? [];
    const hora = horaDe(puerta?.hora_servidor);
    const totalIngresados = examenes.reduce((s, x) => s + x.ingresados, 0);
    const totalFaltan = examenes.reduce((s, x) => s + (x.faltan ?? 0), 0);
    const cuando = [diaMes(hoy ?? puerta?.hora_servidor), examenes[0]?.hora]
        .filter(Boolean)
        .join(' · ');
    const destinoLista =
        usuario && puede('seguimiento_vivo') && examenes[0]
            ? `${esDocente ? '/en-curso/aula' : '/mi-aula'}?examen=${examenes[0].id}&aula=${aula.id}`
            : null;

    const verificar = async (cuerpo, medio) => {
        if (verificando) return;
        setCamara(false);
        setVerificando(true);
        try {
            const { data } = await api.post(`${ruta}/verificaciones`, cuerpo);
            setVeredicto({ ...data, medio });
            senal(VEREDICTOS[data.veredicto]?.tono ?? 'peligro');
            setManual('');
            setBuscar('');
            setBuscado('');
            vivo.recargar();
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (campos.identificador === 'Sin registro') {
                // Nadie con ese código o C.I.: un veredicto a la vista, no
                // solo un texto bajo el campo.
                setVeredicto({
                    veredicto: 'no_registrado',
                    dato: `${cuerpo.identificador} · sin registro en el padrón`,
                    medio,
                });
                senal('peligro');
                setManual('');
            } else if (campos.identificador) {
                setManualAbierto(true);
                setErrorManual(campos.identificador);
            } else if (campos.token) {
                avisar('Código QR no válido', 'error');
            } else if (codigoDe(fallo) === 'AULA_SIN_EXAMEN') {
                avisar('Sin examen a esta hora', 'error');
                vivo.recargar();
            } else {
                avisar(sinPunto(mensajeDe(fallo, 'No se pudo verificar')), 'error');
            }
        } finally {
            setVerificando(false);
        }
    };

    const verificarManual = (e) => {
        e.preventDefault();
        const valor = manual.trim();
        if (!valor) return setErrorManual('Obligatorio');
        if (!IDENTIFICADOR.test(valor)) return setErrorManual('Código SIS o C.I. no válido');
        return verificar({ identificador: valor.toUpperCase() }, 'manual');
    };

    // El campo manual; bajo un veredicto leído a mano queda a la vista para
    // el siguiente, sin cerrar ni robar el foco.
    const formularioManual = (cerrable) => (
        <form className="flex w-full items-start gap-2" noValidate onSubmit={verificarManual}>
            <Campo
                etiqueta="Código SIS o C.I."
                placeholder="Código SIS o C.I."
                className="min-w-0 flex-1"
                autoCapitalize="characters"
                autoComplete="off"
                enterKeyHint="search"
                autoFocus={cerrable}
                value={manual}
                error={errorManual}
                onChange={(e) => {
                    setManual(e.target.value);
                    setErrorManual(null);
                }}
            />
            <Boton type="submit" className="mt-[1.625rem] min-h-11">
                Verificar
            </Boton>
            {cerrable && (
                <button
                    type="button"
                    aria-label="Cerrar ingreso manual"
                    onClick={cerrarManual}
                    className={`mt-[1.625rem] flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 ${FOCO}`}
                >
                    <X className="h-5 w-5" />
                </button>
            )}
        </form>
    );

    const cerrarManual = () => {
        setManualAbierto(false);
        setManual('');
        setErrorManual(null);
    };

    // «Siguiente» deja la puerta lista para el próximo por la misma vía: la
    // cámara encendida si venía de QR, el campo manual vacío y con el foco
    // si venía a mano.
    const siguiente = () => {
        const medio = veredicto?.medio;
        setVeredicto(null);
        setManual('');
        setErrorManual(null);
        setManualAbierto(medio === 'manual');
        if (medio === 'qr') setCamara(true);
    };

    const registrarExcepcion = async ({ tipo, motivo }) => {
        const examenId = veredicto?.examen?.id ?? null;
        const { data } = await api.post(`${ruta}/excepciones`, {
            tipo,
            motivo,
            estudiante_id: veredicto?.estudiante?.id ?? null,
            examen_id: examenes.some((x) => x.id === examenId) ? examenId : null,
        });
        vivo.recargar();
        return data;
    };

    const expulsar = async (ingreso, motivo) => {
        try {
            await api.post(`/puerta/ingresos/${ingreso.id}/expulsion`, { motivo });
        } finally {
            vivo.recargar();
        }
    };

    // La incidencia va al examen del aula en el que la persona actúa: con
    // código de aula, el suyo; con cuenta, el primero que no la rechace.
    const reportar = async (datos) => {
        const candidatos = acceso?.examen_id ? [acceso.examen_id] : examenes.map((x) => x.id);
        let ultimo = new Error('Sin examen');
        for (const id of candidatos) {
            try {
                return await api.post(`/examenes/${id}/incidencias`, datos);
            } catch (fallo) {
                ultimo = fallo;
                if (estadoDe(fallo) !== 403) break;
            }
        }
        throw ultimo;
    };

    const sinAlcance = vivo.error && estadoDe(vivo.error) === 403;

    return (
        <div className="mx-auto max-w-md space-y-4">
            <h1 className="sr-only">Punto de control</h1>
            <div className="rounded-xl border border-slate-200 bg-white p-4">
                <div className="flex items-center justify-between gap-2">
                    <div className="min-w-0">
                        <p className="text-lg font-bold leading-tight text-slate-900">
                            Aula {aula.nombre}
                        </p>
                        <p className="truncate text-sm text-slate-600">
                            {ubicacionSin(
                                aula.nombre,
                                puerta?.aula?.ubicacion ?? resumen.ubicacion
                            )}
                        </p>
                    </div>
                    {aulas.length > 1 && (
                        <button
                            type="button"
                            onClick={() => setCambiando(true)}
                            className={`min-h-11 shrink-0 rounded-lg px-3 text-sm font-medium text-primary-700 hover:bg-primary-50 ${FOCO}`}
                        >
                            Cambiar
                        </button>
                    )}
                </div>
                {puerta && (
                    <>
                        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-600">
                            <span>
                                {cuando}
                                {examenes.length > 2 &&
                                    ` · ${examenes.length} exámenes · ${totalIngresados} ingresaron · faltan ${totalFaltan}`}
                            </span>
                            {vivo.sinConexion && (
                                <Insignia tono="peligro" punto>
                                    Sin conexión
                                </Insignia>
                            )}
                        </p>
                        <ul
                            aria-label="Exámenes del aula"
                            className="mt-3 space-y-2 border-t border-slate-100 pt-3"
                        >
                            {examenes.map((x) => (
                                <li key={x.id} className="flex items-start gap-2.5">
                                    <IconoAsignatura
                                        nombre={x.asignatura}
                                        color={colorDeAsignatura(x.asignatura)}
                                        tamano="xs"
                                        className="mt-0.5"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-medium leading-snug text-slate-800">
                                            {x.asignatura}
                                        </span>
                                        <span className="block truncate text-xs text-slate-600">
                                            {x.tipo} · {x.hora}
                                        </span>
                                        {normasAbiertas && <NormasDelExamen examen={x} />}
                                    </span>
                                    <span className="shrink-0 text-right tabular-nums">
                                        <span className="block text-sm font-medium text-slate-800">
                                            {x.ingresados} ingresaron
                                        </span>
                                        <span className="block text-xs text-slate-600">
                                            Faltan {x.faltan ?? 0}
                                        </span>
                                    </span>
                                </li>
                            ))}
                            {examenes.length === 0 && (
                                <li className="text-sm text-slate-600">Sin exámenes hoy</li>
                            )}
                        </ul>
                        <div className="-mx-2 mt-1 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                onClick={() => setNormasAbiertas((v) => !v)}
                                aria-expanded={normasAbiertas}
                                className={`flex min-h-11 items-center gap-1 rounded-lg px-2 text-sm font-medium text-primary-700 hover:bg-primary-50 ${FOCO}`}
                            >
                                <ChevronRight
                                    className={`h-4 w-4 transition-transform ${normasAbiertas ? 'rotate-90' : ''}`}
                                    strokeWidth={2.5}
                                />
                                Normas
                            </button>
                            <span className="flex items-center gap-1">
                                {examenes.length > 0 && (
                                    <BotonIncidencia
                                        aula={{ id: aula.id, nombre: aula.nombre }}
                                        por={operador}
                                        hora={hora}
                                        registrar={reportar}
                                        catalogo={`/examenes/${acceso?.examen_id ?? examenes[0].id}/incidencias/catalogo`}
                                        variante="enlace"
                                        className="px-2"
                                    />
                                )}
                                {destinoLista && (
                                    <Link
                                        to={destinoLista}
                                        className={`flex min-h-11 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-primary-700 hover:bg-primary-50 ${FOCO}`}
                                    >
                                        <ListChecks className="h-4 w-4" /> Lista
                                    </Link>
                                )}
                            </span>
                        </div>
                    </>
                )}
            </div>

            {!puerta && (
                <div className="rounded-2xl border border-slate-200 bg-white">
                    {sinAlcance ? (
                        <p role="alert" className="px-4 py-8 text-center text-sm text-slate-600">
                            {sinPunto(mensajeDe(vivo.error, 'Sin permiso'))}
                        </p>
                    ) : (
                        <EstadoCarga
                            cargando={vivo.cargando}
                            error={vivo.error}
                            onReintentar={vivo.recargar}
                            filas={4}
                        />
                    )}
                </div>
            )}

            {puerta && verificando && (
                <div
                    className="flex h-48 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white text-base font-medium text-slate-700"
                    role="status"
                >
                    <Loader2 className="h-5 w-5 animate-spin" strokeWidth={2} />
                    Verificando…
                </div>
            )}

            {puerta && !verificando && !veredicto && !puerta.abierta && (
                <div className="flex h-48 items-center justify-center rounded-2xl border border-slate-200 bg-white text-base font-medium text-slate-700">
                    Sin examen a esta hora
                </div>
            )}

            {puerta && !verificando && !veredicto && puerta.abierta && (
                <div className="flex flex-col items-center gap-3 rounded-2xl border border-slate-200 bg-white p-5">
                    {camara ? (
                        <div className="relative h-56 w-56 max-w-full overflow-hidden rounded-2xl bg-slate-900">
                            <LectorQr
                                activo
                                onLectura={(texto) => verificar({ token: texto }, 'qr')}
                                onError={() => {
                                    setCamara(false);
                                    setSinCamara(true);
                                    setManualAbierto(true);
                                }}
                            />
                            <button
                                type="button"
                                aria-label="Apagar cámara"
                                onClick={() => setCamara(false)}
                                className={`absolute right-1.5 top-1.5 flex h-11 w-11 items-center justify-center rounded-lg bg-slate-900/60 text-white hover:bg-slate-900/80 ${FOCO}`}
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                    ) : (
                        <button
                            type="button"
                            onClick={() => {
                                setSinCamara(false);
                                setCamara(true);
                            }}
                            aria-label="Leer código QR"
                            className="flex h-40 w-40 items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 hover:border-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                        >
                            <Camera className="h-14 w-14 text-slate-400" strokeWidth={1.5} />
                        </button>
                    )}
                    <p className="text-sm font-medium text-slate-700">
                        {sinCamara ? 'Cámara no disponible' : 'Código QR del estudiante'}
                    </p>
                    {manualAbierto ? (
                        formularioManual(true)
                    ) : (
                        <Boton
                            type="button"
                            variante="secundario"
                            className="min-h-12 w-full"
                            onClick={() => setManualAbierto(true)}
                        >
                            Código SIS o C.I. manual
                        </Boton>
                    )}
                </div>
            )}

            {puerta && !verificando && veredicto && (
                <VeredictoPuerta
                    veredicto={veredicto}
                    aula={aula}
                    operador={operador}
                    hora={hora}
                    registrarExcepcion={registrarExcepcion}
                    // Con código de aula solo se verifica y se reportan incidencias.
                    conExcepciones={!acceso}
                    onSiguiente={siguiente}
                />
            )}

            {puerta && !verificando && veredicto?.medio === 'manual' && puerta.abierta && (
                <div className="rounded-2xl border border-slate-200 bg-white p-4">
                    {formularioManual(false)}
                </div>
            )}

            {puerta && (
                <IngresosDelAula
                    ingresos={puerta.ingresos ?? []}
                    total={puerta.total_ingresos ?? 0}
                    examenes={examenes}
                    todos={todos}
                    onAlternar={() => {
                        setTodos((v) => !v);
                        setBuscar('');
                        setBuscado('');
                    }}
                    buscar={buscar}
                    onBuscar={setBuscar}
                    aula={aula.nombre}
                    operador={operador}
                    hora={hora}
                    expulsar={acceso ? null : expulsar}
                />
            )}

            <DialogoAulas
                abierto={cambiando}
                aulas={aulas}
                actual={resumen.aula_id}
                onCerrar={() => setCambiando(false)}
                onElegir={(id) => {
                    setCambiando(false);
                    if (id !== resumen.aula_id) elegirAula(id);
                }}
            />

            {aviso}
        </div>
    );
}

Puesto.propTypes = {
    resumen: formaAula.isRequired,
    aulas: PropTypes.arrayOf(formaAula).isRequired,
    hoy: PropTypes.string,
    elegirAula: PropTypes.func.isRequired,
};

// Punto de control (HU-19): `/control` (docente), `/ingreso` (auxiliar) y la
// puerta abierta con código de aula, sin cuenta ni menú. El aula viaja en
// `?aula=<id>`; sin parámetro es la primera abierta de las que la persona
// atiende hoy.
export default function PuntoControl() {
    const { pathname } = useLocation();
    const [parametros, setParametros] = useSearchParams();
    const { datos, cuerpo, cargando, error, recargar } = usarConsulta('/puerta/aulas');
    // Solo las aulas con un examen abierto: fuera de hora la puerta no se usa.
    const aulas = (datos ?? []).filter((a) => a.abierta);
    const pedida = Number(parametros.get('aula')) || null;
    const aula = aulas.find((a) => a.aula_id === pedida) ?? aulas[0] ?? null;

    const elegirAula = (id) =>
        setParametros((previos) => {
            const siguiente = new URLSearchParams(previos);
            siguiente.set('aula', String(id));
            return siguiente;
        });

    if (!aula) {
        return (
            <div className="mx-auto max-w-md space-y-4">
                <h1 className="sr-only">Punto de control</h1>
                <div className="rounded-xl border border-slate-200 bg-white">
                    <EstadoCarga
                        cargando={cargando}
                        error={error}
                        onReintentar={recargar}
                        filas={4}
                    >
                        <SinActividad
                            Icono={ScanLine}
                            titulo="Ningún examen abierto en tus aulas"
                            detalle="El punto de control se abre una hora antes del examen y se cierra al terminar."
                        />
                    </EstadoCarga>
                </div>
            </div>
        );
    }

    // Al cambiar de aula, el puesto empieza de nuevo.
    return (
        <Puesto
            key={`${pathname}|${aula.aula_id}`}
            resumen={aula}
            aulas={aulas}
            hoy={cuerpo?.hoy}
            elegirAula={elegirAula}
        />
    );
}
