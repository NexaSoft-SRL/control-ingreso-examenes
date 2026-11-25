import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { Check, ChevronDown, KeyRound, Plus, Shuffle, UserCheck, UserPlus, X } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import Insignia from '../../componentes/Insignia';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import { errorDeNombre, plural, ubicacionSin } from '../../utiles/texto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const ERROR = 'mt-1.5 text-sm text-danger-600';
// La búsqueda entre todos los auxiliares pide al menos tres letras (ruta 50).
const MINIMO_BUSQUEDA = 3;
const CODIGO = /^\d{5,10}$/;

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');
const sinRepetir = (personas) => [...new Map(personas.map((p) => [p.id, p])).values()];

const FORMA_PERSONA = PropTypes.shape({
    id: PropTypes.number.isRequired,
    nombre: PropTypes.string.isRequired,
});

// Alta de un auxiliar sin salir del asistente (ruta 51): se crea su cuenta y
// la contraseña temporal va a su correo institucional.
export function AuxiliarNuevo({ onAgregado, onAviso }) {
    const [nombre, setNombre] = useState('');
    const [codigo, setCodigo] = useState('');
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);
    // Cambia al agregar: los campos vuelven a nacer sin su marca de «tocado».
    const [vuelta, setVuelta] = useState(0);

    const agregar = async () => {
        const faltas = {};
        const errorNombre = errorDeNombre(nombre, 3);
        if (errorNombre) faltas.nombre = errorNombre;
        if (!codigo.trim()) faltas.codigo_universitario = 'Obligatorio';
        else if (!CODIGO.test(codigo.trim())) faltas.codigo_universitario = 'Entre 5 y 10 dígitos';
        setErrores(faltas);
        if (Object.keys(faltas).length > 0) return;

        setEnviando(true);
        try {
            const correo = `${codigo.trim()}@est.umss.edu`;
            const { data } = await api.post('/auxiliares', {
                nombre: nombre.trim(),
                codigo_universitario: codigo.trim(),
            });
            onAgregado(data.data);
            setNombre('');
            setCodigo('');
            setVuelta((n) => n + 1);
            // Quien lo agrega sabe con qué usuario entra y a dónde llegó su
            // contraseña temporal.
            onAviso(
                data.data?.usuario
                    ? `Cuenta creada · usuario ${data.data.usuario} · la contraseña temporal se envió a ${correo}`
                    : sinPunto(data.message ?? 'Auxiliar agregado')
            );
        } catch (fallo) {
            const existente = fallo?.response?.data?.data;
            if (estadoDe(fallo) === 409 && existente?.id) {
                // Ya tiene cuenta: queda en la lista para asignarlo.
                onAgregado(existente);
                onAviso(sinPunto(mensajeDe(fallo, 'Auxiliar ya registrado')), 'error');
            } else if (Object.keys(erroresDe(fallo)).length > 0) {
                setErrores(erroresDe(fallo));
            } else {
                onAviso(sinPunto(mensajeDe(fallo, 'No se pudo agregar')), 'error');
            }
        } finally {
            setEnviando(false);
        }
    };

    // Enter agrega, como el botón.
    const alEnter = (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        agregar();
    };

    const quitarError = (campo) =>
        setErrores((previos) =>
            Object.fromEntries(Object.entries(previos).filter(([c]) => c !== campo))
        );

    return (
        <>
            <h3 className="mb-2 text-sm font-semibold text-slate-800">Auxiliar nuevo</h3>
            <div key={vuelta} className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-start">
                <Campo
                    etiqueta="Nombre completo"
                    requerido
                    formato="letras"
                    maxLength={100}
                    placeholder="Apellidos y nombres"
                    onKeyDown={alEnter}
                    className="min-w-0"
                    value={nombre}
                    onChange={(e) => {
                        setNombre(e.target.value);
                        quitarError('nombre');
                    }}
                    error={errores.nombre}
                />
                <Campo
                    etiqueta="Código SIS del auxiliar"
                    requerido
                    sufijo="@est.umss.edu"
                    placeholder="201512345"
                    formato="digitos"
                    maxLength={10}
                    onKeyDown={alEnter}
                    className="min-w-0"
                    value={codigo}
                    onChange={(e) => {
                        setCodigo(e.target.value);
                        quitarError('codigo_universitario');
                    }}
                    error={errores.codigo_universitario}
                    validar={(v) => (CODIGO.test(v.trim()) ? null : 'Entre 5 y 10 dígitos')}
                />
                <Boton
                    type="button"
                    variante="secundario"
                    className="min-h-11 sm:mt-[1.625rem]"
                    cargando={enviando}
                    onClick={agregar}
                >
                    <UserPlus className="h-4 w-4" /> Agregar
                </Boton>
            </div>
        </>
    );
}

