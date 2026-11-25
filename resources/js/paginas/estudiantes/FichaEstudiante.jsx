import { useEffect, useId, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { BookOpen, Camera, Loader2, Pencil, ShieldCheck, UserRound } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import EstadoCarga from '../../componentes/EstadoCarga';
import { PuntoFacultad } from '../../componentes/FiltroFacultad';
import IconoAsignatura from '../../componentes/IconoAsignatura';
import Insignia from '../../componentes/Insignia';
import { dimensionesDe, ImagenNoValida, reducirImagen } from '../../utiles/imagen';
import { encuadrarFoto } from '../../utiles/encuadrarFoto';
import { revisarFoto } from '../../utiles/revisarFoto';
import { fechaCorta } from '../../utiles/texto';
import { EditarEstudiante } from '../docente/EdicionDeLista';

// Solo el día («3 nov 2026»): en la ficha la hora sobra.
const soloDia = (fecha) => (fecha ? fechaCorta(String(fecha).slice(0, 10)) : '');

// De dónde salió el dato del estudiante: «Administración» si lo cargó la
// administración (verificado), «Docente» si llegó en la lista de un grupo.
export function Origen({ origen }) {
    return origen === 'Administración' ? (
        <Insignia tono="exito">
            <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" /> Administración
        </Insignia>
    ) : (
        <Insignia tono="neutro">
            <UserRound className="h-3.5 w-3.5" aria-hidden="true" /> {origen ?? 'Docente'}
        </Insignia>
    );
}

Origen.propTypes = { origen: PropTypes.string };

// Un dato de la ficha con su etiqueta a la vista.
function Dato({ etiqueta, children }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-medium text-slate-500">{etiqueta}</dt>
            <dd className="min-w-0 break-words text-sm font-medium text-slate-900">{children}</dd>
        </div>
    );
}

Dato.propTypes = {
    etiqueta: PropTypes.string.isRequired,
    children: PropTypes.node,
};

// La foto del estudiante (GET /api/estudiantes/{id}/foto). La
// administración la pone o la cambia en cualquier momento: la cámara abre
// el selector y la foto elegida se valida, se reduce, se recorta en cuadrado
// alrededor del rostro, se revisa (un solo rostro, de frente) y se guarda de
// una vez.
const MINIMO_PX = 240;

