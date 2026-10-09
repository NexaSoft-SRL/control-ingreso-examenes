import React from 'react';
import { api } from '../../api/cliente';
import { estadoDe } from '../../api/errores';
import { plural } from '../../utiles/texto';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';

/**
 * Bitacora (HU-07). Consume GET /api/bitacora, que filtra por usuario_id,
 * rango de fechas (desde, hasta; Y-m-d) y operacion. El menu, la cuenta y el
 * cierre de sesion son del armazon (EsquemaApp); una sesion vencida la
 * resuelve el cliente de la API.
 */
const etiquetaOperacion = {
    'sesion.iniciar': 'Inicio de sesión',
    'sesion.cerrar': 'Cierre de sesión',
    'sesion.fallida': 'Intento de sesión fallido',
    'usuario.registrar': 'Creación de cuenta',
    'usuario.actualizar': 'Edición de cuenta',
    'usuario.bloquear': 'Bloqueo de cuenta',
    'usuario.desbloquear': 'Desbloqueo de cuenta',
    'usuario.restablecer_temporal': 'Restablecimiento de contraseña',
    'rol.crear': 'Creación de rol',
    'rol.modificar': 'Edición de rol',
    'rol.eliminar': 'Eliminación de rol',
    'norma.plantilla_crear': 'Creación de plantilla de norma',
    'norma.plantilla_editar': 'Edición de plantilla de norma',
    'norma.plantilla_quitar': 'Retiro de plantilla de norma',
    'periodo.ajustar': 'Ajuste del período',
    'oferta.importar': 'Importación de la oferta',
    'docente.activar_cuenta': 'Activación de cuenta de docente',
    'inscritos.cargar': 'Carga de inscritos',
    'padron.resolver_conflicto': 'Resolución de conflicto del padrón',
    'examen.registrar': 'Registro de examen',
    'examen.modificar': 'Edición de examen',
    'examen.eliminar': 'Eliminación de examen',
    'habilitacion.habilitar': 'Habilitación de estudiantes',
    'habilitacion.inhabilitar': 'Inhabilitación de estudiantes',
    'habilitacion.repartir': 'Reparto por aula',
    'estudiante.registrar': 'Registro de estudiante',
    'estudiante.actualizar': 'Edición de estudiante',
    'estudiante.baja': 'Baja de estudiante',
    'estudiante.reactivar': 'Reactivación de estudiante',
    'padron.importar': 'Carga masiva del padrón',
    'docente.registrar': 'Alta de docente',
    'asignatura.registrar': 'Registro de asignatura',
    'asignatura.eliminar': 'Eliminación de asignatura',
};

const etiquetaTabla = {
    asignaturas: 'Asignatura',
    cargas_inscritos: 'Carga de inscritos',
    conflictos_padron: 'Conflicto del padrón',
    habilitaciones: 'Habilitación',
    importaciones_oferta: 'Importación de la oferta',
    plantillas_norma: 'Plantilla de norma',
    aulas: 'Aula',
    docentes: 'Docente',
    estudiantes: 'Estudiante',
    students: 'Estudiante',
    examenes: 'Examen',
    grupos: 'Grupo',
    periodos: 'Período',
    roles: 'Rol',
    usuarios: 'Usuario',
};

const CAMPO =
    'min-h-11 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/40';

// La API da `name`; el contrato nuevo de cuentas, `nombre`.
const nombreDe = (usuario) => usuario?.nombre ?? usuario?.name ?? '';

const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

// Se parsea el texto tal cual llega para no correr la hora por la zona horaria del navegador.
function formatearFechaHora(fecha) {
    const partes = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(fecha ?? '');

    if (!partes) {
        return fecha ?? '';
    }

    const [, anio, mes, dia, horas, minutos] = partes;

    return `${dia}/${meses[Number(mes) - 1]}/${anio} ${horas}:${minutos}`;
}

