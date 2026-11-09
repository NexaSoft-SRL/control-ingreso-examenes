import { useState } from 'react';
import { Activity, History, Search, ShieldCheck } from 'lucide-react';
import { erroresDe, estadoDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import Campo from '../../componentes/Campo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import { usarSesion } from '../../sesion/SesionContexto';
import { fechaCorta } from '../../utiles/texto';

const POR_PAGINA = 25;
const ROLES_DE_INICIO = ['Administrador', 'Docente', 'Auxiliar'];

function hoy() {
    const d = new Date();
    const dos = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`;
}

// Bitácora de operaciones (HU-07): de solo lectura, nadie la edita ni la
// borra. Se filtra por usuario, rol, tipo de operación y rango de fechas; el
// servidor pagina y entrega el texto de cada operación ya resuelto.
export default function Bitacora() {
    const { puede } = usarSesion();
    const lista = usarPaginaServidor({
        porPagina: POR_PAGINA,
        filtros: { rol: '', operacion: '', desde: '', hasta: '' },
    });
    const { datos, meta, cargando, error, recargar } = usarConsulta('/bitacora', {
        parametros: lista.parametros,
    });
    // Los roles creados solo los puede listar quien administra los roles.
    const roles = usarConsulta('/roles', { activa: puede('usuarios_roles') });

    // Las opciones de los filtros sobreviven a una consulta rechazada.
    const [catalogo, setCatalogo] = useState(null);
    if (meta && meta !== catalogo) setCatalogo(meta);

    const { rol, operacion, desde, hasta } = lista.filtros;
    const nombresDeRol = roles.datos?.map((r) => r.nombre) ?? ROLES_DE_INICIO;
    const opcionesDeRol =
        rol && !nombresDeRol.includes(rol) ? [...nombresDeRol, rol] : nombresDeRol;
    const operaciones = catalogo?.operaciones ?? [];
    const primera = catalogo?.fecha_minima ?? undefined;
    const ultima = hoy();

    const rechazo = estadoDe(error) === 422;
    const deCampo = rechazo ? erroresDe(error) : {};

    return (
        <div className="space-y-6">
            <Encabezado titulo="Bitácora">
                <Insignia tono="neutro">Solo lectura</Insignia>
            </Encabezado>

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-[minmax(0,1.4fr)_9.5rem_minmax(0,1fr)_10rem_10rem]">
                <Campo
                    etiqueta="Buscar"
                    icono={Search}
                    type="search"
                    className="col-span-2 min-w-0 lg:col-span-1"
                    placeholder="Usuario o registro"
                    maxLength={100}
                    value={lista.buscar}
                    error={deCampo.buscar}
                    onChange={(e) => lista.ponerBuscar(e.target.value)}
                />
                <Seleccion
                    etiqueta="Rol"
                    icono={ShieldCheck}
                    className="min-w-0"
                    value={rol}
                    onChange={(e) => lista.ponerFiltro('rol', e.target.value)}
                >
                    <option value="">Todos</option>
                    {opcionesDeRol.map((nombre) => (
                        <option key={nombre} value={nombre}>
                            {nombre}
                        </option>
                    ))}
                </Seleccion>
                <Seleccion
                    etiqueta="Operación"
                    icono={Activity}
                    className="min-w-0"
                    value={operacion}
                    onChange={(e) => lista.ponerFiltro('operacion', e.target.value)}
                >
                    <option value="">Todas</option>
                    {operaciones.map((op) => (
                        <option key={op.clave} value={op.clave}>
                            {op.texto}
                        </option>
                    ))}
                </Seleccion>
                <Campo
                    etiqueta="Desde"
                    type="date"
                    className="min-w-0"
                    min={primera}
                    max={hasta || ultima}
                    value={desde}
                    error={deCampo.desde}
                    onChange={(e) => lista.ponerFiltro('desde', e.target.value)}
                />
                <Campo
                    etiqueta="Hasta"
                    type="date"
                    className="min-w-0"
                    min={desde || primera}
                    max={ultima}
                    value={hasta}
                    error={deCampo.hasta}
                    onChange={(e) => lista.ponerFiltro('hasta', e.target.value)}
                />
            </div>

            <Tarjeta sinRelleno titulo="Operaciones">
                {rechazo ? (
                    <p role="alert" className="px-4 py-8 text-center text-sm text-danger-600">
                        Filtros no válidos
                    </p>
                ) : (
                    <EstadoCarga
                        cargando={cargando}
                        error={error}
                        vacio={datos?.length === 0}
                        textoVacio="Sin resultados"
                        onReintentar={recargar}
                        filas={8}
                    >
                        <ul className="divide-y divide-slate-200">
                            {(datos ?? []).map((b) => (
                                <li
                                    key={b.id}
                                    className="flex min-h-11 items-start gap-3 px-4 py-2.5 hover:bg-slate-50"
                                >
                                    <History
                                        className="mt-0.5 h-4 w-4 shrink-0 text-slate-500"
                                        strokeWidth={1.75}
                                        aria-hidden="true"
                                    />
                                    <div className="min-w-0 flex-1 sm:flex sm:items-baseline sm:gap-4">
                                        <div className="min-w-0 flex-1">
                                            <p
                                                className="truncate text-sm text-slate-800"
                                                title={`${b.usuario} · ${b.operacion_texto}`}
                                            >
                                                <span className="font-medium">{b.usuario}</span> ·{' '}
                                                {b.operacion_texto}
                                            </p>
                                            {b.entidad && (
                                                <p
                                                    className="truncate text-xs text-slate-600"
                                                    title={b.entidad}
                                                >
                                                    {b.entidad}
                                                </p>
                                            )}
                                        </div>
                                        <p className="mt-0.5 shrink-0 text-xs tabular-nums text-slate-600 sm:mt-0">
                                            {[b.rol, fechaCorta(b.fecha)]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                        <Paginacion
                            {...lista.paginacion(meta)}
                            unidad={['operación', 'operaciones']}
                            className="border-t border-slate-200"
                        />
                    </EstadoCarga>
                )}
            </Tarjeta>
        </div>
    );
}
