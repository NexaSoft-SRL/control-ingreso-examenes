import { useState } from 'react';
import { DatabaseBackup, Download } from 'lucide-react';
import { api } from '../../api/cliente';
import descargar from '../../api/descargar';
import { codigoDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Chips from '../../componentes/Chips';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Tarjeta from '../../componentes/Tarjeta';
import { fechaCorta } from '../../utiles/texto';

const POR_PAGINA = 15;
const TH = 'px-4 py-3 font-medium';

const ESTADOS = {
    correcto: { tono: 'exito', texto: 'Correcto' },
    fallido: { tono: 'peligro', texto: 'Fallido' },
    restaurado: { tono: 'primario', texto: 'Restaurado' },
    incompatible: { tono: 'advertencia', texto: 'Incompatible' },
};

// `valor` es lo que viaja en `?filtro=`; `clave`, la de `meta.conteos`.
const FILTROS = [
    { valor: null, clave: 'todos', etiqueta: 'Todos' },
    { valor: 'programados', clave: 'programados', etiqueta: 'Programados' },
    { valor: 'puntuales', clave: 'puntuales', etiqueta: 'Puntuales' },
    { valor: 'fallidos', clave: 'fallidos', etiqueta: 'Fallidos' },
];

const miles = (n) => Number(n ?? 0).toLocaleString('es-BO');
const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

// 50331648 → «48 MB»; 1468006 → «1,4 MB»; 20480 → «20 KB»; nulo → «—».
export function tamanoLegible(bytes) {
    if (bytes === null || bytes === undefined) return '—';
    const decimal = (n) =>
        n.toLocaleString('es-BO', { maximumFractionDigits: n < 10 ? 1 : 0, useGrouping: true });
    if (bytes >= 1024 ** 3) return `${decimal(bytes / 1024 ** 3)} GB`;
    if (bytes >= 1024 ** 2) return `${decimal(bytes / 1024 ** 2)} MB`;
    if (bytes >= 1024) return `${decimal(bytes / 1024)} KB`;
    return `${bytes} B`;
}

// HU-25 · Respaldo y restauración: a demanda y programado. Restaurar es
// destructivo: pide confirmación en la misma fila. El servidor rechaza el
// respaldo de un esquema anterior (409 `RESPALDO_INCOMPATIBLE`) y la fila
// queda marcada.
export default function Respaldo() {
    const lista = usarPaginaServidor({ porPagina: POR_PAGINA, filtros: { filtro: null } });
    const { datos, meta, cargando, error, recargar } = usarConsulta('/respaldos', {
        parametros: lista.parametros,
    });
    const [confirmando, setConfirmando] = useState(null);
    const [generando, setGenerando] = useState(false);
    // Id del respaldo que se está restaurando o descargando.
    const [restaurando, setRestaurando] = useState(null);
    const [descargando, setDescargando] = useState(null);
    // Ids que el servidor rechazó por incompatibles en esta visita.
    const [incompatibles, setIncompatibles] = useState([]);
    const [aviso, avisar] = useAviso();

    const { filtro } = lista.filtros;
    const respaldos = datos ?? [];
    const ocupado = generando || restaurando !== null;

    const subtitulo = meta
        ? [meta.programacion, meta.ultimo ? `último: ${fechaCorta(meta.ultimo)}` : null]
              .filter(Boolean)
              .join(' · ')
        : undefined;

    function alDia() {
        // El nuevo queda arriba: se sale de los filtros que lo ocultarían.
        if (filtro === 'programados' || filtro === 'fallidos') lista.ponerFiltro('filtro', null);
        else lista.irA(1);
        return recargar();
    }

    function respaldar() {
        setGenerando(true);
        setConfirmando(null);
        api.post('/respaldos')
            .then(() => {
                avisar('Respaldo generado');
                return alDia();
            })
            .catch((rechazo) => {
                avisar(sinPunto(mensajeDe(rechazo, 'No se pudo generar el respaldo')), 'error');
                // El intento fallido queda en el historial.
                return recargar();
            })
            .finally(() => setGenerando(false));
    }

    function bajar(r) {
        setDescargando(r.id);
        descargar(`/respaldos/${r.id}/descargar`, { nombre: r.archivo ?? 'respaldo.sql' })
            .then((nombre) => avisar(`${nombre} descargado`))
            .catch((rechazo) =>
                avisar(sinPunto(mensajeDe(rechazo, 'No se pudo descargar')), 'error')
            )
            .finally(() => setDescargando(null));
    }

    function restaurar(r) {
        setRestaurando(r.id);
        api.post(`/respaldos/${r.id}/restaurar`)
            .then(() => {
                setConfirmando(null);
                avisar(`Restaurado al ${fechaCorta(r.fecha)}`);
                return recargar();
            })
            .catch((rechazo) => {
                setConfirmando(null);
                if (codigoDe(rechazo) === 'RESPALDO_INCOMPATIBLE') {
                    setIncompatibles((previos) => [...previos, r.id]);
                    avisar('Respaldo incompatible', 'error');
                    return undefined;
                }
                avisar(sinPunto(mensajeDe(rechazo, 'No se pudo restaurar')), 'error');
                return recargar();
            })
            .finally(() => setRestaurando(null));
    }

    function estadoDe(r) {
        if (incompatibles.includes(r.id)) return ESTADOS.incompatible;
        if (r.restaurado) return ESTADOS.restaurado;
        return r.estado === 'fallido' ? ESTADOS.fallido : ESTADOS.correcto;
    }

    function acciones(r) {
        if (r.estado !== 'correcto') return null;
        const fecha = fechaCorta(r.fecha);
        if (confirmando === r.id) {
            return (
                <div
                    className="flex flex-wrap items-center justify-end gap-2"
                    role="group"
                    aria-label={`Restaurar el respaldo del ${fecha}`}
                >
                    <span className="mr-auto text-sm font-medium text-danger-700 sm:mr-1">
                        Sobrescribe los datos actuales
                    </span>
                    <Boton
                        variante="secundario"
                        tamano="chico"
                        className="min-h-10"
                        disabled={restaurando === r.id}
                        onClick={() => setConfirmando(null)}
                    >
                        Cancelar
                    </Boton>
                    <Boton
                        variante="peligro"
                        tamano="chico"
                        className="min-h-10"
                        disabled={restaurando === r.id}
                        onClick={() => restaurar(r)}
                    >
                        {restaurando === r.id ? 'Restaurando' : 'Restaurar'}
                    </Boton>
                </div>
            );
        }
        return (
            <div className="flex flex-wrap justify-end gap-2">
                <Boton
                    variante="enlace"
                    tamano="chico"
                    className="min-h-10"
                    aria-label={`Descargar el respaldo del ${fecha}`}
                    disabled={descargando === r.id}
                    onClick={() => bajar(r)}
                >
                    <Download className="h-4 w-4" strokeWidth={2} aria-hidden="true" />
                    Descargar
                </Boton>
                {!incompatibles.includes(r.id) && (
                    <Boton
                        variante="peligroContorno"
                        tamano="chico"
                        className="min-h-10"
                        aria-label={`Restaurar el respaldo del ${fecha}`}
                        disabled={ocupado}
                        onClick={() => setConfirmando(r.id)}
                    >
                        Restaurar
                    </Boton>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <Encabezado titulo="Respaldo" subtitulo={subtitulo}>
                <Boton onClick={respaldar} disabled={ocupado}>
                    <DatabaseBackup className="h-4 w-4" strokeWidth={2} aria-hidden="true" />
                    {generando ? 'Respaldando' : 'Respaldar ahora'}
                </Boton>
            </Encabezado>

            {meta && (
                <Chips
                    etiqueta="Filtrar respaldos"
                    valor={filtro}
                    onCambiar={(valor) => {
                        setConfirmando(null);
                        lista.ponerFiltro('filtro', valor);
                    }}
                    opciones={FILTROS.map((f) => ({
                        valor: f.valor,
                        etiqueta: f.etiqueta,
                        conteo: miles(meta.conteos?.[f.clave]),
                    }))}
                />
            )}

            <Tarjeta sinRelleno titulo="Historial">
                <EstadoCarga
                    cargando={cargando}
                    error={error}
                    vacio={datos?.length === 0}
                    textoVacio={filtro ? 'Sin resultados' : 'Sin respaldos'}
                    onReintentar={recargar}
                    filas={8}
                >
                    <ul className="divide-y divide-slate-200 sm:hidden">
                        {respaldos.map((r) => (
                            <li key={r.id} className="space-y-2 px-4 py-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-slate-800">
                                            {fechaCorta(r.fecha)}
                                        </p>
                                        <p className="text-xs text-slate-600">
                                            {r.tipo} · {tamanoLegible(r.tamano_bytes)}
                                        </p>
                                    </div>
                                    <Insignia tono={estadoDe(r).tono}>{estadoDe(r).texto}</Insignia>
                                </div>
                                {acciones(r)}
                            </li>
                        ))}
                    </ul>
                    <table
                        className="hidden w-full text-left text-sm sm:table"
                        aria-label="Historial"
                    >
                        <thead className="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-600">
                            <tr>
                                <th scope="col" className={TH}>
                                    Fecha
                                </th>
                                <th scope="col" className={TH}>
                                    Tipo
                                </th>
                                <th scope="col" className={TH}>
                                    Tamaño
                                </th>
                                <th scope="col" className={TH}>
                                    Estado
                                </th>
                                <th scope="col" className={TH}>
                                    <span className="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {respaldos.map((r) => (
                                <tr key={r.id} className="h-16 hover:bg-slate-50">
                                    <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-800">
                                        {fechaCorta(r.fecha)}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{r.tipo}</td>
                                    <td className="whitespace-nowrap px-4 py-3 text-slate-700">
                                        {tamanoLegible(r.tamano_bytes)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Insignia tono={estadoDe(r).tono}>
                                            {estadoDe(r).texto}
                                        </Insignia>
                                    </td>
                                    <td className="px-4 py-2">{acciones(r)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </EstadoCarga>
                {meta && meta.total > 0 && (
                    <Paginacion
                        {...lista.paginacion(meta)}
                        unidad={['respaldo', 'respaldos']}
                        className="border-t border-slate-200"
                    />
                )}
            </Tarjeta>
            {aviso}
        </div>
    );
}
