import { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import {
    TriangleAlert,
    Check,
    Image as IconoImagen,
    ListChecks,
    Pencil,
    Trash2,
    Video,
} from 'lucide-react';
import { api } from '../api/cliente';
import Boton from './Boton';
import Campo from './Campo';
import FotosEvidencia from './FotosEvidencia';
import VideoEvidencia from './VideoEvidencia';
import Chips from './Chips';
import Dialogo from './Dialogo';
import Insignia from './Insignia';
import Seleccion, { AreaTexto } from './Seleccion';
import { useAviso } from './Aviso';
import { erroresDe, mensajeDe } from '../api/errores';
import { errorDeNombre, fechaCorta } from '../utiles/texto';

// Lo que pasa durante un examen y hay que dejar asentado. Las reporta quien
// está en el aula (auxiliar o docente) y las atiende el docente del examen.
export const TIPOS_INCIDENCIA = {
    INTENTO_NO_AUTORIZADO: 'Intento rechazado',
    SUPLANTACION: 'Suplantación de identidad',
    MATERIAL_NO_PERMITIDO: 'Material no permitido',
    DISPOSITIVO_NO_PERMITIDO: 'Dispositivo no permitido',
    INGRESO_REPETIDO: 'Ingreso repetido',
    EXPULSION: 'Expulsión',
    CAMBIO_AULA: 'Cambio de aula',
    OTRA: 'Otra',
};

// La gravedad la elige quien registra (el tipo o la lista del docente solo la
// sugieren) y se lee por el color y por el texto: leve, grave o muy grave.
export const GRAVEDADES = {
    MUY_GRAVE: { texto: 'Muy grave', tono: 'peligro' },
    GRAVE: { texto: 'Grave', tono: 'advertencia' },
    LEVE: { texto: 'Leve', tono: 'info' },
};

export const GRAVEDAD_DE_TIPO = {
    SUPLANTACION: 'MUY_GRAVE',
    EXPULSION: 'MUY_GRAVE',
    INGRESO_REPETIDO: 'GRAVE',
    INTENTO_NO_AUTORIZADO: 'GRAVE',
    MATERIAL_NO_PERMITIDO: 'GRAVE',
    DISPOSITIVO_NO_PERMITIDO: 'GRAVE',
    CAMBIO_AULA: 'LEVE',
    OTRA: 'LEVE',
};

// La gravedad que sugiere cada tipo cuando no hay otra elegida.
// Los que asienta otra operación: no se eligen ni se cambian.
const FIJOS = ['EXPULSION', 'INGRESO_REPETIDO'];

// Hasta tres fotos de evidencia por incidencia.
const MAXIMO_FOTOS = 3;

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const NIVELES = ['LEVE', 'GRAVE', 'MUY_GRAVE'];
const NIVEL_ACTIVO = {
    LEVE: 'border-info-600 bg-info-50 text-info-700 ring-2 ring-info-600/20',
    GRAVE: 'border-warning-600 bg-warning-50 text-warning-800 ring-2 ring-warning-600/20',
    MUY_GRAVE: 'border-danger-600 bg-danger-50 text-danger-700 ring-2 ring-danger-600/20',
};

// La gravedad como tres etiquetas que se eligen con un toque.
export function SelectorGravedad({
    valor,
    onCambiar,
    error,
    etiqueta = 'Gravedad',
    compacto = false,
}) {
    return (
        <div>
            {etiqueta && !compacto && (
                <span className="mb-1.5 block text-sm font-medium text-slate-700">
                    {etiqueta} <span className="text-danger-600">*</span>
                </span>
            )}
            <div
                role="radiogroup"
                aria-label={etiqueta}
                className={`grid grid-cols-3 gap-2 ${compacto ? 'w-full sm:w-72' : ''}`}
            >
                {NIVELES.map((nivel) => {
                    const activo = valor === nivel;
                    return (
                        <button
                            key={nivel}
                            type="button"
                            role="radio"
                            aria-checked={activo}
                            onClick={() => onCambiar(nivel)}
                            className={`rounded-lg border px-2 text-sm font-semibold transition-colors ${compacto ? 'min-h-9' : 'min-h-11'} ${activo ? NIVEL_ACTIVO[nivel] : 'border-slate-200 text-slate-600 hover:bg-slate-50'} ${FOCO}`}
                        >
                            {GRAVEDADES[nivel].texto}
                        </button>
                    );
                })}
            </div>
            {error && (
                <p role="alert" className="mt-1.5 text-sm text-danger-700">
                    {error}
                </p>
            )}
        </div>
    );
}

SelectorGravedad.propTypes = {
    valor: PropTypes.string,
    onCambiar: PropTypes.func.isRequired,
    error: PropTypes.string,
    etiqueta: PropTypes.string,
    compacto: PropTypes.bool,
};

// «Otra no prevista»: se escribe su nombre y se elige su gravedad.
const NUEVA = '__nueva';
const claveDe = (item) => (item.propia ? `p:${item.nombre}` : item.tipo);

// Lo que se ofrece al registrar (GET /api/examenes/{id}/incidencias/catalogo):
// los tipos del sistema con la gravedad que prefiere el docente y sus
// incidencias propias. Si no llega, los tipos con su gravedad de siempre.
function useCatalogo(ruta, abierto) {
    const [estado, setEstado] = useState({ lista: null, puedeGuardar: false });
    useEffect(() => {
        if (!abierto || !ruta) return undefined;
        let vigente = true;
        Promise.resolve()
            .then(() => api.get(ruta))
            .then((respuesta) => {
                if (!vigente) return;
                const lista = respuesta?.data?.data;
                setEstado({
                    lista: Array.isArray(lista) ? lista : null,
                    puedeGuardar: Boolean(respuesta?.data?.puede_guardar),
                });
            })
            .catch(() => vigente && setEstado({ lista: null, puedeGuardar: false }));
        return () => {
            vigente = false;
        };
    }, [ruta, abierto]);
    const lista =
        estado.lista ??
        REPORTABLES.filter((clave) => clave !== 'OTRA').map((clave) => ({
            tipo: clave,
            nombre: TIPOS_INCIDENCIA[clave],
            gravedad: GRAVEDAD_DE_TIPO[clave],
            propia: false,
        }));
    return { lista, puedeGuardar: estado.puedeGuardar };
}

// Con fotos o video, el cuerpo va como formulario multipart (`evidencias[]`
// y `video`).
function cuerpoCon(datos, fotos, video = null) {
    if (fotos.length === 0 && !video) return datos;
    const formulario = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => {
        if (valor !== null && valor !== undefined) formulario.append(clave, valor);
    });
    fotos.forEach((foto) => formulario.append('evidencias[]', foto));
    if (video) formulario.append('video', video);
    return formulario;
}

