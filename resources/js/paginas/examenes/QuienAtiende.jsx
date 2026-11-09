import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { KeyRound, Plus, Shuffle, UserCheck, UserPlus, X } from 'lucide-react';
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
function AuxiliarNuevo({ onAgregado, onAviso }) {
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
            const { data } = await api.post('/auxiliares', {
                nombre: nombre.trim(),
                codigo_universitario: codigo.trim(),
            });
            onAgregado(data.data);
            setNombre('');
            setCodigo('');
            setVuelta((n) => n + 1);
            onAviso(sinPunto(data.message ?? 'Auxiliar agregado'));
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
                    placeholder="Apellidos y nombres"
                    className="min-w-0"
                    value={nombre}
                    onChange={(e) => {
                        setNombre(e.target.value);
                        quitarError('nombre');
                    }}
                    error={errores.nombre}
                />
                <Campo
                    etiqueta="Correo institucional"
                    requerido
                    sufijo="@est.umss.edu"
                    placeholder="Código SIS"
                    inputMode="numeric"
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
    const docentes = sinRepetir([yo, ...conocidos.filter((p) => p.es_docente)]);
    const otros = buscando
        ? (hallados.datos ?? []).filter((p) => !auxiliares.some((a) => a.id === p.id))
        : [];
    const nombreDe = (id) =>
        [...docentes, ...auxiliares].find((p) => p.id === id)?.nombre ?? String(id);

    const de = (aulaId) => personal[aulaId] ?? { encargado: yo.id, apoyo: [] };
    const fijar = (aulaId, cambio) =>
        onPersonal({ ...personal, [aulaId]: { ...de(aulaId), ...cambio } });
    // Un auxiliar a cargo de cada aula; la primera y las que sobran quedan
    // con el docente.
    const repartir = () =>
        onPersonal(
            Object.fromEntries(
                aulas.map((a, i) => [
                    a.id,
                    { encargado: i === 0 ? yo.id : (auxiliares[i - 1]?.id ?? yo.id), apoyo: [] },
                ])
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

    return (
        <Tarjeta
            titulo="4. Quién atiende"
            icono={UserCheck}
            acciones={
                <>
                    <Insignia tono="primario">{plural(aulas.length, 'aula')}</Insignia>
                    {aulas.length > 2 && auxiliares.length > 0 && (
                        <Boton
                            type="button"
                            variante="secundario"
                            tamano="chico"
                            onClick={repartir}
                        >
                            <Shuffle className="h-4 w-4" /> Repartir
                        </Boton>
                    )}
                </>
            }
        >
            {sobrecargados.length > 0 && (
                <p
                    role="status"
                    className="mb-3 rounded-lg bg-warning-50 px-3 py-2 text-sm font-medium text-warning-800"
                >
                    {sobrecargados.join(' · ')}
                </p>
            )}
            {aulas.length > 0 ? (
                <ul
                    className={`grid gap-2 ${aulas.length > 4 ? 'lg:grid-cols-2' : ''}`}
                    aria-label="Aulas del examen"
                >
                    {aulas.map((a, i) => {
                        const { encargado, apoyo } = de(a.id);
                        const libres = auxiliares.filter(
                            (x) => !apoyo.includes(x.id) && x.id !== encargado
                        );
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
                                <div className="mt-1 grid grid-cols-2 gap-3">
                                    <Seleccion
                                        etiqueta="A cargo"
                                        className="min-w-0"
                                        value={encargado}
                                        onChange={(e) => {
                                            const id = Number(e.target.value);
                                            fijar(a.id, {
                                                encargado: id,
                                                apoyo: apoyo.filter((x) => x !== id),
                                            });
                                        }}
                                    >
                                        {docentes.map((x) => (
                                            <option key={x.id} value={x.id}>
                                                {x.nombre}
                                            </option>
                                        ))}
                                        {auxiliares.map((x) => (
                                            <option key={x.id} value={x.id}>
                                                {x.nombre}
                                            </option>
                                        ))}
                                    </Seleccion>
                                    <Seleccion
                                        etiqueta="Apoyo"
                                        className="min-w-0"
                                        value=""
                                        disabled={libres.length === 0}
                                        onChange={(e) =>
                                            e.target.value &&
                                            fijar(a.id, {
                                                apoyo: [...apoyo, Number(e.target.value)],
                                            })
                                        }
                                    >
                                        <option value="">
                                            {apoyo.length ? 'Agregar' : 'Nadie'}
                                        </option>
                                        {libres.map((x) => (
                                            <option key={x.id} value={x.id}>
                                                {x.nombre}
                                            </option>
                                        ))}
                                    </Seleccion>
                                </div>
                                {apoyo.length > 0 && (
                                    <ul
                                        className="mt-2 flex flex-wrap justify-end gap-2"
                                        aria-label={`Apoyo del aula ${a.nombre}`}
                                    >
                                        {apoyo.map((id) => (
                                            <li
                                                key={id}
                                                className="inline-flex min-w-0 max-w-full items-center rounded-full bg-slate-100 pl-3 text-sm text-slate-800"
                                            >
                                                <span className="truncate">{nombreDe(id)}</span>
                                                <button
                                                    type="button"
                                                    aria-label={`Quitar a ${nombreDe(id)}`}
                                                    onClick={() =>
                                                        fijar(a.id, {
                                                            apoyo: apoyo.filter((y) => y !== id),
                                                        })
                                                    }
                                                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-600 hover:bg-slate-200 ${FOCO}`}
                                                >
                                                    <X className="h-4 w-4" />
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {error && (
                                    <p role="alert" className={ERROR}>
                                        {error}
                                    </p>
                                )}
                            </li>
                        );
                    })}
                </ul>
            ) : (
                <p className="rounded-lg border border-slate-200 py-4 text-center text-sm text-slate-600">
                    Sin aulas
                </p>
            )}

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
                                className={`inline-flex min-h-10 min-w-0 max-w-full items-center rounded-full border border-slate-200 bg-white pl-3 text-sm text-slate-800 ${quitable(x.id) ? '' : 'pr-3'}`}
                            >
                                <span className="truncate">{x.nombre}</span>
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
                                    onClick={() => onSumar(x)}
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
                <AuxiliarNuevo
                    onAgregado={(persona) => {
                        onSumar(persona);
                        propios.recargar();
                    }}
                    onAviso={onAviso}
                />
            </div>
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
