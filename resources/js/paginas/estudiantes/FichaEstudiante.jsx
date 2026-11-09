import { useEffect, useId, useState } from 'react';
import PropTypes from 'prop-types';
import {
    BookOpen,
    CalendarDays,
    Camera,
    GraduationCap,
    IdCard,
    Loader2,
    Mail,
    Pencil,
    X,
    Check,
    ShieldCheck,
    UserRound,
    UsersRound,
} from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import EstadoCarga from '../../componentes/EstadoCarga';
import { PuntoFacultad } from '../../componentes/FiltroFacultad';
import IconoAsignatura from '../../componentes/IconoAsignatura';
import Insignia from '../../componentes/Insignia';
import { dimensionesDe, ImagenNoValida, reducirImagen } from '../../utiles/imagen';
import { encuadrarFoto } from '../../utiles/encuadrarFoto';
import { revisarFoto } from '../../utiles/revisarFoto';

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

function Dato({ icono: Icono, etiqueta, children }) {
    return (
        <div className="flex min-w-0 items-center gap-2">
            <dt className="shrink-0 text-slate-400">
                <Icono className="h-4 w-4" aria-hidden="true" />
                <span className="sr-only">{etiqueta}</span>
            </dt>
            <dd className="min-w-0 break-words text-sm text-slate-700">{children}</dd>
        </div>
    );
}

Dato.propTypes = {
    icono: PropTypes.elementType.isRequired,
    etiqueta: PropTypes.string.isRequired,
    children: PropTypes.node,
};

// Cómo llegó el estudiante al grupo, con el mismo ícono que su origen.
function Via({ via }) {
    const Icono = via === 'Administración' ? ShieldCheck : UserRound;
    return (
        <span
            className="ml-auto inline-flex shrink-0 items-center text-slate-400"
            title={`Vía: ${via}`}
        >
            <Icono className="h-3.5 w-3.5" aria-hidden="true" />
            <span className="sr-only">Vía: {via}</span>
        </span>
    );
}

Via.propTypes = { via: PropTypes.string.isRequired };

// La foto del estudiante (GET /api/estudiantes/{id}/foto). La
// administración la pone o la cambia en cualquier momento: la cámara abre
// el selector y la foto elegida se valida, se reduce y se guarda de una vez.
const MINIMO_PX = 240;

function FotoDelEstudiante({ id, conFoto, alCambiar }) {
    const entrada = useId();
    const [url, setUrl] = useState(null);
    const [version, setVersion] = useState(0);
    const [enviando, setEnviando] = useState(false);
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
        try {
            const medidas = await dimensionesDe(elegida);
            if (medidas && Math.min(medidas.ancho, medidas.alto) < MINIMO_PX) {
                throw new ImagenNoValida(`Resolución insuficiente · mínimo ${MINIMO_PX} px`);
            }
            // Cuadrada alrededor del rostro y revisada (un solo rostro, de
            // frente) antes de guardarla.
            const reducida = await encuadrarFoto(await reducirImagen(elegida));
            const { errores } = await revisarFoto(reducida);
            if (errores.length > 0) throw new ImagenNoValida(errores[0]);
            const formulario = new FormData();
            formulario.append('foto', reducida);
            await api.post(`/estudiantes/${id}/foto`, formulario);
            avisar('Foto guardada');
            setVersion((v) => v + 1);
            alCambiar();
        } catch (fallo) {
            avisar(
                fallo instanceof ImagenNoValida
                    ? fallo.message
                    : (erroresDe(fallo).foto ?? mensajeDe(fallo, 'No se pudo guardar')),
                'error'
            );
        } finally {
            setEnviando(false);
        }
    }

    return (
        <div className="relative shrink-0">
            {url ? (
                <img
                    src={url}
                    alt="Foto del estudiante"
                    className="h-28 w-28 rounded-xl border border-slate-200 object-cover shadow-sm"
                />
            ) : (
                <span className="flex h-28 w-28 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-500">
                    <UserRound className="h-7 w-7 text-slate-400" strokeWidth={1.5} />
                    Sin foto
                </span>
            )}
            {enviando && (
                <span className="absolute inset-0 flex items-center justify-center rounded-xl bg-white/70">
                    <Loader2 className="h-6 w-6 animate-spin text-primary-600" aria-hidden="true" />
                    <span className="sr-only">Guardando foto</span>
                </span>
            )}
            <label
                htmlFor={entrada}
                title={accion}
                className="absolute -bottom-2 -right-2 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-primary-50 hover:text-primary-700 focus-within:outline focus-within:outline-2 focus-within:outline-primary-600"
            >
                <Camera className="h-4 w-4" aria-hidden="true" />
                <input
                    id={entrada}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                    className="sr-only"
                    aria-label={accion}
                    disabled={enviando}
                    onChange={(e) => {
                        subir(e.target.files?.[0] ?? null);
                        e.target.value = '';
                    }}
                />
            </label>
            {aviso}
        </div>
    );
}

