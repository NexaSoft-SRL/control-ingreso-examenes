import PropTypes from 'prop-types';
import { useState } from 'react';
import { Copy, Plus } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import usarPaginaServidor from '../../api/usarPaginaServidor';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Campo from '../../componentes/Campo';
import Chips from '../../componentes/Chips';
import Dato from '../../componentes/Dato';
import Dialogo from '../../componentes/Dialogo';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import Paginacion from '../../componentes/Paginacion';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';
import PermisosPorRol from './PermisosPorRol';

const POR_PAGINA = 20;
const TH = 'px-4 py-3 font-medium';
const CABECERA = 'bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-600';
const FICHA =
    'inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-3.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const USUARIO = /^[a-z0-9._-]+$/i;
const CORREO = /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i;
const miles = (n) => Number(n ?? 0).toLocaleString('es-BO');

const FORMA_CUENTA = PropTypes.shape({
    id: PropTypes.number,
    nombre: PropTypes.string,
    usuario: PropTypes.string,
    correo: PropTypes.string,
    rol: PropTypes.string,
    estado: PropTypes.string,
});

function Estado({ estado }) {
    return (
        <Insignia tono={estado === 'activo' ? 'exito' : 'peligro'}>
            {estado === 'activo' ? 'Activo' : 'Bloqueado'}
        </Insignia>
    );
}

Estado.propTypes = { estado: PropTypes.string };

// La contraseña temporal recién emitida. Solo existe en el estado de quien
// la dibuja: al cerrar deja de estar.
function ContrasenaTemporal({ usuario, contrasena, enviadaA = null, conCorreo, avisar }) {
    function copiar() {
        const copia = navigator.clipboard?.writeText(contrasena);
        if (!copia) {
            avisar('No se pudo copiar', 'error');
            return;
        }
        copia
            .then(() => avisar('Contraseña copiada'))
            .catch(() => avisar('No se pudo copiar', 'error'));
    }

    return (
        <div className="space-y-3">
            <div className="rounded-lg border border-primary-200 bg-primary-50 p-4">
                <p className="text-xs font-medium uppercase tracking-wide text-primary-700">
                    Contraseña temporal
                </p>
                <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                    <code className="break-all text-lg font-semibold tracking-wider text-slate-900">
                        {contrasena}
                    </code>
                    <Boton type="button" variante="secundario" onClick={copiar}>
                        <Copy className="h-4 w-4" aria-hidden="true" /> Copiar
                    </Boton>
                </div>
                <p className="mt-2 break-words text-sm text-slate-700">
                    {usuario}
                    {enviadaA
                        ? ` · enviada a ${enviadaA}`
                        : conCorreo
                          ? ' · correo no enviado'
                          : ' · sin correo'}
                </p>
            </div>
            <p className="text-sm text-slate-600">Caduca en 72 horas</p>
        </div>
    );
}

ContrasenaTemporal.propTypes = {
    usuario: PropTypes.string,
    contrasena: PropTypes.string.isRequired,
    enviadaA: PropTypes.string,
    conCorreo: PropTypes.bool,
    avisar: PropTypes.func.isRequired,
};