function FotoDelEstudiante({ id, conFoto, subidaEn, alCambiar, nombre, alAmpliar }) {
    const entrada = useId();
    const [url, setUrl] = useState(null);
    const [version, setVersion] = useState(0);
    const [grande, setGrande] = useState(false);
    const ampliar = (valor) => {
        setGrande(valor);
        // Al cerrar, se avisa después del evento: el mismo Escape no debe cerrar la ficha.
        if (valor) alAmpliar?.(true);
        else setTimeout(() => alAmpliar?.(false), 0);
    };
    const [enviando, setEnviando] = useState(false);
    // El resultado queda a la vista hasta el siguiente intento: el aviso
    // fugaz no alcanza a leerse.
    const [resultado, setResultado] = useState(null);
    const [aviso, avisar] = useAviso();
    const accion = conFoto ? 'Cambiar foto' : 'Poner foto';

    useEffect(() => {
        if (!conFoto) {
            setUrl(null);
            return undefined;
        }
        let vigente = true;
        let creada = null;
        api.get(`/estudiantes/${id}/foto`, { responseType: 'blob' })
            .then((r) => {
                if (!vigente) return;
                creada = URL.createObjectURL(r.data);
                setUrl(creada);
            })
            .catch(() => {});
        return () => {
            vigente = false;
            if (creada) URL.revokeObjectURL(creada);
        };
    }, [id, conFoto, version]);

    async function subir(elegida) {
        if (!elegida || enviando) return;
        setEnviando(true);
        setResultado(null);
        try {
            const medidas = await dimensionesDe(elegida);
            if (medidas && Math.min(medidas.ancho, medidas.alto) < MINIMO_PX) {
                throw new ImagenNoValida(`Resolución insuficiente · mínimo ${MINIMO_PX} px`);
            }
            const reducida = await encuadrarFoto(await reducirImagen(elegida));
            const { errores, avisos } = await revisarFoto(reducida);
            if (errores.length > 0) throw new ImagenNoValida(errores[0]);
            const formulario = new FormData();
            formulario.append('foto', reducida);
            await api.post(`/estudiantes/${id}/foto`, formulario);
            const texto = ['Foto guardada', ...avisos].join(' · ');
            avisar(texto);
            setResultado({ texto, error: false });
            setVersion((v) => v + 1);
            alCambiar();
        } catch (fallo) {
            const texto =
                fallo instanceof ImagenNoValida
                    ? fallo.message
                    : (erroresDe(fallo).foto ?? mensajeDe(fallo, 'No se pudo guardar'));
            avisar(texto, 'error');
            setResultado({ texto, error: true });
        } finally {
            setEnviando(false);
        }
    }

    return (
        <div className="relative w-28 shrink-0">
            {url ? (
                <button
                    type="button"
                    onClick={() => ampliar(true)}
                    aria-label="Ver foto más grande"
                    className="block w-28 cursor-zoom-in rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                >
                    <img
                        src={url}
                        alt="Foto del estudiante"
                        className="aspect-[3/4] w-28 rounded-xl border border-slate-200 object-cover object-top shadow-sm"
                    />
                </button>
            ) : (
                <span className="flex h-28 w-28 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-500">
                    <UserRound className="h-7 w-7 text-slate-400" strokeWidth={1.5} />
                    Sin foto
                </span>
            )}
            {grande && url && (
                <Dialogo
                    titulo={nombre ?? 'Foto del estudiante'}
                    onCerrar={() => ampliar(false)}
                    ancho="max-w-md"
                >
                    <img
                        src={url}
                        alt={`Foto de ${nombre ?? 'el estudiante'}`}
                        className="mx-auto max-h-[70dvh] w-auto max-w-full rounded-xl"
                    />
                </Dialogo>
            )}
            {enviando && (
                <span className="absolute inset-0 flex items-center justify-center rounded-xl bg-white/70">
                    <Loader2 className="h-6 w-6 animate-spin text-primary-600" aria-hidden="true" />
                    <span className="sr-only">Guardando foto</span>
                </span>
            )}
            {conFoto && subidaEn && (
                <p className="mt-1.5 text-center text-xs">
                    <span className="block font-medium text-slate-700">Con foto</span>
                    <span className="block text-slate-500">del {soloDia(subidaEn)}</span>
                </p>
            )}
            <label
                htmlFor={entrada}
                className="mt-2 flex min-h-11 w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white text-xs font-medium text-slate-700 hover:bg-primary-50 hover:text-primary-700 focus-within:outline focus-within:outline-2 focus-within:outline-primary-600"
            >
                <Camera className="h-4 w-4" aria-hidden="true" />
                {accion}
                <input
                    id={entrada}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                    className="sr-only"
                    disabled={enviando}
                    onChange={(e) => {
                        subir(e.target.files?.[0] ?? null);
                        e.target.value = '';
                    }}
                />
            </label>
            {resultado && (
                <p
                    role={resultado.error ? 'alert' : 'status'}
                    className={`mt-3 text-xs font-medium ${resultado.error ? 'text-danger-700' : 'text-success-700'}`}
                >
                    {resultado.texto}
                </p>
            )}
            {aviso}
        </div>
    );
}

FotoDelEstudiante.propTypes = {
    id: PropTypes.number.isRequired,
    conFoto: PropTypes.bool,
    subidaEn: PropTypes.string,
    alCambiar: PropTypes.func.isRequired,
    nombre: PropTypes.string,
    alAmpliar: PropTypes.func,
};

