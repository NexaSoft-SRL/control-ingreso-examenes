import { useEffect, useState } from 'react';
import PropTypes from 'prop-types';
import { TriangleAlert, Check, Image as IconoImagen, Pencil } from 'lucide-react';
import { api } from '../api/cliente';
import Boton from './Boton';
import Campo from './Campo';
import Chips from './Chips';
import FotosEvidencia from './FotosEvidencia';
import Dialogo from './Dialogo';
import Insignia from './Insignia';
import Seleccion, { AreaTexto } from './Seleccion';
import { useAviso } from './Aviso';
import { erroresDe, mensajeDe } from '../api/errores';
import { fechaCorta } from '../utiles/texto';

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

// La gravedad sale del tipo y se lee por el color y por el texto: leve,
// grave o muy grave (decimotercera revisión).
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

// Los que asienta otra operación: no se eligen ni se cambian.
const FIJOS = ['EXPULSION', 'INGRESO_REPETIDO'];

const opcionDe = (clave) =>
    `${TIPOS_INCIDENCIA[clave]} · ${GRAVEDADES[GRAVEDAD_DE_TIPO[clave]].texto}`;

// Hasta tres fotos de evidencia por incidencia.
const MAXIMO_FOTOS = 3;

// Con fotos, el cuerpo va como formulario multipart (`evidencias[]`).
function cuerpoCon(datos, fotos) {
    if (fotos.length === 0) return datos;
    const formulario = new FormData();
    Object.entries(datos).forEach(([clave, valor]) => {
        if (valor !== null && valor !== undefined) formulario.append(clave, valor);
    });
    fotos.forEach((foto) => formulario.append('evidencias[]', foto));
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
// Un nombre escrito a mano: palabras de letras separadas por un espacio, un
// punto, un apóstrofo o un guion, con al menos dos letras.
const NOMBRE = /^\p{L}+(?:(?:\.\s*|\s+|['-])\p{L}+)*\.?$/u;
const errorDeNombre = (texto) =>
    (texto.match(/\p{L}/gu) ?? []).length >= 2 && NOMBRE.test(texto) ? null : 'error';
// El estudiante va por su código SIS o C.I. (el servidor exige que esté
// inscrito en el examen) o, si no se lo pudo identificar, por su nombre.
const errorDeEstudiante = (texto) => {
    const valor = texto.trim();
    if (!valor || /^\d/.test(valor)) return null;
    return errorDeNombre(valor) ? 'Un código SIS o solo letras' : null;
};
const REPORTABLES = Object.keys(TIPOS_INCIDENCIA).filter((clave) => !FIJOS.includes(clave));

const formaAula = PropTypes.shape({
    id: PropTypes.number.isRequired,
    nombre: PropTypes.string.isRequired,
});

// Botón «Incidencia» y su formulario. Con `aulas` (el docente, que pasa por
// varias) se elige el aula; con `aula` queda fija (el auxiliar en la suya).
// `registrar({ aula_id, tipo, estudiante, detalle })` hace la llamada
// (POST /api/examenes/{examen}/incidencias) y devuelve su promesa;
// `alRegistrar` se llama cuando terminó bien.
export function BotonIncidencia({
    aula,
    aulas,
    por,
    hora,
    registrar,
    alRegistrar,
    variante = 'secundario',
    className = '',
}) {
    const [aviso, avisar] = useAviso();
    const [abierto, setAbierto] = useState(false);
    const [tipo, setTipo] = useState('');
    const [donde, setDonde] = useState(aula?.id ?? aulas?.[0]?.id ?? '');
    const [estudiante, setEstudiante] = useState('');
    const [detalle, setDetalle] = useState('');
    const [fotos, setFotos] = useState([]);
    const [intento, setIntento] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [errores, setErrores] = useState({});
    const corto = detalle.trim().length < DETALLE_MINIMO;
    const aulaId = aula?.id ?? (donde === '' ? aulas?.[0]?.id : Number(donde));

    const cerrar = () => {
        setAbierto(false);
        setTipo('');
        setEstudiante('');
        setDetalle('');
        setFotos([]);
        setIntento(false);
        setErrores({});
    };
    const enviar = async () => {
        setIntento(true);
        if (!tipo || corto || enviando) return;
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
                        tipo,
                        estudiante: estudiante.trim() || null,
                        detalle: detalle.trim(),
                    },
                    fotos
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
                            disabled={!tipo || corto || enviando}
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
                        onChange={(e) => setTipo(e.target.value)}
                    >
                        <option value="" disabled>
                            Elegir
                        </option>
                        {REPORTABLES.map((clave) => (
                            <option key={clave} value={clave}>
                                {opcionDe(clave)}
                            </option>
                        ))}
                    </Seleccion>
                    <Campo
                        etiqueta="Estudiante"
                        placeholder="Código SIS, C.I. o nombre"
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
                            {(i.evidencia || corregir) && (
                                <p className="-ml-2 mt-1 flex flex-wrap gap-1">
                                    {i.evidencia && (
                                        <Boton
                                            type="button"
                                            variante="enlace"
                                            tamano="chico"
                                            onClick={() => setViendo(i)}
                                        >
                                            <IconoImagen className="h-4 w-4" /> Evidencia
                                            {fotosDe(i) > 1 && ` · ${fotosDe(i)}`}
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

// Filtra una lista de incidencias por gravedad, con el conteo de cada una.
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
    const cantidad = Math.max(1, fotosDe(incidencia));
    const [urls, setUrls] = useState([]);
    const [fallo, setFallo] = useState(false);
    const [actual, setActual] = useState(0);

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
            setFallo(abiertas.length === 0);
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
            {fallo && <p className="text-sm text-danger-600">No se pudo abrir</p>}
            {urls.length === 0 && !fallo && <p className="text-sm text-slate-600">Cargando…</p>}
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
    const [detalle, setDetalle] = useState(incidencia.detalle ?? '');
    const [estudiante, setEstudiante] = useState(incidencia.estudiante ?? '');
    const [tocoEstudiante, setTocoEstudiante] = useState(false);
    const [fotos, setFotos] = useState([]);
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();
    const corto = detalle.trim().length < DETALLE_MINIMO;
    // Las fotos se agregan a las que ya tiene, hasta tres en total.
    const yaTiene = fotosDe(incidencia);
    const libres = Math.max(0, MAXIMO_FOTOS - yaTiene);

    const guardar = async () => {
        if (corto) return setErrores({ detalle: `Mínimo ${DETALLE_MINIMO} caracteres` });
        const errorEstudiante = tocoEstudiante ? errorDeEstudiante(estudiante) : null;
        if (errorEstudiante) return setErrores({ estudiante: errorEstudiante });
        if (enviando) return undefined;
        setEnviando(true);
        setErrores({});
        try {
            const datos = { tipo, detalle: detalle.trim() };
            // Solo viaja si se cambió: el texto mostrado no es el guardado.
            if (tocoEstudiante) datos.estudiante = estudiante.trim();
            await corregir(incidencia.id, cuerpoCon(datos, fotos));
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
                        disabled={(delSistema ? fotos.length === 0 : corto) || enviando}
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
                                {opcionDe(clave)}
                            </option>
                        ))}
                    </Seleccion>
                )}
                {!delSistema && (
                    <Campo
                        etiqueta="Estudiante"
                        placeholder="Código SIS, C.I. o nombre"
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
        evidencia: PropTypes.bool,
        evidencias: PropTypes.number,
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