// Cuántas fotos tiene: las respuestas viejas solo decían si había una.
const fotosDe = (incidencia) => incidencia.evidencias ?? (incidencia.evidencia ? 1 : 0);

// El primer error de las fotos, venga de la lista o de una de ellas.
const errorDeFotos = (errores) =>
    errores.evidencias ??
    Object.entries(errores).find(([clave]) => clave.startsWith('evidencias.'))?.[1] ??
    errores.evidencia;

// Quién atendió la incidencia y cuándo: 'Blanco Coca Leticia · 9 oct 2026, 16:12'.
// `atendida_en` es un instante ISO ('2026-10-09T16:12:40-04:00').
function atencionDe(incidencia) {
    return [incidencia.atendida_por, fechaCorta(incidencia.atendida_en)]
        .filter(Boolean)
        .join(' · ');
}

// La expulsión exige el ingreso y el ingreso repetido lo asienta la puerta.
const DETALLE_MINIMO = 5;
// El estudiante va por su código (el servidor exige que esté inscrito en el
// examen) o, si no se lo pudo identificar, por su nombre.
const errorDeEstudiante = (texto) => {
    const valor = texto.trim();
    if (!valor || /^\d+$/.test(valor)) return null;
    return errorDeNombre(valor) ? 'Un código SIS o solo letras' : null;
};
const REPORTABLES = Object.keys(TIPOS_INCIDENCIA).filter((clave) => !FIJOS.includes(clave));

const formaAula = PropTypes.shape({
    id: PropTypes.number.isRequired,
    nombre: PropTypes.string.isRequired,
});

