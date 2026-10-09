import PropTypes from 'prop-types';
import { useState } from 'react';
import Buscador from '../../componentes/Buscador';
import Chips from '../../componentes/Chips';
import { PuntoFacultad } from '../../componentes/FiltroFacultad';
import Insignia from '../../componentes/Insignia';
import Tarjeta from '../../componentes/Tarjeta';
import { colorDeAsignatura, normalizar } from '../../utiles/texto';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

export const FORMA_GRUPO = PropTypes.shape({
    id: PropTypes.number.isRequired,
    codigo: PropTypes.string.isRequired,
    asignatura: PropTypes.shape({
        codigo: PropTypes.string,
        nombre: PropTypes.string.isRequired,
    }).isRequired,
    nivel: PropTypes.string,
    facultad: PropTypes.string,
    horarios: PropTypes.arrayOf(
        PropTypes.shape({
            dia: PropTypes.string,
            hora: PropTypes.string,
            aula: PropTypes.string,
        })
    ),
    inscritos: PropTypes.number,
    con_lista: PropTypes.bool,
});

// «LU 06:45-08:15 (691C) · MI 06:45-08:15 (690D)».
export function horariosDe(grupo) {
    return (grupo.horarios ?? [])
        .map((h) => [h.dia, h.hora, h.aula ? `(${h.aula})` : null].filter(Boolean).join(' '))
        .join(' · ');
}

function EstadoDeLista({ grupo }) {
    return grupo.con_lista ? (
        <Insignia tono="exito">Lista cargada · {grupo.inscritos}</Insignia>
    ) : (
        <Insignia tono="advertencia">Sin lista</Insignia>
    );
}

EstadoDeLista.propTypes = { grupo: FORMA_GRUPO.isRequired };

// Con pocos grupos, una tarjeta por grupo.
export function TarjetasDeGrupos({ grupos, elegido, onElegir }) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {grupos.map((g) => {
                const activo = elegido === g.id;
                return (
                    <button
                        key={g.id}
                        type="button"
                        aria-pressed={activo}
                        onClick={() => onElegir(g.id)}
                        className={`min-w-0 rounded-xl border bg-white p-4 text-left ${FOCO} ${
                            activo
                                ? 'border-primary-500 ring-2 ring-primary-500/30'
                                : 'border-slate-200 hover:bg-slate-50'
                        }`}
                    >
                        <span className="mb-1 flex flex-wrap items-center justify-between gap-2">
                            {g.facultad ? <PuntoFacultad sigla={g.facultad} /> : <span />}
                            <EstadoDeLista grupo={g} />
                        </span>
                        <span className="block break-words text-sm font-semibold text-slate-900">
                            {g.asignatura.nombre}
                        </span>
                        <span className="block text-xs text-slate-600">
                            {[`Grupo ${g.codigo}`, g.asignatura.codigo, g.nivel]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                        <span className="mt-2 block break-words text-xs text-slate-600">
                            {horariosDe(g)}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

TarjetasDeGrupos.propTypes = {
    grupos: PropTypes.arrayOf(FORMA_GRUPO).isRequired,
    elegido: PropTypes.number,
    onElegir: PropTypes.func.isRequired,
};

// Con muchos grupos, una lista por asignatura con búsqueda y el filtro
// «Sin lista». La lista se desplaza dentro de su tarjeta.
export default function SelectorDeGrupos({ grupos, elegido, onElegir }) {
    const [buscado, setBuscado] = useState('');
    const [filtro, setFiltro] = useState(null);

    const texto = normalizar(buscado.trim());
    const filtrados = grupos.filter(
        (g) =>
            (filtro === null || !g.con_lista) &&
            (!texto ||
                normalizar(
                    `${g.asignatura.nombre} ${g.asignatura.codigo ?? ''} grupo ${g.codigo}`
                ).includes(texto))
    );
    const asignaturas = [...new Set(filtrados.map((g) => g.asignatura.nombre))];

    return (
        <Tarjeta sinRelleno className="lg:sticky lg:top-0">
            <div className="space-y-3 border-b border-slate-200 p-3">
                <Buscador
                    valor={buscado}
                    onCambiar={setBuscado}
                    placeholder="Asignatura o grupo"
                    etiqueta="Buscar grupo"
                />
                <Chips
                    etiqueta="Estado de la lista"
                    valor={filtro}
                    onCambiar={setFiltro}
                    opciones={[
                        { valor: null, etiqueta: 'Todos', conteo: grupos.length },
                        {
                            valor: 'sin_lista',
                            etiqueta: 'Sin lista',
                            conteo: grupos.filter((g) => !g.con_lista).length,
                        },
                    ]}
                />
            </div>
            <div
                className="max-h-72 overflow-y-auto lg:max-h-[calc(100dvh-16rem)]"
                role="group"
                aria-label="Grupos"
            >
                {asignaturas.map((a) => (
                    <div key={a}>
                        <h2 className="sticky top-0 flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">
                            <span
                                className="h-2 w-2 shrink-0 rounded-full"
                                style={{ backgroundColor: colorDeAsignatura(a) }}
                            />
                            <span className="min-w-0 truncate">{a}</span>
                        </h2>
                        <ul className="divide-y divide-slate-100">
                            {filtrados
                                .filter((g) => g.asignatura.nombre === a)
                                .map((g) => {
                                    const activo = elegido === g.id;
                                    return (
                                        <li key={g.id}>
                                            <button
                                                type="button"
                                                aria-pressed={activo}
                                                onClick={() => onElegir(g.id)}
                                                className={`flex min-h-12 w-full items-center gap-2 px-3 py-2 text-left focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600 ${
                                                    activo
                                                        ? 'bg-primary-50 shadow-[inset_3px_0_0_var(--color-primary-600)]'
                                                        : 'hover:bg-slate-50'
                                                }`}
                                            >
                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-sm font-medium text-slate-900">
                                                        Grupo {g.codigo}
                                                    </span>
                                                    <span className="block truncate text-xs text-slate-600">
                                                        {(g.horarios ?? [])
                                                            .map(
                                                                (h) =>
                                                                    `${h.dia} ${String(h.hora ?? '').split('-')[0]}`
                                                            )
                                                            .join(' · ')}
                                                    </span>
                                                </span>
                                                <span className="shrink-0">
                                                    <EstadoDeLista grupo={g} />
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                        </ul>
                    </div>
                ))}
                {filtrados.length === 0 && (
                    <p className="py-8 text-center text-sm text-slate-600">Sin resultados</p>
                )}
            </div>
        </Tarjeta>
    );
}

SelectorDeGrupos.propTypes = {
    grupos: PropTypes.arrayOf(FORMA_GRUPO).isRequired,
    elegido: PropTypes.number,
    onElegir: PropTypes.func.isRequired,
};