// Ficha de un estudiante: sus datos, su origen y las materias que lleva en
// los períodos vigentes, con el grupo y el docente de cada una. En escritorio,
// la identidad a la izquierda y las materias a la derecha; en teléfono, una
// sola columna.
export default function FichaEstudiante({ estudiante, onCerrar, onFotoCambiada }) {
    const { datos, cargando, error, recargar } = usarConsulta(`/estudiantes/${estudiante.id}`);
    const ficha = datos?.id === estudiante.id ? datos : null;
    const materias = ficha?.materias ?? [];
    const conflictos = ficha?.conflictos ?? [];
    const [editando, setEditando] = useState(false);
    // Con la foto ampliada, Escape solo la cierra a ella, no a la ficha.
    const fotoAmpliada = useRef(false);
    const [aviso, avisar] = useAviso();

    return (
        <Dialogo
            titulo={ficha?.nombre ?? estudiante.nombre}
            onCerrar={() => {
                if (!fotoAmpliada.current) onCerrar();
            }}
            ancho="max-w-4xl"
            acciones={
                ficha && (
                    <Boton type="button" onClick={() => setEditando(true)}>
                        <Pencil className="h-4 w-4" /> Editar datos
                    </Boton>
                )
            }
        >
            <EstadoCarga
                cargando={cargando || (!ficha && !error)}
                error={error}
                onReintentar={recargar}
                filas={4}
                className="!p-0"
            >
                {ficha && (
                    <div className="grid gap-5 md:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
                        <div className="min-w-0 space-y-4 self-start rounded-xl bg-gradient-to-br from-primary-50 to-slate-50 p-4">
                            <div className="flex gap-4">
                                <FotoDelEstudiante
                                    id={ficha.id}
                                    nombre={ficha.nombre}
                                    alAmpliar={(abierta) => {
                                        fotoAmpliada.current = abierta;
                                    }}
                                    conFoto={Boolean(ficha.con_foto)}
                                    subidaEn={ficha.foto_subida_en ?? undefined}
                                    alCambiar={() => {
                                        recargar();
                                        onFotoCambiada?.();
                                    }}
                                />
                                <div className="min-w-0 flex-1 space-y-2.5">
                                    <div className="space-y-1">
                                        <p className="text-xs font-medium text-slate-500">
                                            Código SIS
                                        </p>
                                        <p className="font-mono text-xl font-semibold tracking-wide text-slate-900">
                                            {ficha.codigo}
                                        </p>
                                    </div>
                                    {ficha.facultad && <PuntoFacultad sigla={ficha.facultad} />}
                                    <dl className="space-y-2.5 border-t border-slate-200/80 pt-3">
                                        <Dato etiqueta="Carrera">{ficha.carrera ?? '—'}</Dato>
                                        <Dato etiqueta="C.I.">
                                            {ficha.documento ?? (
                                                <Insignia tono="advertencia">Sin carnet</Insignia>
                                            )}
                                        </Dato>
                                        <Dato etiqueta="Correo">
                                            <span className="break-all">{ficha.correo ?? '—'}</span>
                                        </Dato>
                                        <Dato etiqueta="Registrado por">
                                            {ficha.origen ?? 'Docente'}
                                        </Dato>
                                    </dl>
                                </div>
                            </div>
                            <p className="text-xs text-slate-500">
                                Registrado {soloDia(ficha.registrado_en) || 'sin fecha'} ·
                                actualizado {soloDia(ficha.actualizado_en) || 'sin fecha'}
                            </p>
                        </div>

                        <div className="min-w-0 space-y-5">
                            {conflictos.length > 0 && (
                                <section aria-label="Historial de conflictos">
                                    <h3 className="text-sm font-semibold text-slate-900">
                                        Historial de conflictos
                                        <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                            {conflictos.length}
                                        </span>
                                    </h3>
                                    <ol className="mt-2 space-y-2">
                                        {conflictos.map((conflicto) => (
                                            <li
                                                key={conflicto.id}
                                                className="rounded-lg border border-slate-200 p-3 text-sm"
                                            >
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <strong className="text-slate-800">
                                                        {conflicto.tipo === 'DOCUMENTO_DISTINTO'
                                                            ? 'Documento distinto'
                                                            : 'Nombre distinto'}
                                                    </strong>
                                                    <Insignia
                                                        tono={
                                                            conflicto.estado === 'resuelto'
                                                                ? 'exito'
                                                                : 'advertencia'
                                                        }
                                                    >
                                                        {conflicto.estado === 'resuelto'
                                                            ? 'Resuelto'
                                                            : 'Pendiente'}
                                                    </Insignia>
                                                </div>
                                                <p className="mt-1 break-words text-slate-700">
                                                    Propuesto: {conflicto.nombre_nuevo}
                                                    {conflicto.documento_nuevo &&
                                                        ` · C.I. ${conflicto.documento_nuevo}`}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {[
                                                        conflicto.asignatura &&
                                                            `${conflicto.asignatura}${conflicto.grupo ? `, grupo ${conflicto.grupo}` : ''}`,
                                                        `Reportado por ${conflicto.reportado_por ?? conflicto.via ?? '—'}`,
                                                        fechaCorta(conflicto.reportado_en),
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                                {conflicto.estado === 'resuelto' && (
                                                    <p className="text-xs text-slate-500">
                                                        Resolución:{' '}
                                                        {conflicto.resolucion === 'USAR_CARGA'
                                                            ? 'Se usó el nuevo'
                                                            : 'Se dejó el actual'}
                                                        {conflicto.resuelto_por &&
                                                            ` · ${conflicto.resuelto_por}`}
                                                        {conflicto.resuelto_en &&
                                                            ` · ${fechaCorta(conflicto.resuelto_en)}`}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                </section>
                            )}

                            <section aria-label="Materias">
                                <h3 className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <BookOpen
                                        className="h-4 w-4 text-primary-600"
                                        aria-hidden="true"
                                    />
                                    Materias
                                    <span className="rounded-full bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-700">
                                        {materias.length}
                                    </span>
                                </h3>
                                {materias.length === 0 ? (
                                    <p className="mt-2 flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-600">
                                        <BookOpen
                                            className="h-6 w-6 text-slate-400"
                                            aria-hidden="true"
                                        />
                                        Sin materias
                                    </p>
                                ) : (
                                    <ul className="mt-2 divide-y divide-slate-200 rounded-xl border border-slate-200">
                                        {materias.map((m) => (
                                            <li
                                                key={`${m.grupo_id ?? `${m.asignatura.codigo}-${m.grupo}`}-${m.periodo}`}
                                                className="flex min-w-0 gap-3 p-3"
                                            >
                                                <IconoAsignatura
                                                    nombre={m.asignatura.nombre}
                                                    color={m.asignatura.color}
                                                />
                                                <div className="min-w-0 flex-1 space-y-0.5">
                                                    <p className="text-sm font-semibold leading-snug text-slate-900">
                                                        {m.asignatura.nombre}
                                                    </p>
                                                    <p className="text-xs text-slate-600">
                                                        <span className="font-mono">
                                                            {m.asignatura.codigo}
                                                        </span>{' '}
                                                        · Grupo {m.grupo} · {m.periodo}
                                                    </p>
                                                    <p className="text-xs text-slate-700">
                                                        <span className="text-slate-500">
                                                            Docente:
                                                        </span>{' '}
                                                        {m.docente ?? 'Por designar'}
                                                        {m.via && (
                                                            <span className="text-slate-500">
                                                                {' '}
                                                                · vía {m.via}
                                                            </span>
                                                        )}
                                                    </p>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>
                        </div>
                    </div>
                )}
            </EstadoCarga>
            {editando && ficha && (
                <EditarEstudiante
                    estudiante={ficha}
                    ruta={`/estudiantes/${ficha.id}`}
                    conCodigo
                    onEditado={(texto) => {
                        setEditando(false);
                        avisar(texto);
                        recargar();
                        onFotoCambiada?.();
                    }}
                    onCerrar={() => setEditando(false)}
                />
            )}
            {aviso}
        </Dialogo>
    );
}

FichaEstudiante.propTypes = {
    estudiante: PropTypes.shape({
        id: PropTypes.number.isRequired,
        nombre: PropTypes.string.isRequired,
    }).isRequired,
    onCerrar: PropTypes.func.isRequired,
    onFotoCambiada: PropTypes.func,
};