// Botón «Incidencia» y su formulario. Con `aulas` (el docente, que pasa por
// varias) se elige el aula; con `aula` queda fija (el auxiliar en la suya).
// `registrar({ aula_id, tipo, tipo_texto, gravedad, estudiante, detalle })`
// hace la llamada (POST /api/examenes/{examen}/incidencias) y devuelve su
// promesa; `alRegistrar` se llama cuando terminó bien. `catalogo` es la ruta
// de lo que se ofrece en ese examen.
export function BotonIncidencia({
    aula,
    aulas,
    por,
    hora,
    registrar,
    alRegistrar,
    catalogo,
    variante = 'secundario',
    className = '',
}) {
    const [aviso, avisar] = useAviso();
    const [abierto, setAbierto] = useState(false);
    const [tipo, setTipo] = useState('');
    const [gravedad, setGravedad] = useState('');
    const [nueva, setNueva] = useState('');
    const [guardarComun, setGuardarComun] = useState(false);
    const [donde, setDonde] = useState(aula?.id ?? aulas?.[0]?.id ?? '');
    const [estudiante, setEstudiante] = useState('');
    const [detalle, setDetalle] = useState('');
    const [fotos, setFotos] = useState([]);
    const [video, setVideo] = useState(null);
    const [intento, setIntento] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [errores, setErrores] = useState({});
    const corto = detalle.trim().length < DETALLE_MINIMO;
    const aulaId = aula?.id ?? (donde === '' ? aulas?.[0]?.id : Number(donde));
    const { lista, puedeGuardar } = useCatalogo(catalogo, abierto);
    const sistema = lista.filter((i) => !i.propia);
    const propias = lista.filter((i) => i.propia);
    const elegida = lista.find((i) => claveDe(i) === tipo) ?? null;
    const esNueva = tipo === NUEVA;
    const faltaNombre = esNueva && nueva.trim().length < 3;
    const listo = Boolean(tipo) && Boolean(gravedad) && !corto && !faltaNombre;

    const elegir = (clave) => {
        setTipo(clave);
        const item = lista.find((i) => claveDe(i) === clave);
        // La gravedad sugerida se marca sola y se puede cambiar.
        setGravedad(item?.gravedad ?? '');
    };

    const cerrar = () => {
        setAbierto(false);
        setTipo('');
        setGravedad('');
        setNueva('');
        setGuardarComun(false);
        setEstudiante('');
        setDetalle('');
        setFotos([]);
        setVideo(null);
        setIntento(false);
        setErrores({});
    };
    const enviar = async () => {
        setIntento(true);
        if (!listo || enviando) return;
        const errorEstudiante = errorDeEstudiante(estudiante);
        if (errorEstudiante) {
            setErrores({ estudiante: errorEstudiante });
            return;
        }
        setEnviando(true);
        setErrores({});
        try {
            await registrar(
                cuerpoCon(
                    {
                        aula_id: aulaId,
                        tipo: esNueva || elegida?.propia ? 'OTRA' : tipo,
                        tipo_texto: esNueva
                            ? nueva.trim()
                            : elegida?.propia
                              ? elegida.nombre
                              : null,
                        gravedad,
                        guardar_comun: esNueva && guardarComun ? 1 : null,
                        estudiante: estudiante.trim() || null,
                        detalle: detalle.trim(),
                    },
                    fotos,
                    video
                )
            );
            cerrar();
            avisar('Incidencia registrada');
            alRegistrar?.();
        } catch (error) {
            const campos = erroresDe(error);
            setErrores(campos);
            if (Object.keys(campos).length === 0) {
                avisar(mensajeDe(error, 'No se pudo registrar'), 'error');
            }
        } finally {
            setEnviando(false);
        }
    };
    const cabecera = [aula ? `Aula ${aula.nombre}` : null, hora, por].filter(Boolean).join(' · ');

    return (
        <>
            <Boton
                type="button"
                variante={variante}
                className={className}
                onClick={() => setAbierto(true)}
            >
                <TriangleAlert className="h-4 w-4" /> Incidencia
            </Boton>
            <Dialogo
                titulo="Incidencia"
                abierto={abierto}
                onCerrar={cerrar}
                acciones={
                    <>
                        <Boton type="button" variante="secundario" onClick={cerrar}>
                            Cancelar
                        </Boton>
                        <Boton
                            type="button"
                            onClick={enviar}
                            disabled={!listo || enviando}
                            cargando={enviando}
                        >
                            Registrar
                        </Boton>
                    </>
                }
            >
                <div className="space-y-4">
                    {cabecera && <p className="text-sm text-slate-600">{cabecera}</p>}
                    {!aula && aulas && (
                        <Seleccion
                            etiqueta="Aula"
                            requerido
                            value={donde}
                            onChange={(e) => setDonde(e.target.value)}
                        >
                            {aulas.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.nombre}
                                </option>
                            ))}
                        </Seleccion>
                    )}
                    <Seleccion
                        etiqueta="Tipo"
                        requerido
                        value={tipo}
                        onChange={(e) => elegir(e.target.value)}
                    >
                        <option value="" disabled>
                            Elegir
                        </option>
                        <optgroup label="Tipos">
                            {sistema.map((i) => (
                                <option key={claveDe(i)} value={claveDe(i)}>
                                    {i.nombre}
                                </option>
                            ))}
                        </optgroup>
                        {propias.length > 0 && (
                            <optgroup label="Mis incidencias">
                                {propias.map((i) => (
                                    <option key={claveDe(i)} value={claveDe(i)}>
                                        {i.nombre}
                                    </option>
                                ))}
                            </optgroup>
                        )}
                        <option value={NUEVA}>Otra no prevista…</option>
                    </Seleccion>
                    {esNueva && (
                        <div className="space-y-2">
                            <Campo
                                etiqueta="Qué pasó"
                                requerido
                                maxLength={80}
                                placeholder="Ej.: Se comunicó por señas"
                                value={nueva}
                                error={
                                    intento && faltaNombre
                                        ? 'Mínimo 3 caracteres'
                                        : errores.tipo_texto
                                }
                                onChange={(e) => setNueva(e.target.value)}
                            />
                            {puedeGuardar && (
                                <label className="flex items-center gap-2 text-sm text-slate-700">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-slate-300"
                                        checked={guardarComun}
                                        onChange={(e) => setGuardarComun(e.target.checked)}
                                    />
                                    Guardar en mis incidencias comunes
                                </label>
                            )}
                        </div>
                    )}
                    {tipo && (
                        <SelectorGravedad
                            valor={gravedad}
                            onCambiar={setGravedad}
                            error={
                                intento && !gravedad
                                    ? 'Elija leve, grave o muy grave'
                                    : errores.gravedad
                            }
                        />
                    )}
                    <Campo
                        etiqueta="Estudiante"
                        placeholder="Código SIS o nombre"
                        maxLength={150}
                        value={estudiante}
                        error={errores.estudiante}
                        onChange={(e) => {
                            setEstudiante(e.target.value);
                            setErrores(({ estudiante: _quitado, ...resto }) => resto);
                        }}
                    />
                    <AreaTexto
                        etiqueta="Detalle"
                        requerido
                        rows={3}
                        maxLength={2000}
                        value={detalle}
                        onChange={(e) => setDetalle(e.target.value)}
                        error={
                            intento && corto
                                ? `Mínimo ${DETALLE_MINIMO} caracteres`
                                : errores.detalle
                        }
                        pie={corto ? `Mínimo ${DETALLE_MINIMO} caracteres` : null}
                    />
                    <FotosEvidencia
                        etiqueta="Evidencia"
                        fotos={fotos}
                        onCambiar={setFotos}
                        maximo={MAXIMO_FOTOS}
                        error={errorDeFotos(errores)}
                    />
                    <VideoEvidencia video={video} onCambiar={setVideo} error={errores.video} />
                </div>
            </Dialogo>
            {aviso}
        </>
    );
}