AuxiliarNuevo.propTypes = {
    onAgregado: PropTypes.func.isRequired,
    onAviso: PropTypes.func.isRequired,
};

// Paso «Quién atiende» (HU-18): por cada aula, quién queda a cargo (el
// docente o un auxiliar) y quiénes apoyan; el código de acceso del aula; y
// los auxiliares del docente, con búsqueda entre los demás, alta de uno nuevo
// y baja de la lista. `conocidos` trae a quienes ya atienden el examen y a los
// docentes de sus grupos (`es_docente`), que se ofrecen «a cargo».
// `personal` es `{ [aulaId]: { encargado, apoyo: [id] } }`.
export default function QuienAtiende({
    aulas,
    personal,
    onPersonal,
    yo,
    conocidos = [],
    sumados,
    onSumar,
    onQuitar,
    errores = {},
    conCodigo = [],
    usanCodigo = [],
    onCodigo,
    onAviso,
    children,
}) {
    const propios = usarConsulta('/auxiliares');
    const [buscar, setBuscar] = useState('');
    const [buscado, setBuscado] = useState('');
    useEffect(() => {
        const espera = setTimeout(() => setBuscado(buscar.trim()), 300);
        return () => clearTimeout(espera);
    }, [buscar]);
    const buscando = buscado.length >= MINIMO_BUSQUEDA;
    const hallados = usarConsulta('/auxiliares', {
        parametros: { buscar: buscado },
        activa: buscando,
    });

    // Los de la cuenta, los sumados aquí y los que ya atienden el examen.
    const auxiliares = sinRepetir([
        ...(propios.datos ?? []),
        ...sumados,
        ...conocidos.filter((p) => !p.es_docente && p.id !== yo.id),
    ]).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
    // Un docente con varios grupos está pendiente solo si todos lo están.
    const docentes = sinRepetir([
        yo,
        ...conocidos
            .filter((p) => p.es_docente)
            .map((p) => ({
                ...p,
                pendiente: conocidos
                    .filter((q) => q.es_docente && q.id === p.id)
                    .every((q) => q.pendiente),
            })),
    ]);
    const otros = buscando
        ? (hallados.datos ?? []).filter((p) => !auxiliares.some((a) => a.id === p.id))
        : [];
    const nombreDe = (id) =>
        [...docentes, ...auxiliares].find((p) => p.id === id)?.nombre ?? String(id);

    const de = (aulaId) => personal[aulaId] ?? { encargado: yo.id, apoyo: [] };
    // El equipo del examen: quienes están a cargo o de apoyo en alguna aula, más
    // los docentes de sus grupos. Cualquiera del equipo atiende cualquier aula
    // y todo lo que registra queda con su nombre.
    const idsDelEquipo = [
        ...new Set(
            aulas
                .flatMap((a) => [de(a.id).encargado, ...de(a.id).apoyo])
                .filter((id) => id !== yo.id)
        ),
    ];
    const delEquipo = (id) => idsDelEquipo.includes(id);
    // Sumar a alguien deja su apoyo en todas las aulas; quitarlo lo saca de todas.
    const sumarAlEquipo = (id) =>
        onPersonal(
            Object.fromEntries(
                aulas.map((a) => {
                    const actual = de(a.id);
                    return [
                        a.id,
                        actual.encargado === id || actual.apoyo.includes(id)
                            ? actual
                            : { ...actual, apoyo: [...actual.apoyo, id] },
                    ];
                })
            )
        );
    const sacarDelEquipo = (id) =>
        onPersonal(
            Object.fromEntries(
                aulas.map((a) => {
                    const actual = de(a.id);
                    return [
                        a.id,
                        {
                            encargado: actual.encargado === id ? yo.id : actual.encargado,
                            apoyo: actual.apoyo.filter((x) => x !== id),
                        },
                    ];
                })
            )
        );
    const [ajustando, setAjustando] = useState(
        () => conCodigo.length > 0 || Object.keys(errores).some((c) => c.startsWith('aulas.'))
    );
    const fijar = (aulaId, cambio) =>
        onPersonal({ ...personal, [aulaId]: { ...de(aulaId), ...cambio } });
    // Un auxiliar a cargo de cada aula; la primera y las que sobran quedan
    // con el docente.
    const repartir = () =>
        onPersonal(
            Object.fromEntries(
                aulas.map((a, i) => {
                    const encargado = i === 0 ? yo.id : (auxiliares[i - 1]?.id ?? yo.id);
                    return [
                        a.id,
                        { encargado, apoyo: idsDelEquipo.filter((x) => x !== encargado) },
                    ];
                })
            )
        );
    const errorDe = (i) => Object.entries(errores).find(([c]) => c.startsWith(`aulas.${i}.`))?.[1];

    // Nadie atiende más de dos aulas a la vez: el paso queda pendiente
    // (AvanceDeExamen::AULAS_POR_PERSONA) y aquí se dice quién se pasa.
    const cargaPorPersona = aulas.reduce((cuenta, a) => {
        const id = de(a.id).encargado;
        return { ...cuenta, [id]: (cuenta[id] ?? 0) + 1 };
    }, {});
    const sobrecargados = Object.entries(cargaPorPersona)
        .filter(([, n]) => n > 2)
        .map(([id, n]) => `${nombreDe(Number(id))} · ${plural(n, 'aula')} a la vez`);

    // Se quitan los de la cuenta (en el servidor) y los sumados en la visita.
    const [porQuitar, setPorQuitar] = useState(null);
    const [quitando, setQuitando] = useState(false);
    const sumarNuevo = (persona) => {
        onSumar(persona);
        propios.recargar();
        sumarAlEquipo(persona.id);
    };
    const esDeLaCuenta = (id) => (propios.datos ?? []).some((p) => p.id === id);
    const quitable = (id) => esDeLaCuenta(id) || sumados.some((p) => p.id === id);
    const quitar = async () => {
        setQuitando(true);
        try {
            if (esDeLaCuenta(porQuitar.id)) await api.delete(`/auxiliares/${porQuitar.id}`);
            onQuitar(porQuitar.id);
            propios.recargar();
            onAviso('Auxiliar quitado');
        } catch (fallo) {
            if (estadoDe(fallo) === 404) {
                // Ya no estaba en la lista.
                onQuitar(porQuitar.id);
                propios.recargar();
            } else {
                onAviso(sinPunto(mensajeDe(fallo, 'No se pudo quitar')), 'error');
            }
        } finally {
            setQuitando(false);
            setPorQuitar(null);
        }
    };

    // Un rechazo en un aula, o un examen que ya tiene códigos de aula, abre el
    // ajuste y lo deja abierto.
    const hayErrorDeAula = Object.keys(errores).some((campo) => campo.startsWith('aulas.'));
    const hayCodigos = conCodigo.length > 0;
    useEffect(() => {
        if (hayErrorDeAula || hayCodigos) setAjustando(true);
    }, [hayErrorDeAula, hayCodigos]);
    const abiertoAjuste = ajustando;
    const miembros = [
        ...docentes.map((d) => ({ ...d, esDocente: true, pendiente: Boolean(d.pendiente) })),
        ...idsDelEquipo
            .filter((id) => !docentes.some((d) => d.id === id))
            .map((id) => ({ id, nombre: nombreDe(id), esDocente: false })),
    ];

    return (
        <Tarjeta
            titulo="4. Equipo"
            icono={UserCheck}
            acciones={<Insignia tono="primario">{plural(miembros.length, 'persona')}</Insignia>}
        >
            {sobrecargados.length > 0 && (
                <p
                    role="status"
                    className="mb-3 rounded-lg bg-warning-50 px-3 py-2 text-sm font-medium text-warning-800"
                >
                    {sobrecargados.join(' · ')}
                </p>
            )}
            <section
                aria-label="Equipo del examen"
                className="rounded-lg border border-slate-200 p-4"
            >
                <h3 className="mb-2 text-sm font-semibold text-slate-800">
                    Equipo · {miembros.length}
                </h3>
                <ul className="flex flex-wrap gap-2" aria-label="Equipo">
                    {miembros.map((m) => (
                        <li
                            key={m.id}
                            className={`inline-flex min-h-10 min-w-0 max-w-full items-center rounded-full bg-primary-50 pl-3 text-sm font-medium text-primary-900 ${m.esDocente ? 'pr-3' : ''}`}
                        >
                            <span className="truncate">{m.nombre}</span>{' '}
                            <span className="ml-1.5 shrink-0 text-xs font-normal text-primary-700">
                                ·{' '}
                                {m.esDocente
                                    ? m.pendiente
                                        ? 'docente · invitación pendiente'
                                        : 'docente'
                                    : 'auxiliar'}
                            </span>
                            {!m.esDocente && (
                                <button
                                    type="button"
                                    aria-label={`Sacar a ${m.nombre} del equipo`}
                                    onClick={() => sacarDelEquipo(m.id)}
                                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-primary-700 hover:bg-primary-100 ${FOCO}`}
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            </section>

            <div className="mt-4 rounded-lg bg-slate-50 p-4">
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h3 className="text-sm font-semibold text-slate-800">
                        Auxiliares · {auxiliares.length}
                    </h3>
                    <Buscador
                        valor={buscar}
                        onCambiar={setBuscar}
                        placeholder="Buscar auxiliar"
                        className="w-full sm:w-64"
                    />
                </div>
                {propios.error && auxiliares.length === 0 ? (
                    <p role="alert" className="mb-4 text-sm text-danger-600">
                        No se pudo cargar{' '}
                        <Boton
                            type="button"
                            variante="enlace"
                            tamano="chico"
                            onClick={propios.recargar}
                        >
                            Reintentar
                        </Boton>
                    </p>
                ) : (
                    <ul className="mb-4 flex flex-wrap gap-2" aria-label="Auxiliares">
                        {auxiliares.map((x) => (
                            <li
                                key={x.id}
                                className={`inline-flex min-h-10 min-w-0 max-w-full items-center rounded-full border bg-white pl-1 text-sm text-slate-800 ${delEquipo(x.id) ? 'border-primary-500' : 'border-slate-200'} ${quitable(x.id) ? '' : 'pr-3'}`}
                            >
                                <button
                                    type="button"
                                    aria-pressed={delEquipo(x.id)}
                                    aria-label={
                                        delEquipo(x.id)
                                            ? `Sacar a ${x.nombre} del equipo`
                                            : `Sumar a ${x.nombre} al equipo`
                                    }
                                    onClick={() =>
                                        delEquipo(x.id) ? sacarDelEquipo(x.id) : sumarAlEquipo(x.id)
                                    }
                                    className={`flex min-h-10 min-w-0 items-center gap-1.5 rounded-full px-2 hover:bg-slate-50 ${FOCO}`}
                                >
                                    {delEquipo(x.id) ? (
                                        <Check className="h-4 w-4 shrink-0 text-primary-700" />
                                    ) : (
                                        <Plus className="h-4 w-4 shrink-0 text-slate-500" />
                                    )}
                                    <span className="truncate">{x.nombre}</span>{' '}
                                    {x.usuario && (
                                        <span className="ml-1 shrink-0 text-xs text-slate-500">
                                            · usuario: {x.usuario}
                                        </span>
                                    )}
                                </button>
                                {quitable(x.id) && (
                                    <button
                                        type="button"
                                        aria-label={`Quitar a ${x.nombre} de la lista`}
                                        title="Quitar de la lista"
                                        onClick={() => setPorQuitar(x)}
                                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100 ${FOCO}`}
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                )}
                            </li>
                        ))}
                        {otros.map((x) => (
                            <li key={x.id} className="min-w-0 max-w-full">
                                <button
                                    type="button"
                                    aria-label={`Sumar a ${x.nombre}`}
                                    onClick={() => {
                                        onSumar(x);
                                        sumarAlEquipo(x.id);
                                    }}
                                    className={`inline-flex min-h-10 max-w-full items-center gap-1.5 rounded-full border border-dashed border-primary-500 bg-white px-3 text-sm font-medium text-primary-700 hover:bg-primary-50 ${FOCO}`}
                                >
                                    <Plus className="h-4 w-4 shrink-0" />
                                    <span className="truncate">{x.nombre}</span>
                                </button>
                            </li>
                        ))}
                        {auxiliares.length === 0 && otros.length === 0 && !propios.cargando && (
                            <li className="text-sm text-slate-600">Sin auxiliares</li>
                        )}
                    </ul>
                )}
                <AuxiliarNuevo onAgregado={sumarNuevo} onAviso={onAviso} />
            </div>

            {aulas.length > 0 && (
                <div className="mt-4">
                    <button
                        type="button"
                        aria-expanded={abiertoAjuste}
                        onClick={() => setAjustando((previo) => !previo)}
                        className={`-ml-1 flex min-h-10 items-center gap-1 rounded-lg px-1 text-sm font-semibold text-slate-700 hover:text-slate-900 ${FOCO}`}
                    >
                        <ChevronDown
                            className={`h-4 w-4 transition-transform ${abiertoAjuste ? '' : '-rotate-90'}`}
                            aria-hidden="true"
                        />
                        Ajustar por aula
                    </button>
                    {abiertoAjuste && (
                        <>
                            {aulas.length > 2 && auxiliares.length > 0 && (
                                <div className="mb-2 flex justify-end">
                                    <Boton
                                        type="button"
                                        variante="secundario"
                                        tamano="chico"
                                        onClick={repartir}
                                    >
                                        <Shuffle className="h-4 w-4" /> Repartir
                                    </Boton>
                                </div>
                            )}
                            <ul
                                className={`mt-1 grid gap-2 ${aulas.length > 4 ? 'lg:grid-cols-2' : ''}`}
                                aria-label="Aulas del examen"
                            >
                                {aulas.map((a, i) => {
                                    const { encargado } = de(a.id);
                                    const error = errorDe(i);
                                    return (
                                        <li
                                            key={a.id}
                                            aria-label={`Aula ${a.nombre}`}
                                            className="min-w-0 rounded-lg border border-slate-200 p-3"
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="min-w-0 truncate text-sm font-semibold text-slate-800">
                                                    Aula {a.nombre}
                                                    {ubicacionSin(a.nombre, a.ubicacion) && (
                                                        <span className="font-normal text-slate-600">
                                                            {' '}
                                                            · {ubicacionSin(a.nombre, a.ubicacion)}
                                                        </span>
                                                    )}
                                                </p>
                                                <button
                                                    type="button"
                                                    aria-label={`Código de acceso del aula ${a.nombre}`}
                                                    title="Código de acceso"
                                                    disabled={!conCodigo.includes(a.id)}
                                                    onClick={() => onCodigo(a)}
                                                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg hover:bg-slate-100 disabled:text-slate-300 disabled:hover:bg-transparent ${usanCodigo.includes(a.id) ? 'text-primary-700' : 'text-slate-600'} ${FOCO}`}
                                                >
                                                    <KeyRound className="h-4 w-4" />
                                                </button>
                                            </div>
                                            <Seleccion
                                                etiqueta="A cargo"
                                                className="mt-1 min-w-0"
                                                value={encargado}
                                                onChange={(e) => {
                                                    const id = Number(e.target.value);
                                                    const { apoyo, encargado: antes } = de(a.id);
                                                    // Quien deja de estar a cargo sigue en el equipo.
                                                    fijar(a.id, {
                                                        encargado: id,
                                                        apoyo: [
                                                            ...apoyo.filter((x) => x !== id),
                                                            ...(antes !== yo.id && antes !== id
                                                                ? [antes]
                                                                : []),
                                                        ],
                                                    });
                                                }}
                                            >
                                                {miembros.map((x) => (
                                                    <option key={x.id} value={x.id}>
                                                        {x.nombre}
                                                    </option>
                                                ))}
                                            </Seleccion>
                                            {error && (
                                                <p role="alert" className={ERROR}>
                                                    {error}
                                                </p>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </>
                    )}
                </div>
            )}
            {aulas.length === 0 && (
                <p className="mt-4 rounded-lg border border-slate-200 py-4 text-center text-sm text-slate-600">
                    Sin aulas
                </p>
            )}
            {children}
            <Dialogo
                titulo="Quitar auxiliar"
                abierto={porQuitar !== null}
                onCerrar={() => setPorQuitar(null)}
                acciones={
                    <>
                        <Boton
                            type="button"
                            variante="secundario"
                            onClick={() => setPorQuitar(null)}
                        >
                            Cancelar
                        </Boton>
                        <Boton
                            type="button"
                            variante="peligro"
                            disabled={quitando}
                            onClick={quitar}
                        >
                            Quitar
                        </Boton>
                    </>
                }
            >
                {porQuitar && (
                    <p className="break-words text-sm font-medium text-slate-800">
                        {porQuitar.nombre}
                    </p>
                )}
            </Dialogo>
        </Tarjeta>
    );
}

QuienAtiende.propTypes = {
    aulas: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            nombre: PropTypes.string.isRequired,
            ubicacion: PropTypes.string,
        })
    ).isRequired,
    personal: PropTypes.objectOf(
        PropTypes.shape({
            encargado: PropTypes.number.isRequired,
            apoyo: PropTypes.arrayOf(PropTypes.number).isRequired,
        })
    ).isRequired,
    onPersonal: PropTypes.func.isRequired,
    yo: FORMA_PERSONA.isRequired,
    conocidos: PropTypes.arrayOf(FORMA_PERSONA),
    sumados: PropTypes.arrayOf(FORMA_PERSONA).isRequired,
    onSumar: PropTypes.func.isRequired,
    onQuitar: PropTypes.func.isRequired,
    errores: PropTypes.objectOf(PropTypes.string),
    conCodigo: PropTypes.arrayOf(PropTypes.number),
    usanCodigo: PropTypes.arrayOf(PropTypes.number),
    onCodigo: PropTypes.func.isRequired,
    onAviso: PropTypes.func.isRequired,
    children: PropTypes.node,
};