function describirEntidad(operacion) {
    if (operacion.descripcion) {
        return operacion.descripcion;
    }

    if (!operacion.tabla_afectada) {
        return '—';
    }

    const tabla = etiquetaTabla[operacion.tabla_afectada] ?? operacion.tabla_afectada;

    return operacion.registro_id === null ? tabla : `${tabla} #${operacion.registro_id}`;
}

function Bitacora() {
    const [operaciones, setOperaciones] = React.useState([]);
    const [cargando, setCargando] = React.useState(true);
    const [error, setError] = React.useState(null);
    const [usuariosConocidos, setUsuariosConocidos] = React.useState([]);
    const [operacionesConocidas, setOperacionesConocidas] = React.useState(
        Object.keys(etiquetaOperacion)
    );

    const [usuarioFiltro, setUsuarioFiltro] = React.useState('');
    const [fechaFiltro, setFechaFiltro] = React.useState('');
    const [operacionFiltro, setOperacionFiltro] = React.useState('');
    const [hastaFiltro, setHastaFiltro] = React.useState('');
    const [vigentes, setVigentes] = React.useState({});

    const ultimaConsulta = React.useRef(0);

    const consultar = React.useCallback(async (filtros) => {
        const numeroConsulta = ++ultimaConsulta.current;
        const params = {};

        if (filtros.usuario) params.usuario_id = filtros.usuario;
        // El backlog pide rango de fechas: "desde" solo ya acota, y
        // "hasta" solo lista todo lo anterior a esa fecha.
        if (filtros.desde) params.desde = filtros.desde;
        if (filtros.hasta) params.hasta = filtros.hasta;
        if (filtros.operacion) params.operacion = filtros.operacion;

        setVigentes(filtros);
        setCargando(true);
        setError(null);

        try {
            const respuesta = await api.get('/bitacora', { params });

            if (numeroConsulta !== ultimaConsulta.current) {
                return;
            }

            const datos = respuesta.data?.data ?? [];
            setOperaciones(datos);

            setUsuariosConocidos((anteriores) => {
                const porId = new Map(anteriores.map((usuario) => [usuario.id, usuario]));
                datos.forEach((operacion) => {
                    if (operacion.usuario) {
                        porId.set(operacion.usuario.id, operacion.usuario);
                    }
                });
                return [...porId.values()].sort((a, b) => nombreDe(a).localeCompare(nombreDe(b)));
            });

            setOperacionesConocidas((anteriores) => [
                ...new Set([...anteriores, ...datos.map((operacion) => operacion.operacion)]),
            ]);
        } catch (excepcion) {
            if (numeroConsulta !== ultimaConsulta.current) {
                return;
            }

            setOperaciones([]);
            setError(estadoDe(excepcion) === 422 ? 'filtros' : 'carga');
        } finally {
            if (numeroConsulta === ultimaConsulta.current) {
                setCargando(false);
            }
        }
    }, []);

    React.useEffect(() => {
        consultar({});
    }, [consultar]);

    function manejarFiltrar(evento) {
        evento.preventDefault();
        consultar({
            usuario: usuarioFiltro,
            desde: fechaFiltro,
            hasta: hastaFiltro,
            operacion: operacionFiltro,
        });
    }

    return (
        <div className="space-y-5">
            <Encabezado titulo="Bitácora" />

            <form
                className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:flex-wrap sm:items-end"
                onSubmit={manejarFiltrar}
            >
                <div className="flex min-w-0 flex-1 flex-col gap-1.5 sm:min-w-[220px]">
                    <label className="text-sm font-medium text-slate-700" htmlFor="usuario-filtro">
                        Usuario
                    </label>

                    <select
                        id="usuario-filtro"
                        className={CAMPO}
                        value={usuarioFiltro}
                        onChange={(e) => setUsuarioFiltro(e.target.value)}
                    >
                        <option value="">Todos los usuarios</option>
                        {usuariosConocidos.map((usuario) => (
                            <option key={usuario.id} value={usuario.id}>
                                {nombreDe(usuario)}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex min-w-0 flex-col gap-1.5">
                    <label className="text-sm font-medium text-slate-700" htmlFor="fecha-filtro">
                        Desde
                    </label>

                    <input
                        id="fecha-filtro"
                        type="date"
                        className={CAMPO}
                        value={fechaFiltro}
                        onChange={(e) => setFechaFiltro(e.target.value)}
                    />
                </div>

                <div className="flex min-w-0 flex-col gap-1.5">
                    <label className="text-sm font-medium text-slate-700" htmlFor="hasta-filtro">
                        Hasta
                    </label>

                    <input
                        id="hasta-filtro"
                        type="date"
                        className={CAMPO}
                        value={hastaFiltro}
                        onChange={(e) => setHastaFiltro(e.target.value)}
                    />
                </div>

                <div className="flex min-w-0 flex-col gap-1.5 sm:min-w-[200px]">
                    <label
                        className="text-sm font-medium text-slate-700"
                        htmlFor="operacion-filtro"
                    >
                        Operación
                    </label>

                    <select
                        id="operacion-filtro"
                        className={CAMPO}
                        value={operacionFiltro}
                        onChange={(e) => setOperacionFiltro(e.target.value)}
                    >
                        <option value="">Todas las operaciones</option>
                        {operacionesConocidas.map((codigo) => (
                            <option key={codigo} value={codigo}>
                                {etiquetaOperacion[codigo] ?? codigo}
                            </option>
                        ))}
                    </select>
                </div>

                <button
                    type="submit"
                    className="min-h-11 rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:cursor-not-allowed disabled:opacity-50"
                    disabled={cargando}
                >
                    Filtrar
                </button>
            </form>

            <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <div className="grid min-w-[760px] grid-cols-[1fr_1.3fr_1.2fr_1.6fr] items-center gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs font-semibold tracking-wide text-slate-500">
                    <div>FECHA Y HORA</div>
                    <div>USUARIO</div>
                    <div>ACCIÓN</div>
                    <div>ENTIDAD AFECTADA</div>
                </div>

                {error === 'filtros' ? (
                    <p role="alert" className="px-4 py-8 text-center text-sm text-danger-600">
                        Filtros no válidos
                    </p>
                ) : (
                    <EstadoCarga
                        cargando={cargando}
                        error={error}
                        vacio={operaciones.length === 0}
                        textoVacio="Sin eventos"
                        onReintentar={() => consultar(vigentes)}
                    >
                        {operaciones.map((operacion) => (
                            <div
                                key={operacion.id}
                                className="grid min-w-[760px] grid-cols-[1fr_1.3fr_1.2fr_1.6fr] items-center gap-3 border-b border-slate-100 px-4 py-3.5 text-sm last:border-b-0"
                            >
                                <div className="font-mono text-xs text-slate-500">
                                    {formatearFechaHora(operacion.fecha_operacion)}
                                </div>
                                <div className="font-semibold text-slate-800">
                                    {operacion.usuario ? (
                                        nombreDe(operacion.usuario)
                                    ) : (
                                        <span className="font-normal text-slate-500 italic">
                                            {operacion.operacion === 'sesion.fallida'
                                                ? 'Sin identificar'
                                                : 'Usuario eliminado'}
                                        </span>
                                    )}
                                </div>
                                <div>
                                    {etiquetaOperacion[operacion.operacion] ?? operacion.operacion}
                                </div>
                                <div className="text-slate-600">{describirEntidad(operacion)}</div>
                            </div>
                        ))}
                    </EstadoCarga>
                )}
            </div>

            {!cargando && !error && operaciones.length > 0 && (
                <p className="text-sm text-slate-600">{plural(operaciones.length, 'evento')}</p>
            )}
        </div>
    );
}

export default Bitacora;