BotonIncidencia.propTypes = {
    aula: formaAula,
    aulas: PropTypes.arrayOf(formaAula),
    por: PropTypes.string,
    hora: PropTypes.string,
    registrar: PropTypes.func.isRequired,
    alRegistrar: PropTypes.func,
    catalogo: PropTypes.string,
    variante: PropTypes.string,
    className: PropTypes.string,
};

// Las incidencias de un examen o de un aula (GET /api/examenes/{examen}/incidencias).
// `atender(id)` muestra el botón con que el docente las da por atendidas
// (POST /api/incidencias/{id}/atencion) y devuelve su promesa; `conAula`
// agrega el aula a cada una. Las atendidas muestran quién y cuándo si la
// lista trae `atendida_por` y `atendida_en`.
export function ListaIncidencias({ items, atender, corregir, conAula = false, limite = null }) {
    const [enCurso, setEnCurso] = useState(null);
    const [viendo, setViendo] = useState(null);
    const [corrigiendo, setCorrigiendo] = useState(null);
    // «Atender» no se deshace: el primer toque pide confirmar.
    const [porAtender, setPorAtender] = useState(null);
    const [todas, setTodas] = useState(false);

    if (items.length === 0) {
        return <p className="px-4 py-8 text-center text-sm text-slate-600">Sin incidencias</p>;
    }

    const visibles = limite && !todas ? items.slice(0, limite) : items;

    const alAtender = async (id) => {
        setEnCurso(id);
        try {
            await atender(id);
        } finally {
            setEnCurso(null);
            setPorAtender(null);
        }
    };

    return (
        <>
            <ul className="divide-y divide-slate-200">
                {visibles.map((i) => (
                    <li key={i.id} className="flex flex-wrap items-start gap-x-3 gap-y-2 px-4 py-3">
                        <div className="min-w-0 flex-1 basis-56">
                            <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <Insignia tono={tonoDe(i)} punto>
                                    {i.tipo_texto ?? TIPOS_INCIDENCIA[i.tipo] ?? i.tipo}
                                </Insignia>
                                {gravedadDe(i) && (
                                    <span
                                        className={`text-xs font-semibold ${i.atendida ? 'text-slate-500' : TEXTO_GRAVEDAD[gravedadDe(i)]}`}
                                    >
                                        {GRAVEDADES[gravedadDe(i)].texto}
                                    </span>
                                )}
                                <span className="text-xs tabular-nums text-slate-600">
                                    {conAula && `Aula ${i.aula} · `}
                                    {i.hora}
                                </span>
                            </p>
                            {i.estudiante && (
                                <p className="mt-1 break-words text-sm font-medium text-slate-900">
                                    {i.estudiante}
                                </p>
                            )}
                            <p className="mt-0.5 text-sm text-slate-700">{i.detalle}</p>
                            <p className="mt-0.5 break-words text-xs text-slate-600">
                                {[
                                    i.por,
                                    i.rol,
                                    i.corregida && `Corregida por ${i.corregida_por ?? '—'}`,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                            {(i.evidencia || i.video || corregir) && (
                                <p className="-ml-2 mt-1 flex flex-wrap gap-1">
                                    {(i.evidencia || i.video) && (
                                        <Boton
                                            type="button"
                                            variante="enlace"
                                            tamano="chico"
                                            onClick={() => setViendo(i)}
                                        >
                                            {i.evidencia ? (
                                                <IconoImagen className="h-4 w-4" />
                                            ) : (
                                                <Video className="h-4 w-4" />
                                            )}{' '}
                                            Evidencia
                                            {fotosDe(i) > 1 && ` · ${fotosDe(i)}`}
                                            {i.evidencia && i.video && ' · video'}
                                        </Boton>
                                    )}
                                    {corregir && (
                                        <Boton
                                            type="button"
                                            variante="enlace"
                                            tamano="chico"
                                            onClick={() => setCorrigiendo(i)}
                                        >
                                            <Pencil className="h-4 w-4" />{' '}
                                            {i.tipo === 'INGRESO_REPETIDO'
                                                ? 'Adjuntar evidencia'
                                                : 'Corregir'}
                                        </Boton>
                                    )}
                                </p>
                            )}
                        </div>
                        {i.atendida ? (
                            <span className="flex min-h-9 min-w-0 max-w-full flex-col items-end justify-center text-sm text-slate-600">
                                <span className="inline-flex items-center gap-1">
                                    <Check className="h-4 w-4" strokeWidth={3} /> Atendida
                                </span>
                                {atencionDe(i) && (
                                    <span className="max-w-full truncate text-xs tabular-nums">
                                        {atencionDe(i)}
                                    </span>
                                )}
                            </span>
                        ) : atender && porAtender === i.id ? (
                            <span className="flex shrink-0 items-center gap-1">
                                <Boton
                                    type="button"
                                    variante="fantasma"
                                    tamano="chico"
                                    onClick={() => setPorAtender(null)}
                                >
                                    Cancelar
                                </Boton>
                                <Boton
                                    type="button"
                                    tamano="chico"
                                    cargando={enCurso === i.id}
                                    onClick={() => alAtender(i.id)}
                                >
                                    Confirmar
                                </Boton>
                            </span>
                        ) : atender ? (
                            <Boton
                                type="button"
                                variante="secundario"
                                tamano="chico"
                                className="shrink-0"
                                disabled={enCurso === i.id}
                                onClick={() => setPorAtender(i.id)}
                            >
                                Atender
                            </Boton>
                        ) : (
                            <span className="inline-flex min-h-9 shrink-0 items-center text-sm font-medium text-danger-700">
                                Sin atender
                            </span>
                        )}
                    </li>
                ))}
            </ul>
            {limite && items.length > limite && (
                <div className="border-t border-slate-200 px-4 py-2">
                    <Boton
                        type="button"
                        variante="enlace"
                        tamano="chico"
                        onClick={() => setTodas((v) => !v)}
                    >
                        {todas ? 'Ver menos' : `Ver todas · ${items.length}`}
                    </Boton>
                </div>
            )}
            {viendo && <VerEvidencia incidencia={viendo} onCerrar={() => setViendo(null)} />}
            {corrigiendo && (
                <CorregirIncidencia
                    incidencia={corrigiendo}
                    corregir={corregir}
                    onCerrar={() => setCorrigiendo(null)}
                />
            )}
        </>
    );
}

const TEXTO_GRAVEDAD = {
    MUY_GRAVE: 'text-danger-700',
    GRAVE: 'text-warning-700',
    LEVE: 'text-info-700',
};

export const gravedadDe = (i) => i.gravedad ?? GRAVEDAD_DE_TIPO[i.tipo] ?? null;

// Filtro por gravedad de una lista de incidencias (o de sucesos con
// `gravedad`). `valor` nulo: todas.
export function FiltroGravedad({ items, valor, onCambiar }) {
    return (
        <Chips
            etiqueta="Gravedad"
            valor={valor}
            onCambiar={onCambiar}
            opciones={[
                { valor: null, etiqueta: 'Todas', conteo: items.length },
                ...Object.entries(GRAVEDADES).map(([clave, g]) => ({
                    valor: clave,
                    etiqueta: g.texto,
                    conteo: items.filter((i) => gravedadDe(i) === clave).length,
                })),
            ]}
        />
    );
}

FiltroGravedad.propTypes = {
    items: PropTypes.arrayOf(PropTypes.object).isRequired,
    valor: PropTypes.string,
    onCambiar: PropTypes.func.isRequired,
};
const tonoDe = (i) => (i.atendida ? 'neutro' : (GRAVEDADES[gravedadDe(i)]?.tono ?? 'peligro'));

// Las fotos de la evidencia (GET /api/incidencias/{id}/evidencias/{n}),
// pedidas con la sesión y mostradas desde la memoria: una grande y debajo
// las miniaturas para pasar de una a otra.
function VerEvidencia({ incidencia, onCerrar }) {
    const cantidad = fotosDe(incidencia);
    const [urls, setUrls] = useState([]);
    const [fallo, setFallo] = useState(false);
    const [actual, setActual] = useState(0);
    const [video, setVideo] = useState(null);

    // El video (GET /api/incidencias/{id}/video), también con la sesión.
    useEffect(() => {
        if (!incidencia.video) return undefined;
        let vigente = true;
        let creada = null;
        api.get(`/incidencias/${incidencia.id}/video`, { responseType: 'blob' })
            .then((respuesta) => {
                creada = URL.createObjectURL(respuesta.data);
                if (vigente) setVideo(creada);
            })
            .catch(() => vigente && setVideo(false));
        return () => {
            vigente = false;
            if (creada) URL.revokeObjectURL(creada);
        };
    }, [incidencia.id, incidencia.video]);

    useEffect(() => {
        let vigente = true;
        const creadas = [];
        Promise.all(
            Array.from({ length: cantidad }, (_, i) =>
                api
                    .get(`/incidencias/${incidencia.id}/evidencias/${i + 1}`, {
                        responseType: 'blob',
                    })
                    .then((respuesta) => {
                        const url = URL.createObjectURL(respuesta.data);
                        creadas.push(url);
                        return url;
                    })
                    .catch(() => null)
            )
        ).then((todas) => {
            if (!vigente) return;
            const abiertas = todas.filter(Boolean);
            setUrls(abiertas);
            setFallo(cantidad > 0 && abiertas.length === 0);
        });
        return () => {
            vigente = false;
            creadas.forEach((url) => URL.revokeObjectURL(url));
        };
    }, [incidencia.id, cantidad]);

    return (
        <Dialogo titulo="Evidencia" abierto onCerrar={onCerrar}>
            <p className="mb-3 text-sm text-slate-600">
                {[
                    incidencia.tipo_texto ?? TIPOS_INCIDENCIA[incidencia.tipo],
                    incidencia.hora,
                    cantidad > 1 && `${cantidad} fotos`,
                    incidencia.video && 'video',
                ]
                    .filter(Boolean)
                    .join(' · ')}
            </p>
            {urls[actual] && (
                <img
                    src={urls[actual]}
                    alt={`Evidencia ${actual + 1}`}
                    className="max-h-[55vh] w-full rounded-lg object-contain"
                />
            )}
            {urls.length > 1 && (
                <div className="mt-3 flex gap-2">
                    {urls.map((url, i) => (
                        <button
                            key={url}
                            type="button"
                            aria-label={`Ver la foto ${i + 1}`}
                            aria-pressed={i === actual}
                            onClick={() => setActual(i)}
                            className={`h-16 w-16 overflow-hidden rounded-lg border-2 ${i === actual ? 'border-primary-600' : 'border-transparent opacity-70 hover:opacity-100'}`}
                        >
                            <img src={url} alt="" className="h-full w-full object-cover" />
                        </button>
                    ))}
                </div>
            )}
            {video && (
                <video
                    src={video}
                    controls
                    playsInline
                    aria-label="Video de evidencia"
                    className={`max-h-[55vh] w-full rounded-lg bg-slate-900 ${urls.length > 0 ? 'mt-3' : ''}`}
                />
            )}
            {(fallo || video === false) && (
                <p className="text-sm text-danger-600">No se pudo abrir</p>
            )}
            {urls.length === 0 && !fallo && video === null && (
                <p className="text-sm text-slate-600">Cargando…</p>
            )}
        </Dialogo>
    );
}

VerEvidencia.propTypes = {
    incidencia: PropTypes.shape({
        id: PropTypes.number.isRequired,
        tipo: PropTypes.string,
        tipo_texto: PropTypes.string,
        hora: PropTypes.string,
        evidencia: PropTypes.bool,
        evidencias: PropTypes.number,
        video: PropTypes.bool,
    }).isRequired,
    onCerrar: PropTypes.func.isRequired,
};

// El docente corrige el tipo, el detalle y la foto. `corregir(id, cuerpo)`
// hace la llamada (POST /api/incidencias/{id}/correccion).
function CorregirIncidencia({ incidencia, corregir, onCerrar }) {
    const fijo = FIJOS.includes(incidencia.tipo);
    // El texto del ingreso repetido lo escribe la puerta: no se reescribe.
    const delSistema = incidencia.tipo === 'INGRESO_REPETIDO';
    const [tipo, setTipo] = useState(incidencia.tipo);
    const [gravedad, setGravedad] = useState(
        incidencia.gravedad ?? GRAVEDAD_DE_TIPO[incidencia.tipo] ?? 'LEVE'
    );
    const [nombre, setNombre] = useState(incidencia.nombre_propio ?? '');
    const [detalle, setDetalle] = useState(incidencia.detalle ?? '');
    const [estudiante, setEstudiante] = useState(incidencia.estudiante ?? '');
    const [tocoEstudiante, setTocoEstudiante] = useState(false);
    const [fotos, setFotos] = useState([]);
    const [video, setVideo] = useState(null);
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();
    const corto = detalle.trim().length < DETALLE_MINIMO;
    // Las fotos se agregan a las que ya tiene, hasta tres en total.
    const yaTiene = fotosDe(incidencia);
    const libres = Math.max(0, MAXIMO_FOTOS - yaTiene);

    const guardar = async () => {
        if (corto) return setErrores({ detalle: `Mínimo ${DETALLE_MINIMO} caracteres` });
        if (tipo === 'OTRA' && nombre.trim() && nombre.trim().length < 3) {
            return setErrores({ tipo_texto: 'Mínimo 3 caracteres' });
        }
        const errorEstudiante = tocoEstudiante ? errorDeEstudiante(estudiante) : null;
        if (errorEstudiante) return setErrores({ estudiante: errorEstudiante });
        if (enviando) return undefined;
        setEnviando(true);
        setErrores({});
        try {
            const datos = { tipo, gravedad, detalle: detalle.trim() };
            if (tipo === 'OTRA') datos.tipo_texto = nombre.trim();
            // Solo viaja si se cambió: el texto mostrado no es el guardado.
            if (tocoEstudiante) datos.estudiante = estudiante.trim();
            await corregir(incidencia.id, cuerpoCon(datos, fotos, video));
            onCerrar();
        } catch (error) {
            const campos = erroresDe(error);
            setErrores(campos);
            if (Object.keys(campos).length === 0) {
                avisar(mensajeDe(error, 'No se pudo guardar'), 'error');
            }
        } finally {
            setEnviando(false);
        }
        return undefined;
    };

    return (
        <Dialogo
            titulo={delSistema ? 'Evidencia' : 'Corregir incidencia'}
            abierto
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton
                        type="button"
                        onClick={guardar}
                        disabled={(delSistema ? fotos.length === 0 && !video : corto) || enviando}
                        cargando={enviando}
                    >
                        Guardar
                    </Boton>
                </>
            }
        >
            <div className="space-y-4">
                {fijo ? (
                    <p className="text-sm font-medium text-slate-800">
                        {TIPOS_INCIDENCIA[incidencia.tipo]}
                    </p>
                ) : (
                    <Seleccion
                        etiqueta="Tipo"
                        requerido
                        value={tipo}
                        onChange={(e) => setTipo(e.target.value)}
                    >
                        {REPORTABLES.map((clave) => (
                            <option key={clave} value={clave}>
                                {clave === 'OTRA' ? 'Otra no prevista' : TIPOS_INCIDENCIA[clave]}
                            </option>
                        ))}
                    </Seleccion>
                )}
                {!fijo && tipo === 'OTRA' && (
                    <Campo
                        etiqueta="Qué pasó"
                        maxLength={80}
                        placeholder="Ej.: Se comunicó por señas"
                        value={nombre}
                        error={errores.tipo_texto}
                        onChange={(e) => setNombre(e.target.value)}
                    />
                )}
                {!delSistema && (
                    <SelectorGravedad
                        valor={gravedad}
                        onCambiar={setGravedad}
                        error={errores.gravedad}
                    />
                )}
                {!delSistema && (
                    <Campo
                        etiqueta="Estudiante"
                        placeholder="Código SIS o nombre"
                        maxLength={150}
                        value={estudiante}
                        error={errores.estudiante}
                        onChange={(e) => {
                            setEstudiante(e.target.value);
                            setTocoEstudiante(true);
                        }}
                    />
                )}
                {delSistema ? (
                    <p className="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700">
                        {incidencia.detalle}
                    </p>
                ) : (
                    <AreaTexto
                        etiqueta="Detalle"
                        requerido
                        rows={3}
                        maxLength={2000}
                        value={detalle}
                        onChange={(e) => setDetalle(e.target.value)}
                        error={errores.detalle}
                        pie={corto ? `Mínimo ${DETALLE_MINIMO} caracteres` : null}
                    />
                )}
                {libres > 0 ? (
                    <FotosEvidencia
                        etiqueta={yaTiene > 0 ? `Agregar fotos · tiene ${yaTiene}` : 'Evidencia'}
                        fotos={fotos}
                        onCambiar={setFotos}
                        maximo={libres}
                        error={errorDeFotos(errores)}
                    />
                ) : (
                    <p className="text-sm text-slate-600">{MAXIMO_FOTOS} fotos de evidencia</p>
                )}
                {incidencia.video ? (
                    <p className="text-sm text-slate-600">Ya tiene un video</p>
                ) : (
                    <VideoEvidencia video={video} onCambiar={setVideo} error={errores.video} />
                )}
            </div>
            {aviso}
        </Dialogo>
    );
}

CorregirIncidencia.propTypes = {
    incidencia: PropTypes.shape({
        id: PropTypes.number.isRequired,
        tipo: PropTypes.string,
        detalle: PropTypes.string,
        estudiante: PropTypes.string,
        gravedad: PropTypes.string,
        nombre_propio: PropTypes.string,
        evidencia: PropTypes.bool,
        evidencias: PropTypes.number,
        video: PropTypes.bool,
    }).isRequired,
    corregir: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};

ListaIncidencias.propTypes = {
    items: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            tipo: PropTypes.string,
            tipo_texto: PropTypes.string,
            aula: PropTypes.string,
            estudiante: PropTypes.string,
            detalle: PropTypes.string,
            hora: PropTypes.string,
            por: PropTypes.string,
            rol: PropTypes.string,
            atendida: PropTypes.bool,
            atendida_por: PropTypes.string,
            atendida_en: PropTypes.string,
            gravedad: PropTypes.string,
            evidencia: PropTypes.bool,
            corregida: PropTypes.bool,
            corregida_por: PropTypes.string,
        })
    ).isRequired,
    atender: PropTypes.func,
    corregir: PropTypes.func,
    conAula: PropTypes.bool,
    limite: PropTypes.number,
};