// Alta o edición de una cuenta. `cuenta` sin id es una nueva. Con id suma
// el bloqueo y el restablecimiento de la contraseña.
function FormularioCuenta({ cuenta, roles, onCreada, onGuardada, onCerrar, avisar }) {
    const nueva = !cuenta.id;
    const [nombre, setNombre] = useState(cuenta.nombre ?? '');
    const [usuario, setUsuario] = useState(cuenta.usuario ?? '');
    const [correo, setCorreo] = useState(cuenta.correo ?? '');
    const [rol, setRol] = useState(cuenta.rol ?? '');
    const [estado, setEstado] = useState(cuenta.estado ?? 'activo');
    const [enviado, setEnviado] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [errores, setErrores] = useState({});
    const [rechazo, setRechazo] = useState(null);
    // null → 'confirmar' → 'enviando' → la respuesta con la temporal.
    const [temporal, setTemporal] = useState(null);

    const opciones = cuenta.rol && !roles.includes(cuenta.rol) ? [...roles, cuenta.rol] : roles;

    const errorNombre = !nombre.trim()
        ? 'Obligatorio'
        : nombre.trim().length < 3
          ? 'Mínimo 3 caracteres'
          : null;
    const errorUsuario = !usuario.trim()
        ? 'Obligatorio'
        : !USUARIO.test(usuario.trim())
          ? 'Solo letras, números, puntos y guiones'
          : null;
    const errorCorreo = correo.trim() && !CORREO.test(correo.trim()) ? 'Correo no válido' : null;
    const errorRol = !rol ? 'Obligatorio' : null;

    const cambiar = (campo, poner) => (e) => {
        poner(e.target.value);
        setErrores((previos) => ({ ...previos, [campo]: undefined }));
    };

    function enviar(e) {
        e.preventDefault();
        setEnviado(true);
        setRechazo(null);
        if (errorNombre || errorUsuario || errorCorreo || errorRol || enviando) return;

        const escrito = correo.trim().toLowerCase();
        const cuerpo = {
            nombre: nombre.trim(),
            usuario: usuario.trim().toLowerCase(),
            correo: escrito === '' ? null : escrito,
            rol,
        };
        if (!nueva && estado !== cuenta.estado) cuerpo.activo = estado === 'activo';

        const mensaje =
            estado === cuenta.estado
                ? 'Cambios guardados'
                : estado === 'bloqueado'
                  ? 'Cuenta bloqueada'
                  : 'Cuenta desbloqueada';

        setEnviando(true);
        (nueva ? api.post('/usuarios', cuerpo) : api.put(`/usuarios/${cuenta.id}`, cuerpo))
            .then((respuesta) => {
                if (nueva) {
                    onCreada({ ...respuesta.data, conCorreo: escrito !== '' });
                    return;
                }
                onGuardada(mensaje);
            })
            .catch((error) => {
                setEnviando(false);
                const porCampo = erroresDe(error);
                setErrores(porCampo);
                if (Object.keys(porCampo).length === 0) {
                    setRechazo(mensajeDe(error, 'No se pudo guardar'));
                }
            });
    }

    function restablecer() {
        setTemporal('enviando');
        setRechazo(null);
        api.post(`/usuarios/${cuenta.id}/contrasena-temporal`)
            .then((respuesta) => {
                setTemporal(respuesta.data);
                avisar('Contraseña restablecida');
            })
            .catch((error) => {
                setTemporal(null);
                setRechazo(mensajeDe(error, 'No se pudo restablecer'));
            });
    }

    const emitida = temporal !== null && typeof temporal === 'object' ? temporal : null;

    return (
        <Dialogo
            titulo={nueva ? 'Nuevo usuario' : 'Editar usuario'}
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton type="submit" form="formulario-cuenta" disabled={enviando}>
                        {nueva ? 'Crear' : 'Guardar'}
                    </Boton>
                </>
            }
        >
            <form id="formulario-cuenta" className="space-y-4" onSubmit={enviar} noValidate>
                <Campo
                    etiqueta="Nombre"
                    requerido
                    value={nombre}
                    error={errores.nombre ?? (enviado ? (errorNombre ?? undefined) : undefined)}
                    validar={() => errorNombre}
                    onChange={cambiar('nombre', setNombre)}
                />
                <Campo
                    etiqueta="Usuario"
                    requerido
                    autoCapitalize="none"
                    autoComplete="off"
                    spellCheck={false}
                    value={usuario}
                    error={errores.usuario ?? (enviado ? (errorUsuario ?? undefined) : undefined)}
                    validar={() => errorUsuario}
                    onChange={cambiar('usuario', setUsuario)}
                />
                <Campo
                    etiqueta="Correo"
                    type="email"
                    autoCapitalize="none"
                    autoComplete="off"
                    placeholder="usuario@umss.edu.bo"
                    value={correo}
                    error={errores.correo ?? (enviado ? (errorCorreo ?? undefined) : undefined)}
                    validar={() => errorCorreo}
                    onChange={cambiar('correo', setCorreo)}
                />
                <div>
                    <Seleccion
                        etiqueta="Rol"
                        requerido
                        value={rol}
                        aria-invalid={errores.rol ? 'true' : undefined}
                        onChange={cambiar('rol', setRol)}
                    >
                        {!rol && <option value="">Sin rol</option>}
                        {opciones.map((r) => (
                            <option key={r}>{r}</option>
                        ))}
                    </Seleccion>
                    {(errores.rol || (enviado && errorRol)) && (
                        <span className="mt-1.5 block text-sm text-danger-600">
                            {errores.rol ?? errorRol}
                        </span>
                    )}
                </div>
                {!nueva && (
                    <div className="space-y-3 border-t border-slate-200 pt-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <Estado estado={estado} />
                            <div className="flex flex-wrap gap-2">
                                {temporal === 'confirmar' ? (
                                    <>
                                        <Boton
                                            type="button"
                                            variante="secundario"
                                            tamano="chico"
                                            className="min-h-10"
                                            onClick={() => setTemporal(null)}
                                        >
                                            No restablecer
                                        </Boton>
                                        <Boton
                                            type="button"
                                            tamano="chico"
                                            className="min-h-10"
                                            onClick={restablecer}
                                        >
                                            Confirmar
                                        </Boton>
                                    </>
                                ) : (
                                    <>
                                        <Boton
                                            type="button"
                                            variante="secundario"
                                            tamano="chico"
                                            className="min-h-10"
                                            disabled={temporal === 'enviando'}
                                            onClick={() => setTemporal('confirmar')}
                                        >
                                            Restablecer contraseña
                                        </Boton>
                                        <Boton
                                            type="button"
                                            variante={
                                                estado === 'activo'
                                                    ? 'peligroContorno'
                                                    : 'secundario'
                                            }
                                            tamano="chico"
                                            className="min-h-10"
                                            onClick={() =>
                                                setEstado(
                                                    estado === 'activo' ? 'bloqueado' : 'activo'
                                                )
                                            }
                                        >
                                            {estado === 'activo' ? 'Bloquear' : 'Desbloquear'}
                                        </Boton>
                                    </>
                                )}
                            </div>
                        </div>
                        {emitida && (
                            <ContrasenaTemporal
                                usuario={cuenta.usuario}
                                contrasena={emitida.contrasena_temporal}
                                enviadaA={emitida.enviada_a}
                                conCorreo={Boolean(cuenta.correo)}
                                avisar={avisar}
                            />
                        )}
                    </div>
                )}
                {rechazo && (
                    <p role="alert" className="text-sm text-danger-600">
                        {rechazo}
                    </p>
                )}
            </form>
        </Dialogo>
    );
}

