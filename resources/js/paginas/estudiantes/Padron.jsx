import { useState } from 'react';
import { Upload } from 'lucide-react';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Dato from '../../componentes/Dato';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import FiltroFacultad, { PuntoFacultad } from '../../componentes/FiltroFacultad';
import Paginacion from '../../componentes/Paginacion';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import CargaInscripciones from './CargaInscripciones';
import Conflictos from './Conflictos';
import FichaEstudiante, { Origen } from './FichaEstudiante';

const POR_PAGINA = 25;
const TH = 'px-4 py-3 font-medium';
const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const miles = (n) => (typeof n === 'number' ? n.toLocaleString('es-BO') : '—');
const grupos = (n) => `${n} ${n === 1 ? 'grupo' : 'grupos'}`;

// Un solo padrón para toda la universidad: cada estudiante existe una vez,
// se cargue por la vía que se cargue. Las diferencias entre cargas quedan
// como conflicto para la administración.
export default function Padron() {
    const [pestana, setPestana] = useState('padron');
    const [ficha, setFicha] = useState(null);
    const [cargando, setCargando] = useState(false);
    // Cada carga vuelve a pedir los conflictos aunque la pestaña esté abierta.
    const [cargas, setCargas] = useState(0);
    const [aviso, avisar] = useAviso();

    const resumen = usarConsulta('/estudiantes/resumen');
    const lista = usarPaginaServidor({
        porPagina: POR_PAGINA,
        filtros: { facultad: null, carrera: null },
    });
    const estudiantes = usarConsulta('/estudiantes', { parametros: lista.parametros });
    const { facultad, carrera } = lista.filtros;
    const carreras = usarConsulta('/oferta/carreras', { parametros: { facultad } });

    const cifras = resumen.datos ?? {};
    const pendientes = cifras.conflictos_pendientes;
    const conteos = estudiantes.meta?.conteos ?? {};
    const filas = estudiantes.datos ?? [];

    function refrescar() {
        resumen.recargar();
        estudiantes.recargar();
    }

    return (
        <div className="space-y-6">
            <Encabezado titulo="Padrón">
                <Boton onClick={() => setCargando(true)}>
                    <Upload className="h-4 w-4" aria-hidden="true" /> Cargar inscripciones
                </Boton>
            </Encabezado>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Dato etiqueta="Estudiantes" valor={miles(cifras.estudiantes)} />
                <Dato etiqueta="Inscripciones" valor={miles(cifras.inscripciones)} />
                <Dato
                    etiqueta="Cargados por docentes"
                    valor={miles(cifras.cargados_por_docentes)}
                />
                <Dato
                    etiqueta="Conflictos pendientes"
                    valor={miles(pendientes)}
                    tono={pendientes > 0 ? 'advertencia' : 'neutro'}
                    onClick={() => setPestana('conflictos')}
                />
            </div>

            <div
                role="tablist"
                aria-label="Padrón"
                className="flex w-fit max-w-full gap-1 rounded-lg border border-slate-200 bg-white p-1 text-sm font-medium"
            >
                {[
                    ['padron', 'Estudiantes'],
                    [
                        'conflictos',
                        typeof pendientes === 'number'
                            ? `Conflictos (${pendientes})`
                            : 'Conflictos',
                    ],
                ].map(([clave, etiqueta]) => (
                    <button
                        key={clave}
                        type="button"
                        role="tab"
                        aria-selected={pestana === clave}
                        onClick={() => setPestana(clave)}
                        className={`min-h-10 whitespace-nowrap rounded-md px-4 ${FOCO} ${
                            pestana === clave
                                ? 'bg-primary-600 text-white'
                                : 'text-slate-700 hover:bg-slate-100'
                        }`}
                    >
                        {etiqueta}
                    </button>
                ))}
            </div>

            {pestana === 'padron' && (
                <>
                    <FiltroFacultad
                        campo="clave"
                        valor={facultad}
                        onCambiar={(clave) =>
                            lista.ponerFiltros({ facultad: clave, carrera: null })
                        }
                        conteo={(sigla) => {
                            const n = sigla ? conteos[sigla] : conteos.todas;
                            return typeof n === 'number' ? miles(n) : undefined;
                        }}
                    />

                    <Tarjeta sinRelleno titulo="Estudiantes">
                        <div className="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:grid-cols-[minmax(0,24rem)_minmax(0,20rem)]">
                            <Buscador
                                valor={lista.buscar}
                                onCambiar={lista.ponerBuscar}
                                placeholder="Nombre, código o documento"
                                etiqueta="Buscar estudiante"
                            />
                            <Seleccion
                                className="min-w-0"
                                aria-label="Filtrar por carrera"
                                value={carrera ?? ''}
                                onChange={(e) =>
                                    lista.ponerFiltro(
                                        'carrera',
                                        e.target.value === '' ? null : Number(e.target.value)
                                    )
                                }
                            >
                                <option value="">Todas las carreras</option>
                                {(carreras.datos ?? []).map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.nombre}
                                    </option>
                                ))}
                            </Seleccion>
                        </div>

                        <EstadoCarga
                            cargando={estudiantes.cargando}
                            error={estudiantes.error}
                            vacio={filas.length === 0}
                            textoVacio="Sin resultados"
                            onReintentar={estudiantes.recargar}
                            filas={8}
                        >
                            <ul
                                aria-label="Estudiantes"
                                className="divide-y divide-slate-200 sm:hidden"
                            >
                                {filas.map((e) => (
                                    <li key={e.id}>
                                        <button
                                            type="button"
                                            onClick={() => setFicha(e)}
                                            className={`block w-full space-y-1.5 px-4 py-3 text-left hover:bg-slate-50 ${FOCO} focus-visible:-outline-offset-2`}
                                        >
                                            <span className="flex items-start justify-between gap-3">
                                                <span className="min-w-0 truncate text-sm font-medium text-slate-800">
                                                    {e.nombre}
                                                </span>
                                                <Origen origen={e.origen} />
                                            </span>
                                            <span className="block text-xs text-slate-600">
                                                <span className="font-mono">{e.codigo}</span> · CI{' '}
                                                {e.documento}
                                            </span>
                                            <span className="flex min-w-0 items-center gap-3 text-xs text-slate-600">
                                                {e.facultad && <PuntoFacultad sigla={e.facultad} />}
                                                <span className="min-w-0 truncate">
                                                    {e.carrera ? `${e.carrera} · ` : ''}
                                                    {grupos(e.grupos)}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                            <table className="hidden w-full text-left text-sm sm:table">
                                <thead className="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-600">
                                    <tr>
                                        <th scope="col" className={TH}>
                                            Código
                                        </th>
                                        <th scope="col" className={TH}>
                                            Estudiante
                                        </th>
                                        <th scope="col" className={TH}>
                                            Documento
                                        </th>
                                        <th scope="col" className={TH}>
                                            Facultad
                                        </th>
                                        <th scope="col" className={`${TH} hidden lg:table-cell`}>
                                            Carrera
                                        </th>
                                        <th scope="col" className={`${TH} hidden md:table-cell`}>
                                            Grupos
                                        </th>
                                        <th scope="col" className={TH}>
                                            Origen
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200">
                                    {filas.map((e) => (
                                        <tr
                                            key={e.id}
                                            onClick={() => setFicha(e)}
                                            className="cursor-pointer hover:bg-slate-50"
                                        >
                                            <td className="px-4 py-3 font-mono text-xs text-slate-700">
                                                {e.codigo}
                                            </td>
                                            <td className="max-w-0 px-4 py-1 sm:w-2/5 lg:w-1/3">
                                                <button
                                                    type="button"
                                                    title={e.nombre}
                                                    className={`block min-h-10 w-full truncate rounded text-left font-medium text-slate-800 ${FOCO}`}
                                                >
                                                    {e.nombre}
                                                </button>
                                            </td>
                                            <td className="px-4 py-3 text-slate-700">
                                                {e.documento}
                                            </td>
                                            <td className="px-4 py-3">
                                                {e.facultad && <PuntoFacultad sigla={e.facultad} />}
                                            </td>
                                            <td
                                                className="hidden max-w-0 truncate px-4 py-3 text-slate-700 lg:table-cell lg:w-1/5"
                                                title={e.carrera}
                                            >
                                                {e.carrera}
                                            </td>
                                            <td className="hidden px-4 py-3 text-slate-700 md:table-cell">
                                                {e.grupos}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Origen origen={e.origen} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <Paginacion
                                {...lista.paginacion(estudiantes.meta)}
                                unidad={['estudiante', 'estudiantes']}
                                className="border-t border-slate-200"
                            />
                        </EstadoCarga>
                    </Tarjeta>
                </>
            )}

            {pestana === 'conflictos' && (
                <Conflictos key={cargas} onCambio={refrescar} avisar={avisar} />
            )}

            {ficha && <FichaEstudiante estudiante={ficha} onCerrar={() => setFicha(null)} />}

            {cargando && (
                <CargaInscripciones
                    facultadInicial={facultad}
                    onCerrar={() => setCargando(false)}
                    onCargada={() => {
                        setCargas((n) => n + 1);
                        refrescar();
                    }}
                    onVerConflictos={() => {
                        setCargando(false);
                        setPestana('conflictos');
                    }}
                    avisar={avisar}
                />
            )}
            {aviso}
        </div>
    );
}
