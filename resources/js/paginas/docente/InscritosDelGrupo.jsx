import PropTypes from 'prop-types';
import { useState } from 'react';
import { Clock, Download, Pencil, Upload, UserMinus, UserPlus } from 'lucide-react';
import descargar from '../../api/descargar';
import { estadoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import EstadoCarga from '../../componentes/EstadoCarga';
import IconoAsignatura from '../../componentes/IconoAsignatura';
import Paginacion from '../../componentes/Paginacion';
import Tarjeta from '../../componentes/Tarjeta';
import CargaDeLista from './CargaDeLista';
import { AgregarEstudiante, EditarEstudiante, QuitarInscrito } from './EdicionDeLista';
import FotoDelInscrito, { BotonFoto } from './FotoDelInscrito';
import ResumenDeCarga from './ResumenDeCarga';
import { colorDeAsignatura, plural } from '../../utiles/texto';
import { FORMA_GRUPO } from './SelectorDeGrupos';

// El C.I. bajo el nombre, o que falta.
const ci = (e) => (e.documento ? `C.I. ${e.documento}` : 'Sin C.I.');

// De dónde viene el registro del estudiante, bajo su nombre: del padrón que
// verificó la administración o registrado por el docente.
const origenDe = (origen) =>
    origen === 'Docente' ? 'Registrado por el docente' : 'Padrón verificado';

// La lista de inscritos del grupo elegido: carga desde archivo, resumen de
// la última carga, búsqueda, páginas de 25 y descarga. También se agrega a
// un estudiante suelto y se quita a un inscrito con motivo.
export default function InscritosDelGrupo({
    grupo,
    resumen = null,
    onCargada,
    onCambiada,
    onCerrarResumen,
    onAvisar,
}) {
    const [eligiendo, setEligiendo] = useState(false);
    const [descargando, setDescargando] = useState(false);
    const [agregando, setAgregando] = useState(false);
    const [quitando, setQuitando] = useState(null);
    const [editando, setEditando] = useState(null);
    const [conFoto, setConFoto] = useState(null);
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

    function cambiada(aviso) {
        setAgregando(false);
        setQuitando(null);
        setEditando(null);
        onAvisar(aviso);
        onCambiada();
        recargar();
    }

    // Quitar al único de la última página la dejaría vacía.
    function quitado(aviso) {
        const pagina = meta?.pagina ?? 1;
        if (inscritos.length === 1 && pagina > 1) lista.irA(pagina - 1);
        cambiada(aviso);
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
            <header className="space-y-3 border-b border-slate-200 px-4 py-4 sm:px-5">
                <div>
                    <div className="flex min-w-0 items-start gap-3">
                        <IconoAsignatura
                            nombre={grupo.asignatura.nombre}
                            color={colorDeAsignatura(grupo.asignatura.nombre)}
                            tamano="md"
                        />
                        <div className="min-w-0">
                            <h2 className="break-words text-base font-semibold text-slate-900">
                                {grupo.asignatura.nombre} · Grupo {grupo.codigo}
                            </h2>
                            <p className="text-sm text-slate-600">
                                {[grupo.asignatura.codigo, grupo.nivel].filter(Boolean).join(' · ')}
                                {' · '}
                                {hayLista ? (
                                    plural(grupo.inscritos ?? 0, 'inscrito')
                                ) : (
                                    <span className="font-medium text-warning-700">
                                        Sin lista cargada
                                    </span>
                                )}
                            </p>
                        </div>
                    </div>
                </div>
                {grupo.horarios?.length > 0 && (
                    <ul className="flex flex-wrap gap-2" aria-label="Horario">
                        {grupo.horarios.map((h) => (
                            <li
                                key={`${h.dia}-${h.hora}-${h.aula}`}
                                className="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700"
                            >
                                <Clock className="h-3.5 w-3.5 text-slate-500" aria-hidden="true" />
                                <span className="font-semibold">{h.dia}</span> {h.hora}
                                {h.aula && <span className="text-slate-500">· {h.aula}</span>}
                            </li>
                        ))}
                    </ul>
                )}
                {/* Las dos vías siempre: uno por uno o la lista entera. Sin lista,
                    la carga del archivo queda abierta debajo. */}
                {!eligiendo && (
                    <div className="flex flex-wrap gap-2">
                        <Boton
                            type="button"
                            className="whitespace-nowrap"
                            variante={hayLista ? 'primario' : 'secundario'}
                            onClick={() => setAgregando(true)}
                        >
                            <UserPlus className="h-4 w-4 shrink-0" /> Agregar estudiante
                        </Boton>
                        {hayLista && (
                            <>
                                <Boton
                                    type="button"
                                    variante="secundario"
                                    className="whitespace-nowrap"
                                    onClick={() => setEligiendo(true)}
                                >
                                    <Upload className="h-4 w-4 shrink-0" /> Cargar archivo
                                </Boton>
                                <Boton
                                    type="button"
                                    variante="secundario"
                                    className="whitespace-nowrap"
                                    disabled={descargando}
                                    onClick={reporte}
                                    title="Hoja de cálculo con los inscritos"
                                >
                                    <Download className="h-4 w-4 shrink-0" /> Descargar lista
                                </Boton>
                            </>
                        )}
                    </div>
                )}
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

            {!hayLista ? null : negado ? (
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
                                            <span className="font-mono">{e.codigo}</span> · {ci(e)}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            {origenDe(e.origen)}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <BotonFoto estudiante={e} onAbrir={setConFoto} />
                                        <BotonEditar estudiante={e} onEditar={setEditando} />
                                        <BotonQuitar estudiante={e} onQuitar={setQuitando} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                        <table className="hidden w-full text-left text-sm sm:table">
                            <thead className="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-600">
                                <tr>
                                    {['Código SIS', 'Estudiante', 'Foto'].map((t) => (
                                        <th
                                            key={t}
                                            scope="col"
                                            className="px-4 py-3 font-medium first:pl-5"
                                        >
                                            {t}
                                        </th>
                                    ))}
                                    <th scope="col" className="w-14 px-4 py-3">
                                        <span className="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {inscritos.map((e) => (
                                    <tr key={e.id} className="hover:bg-slate-50">
                                        <td className="px-4 py-3 pl-5 font-mono text-xs text-slate-700">
                                            {e.codigo}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span className="block font-medium text-slate-800">
                                                {e.nombre}
                                            </span>
                                            <span className="block text-xs text-slate-500">
                                                {ci(e)} · {origenDe(e.origen)}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <BotonFoto estudiante={e} onAbrir={setConFoto} />
                                        </td>
                                        <td className="py-1 pr-3">
                                            <span className="flex justify-end">
                                                <BotonEditar
                                                    estudiante={e}
                                                    onEditar={setEditando}
                                                />
                                                <BotonQuitar
                                                    estudiante={e}
                                                    onQuitar={setQuitando}
                                                />
                                            </span>
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
            {agregando && (
                <AgregarEstudiante
                    grupo={grupo}
                    onAgregado={cambiada}
                    onCerrar={() => setAgregando(false)}
                />
            )}
            {conFoto && (
                <FotoDelInscrito
                    grupoId={grupo.id}
                    estudiante={conFoto}
                    onHabilitada={(texto) => {
                        setConFoto(null);
                        onAvisar(texto);
                        recargar();
                    }}
                    onCerrar={() => setConFoto(null)}
                />
            )}
            {editando && (
                <EditarEstudiante
                    estudiante={editando}
                    ruta={`/docente/grupos/${grupo.id}/inscritos/${editando.id}`}
                    onEditado={cambiada}
                    onCerrar={() => setEditando(null)}
                />
            )}
            {quitando && (
                <QuitarInscrito
                    grupoId={grupo.id}
                    estudiante={quitando}
                    onQuitado={quitado}
                    onCerrar={() => setQuitando(null)}
                />
            )}
        </Tarjeta>
    );
}

function BotonEditar({ estudiante, onEditar }) {
    return (
        <button
            type="button"
            aria-label={`Editar a ${estudiante.nombre}`}
            title="Editar nombre y C.I."
            onClick={() => onEditar(estudiante)}
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
        >
            <Pencil className="h-4 w-4" />
        </button>
    );
}

BotonEditar.propTypes = {
    estudiante: PropTypes.shape({ nombre: PropTypes.string.isRequired }).isRequired,
    onEditar: PropTypes.func.isRequired,
};

function BotonQuitar({ estudiante, onQuitar }) {
    return (
        <button
            type="button"
            aria-label={`Quitar a ${estudiante.nombre} del grupo`}
            title="Quitar del grupo"
            onClick={() => onQuitar(estudiante)}
            className="ml-auto flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-danger-50 hover:text-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-danger-600"
        >
            <UserMinus className="h-4 w-4" />
        </button>
    );
}

BotonQuitar.propTypes = {
    estudiante: PropTypes.shape({ nombre: PropTypes.string.isRequired }).isRequired,
    onQuitar: PropTypes.func.isRequired,
};

InscritosDelGrupo.propTypes = {
    grupo: FORMA_GRUPO.isRequired,
    resumen: PropTypes.object,
    onCargada: PropTypes.func.isRequired,
    onCambiada: PropTypes.func.isRequired,
    onCerrarResumen: PropTypes.func.isRequired,
    onAvisar: PropTypes.func.isRequired,
};
