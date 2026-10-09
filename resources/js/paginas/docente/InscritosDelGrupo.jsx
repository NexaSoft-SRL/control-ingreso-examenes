import PropTypes from 'prop-types';
import { useState } from 'react';
import { Download, Upload } from 'lucide-react';
import descargar from '../../api/descargar';
import { estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Tarjeta from '../../componentes/Tarjeta';
import CargaDeLista from './CargaDeLista';
import ResumenDeCarga from './ResumenDeCarga';
import { FORMA_GRUPO, horariosDe } from './SelectorDeGrupos';

const tonoDe = (origen) => (origen === 'Docente' ? 'neutro' : 'exito');

// La lista de inscritos del grupo elegido: carga desde archivo, resumen de
// la última carga, búsqueda, páginas de 25 y descarga.
export default function InscritosDelGrupo({
    grupo,
    resumen = null,
    onCargada,
    onCerrarResumen,
    onAvisar,
}) {
    const [eligiendo, setEligiendo] = useState(false);
    const [descargando, setDescargando] = useState(false);
    const lista = usarPaginaServidor({ porPagina: 25 });
    const hayLista = Boolean(grupo.con_lista);

    const { datos, meta, cargando, error, recargar } = usarConsulta(
        `/docente/grupos/${grupo.id}/inscritos`,
        { parametros: lista.parametros, activa: hayLista }
    );
    const inscritos = datos ?? [];
    const negado = error && [403, 404].includes(estadoDe(error));

    function cargada(resultado) {
        setEligiendo(false);
        lista.ponerBuscar('');
        lista.irA(1);
        onCargada(resultado);
        if (hayLista) recargar();
    }

    async function reporte() {
        setDescargando(true);
        try {
            const nombre = await descargar(`/docente/grupos/${grupo.id}/inscritos/descarga`, {
                nombre: `estudiantes_${grupo.asignatura.codigo ?? 'grupo'}_g${grupo.codigo}.xlsx`,
            });
            onAvisar(`${nombre} descargado`);
        } catch (fallo) {
            onAvisar(mensajeDe(fallo, 'No se pudo descargar'), 'error');
        } finally {
            setDescargando(false);
        }
    }

    return (
        <Tarjeta sinRelleno>
            <header className="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-5">
                <div className="min-w-0">
                    <h2 className="text-base font-semibold text-slate-800">Lista de inscritos</h2>
                    <p className="break-words text-sm text-slate-600">
                        {grupo.asignatura.nombre} · Grupo {grupo.codigo}
                    </p>
                    <p className="break-words text-xs text-slate-600">
                        {[grupo.asignatura.codigo, grupo.nivel, horariosDe(grupo)]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                <div className="flex flex-col gap-2 sm:flex-row">
                    {hayLista && !eligiendo && (
                        <Boton
                            type="button"
                            variante="secundario"
                            className="whitespace-nowrap"
                            onClick={() => setEligiendo(true)}
                        >
                            <Upload className="h-4 w-4 shrink-0" /> Cargar archivo
                        </Boton>
                    )}
                    <Boton
                        type="button"
                        variante="secundario"
                        className="whitespace-nowrap"
                        disabled={!hayLista || descargando}
                        onClick={reporte}
                    >
                        <Download className="h-4 w-4 shrink-0" /> Reporte de mis estudiantes
                    </Boton>
                </div>
            </header>

            {(!hayLista || eligiendo) && (
                <CargaDeLista
                    grupoId={grupo.id}
                    onCargada={cargada}
                    onCancelar={eligiendo ? () => setEligiendo(false) : undefined}
                    onAvisar={onAvisar}
                />
            )}

            {resumen && !eligiendo && (
                <ResumenDeCarga resultado={resumen} onCerrar={onCerrarResumen} />
            )}

            {!hayLista ? (
                <p className="py-10 text-center text-sm text-slate-600">Sin lista cargada</p>
            ) : negado ? (
                <p role="alert" className="px-4 py-10 text-center text-sm text-slate-700">
                    {mensajeDe(error, 'Sin acceso a este grupo')}
                </p>
            ) : (
                <>
                    <div className="border-b border-slate-200 p-3 sm:px-5">
                        <Buscador
                            valor={lista.buscar}
                            onCambiar={lista.ponerBuscar}
                            placeholder="Nombre, código o documento"
                            etiqueta="Buscar estudiante"
                        />
                    </div>
                    <EstadoCarga
                        cargando={cargando}
                        error={error}
                        vacio={inscritos.length === 0}
                        textoVacio="Sin resultados"
                        onReintentar={recargar}
                    >
                        <ul className="divide-y divide-slate-200 sm:hidden">
                            {inscritos.map((e) => (
                                <li
                                    key={e.id}
                                    className="flex min-h-11 items-start justify-between gap-2 px-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="break-words text-sm font-medium text-slate-800">
                                            {e.nombre}
                                        </p>
                                        <p className="text-xs text-slate-600">
                                            <span className="font-mono">{e.codigo}</span> · CI{' '}
                                            {e.documento}
                                        </p>
                                    </div>
                                    <Insignia tono={tonoDe(e.origen)}>{e.origen}</Insignia>
                                </li>
                            ))}
                        </ul>
                        <table className="hidden w-full text-left text-sm sm:table">
                            <thead className="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-600">
                                <tr>
                                    {['Código', 'Estudiante', 'Documento', 'Origen'].map((t) => (
                                        <th
                                            key={t}
                                            scope="col"
                                            className="px-4 py-3 font-medium first:pl-5"
                                        >
                                            {t}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {inscritos.map((e) => (
                                    <tr key={e.id} className="hover:bg-slate-50">
                                        <td className="px-4 py-3 pl-5 font-mono text-xs text-slate-700">
                                            {e.codigo}
                                        </td>
                                        <td className="px-4 py-3 font-medium text-slate-800">
                                            {e.nombre}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{e.documento}</td>
                                        <td className="px-4 py-3">
                                            <Insignia tono={tonoDe(e.origen)}>{e.origen}</Insignia>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </EstadoCarga>
                    {!error && !cargando && (
                        <Paginacion
                            {...lista.paginacion(meta)}
                            unidad={['inscrito', 'inscritos']}
                            className="border-t border-slate-200 sm:px-5"
                        />
                    )}
                </>
            )}
        </Tarjeta>
    );
}

InscritosDelGrupo.propTypes = {
    grupo: FORMA_GRUPO.isRequired,
    resumen: PropTypes.object,
    onCargada: PropTypes.func.isRequired,
    onCerrarResumen: PropTypes.func.isRequired,
    onAvisar: PropTypes.func.isRequired,
};