FotoDelEstudiante.propTypes = {
    id: PropTypes.number.isRequired,
    conFoto: PropTypes.bool,
    alCambiar: PropTypes.func.isRequired,
};

// Ficha de un estudiante: sus datos, su origen y las materias que lleva en
// los períodos vigentes, con el grupo y el docente de cada una.
export default function FichaEstudiante({ estudiante, onCerrar, onFotoCambiada }) {
    const { datos, cargando, error, recargar } = usarConsulta(`/estudiantes/${estudiante.id}`);
    const ficha = datos?.id === estudiante.id ? datos : null;
    const materias = ficha?.materias ?? [];
    const [editando, setEditando] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [valores, setValores] = useState({ nombres: '', apellidos: '', documento: '' });
    const [errores, setErrores] = useState({});
    const [errorGeneral, setErrorGeneral] = useState('');
    const [aviso, avisar] = useAviso();

    useEffect(() => {
        if (ficha && !editando) {
            setValores({
                nombres: ficha.nombres ?? '',
                apellidos: ficha.apellidos ?? '',
                documento: ficha.documento ?? '',
            });
        }
    }, [ficha, editando]);

    async function guardar(e) {
        e.preventDefault();
        setGuardando(true);
        setErrores({});
        setErrorGeneral('');
        try {
            await api.put(`/estudiantes/${estudiante.id}`, {
                ...valores,
                documento: valores.documento.trim() || null,
            });
            setEditando(false);
            await recargar();
            avisar('Datos del estudiante actualizados');
        } catch (fallo) {
            const campos = erroresDe(fallo);
            setErrores(campos);
            if (Object.keys(campos).length === 0) {
                setErrorGeneral(mensajeDe(fallo, 'No se pudieron guardar las correcciones'));
            }
        } finally {
            setGuardando(false);
        }
    }

    return (
        <Dialogo titulo={ficha?.nombre ?? estudiante.nombre} onCerrar={onCerrar} ancho="max-w-2xl">
            <EstadoCarga
                cargando={cargando || (!ficha && !error)}
                error={error}
                onReintentar={recargar}
                filas={4}
                className="!p-0"
            >
                {ficha && (
                    <div className="space-y-5">
                        <div className="flex gap-5 rounded-xl bg-gradient-to-br from-primary-50 to-slate-50 p-4">
                            <FotoDelEstudiante
                                id={ficha.id}
                                conFoto={Boolean(ficha.con_foto)}
                                alCambiar={() => {
                                    recargar();
                                    onFotoCambiada?.();
                                }}
                            />
                            <div className="min-w-0 flex-1 space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                                        <p className="font-mono text-xl font-semibold tracking-wide text-slate-900">
                                            <span className="sr-only">Código SIS </span>
                                            {ficha.codigo}
                                        </p>
                                        <span role="group" aria-label="Origen">
                                            <Origen origen={ficha.origen} />
                                        </span>
                                        {ficha.facultad && (
                                            <span className="inline-flex items-center rounded-full bg-white px-2.5 py-1 ring-1 ring-slate-200">
                                                <PuntoFacultad sigla={ficha.facultad} />
                                            </span>
                                        )}
                                    </div>
                                    {!editando ? (
                                        <Boton
                                            type="button"
                                            variante="secundario"
                                            onClick={() => setEditando(true)}
                                        >
                                            <Pencil className="h-4 w-4" aria-hidden="true" />
                                            Editar datos
                                        </Boton>
                                    ) : (
                                        <div className="flex gap-2">
                                            <Boton
                                                type="button"
                                                variante="secundario"
                                                disabled={guardando}
                                                onClick={() => {
                                                    setEditando(false);
                                                    setErrores({});
                                                    setErrorGeneral('');
                                                }}
                                            >
                                                <X className="h-4 w-4" aria-hidden="true" />{' '}
                                                Cancelar
                                            </Boton>
                                            <Boton
                                                type="submit"
                                                form="corregir-datos-estudiante"
                                                cargando={guardando}
                                            >
                                                <Check className="h-4 w-4" aria-hidden="true" />
                                                Guardar correcciones
                                            </Boton>
                                        </div>
                                    )}
                                </div>
                                {editando ? (
                                    <form
                                        id="corregir-datos-estudiante"
                                        className="grid gap-3 sm:grid-cols-2"
                                        onSubmit={guardar}
                                    >
                                        <Campo
                                            etiqueta="Nombres"
                                            requerido
                                            value={valores.nombres}
                                            error={errores.nombres}
                                            onChange={(e) =>
                                                setValores((actual) => ({
                                                    ...actual,
                                                    nombres: e.target.value,
                                                }))
                                            }
                                        />
                                        <Campo
                                            etiqueta="Apellidos"
                                            requerido
                                            value={valores.apellidos}
                                            error={errores.apellidos}
                                            onChange={(e) =>
                                                setValores((actual) => ({
                                                    ...actual,
                                                    apellidos: e.target.value,
                                                }))
                                            }
                                        />
                                        <Campo
                                            etiqueta="Documento de identidad"
                                            value={valores.documento}
                                            error={errores.documento}
                                            onChange={(e) =>
                                                setValores((actual) => ({
                                                    ...actual,
                                                    documento: e.target.value,
                                                }))
                                            }
                                        />
                                        {errorGeneral && (
                                            <p role="alert" className="text-sm text-danger-700">
                                                {errorGeneral}
                                            </p>
                                        )}
                                    </form>
                                ) : (
                                    <dl className="space-y-1.5">
                                        <Dato icono={UserRound} etiqueta="Nombres">
                                            {ficha.nombres}
                                        </Dato>
                                        <Dato icono={UserRound} etiqueta="Apellidos">
                                            {ficha.apellidos}
                                        </Dato>
                                        <Dato icono={GraduationCap} etiqueta="Carrera">
                                            {ficha.carrera ?? '—'}
                                        </Dato>
                                        <Dato icono={IdCard} etiqueta="C.I.">
                                            {ficha.documento ?? '—'}
                                        </Dato>
                                        <Dato icono={Mail} etiqueta="Correo">
                                            {ficha.correo ?? '—'}
                                        </Dato>
                                    </dl>
                                )}
                            </div>
                        </div>

                        <section aria-label="Materias">
                            <h3 className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <BookOpen className="h-4 w-4 text-primary-600" aria-hidden="true" />
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
                                <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                                    {materias.map((m) => (
                                        <li
                                            key={`${m.grupo_id ?? `${m.asignatura.codigo}-${m.grupo}`}-${m.periodo}`}
                                            className="flex min-w-0 gap-2.5 rounded-xl border border-slate-200 p-2.5 transition-colors hover:border-primary-200 hover:bg-primary-50/40"
                                        >
                                            <IconoAsignatura
                                                nombre={m.asignatura.nombre}
                                                color={m.asignatura.color}
                                            />
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <p className="text-sm font-semibold leading-snug text-slate-900">
                                                    {m.asignatura.nombre}
                                                </p>
                                                <p className="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs text-slate-500">
                                                    <span className="font-mono">
                                                        {m.asignatura.codigo}
                                                    </span>
                                                    <span className="inline-flex items-center gap-1">
                                                        <UsersRound
                                                            className="h-3.5 w-3.5"
                                                            aria-hidden="true"
                                                        />
                                                        Grupo {m.grupo}
                                                    </span>
                                                    <span className="inline-flex items-center gap-1">
                                                        <CalendarDays
                                                            className="h-3.5 w-3.5"
                                                            aria-hidden="true"
                                                        />
                                                        {m.periodo}
                                                    </span>
                                                </p>
                                                <p className="flex min-w-0 items-center gap-1.5 text-xs text-slate-700">
                                                    <UserRound
                                                        className="h-3.5 w-3.5 shrink-0 text-slate-400"
                                                        aria-hidden="true"
                                                    />
                                                    <span className="truncate" title={m.docente}>
                                                        {m.docente ?? 'Por designar'}
                                                    </span>
                                                    {m.via && <Via via={m.via} />}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                )}
            </EstadoCarga>
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