// La lista de incidencias comunes de la cuenta (GET/PUT/DELETE
// /api/incidencias-comunes): la gravedad con que se sugiere cada tipo y las
// incidencias propias, que se agregan y se quitan.
function IncidenciasComunes({ onCerrar }) {
    const [lista, setLista] = useState(null);
    const [nombre, setNombre] = useState('');
    const [gravedad, setGravedad] = useState('');
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();

    useEffect(() => {
        let vigente = true;
        api.get('/incidencias-comunes')
            .then((r) => vigente && setLista(r.data?.data ?? []))
            .catch(() => vigente && setLista([]));
        return () => {
            vigente = false;
        };
    }, []);

    const guardar = async (cuerpo) => {
        try {
            const r = await api.put('/incidencias-comunes', cuerpo);
            setLista(r.data?.data ?? lista);
            setErrores({});
            return true;
        } catch (error) {
            const campos = erroresDe(error);
            setErrores(campos);
            if (Object.keys(campos).length === 0)
                avisar(mensajeDe(error, 'No se pudo guardar'), 'error');
            return false;
        }
    };
    const agregar = async () => {
        if (nombre.trim().length < 3) return setErrores({ nombre: 'Mínimo 3 caracteres' });
        if (!gravedad) return setErrores({ gravedad: 'Elija leve, grave o muy grave' });
        setEnviando(true);
        if (await guardar({ nombre: nombre.trim(), gravedad })) {
            setNombre('');
            setGravedad('');
            avisar('Incidencia agregada');
        }
        setEnviando(false);
        return undefined;
    };
    const quitar = async (item) => {
        try {
            const r = await api.delete(`/incidencias-comunes/${item.id}`);
            setLista(r.data?.data ?? lista);
            avisar(`${item.nombre} quitada`);
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudo quitar'), 'error');
        }
    };

    return (
        <Dialogo titulo="Mis incidencias comunes" abierto onCerrar={onCerrar} ancho="max-w-2xl">
            <div className="space-y-5">
                {lista === null ? (
                    <p className="text-sm text-slate-600">Cargando…</p>
                ) : (
                    <ul className="divide-y divide-slate-100" aria-label="Mis incidencias comunes">
                        {lista.map((item) => (
                            <li
                                key={item.propia ? `p${item.id}` : item.tipo}
                                className="flex flex-col gap-2 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <span className="min-w-0 text-sm font-medium text-slate-800">
                                    {item.nombre}
                                    {item.propia && (
                                        <span className="ml-2 text-xs font-normal text-slate-500">
                                            propia
                                        </span>
                                    )}
                                </span>
                                <span className="flex items-center gap-2">
                                    <SelectorGravedad
                                        compacto
                                        etiqueta={`Gravedad de ${item.nombre}`}
                                        valor={item.gravedad}
                                        onCambiar={(nivel) =>
                                            guardar(
                                                item.propia
                                                    ? { nombre: item.nombre, gravedad: nivel }
                                                    : { tipo: item.tipo, gravedad: nivel }
                                            )
                                        }
                                    />
                                    {item.propia ? (
                                        <button
                                            type="button"
                                            aria-label={`Quitar ${item.nombre}`}
                                            onClick={() => quitar(item)}
                                            className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-danger-50 hover:text-danger-700 ${FOCO}`}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    ) : (
                                        <span className="h-9 w-9 shrink-0" aria-hidden="true" />
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
                <div className="space-y-3 rounded-xl border border-dashed border-slate-300 p-4">
                    <Campo
                        etiqueta="Nueva incidencia"
                        maxLength={80}
                        placeholder="Ej.: Uso de corrector"
                        value={nombre}
                        error={errores.nombre}
                        onChange={(e) => setNombre(e.target.value)}
                    />
                    <SelectorGravedad
                        valor={gravedad}
                        onCambiar={setGravedad}
                        error={errores.gravedad}
                    />
                    <Boton type="button" onClick={agregar} cargando={enviando}>
                        Agregar
                    </Boton>
                </div>
            </div>
            {aviso}
        </Dialogo>
    );
}

IncidenciasComunes.propTypes = { onCerrar: PropTypes.func.isRequired };

// El botón que abre la lista propia de incidencias comunes.
export function BotonIncidenciasComunes({ className = '' }) {
    const [abierto, setAbierto] = useState(false);
    return (
        <>
            <Boton
                type="button"
                variante="secundario"
                className={className}
                onClick={() => setAbierto(true)}
            >
                <ListChecks className="h-4 w-4" /> Mis incidencias
            </Boton>
            {abierto && <IncidenciasComunes onCerrar={() => setAbierto(false)} />}
        </>
    );
}

BotonIncidenciasComunes.propTypes = { className: PropTypes.string };
