import PropTypes from 'prop-types';
import { useEffect, useMemo, useState } from 'react';
import { ChevronRight } from 'lucide-react';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import Buscador from '../../componentes/Buscador';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import { usarFacultades } from '../../sesion/SesionContexto';

const POR_PAGINA = 15;
const GRUPOS_POR_PAGINA = 10;
const MILES = (n) => Number(n ?? 0).toLocaleString('es-BO');
const SINGULAR = (n, uno, varios) => (n === 1 ? uno : varios);

// Oferta académica: qué se dicta en el período. Solo consulta; la oferta se
// corrige volviendo a importar (pantalla Período), no editando aquí.
export default function Oferta() {
    const facultades = usarFacultades();

    const [periodo, setPeriodo] = useState('');
    const [facultad, setFacultad] = useState('');
    const [carrera, setCarrera] = useState('');
    const [nivel, setNivel] = useState('');
    const [expandida, setExpandida] = useState(null);

    const lista = usarPaginaServidor({
        porPagina: POR_PAGINA,
        filtros: { periodo, facultad, carrera, nivel },
    });
    const { ponerFiltros } = lista;

    // Sincronizar los filtros de la pantalla con los del hook de paginación.
    useEffect(() => {
        ponerFiltros({ periodo, facultad, carrera, nivel });
    }, [ponerFiltros, periodo, facultad, carrera, nivel]);

    const consulta = usarConsulta('/oferta/asignaturas', { parametros: lista.parametros });

    // Los períodos detectados: vigentes y no vigentes, con su estado.
    const periodos = usarConsulta('/periodos', { parametros: { por_pagina: 50 } });

    // Las carreras de la facultad elegida.
    const carreras = usarConsulta(facultad ? `/oferta/carreras?facultad=${facultad}` : null, {
        activa: Boolean(facultad),
    });

    // Los niveles que hay en lo filtrado.
    const niveles = usarConsulta('/oferta/niveles', {
        parametros: { periodo, facultad, carrera, buscar: lista.buscar },
    });

    // Al cambiar de facultad, se limpian carrera y nivel.
    useEffect(() => {
        setCarrera('');
        setNivel('');
    }, [facultad]);

    // Al cambiar de carrera o período, se limpia el nivel.
    useEffect(() => {
        setNivel('');
    }, [carrera, periodo]);

    const asignaturas = consulta.datos ?? [];
    const carreraElegida = (carreras.datos ?? []).find((x) => String(x.id) === carrera);

    const subtitulo = useMemo(() => {
        const partes = [];

        if (periodo) {
            const p = (periodos.datos ?? []).find((x) => String(x.id) === periodo);
            partes.push(p ? `Período ${p.codigo}` : 'Período');
        } else if (carreraElegida?.regimen === 'Anual') {
            partes.push('Período anual');
        } else {
            const vigentes = (periodos.datos ?? [])
                .filter((p) => p.estado === 'Vigente')
                .map((p) => p.codigo);
            partes.push(
                vigentes.length > 0 ? `Períodos ${vigentes.join(' y ')}` : 'Períodos vigentes'
            );
        }

        if (facultad) {
            const f = facultades.find((x) => x.clave === facultad);
            partes.push(f?.sigla ?? facultad.toUpperCase());
        }

        if (carreraElegida) partes.push(carreraElegida.nombre);

        return partes.join(' · ');
    }, [periodo, periodos.datos, carreraElegida, facultad, facultades]);

    return (
        <div className="space-y-6">
            <Encabezado titulo="Oferta académica" subtitulo={subtitulo}>
                <Insignia tono="neutro" punto>
                    Importado · solo lectura
                </Insignia>
            </Encabezado>

            <Tarjeta>
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <Seleccion
                        etiqueta="Período"
                        value={periodo}
                        onChange={(e) => setPeriodo(e.target.value)}
                    >
                        <option value="">Vigentes</option>
                        {(periodos.datos ?? []).map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.codigo} · {p.estado}
                            </option>
                        ))}
                    </Seleccion>

                    <Seleccion
                        etiqueta="Facultad"
                        value={facultad}
                        onChange={(e) => setFacultad(e.target.value)}
                    >
                        <option value="">Todas</option>
                        {facultades.map((f) => (
                            <option key={f.clave} value={f.clave}>
                                {f.sigla} · {f.nombre}
                            </option>
                        ))}
                    </Seleccion>

                    <Seleccion
                        etiqueta="Carrera"
                        value={carrera}
                        onChange={(e) => setCarrera(e.target.value)}
                        disabled={!facultad}
                    >
                        <option value="">Todas</option>
                        {(carreras.datos ?? []).map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.nombre}
                            </option>
                        ))}
                    </Seleccion>

                    <Seleccion
                        etiqueta="Nivel"
                        value={nivel}
                        onChange={(e) => setNivel(e.target.value)}
                    >
                        <option value="">Todos</option>
                        {(niveles.datos ?? []).map((n) => (
                            <option key={n} value={n}>
                                {n}
                            </option>
                        ))}
                    </Seleccion>

                    <div className="block col-span-2 lg:col-span-1">
                        <span className="mb-1.5 block text-sm font-medium text-slate-700">
                            Buscar
                        </span>
                        <Buscador
                            valor={lista.buscar}
                            onCambiar={lista.ponerBuscar}
                            placeholder="Asignatura, código o docente"
                        />
                    </div>
                </div>
            </Tarjeta>

            <Tarjeta
                sinRelleno
                titulo="Asignaturas"
                acciones={
                    consulta.meta?.total_grupos > 0 && (
                        <Insignia tono="neutro">
                            {MILES(consulta.meta.total_grupos)}{' '}
                            {SINGULAR(consulta.meta.total_grupos, 'grupo', 'grupos')}
                        </Insignia>
                    )
                }
            >
                <EstadoCarga
                    cargando={consulta.cargando}
                    error={consulta.error}
                    vacio={asignaturas.length === 0}
                    textoVacio="Sin resultados"
                    onReintentar={consulta.recargar}
                    filas={POR_PAGINA}
                >
                    <ul className="divide-y divide-slate-200">
                        {asignaturas.map((a) => (
                            <li key={a.id}>
                                <button
                                    type="button"
                                    aria-expanded={expandida === a.id}
                                    onClick={() => setExpandida(expandida === a.id ? null : a.id)}
                                    className="flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left hover:bg-slate-50"
                                >
                                    <ChevronRight
                                        className={`h-4 w-4 shrink-0 text-slate-500 transition-transform ${
                                            expandida === a.id ? 'rotate-90' : ''
                                        }`}
                                        aria-hidden="true"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium text-slate-800">
                                            {a.nombre}
                                        </span>
                                        <span className="block text-xs text-slate-600">
                                            {a.codigo} · {a.nivel ?? 'Sin nivel'}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-right text-xs text-slate-600">
                                        <span className="block">
                                            {MILES(a.grupos)}{' '}
                                            {SINGULAR(a.grupos, 'grupo', 'grupos')}
                                        </span>
                                        <span className="block">
                                            {MILES(a.inscritos)}{' '}
                                            {SINGULAR(a.inscritos, 'inscrito', 'inscritos')}
                                        </span>
                                    </span>
                                </button>

                                {expandida === a.id && (
                                    <GruposDeAsignatura
                                        asignaturaId={a.id}
                                        buscar={lista.buscar}
                                        periodo={periodo}
                                        facultad={facultad}
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                    <Paginacion
                        {...lista.paginacion(consulta.meta)}
                        unidad={['asignatura', 'asignaturas']}
                        className="border-t border-slate-200"
                    />
                </EstadoCarga>
            </Tarjeta>
        </div>
    );
}

// Los grupos de una asignatura, cargados al desplegar, de a 10.
function GruposDeAsignatura({ asignaturaId, buscar, periodo, facultad }) {
    const lista = usarPaginaServidor({
        porPagina: GRUPOS_POR_PAGINA,
        filtros: { asignaturaId, buscar, periodo, facultad },
    });

    const consulta = usarConsulta(`/oferta/asignaturas/${asignaturaId}/grupos`, {
        parametros: lista.parametros,
    });

    return (
        <div className="border-t border-slate-100 bg-slate-50 px-4 py-3">
            <EstadoCarga
                cargando={consulta.cargando}
                error={consulta.error}
                vacio={consulta.datos?.length === 0}
                textoVacio="Sin grupos"
                onReintentar={consulta.recargar}
                filas={2}
            >
                <ul className="space-y-2">
                    {(consulta.datos ?? []).map((g) => (
                        <li
                            key={g.id}
                            className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg bg-white px-3 py-2 text-xs"
                        >
                            <span className="w-8 shrink-0 font-semibold tabular-nums text-slate-900">
                                {g.codigo}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span
                                    className={`block truncate text-sm font-medium ${
                                        g.docente ? 'text-slate-800' : 'text-danger-700'
                                    }`}
                                >
                                    {g.docente ?? 'Por designar'}
                                </span>

                                <span className="block whitespace-normal text-slate-600">
                                    {(g.horarios ?? [])
                                        .map((h) => `${h.dia} ${h.hora} · ${h.aula ?? 'sin aula'}`)
                                        .join(' · ')}
                                </span>
                            </span>
                            <span className="shrink-0 tabular-nums text-slate-600">
                                {MILES(g.inscritos)} inscritos
                            </span>
                        </li>
                    ))}
                </ul>
                {(consulta.meta?.total ?? 0) > GRUPOS_POR_PAGINA && (
                    <Paginacion
                        {...lista.paginacion(consulta.meta)}
                        unidad={['grupo', 'grupos']}
                        className="mt-3 border-t border-slate-200"
                    />
                )}
            </EstadoCarga>
        </div>
    );
}

GruposDeAsignatura.propTypes = {
    asignaturaId: PropTypes.number.isRequired,
    buscar: PropTypes.string,
    periodo: PropTypes.string,
    facultad: PropTypes.string,
};

Oferta.propTypes = {};
