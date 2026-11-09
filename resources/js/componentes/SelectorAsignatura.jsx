import PropTypes from 'prop-types';
import { useState } from 'react';
import { Check, ChevronDown, Search } from 'lucide-react';
import { colorDeAsignatura, normalizar, plural } from '../utiles/texto';
import IconoAsignatura from './IconoAsignatura';
import usarDesplegable from './usarDesplegable';

// Con más asignaturas que estas, la lista lleva buscador.
const CON_BUSCADOR = 7;

function Detalle({ asignatura }) {
    const partes = [
        asignatura.codigo,
        asignatura.grupos?.length
            ? `${asignatura.grupos.length === 1 ? 'Grupo' : 'Grupos'} ${asignatura.grupos.join(', ')}`
            : null,
    ].filter(Boolean);
    // Sin inscritos no se puede armar el examen: se avisa en ámbar.
    const sinLista = asignatura.inscritos === 0;
    return (
        <span className="block truncate text-xs text-slate-600">
            {partes.join(' · ')}
            {asignatura.inscritos != null &&
                (sinLista ? (
                    <span className="font-medium text-warning-700"> · Sin inscritos</span>
                ) : (
                    ` · ${plural(asignatura.inscritos, 'inscrito')}`
                ))}
        </span>
    );
}

Detalle.propTypes = { asignatura: PropTypes.object.isRequired };

// Lista desplegable de asignaturas con su ícono de área, código, grupos e
// inscritos. El teclado y el cierre salen de usarDesplegable; sin buscador,
// al escribir una letra salta a la asignatura que empieza así.
export default function SelectorAsignatura({
    etiqueta,
    requerido,
    asignaturas,
    valor,
    onCambiar,
    disabled = false,
}) {
    const [filtro, setFiltro] = useState('');

    const elegida = asignaturas.find((a) => a.id === valor) ?? null;
    const buscador = asignaturas.length >= CON_BUSCADOR;
    const visibles = filtro
        ? asignaturas.filter((a) =>
              normalizar(`${a.nombre} ${a.codigo ?? ''}`).includes(normalizar(filtro))
          )
        : asignaturas;
    const { id, caja, lista, abierta, activa, setActiva, alternar, elegir, teclas, idDe } =
        usarDesplegable({
            opciones: visibles,
            elegida,
            alElegir: (a) => onCambiar(a.id),
            // Con buscador, las letras van al buscador.
            texto: buscador ? null : (a) => a.nombre,
            deshabilitada: disabled,
            alAbrir: () => setFiltro(''),
        });

    return (
        <div ref={caja} className="relative">
            {etiqueta && (
                <span
                    id={`${id}-etiqueta`}
                    className="mb-1.5 block text-sm font-medium text-slate-700"
                >
                    {etiqueta}
                    {requerido && (
                        <span className="ml-0.5 text-danger-600" aria-hidden="true">
                            *
                        </span>
                    )}
                </span>
            )}
            <button
                type="button"
                id={`${id}-boton`}
                disabled={disabled}
                aria-haspopup="listbox"
                aria-expanded={abierta}
                aria-labelledby={`${id}-etiqueta ${id}-boton`}
                aria-controls={abierta ? `${id}-lista` : undefined}
                aria-activedescendant={abierta && !buscador ? idDe(activa) : undefined}
                onClick={alternar}
                onKeyDown={teclas}
                className={`flex min-h-14 w-full items-center gap-3 rounded-lg border bg-white px-3 py-2 text-left focus:outline-none focus-visible:border-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/40 disabled:bg-slate-100 ${
                    abierta ? 'border-primary-500 ring-2 ring-primary-500/40' : 'border-slate-300'
                }`}
            >
                {elegida ? (
                    <>
                        <IconoAsignatura
                            nombre={elegida.nombre}
                            color={colorDeAsignatura(elegida.nombre)}
                            tamano="md"
                        />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-semibold text-slate-900">
                                {elegida.nombre}
                            </span>
                            <Detalle asignatura={elegida} />
                        </span>
                    </>
                ) : (
                    <span className="flex-1 text-sm text-slate-500">Elegir asignatura</span>
                )}
                <ChevronDown
                    className={`h-4 w-4 shrink-0 text-slate-500 transition-transform ${abierta ? 'rotate-180' : ''}`}
                    aria-hidden="true"
                />
            </button>

            {abierta && (
                <div className="absolute z-30 mt-1.5 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                    {buscador && (
                        <label className="flex items-center gap-2 border-b border-slate-200 px-3">
                            <Search
                                className="h-4 w-4 shrink-0 text-slate-500"
                                aria-hidden="true"
                            />
                            <input
                                autoFocus
                                aria-label="Buscar asignatura"
                                aria-controls={`${id}-lista`}
                                aria-activedescendant={visibles.length ? idDe(activa) : undefined}
                                placeholder="Nombre o código"
                                className="min-h-11 w-full bg-transparent text-sm focus:outline-none"
                                value={filtro}
                                onChange={(e) => {
                                    setFiltro(e.target.value);
                                    setActiva(0);
                                }}
                                onKeyDown={teclas}
                            />
                        </label>
                    )}
                    <ul
                        ref={lista}
                        id={`${id}-lista`}
                        role="listbox"
                        aria-labelledby={`${id}-etiqueta`}
                        className="max-h-80 overflow-y-auto p-1.5"
                    >
                        {visibles.map((a, i) => {
                            const marcada = a.id === valor;
                            return (
                                <li
                                    key={a.id}
                                    id={idDe(i)}
                                    role="option"
                                    aria-selected={marcada}
                                    onMouseDown={(e) => e.preventDefault()}
                                    onMouseEnter={() => setActiva(i)}
                                    onClick={() => elegir(a)}
                                    className={`flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 ${
                                        i === activa ? 'bg-slate-100' : ''
                                    } ${marcada ? 'bg-primary-50' : ''}`}
                                >
                                    <IconoAsignatura
                                        nombre={a.nombre}
                                        color={colorDeAsignatura(a.nombre)}
                                        tamano="md"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span
                                            className={`block truncate text-sm ${
                                                marcada
                                                    ? 'font-semibold text-primary-800'
                                                    : 'font-medium text-slate-900'
                                            }`}
                                        >
                                            {a.nombre}
                                        </span>
                                        <Detalle asignatura={a} />
                                    </span>
                                    {marcada && (
                                        <Check
                                            className="h-4 w-4 shrink-0 text-primary-600"
                                            aria-hidden="true"
                                        />
                                    )}
                                </li>
                            );
                        })}
                        {visibles.length === 0 && (
                            <li className="px-3 py-4 text-center text-sm text-slate-600">
                                Sin coincidencias
                            </li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}

SelectorAsignatura.propTypes = {
    etiqueta: PropTypes.node,
    requerido: PropTypes.bool,
    asignaturas: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            nombre: PropTypes.string.isRequired,
            codigo: PropTypes.string,
            grupos: PropTypes.arrayOf(PropTypes.string),
            inscritos: PropTypes.number,
        })
    ).isRequired,
    valor: PropTypes.number,
    onCambiar: PropTypes.func.isRequired,
    disabled: PropTypes.bool,
};