FormularioCuenta.propTypes = {
    cuenta: FORMA_CUENTA.isRequired,
    roles: PropTypes.arrayOf(PropTypes.string).isRequired,
    onCreada: PropTypes.func.isRequired,
    onGuardada: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
    avisar: PropTypes.func.isRequired,
};

// HU-15 · No hay registro público: las cuentas se crean aquí. Los roles del
// filtro y de las cifras son los que existen (`meta.conteos`), no una lista
// fija; la matriz de permisos va en PermisosPorRol.
export default function Usuarios() {
    const lista = usarPaginaServidor({
        porPagina: POR_PAGINA,
        filtros: { rol: null, bloqueadas: null },
    });
    const { datos, meta, cargando, error, recargar } = usarConsulta('/usuarios', {
        parametros: lista.parametros,
    });
    const roles = usarConsulta('/roles');
    const [editando, setEditando] = useState(null);
    // La contraseña temporal de la cuenta recién creada, hasta cerrar.
    const [creada, setCreada] = useState(null);
    const [aviso, avisar] = useAviso();

    const { bloqueadas = 0, ...porRol } = meta?.conteos ?? {};
    const nombresDeRol = Object.keys(porRol);
    const rolesParaCuenta = roles.datos ? roles.datos.map((r) => r.nombre) : nombresDeRol;
    const { rol, bloqueadas: soloBloqueadas } = lista.filtros;
    const cuentas = datos ?? [];

    function nuevaCuenta() {
        const inicial =
            rol ?? (rolesParaCuenta.includes('Docente') ? 'Docente' : (rolesParaCuenta[0] ?? null));
        setEditando({ nombre: '', usuario: '', correo: null, rol: inicial, estado: 'activo' });
    }

    function alCrear(respuesta) {
        // La nueva queda arriba y a la vista: se quitan los filtros.
        setEditando(null);
        setCreada(respuesta);
        lista.ponerFiltros({ rol: null, bloqueadas: null });
        lista.ponerBuscar('');
        avisar('Usuario creado');
        recargar();
        roles.recargar();
    }

    function alGuardar(mensaje) {
        setEditando(null);
        avisar(mensaje);
        recargar();
        roles.recargar();
    }

    // Un rol creado, renombrado o eliminado cambia el filtro y las cifras.
    function alCambiarRoles({ anterior } = {}) {
        if (anterior && rol === anterior) lista.ponerFiltro('rol', null);
        return Promise.all([roles.recargar(), recargar()]);
    }

    return (
        <div className="space-y-6">
            <Encabezado titulo="Usuarios y roles">
                <Boton onClick={nuevaCuenta} disabled={rolesParaCuenta.length === 0}>
                    <Plus className="h-4 w-4" strokeWidth={2.5} aria-hidden="true" />
                    Nuevo usuario
                </Boton>
            </Encabezado>

            {meta && (
                <>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {nombresDeRol.map((nombre) => (
                            <div key={nombre} className="min-w-0">
                                <Dato
                                    etiqueta={nombre}
                                    tono={rol === nombre ? 'primario' : 'neutro'}
                                    onClick={() =>
                                        lista.ponerFiltro('rol', rol === nombre ? null : nombre)
                                    }
                                    valor={
                                        <>
                                            {miles(porRol[nombre])}{' '}
                                            <span className="hidden text-sm font-normal text-slate-600 sm:inline">
                                                {porRol[nombre] === 1 ? 'cuenta' : 'cuentas'}
                                            </span>
                                        </>
                                    }
                                />
                            </div>
                        ))}
                    </div>

                    <Chips
                        etiqueta="Filtrar por rol"
                        valor={rol}
                        onCambiar={(valor) => lista.ponerFiltro('rol', valor)}
                        opciones={[
                            { valor: null, etiqueta: 'Todos', conteo: miles(meta.cuentas) },
                            ...nombresDeRol.map((nombre) => ({
                                valor: nombre,
                                etiqueta: nombre,
                                conteo: miles(porRol[nombre]),
                            })),
                        ]}
                        extra={
                            <button
                                type="button"
                                aria-pressed={Boolean(soloBloqueadas)}
                                onClick={() =>
                                    lista.ponerFiltro('bloqueadas', soloBloqueadas ? null : 1)
                                }
                                className={`${FICHA} ${
                                    soloBloqueadas
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                Bloqueadas{' '}
                                <span
                                    className={soloBloqueadas ? 'text-slate-300' : 'text-slate-500'}
                                >
                                    {miles(bloqueadas)}
                                </span>
                            </button>
                        }
                    />
                </>
            )}

            <Tarjeta sinRelleno titulo="Cuentas">
                <div className="border-b border-slate-200 p-4">
                    <Buscador
                        className="sm:max-w-sm"
                        valor={lista.buscar}
                        onCambiar={lista.ponerBuscar}
                        placeholder="Nombre, usuario o correo"
                        etiqueta="Buscar cuenta"
                    />
                </div>
                <EstadoCarga
                    cargando={cargando}
                    error={error}
                    vacio={datos?.length === 0}
                    textoVacio="Sin resultados"
                    onReintentar={recargar}
                    filas={8}
                >
                    <ul className="divide-y divide-slate-200 sm:hidden">
                        {cuentas.map((u) => (
                            <li key={u.id} className="flex items-center gap-3 px-4 py-3">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-slate-800">
                                        {u.nombre}
                                    </p>
                                    <p className="truncate text-xs text-slate-600">
                                        {u.usuario} · {u.correo || 'Sin correo'}
                                    </p>
                                    <p className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-slate-700">
                                        {u.rol ?? 'Sin rol'} <Estado estado={u.estado} />
                                    </p>
                                </div>
                                <Boton
                                    variante="enlace"
                                    tamano="chico"
                                    className="min-h-10 shrink-0"
                                    aria-label={`Editar a ${u.nombre}`}
                                    onClick={() => setEditando(u)}
                                >
                                    Editar
                                </Boton>
                            </li>
                        ))}
                    </ul>
                    <table
                        className="hidden w-full table-fixed text-left text-sm sm:table"
                        aria-label="Cuentas"
                    >
                        <thead className={CABECERA}>
                            <tr>
                                <th scope="col" className={TH}>
                                    Nombre
                                </th>
                                <th scope="col" className={TH}>
                                    Usuario
                                </th>
                                <th scope="col" className={`${TH} w-36`}>
                                    Rol
                                </th>
                                <th scope="col" className={`${TH} w-32`}>
                                    Estado
                                </th>
                                <th scope="col" className={`${TH} w-24`}>
                                    <span className="sr-only">Acciones</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {cuentas.map((u) => (
                                <tr key={u.id} className="hover:bg-slate-50">
                                    <td
                                        className="truncate px-4 py-3 font-medium text-slate-800"
                                        title={u.nombre}
                                    >
                                        {u.nombre}
                                    </td>
                                    <td
                                        className="truncate px-4 py-3 text-slate-700"
                                        title={u.correo || 'Sin correo'}
                                    >
                                        {u.usuario}
                                        <span className="block truncate text-xs text-slate-500">
                                            {u.correo || 'Sin correo'}
                                        </span>
                                    </td>
                                    <td
                                        className="truncate px-4 py-3 text-slate-700"
                                        title={u.rol ?? 'Sin rol'}
                                    >
                                        {u.rol ?? 'Sin rol'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Estado estado={u.estado} />
                                    </td>
                                    <td className="px-4 py-1.5 text-right">
                                        <Boton
                                            variante="enlace"
                                            tamano="chico"
                                            className="min-h-10"
                                            aria-label={`Editar a ${u.nombre}`}
                                            onClick={() => setEditando(u)}
                                        >
                                            Editar
                                        </Boton>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </EstadoCarga>
                {meta && meta.total > 0 && (
                    <Paginacion
                        {...lista.paginacion(meta)}
                        unidad={['cuenta', 'cuentas']}
                        className="border-t border-slate-200"
                    />
                )}
            </Tarjeta>

            <PermisosPorRol
                roles={roles.datos}
                permisos={roles.meta?.permisos ?? []}
                cargando={roles.cargando}
                error={roles.error}
                onReintentar={roles.recargar}
                onCambio={alCambiarRoles}
                avisar={avisar}
            />

            {editando && (
                <FormularioCuenta
                    key={editando.id ?? 'nueva'}
                    cuenta={editando}
                    roles={rolesParaCuenta}
                    onCreada={alCrear}
                    onGuardada={alGuardar}
                    onCerrar={() => setEditando(null)}
                    avisar={avisar}
                />
            )}
            {creada && (
                <Dialogo
                    titulo="Usuario creado"
                    onCerrar={() => setCreada(null)}
                    acciones={<Boton onClick={() => setCreada(null)}>Cerrar</Boton>}
                >
                    <ContrasenaTemporal
                        usuario={creada.data?.usuario}
                        contrasena={creada.contrasena_temporal}
                        enviadaA={creada.enviada_a}
                        conCorreo={creada.conCorreo}
                        avisar={avisar}
                    />
                </Dialogo>
            )}
            {aviso}
        </div>
    );
}
