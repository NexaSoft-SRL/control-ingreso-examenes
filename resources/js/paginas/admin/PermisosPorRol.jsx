import PropTypes from 'prop-types';
import { useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Chips from '../../componentes/Chips';
import Dialogo from '../../componentes/Dialogo';
import EstadoCarga from '../../componentes/EstadoCarga';
import Tarjeta from '../../componentes/Tarjeta';
import { usarSesion } from '../../sesion/SesionContexto';

const TH = 'px-4 py-3 font-medium';
const CABECERA = 'bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-600';
const CASILLA =
    'h-5 w-5 shrink-0 rounded border-slate-300 text-primary-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const ICONO =
    'flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600';
const cuentasDe = (n) => `${n.toLocaleString('es-BO')} ${n === 1 ? 'cuenta' : 'cuentas'}`;

const FORMA_ROL = PropTypes.shape({
    id: PropTypes.number.isRequired,
    nombre: PropTypes.string.isRequired,
    es_sistema: PropTypes.bool,
    cuentas: PropTypes.number,
    permisos: PropTypes.arrayOf(PropTypes.string).isRequired,
});

const FORMA_PERMISOS = PropTypes.arrayOf(
    PropTypes.shape({
        clave: PropTypes.string.isRequired,
        pantalla: PropTypes.string.isRequired,
    })
);

// El error de `permisos` puede venir en `permisos` o en `permisos.N`.
function errorDePermisos(porCampo) {
    const campo = Object.keys(porCampo).find((c) => c === 'permisos' || c.startsWith('permisos.'));
    return campo ? porCampo[campo] : undefined;
}

// «Nuevo rol» o edición de un rol creado: nombre y las pantallas que alcanza.
function FormularioRol({ rol = null, permisos, onGuardado, onCerrar }) {
    const nuevo = rol === null;
    const [nombre, setNombre] = useState(rol?.nombre ?? '');
    const [marcados, setMarcados] = useState(rol?.permisos ?? []);
    const [enviado, setEnviado] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [errores, setErrores] = useState({});
    const [rechazo, setRechazo] = useState(null);

    const errorNombre = !nombre.trim()
        ? 'Obligatorio'
        : nombre.trim().length < 3
          ? 'Mínimo 3 caracteres'
          : null;
    const errorPermisos =
        errorDePermisos(errores) ??
        (enviado && marcados.length === 0 ? 'Al menos un permiso' : null);

    function marcar(clave) {
        setMarcados((previos) =>
            previos.includes(clave) ? previos.filter((c) => c !== clave) : [...previos, clave]
        );
        setErrores((previos) => ({ nombre: previos.nombre }));
    }

    function enviar(e) {
        e.preventDefault();
        setEnviado(true);
        setRechazo(null);
        if (errorNombre || marcados.length === 0 || enviando) return;

        const cuerpo = {
            nombre: nombre.trim(),
            permisos: permisos.map((p) => p.clave).filter((c) => marcados.includes(c)),
        };
        setEnviando(true);
        (nuevo ? api.post('/roles', cuerpo) : api.put(`/roles/${rol.id}`, cuerpo))
            .then(() => onGuardado(nuevo ? 'Rol creado' : 'Cambios guardados', cuerpo.nombre))
            .catch((error) => {
                setEnviando(false);
                const porCampo = erroresDe(error);
                setErrores(porCampo);
                if (!porCampo.nombre && !errorDePermisos(porCampo)) {
                    setRechazo(mensajeDe(error, 'No se pudo guardar'));
                }
            });
    }

    return (
        <Dialogo
            titulo={nuevo ? 'Nuevo rol' : 'Editar rol'}
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton type="submit" form="formulario-rol" disabled={enviando}>
                        {nuevo ? 'Crear' : 'Guardar'}
                    </Boton>
                </>
            }
        >
            <form id="formulario-rol" className="space-y-4" onSubmit={enviar} noValidate>
                <Campo
                    etiqueta="Nombre"
                    requerido
                    maxLength={40}
                    value={nombre}
                    error={errores.nombre ?? (enviado ? (errorNombre ?? undefined) : undefined)}
                    validar={() => errorNombre}
                    onChange={(e) => {
                        setNombre(e.target.value);
                        setErrores((previos) => ({ ...previos, nombre: undefined }));
                    }}
                />
                <fieldset>
                    <legend className="mb-1.5 text-sm font-medium text-slate-700">
                        Permisos
                        <span className="ml-0.5 text-danger-600" aria-hidden="true">
                            *
                        </span>
                    </legend>
                    <ul
                        className={`divide-y divide-slate-200 rounded-lg border ${
                            errorPermisos ? 'border-danger-600' : 'border-slate-300'
                        }`}
                    >
                        {permisos.map((p) => (
                            <li key={p.clave}>
                                <label className="flex min-h-11 cursor-pointer items-center gap-3 px-3.5 py-2 text-sm text-slate-800">
                                    <input
                                        type="checkbox"
                                        className={CASILLA}
                                        checked={marcados.includes(p.clave)}
                                        onChange={() => marcar(p.clave)}
                                    />
                                    <span className="min-w-0">{p.pantalla}</span>
                                </label>
                            </li>
                        ))}
                    </ul>
                    {errorPermisos && (
                        <span className="mt-1.5 block text-sm text-danger-600">
                            {errorPermisos}
                        </span>
                    )}
                </fieldset>
                {rechazo && (
                    <p role="alert" className="text-sm text-danger-600">
                        {rechazo}
                    </p>
                )}
            </form>
        </Dialogo>
    );
}

FormularioRol.propTypes = {
    rol: FORMA_ROL,
    permisos: FORMA_PERMISOS.isRequired,
    onGuardado: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};

// Baja de un rol creado. El servidor la niega con su motivo (409) si el rol
// es de inicio o tiene cuentas.
function EliminarRol({ rol, onEliminado, onCerrar }) {
    const [enviando, setEnviando] = useState(false);
    const [rechazo, setRechazo] = useState(null);

    function eliminar() {
        setEnviando(true);
        setRechazo(null);
        api.delete(`/roles/${rol.id}`)
            .then(() => onEliminado())
            .catch((error) => {
                setEnviando(false);
                setRechazo(mensajeDe(error, 'No se pudo eliminar'));
            });
    }

    return (
        <Dialogo
            titulo="Eliminar rol"
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton variante="peligro" onClick={eliminar} disabled={enviando}>
                        Eliminar
                    </Boton>
                </>
            }
        >
            <p className="text-sm font-medium text-slate-800">{rol.nombre}</p>
            <p className="mt-0.5 text-sm text-slate-600">{cuentasDe(rol.cuentas ?? 0)}</p>
            {rechazo && (
                <p role="alert" className="mt-3 text-sm text-danger-600">
                    {rechazo}
                </p>
            )}
        </Dialogo>
    );
}

EliminarRol.propTypes = {
    rol: FORMA_ROL.isRequired,
    onEliminado: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};

// Matriz de pantallas por rol, editable: una columna por rol existente. Los
// roles de inicio cambian de permisos pero no de nombre, y no se eliminan.
// `onCambio({ anterior })` avisa a la página de que los roles cambiaron
// (`anterior` es el nombre que dejó de existir) y devuelve la recarga.
export default function PermisosPorRol({
    roles = null,
    permisos,
    cargando = false,
    error = null,
    onReintentar,
    onCambio,
    avisar,
}) {
    const { usuario, actualizar } = usarSesion();
    // Permisos marcados sin guardar, por id de rol.
    const [borrador, setBorrador] = useState({});
    const [guardando, setGuardando] = useState(false);
    const [fallos, setFallos] = useState([]);
    const [formulario, setFormulario] = useState(null);
    const [eliminando, setEliminando] = useState(null);
    const [enMovil, setEnMovil] = useState(null);

    const lista = roles ?? [];
    const orden = permisos.map((p) => p.clave);
    const marcadosDe = (rol) => borrador[rol.id] ?? rol.permisos;
    const cambio = (rol) => {
        const actuales = marcadosDe(rol);
        return (
            actuales.length !== rol.permisos.length ||
            actuales.some((c) => !rol.permisos.includes(c))
        );
    };
    const cambiados = lista.filter(cambio);
    const rolMovil = lista.find((r) => r.id === enMovil) ?? lista[0] ?? null;

    function marcar(rol, clave) {
        const actuales = marcadosDe(rol);
        const nuevos = actuales.includes(clave)
            ? actuales.filter((c) => c !== clave)
            : [...actuales, clave];
        setBorrador((previo) => ({ ...previo, [rol.id]: orden.filter((c) => nuevos.includes(c)) }));
        setFallos([]);
    }

    function descartar() {
        setBorrador({});
        setFallos([]);
    }

    async function guardar() {
        setGuardando(true);
        setFallos([]);
        const hechos = [];
        const negados = [];
        for (const rol of cambiados) {
            try {
                await api.put(`/roles/${rol.id}`, { permisos: marcadosDe(rol) });
                hechos.push(rol);
            } catch (rechazo) {
                negados.push({
                    id: rol.id,
                    rol: rol.nombre,
                    mensaje:
                        errorDePermisos(erroresDe(rechazo)) ??
                        mensajeDe(rechazo, 'No se pudo guardar'),
                });
            }
        }
        if (hechos.length > 0) {
            await onCambio();
            // El menú propio sale de los permisos del rol de la cuenta.
            if (hechos.some((r) => r.nombre === usuario?.rol)) actualizar();
        }
        setFallos(negados);
        setGuardando(false);
        if (negados.length === 0) avisar('Cambios guardados');
    }

    function alGuardarRol(mensaje, nombre) {
        const editado = formulario?.rol ?? null;
        if (editado) {
            setBorrador((previo) =>
                Object.fromEntries(
                    Object.entries(previo).filter(([id]) => Number(id) !== editado.id)
                )
            );
            if (editado.nombre === usuario?.rol) actualizar();
        }
        setFormulario(null);
        avisar(mensaje);
        onCambio(editado && editado.nombre !== nombre ? { anterior: editado.nombre } : undefined);
    }

    function alEliminar() {
        const anterior = eliminando.nombre;
        setEliminando(null);
        avisar('Rol eliminado');
        onCambio({ anterior });
    }

    const casilla = (rol, p) => (
        <input
            type="checkbox"
            className={CASILLA}
            aria-label={`${p.pantalla} · ${rol.nombre}`}
            checked={marcadosDe(rol).includes(p.clave)}
            disabled={guardando}
            onChange={() => marcar(rol, p.clave)}
        />
    );

    return (
        <Tarjeta
            sinRelleno
            titulo="Permisos por rol"
            acciones={
                <Boton
                    variante="secundario"
                    tamano="chico"
                    className="min-h-10"
                    disabled={permisos.length === 0}
                    onClick={() => setFormulario({ rol: null })}
                >
                    <Plus className="h-4 w-4" strokeWidth={2.5} aria-hidden="true" />
                    Nuevo rol
                </Boton>
            }
        >
            <EstadoCarga
                cargando={cargando}
                error={error}
                vacio={roles !== null && lista.length === 0}
                textoVacio="Sin roles"
                onReintentar={onReintentar}
                filas={6}
            >
                {rolMovil && (
                    <div className="sm:hidden">
                        <div className="space-y-3 border-b border-slate-200 p-4">
                            <Chips
                                etiqueta="Rol"
                                valor={rolMovil.id}
                                onCambiar={setEnMovil}
                                opciones={lista.map((r) => ({ valor: r.id, etiqueta: r.nombre }))}
                            />
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-xs text-slate-600">
                                    {cuentasDe(rolMovil.cuentas ?? 0)}
                                </span>
                                {!rolMovil.es_sistema && (
                                    <span className="flex gap-2">
                                        <Boton
                                            variante="secundario"
                                            tamano="chico"
                                            className="min-h-10"
                                            aria-label={`Editar rol ${rolMovil.nombre}`}
                                            onClick={() => setFormulario({ rol: rolMovil })}
                                        >
                                            Editar
                                        </Boton>
                                        <Boton
                                            variante="peligroContorno"
                                            tamano="chico"
                                            className="min-h-10"
                                            aria-label={`Eliminar rol ${rolMovil.nombre}`}
                                            onClick={() => setEliminando(rolMovil)}
                                        >
                                            Eliminar
                                        </Boton>
                                    </span>
                                )}
                            </div>
                        </div>
                        <ul className="divide-y divide-slate-200">
                            {permisos.map((p) => (
                                <li key={p.clave}>
                                    <label className="flex min-h-11 cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-sm text-slate-800">
                                        <span className="min-w-0">{p.pantalla}</span>
                                        {casilla(rolMovil, p)}
                                    </label>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
                <div className="hidden overflow-x-auto sm:block">
                    <table className="w-full text-left text-sm" aria-label="Permisos por rol">
                        <thead className={CABECERA}>
                            <tr>
                                <th scope="col" className={TH}>
                                    Pantalla
                                </th>
                                {lista.map((r) => (
                                    <th
                                        key={r.id}
                                        scope="col"
                                        className={`${TH} text-center normal-case`}
                                    >
                                        <span className="block uppercase">{r.nombre}</span>
                                        <span className="block font-normal tracking-normal text-slate-500">
                                            {cuentasDe(r.cuentas ?? 0)}
                                        </span>
                                        {!r.es_sistema && (
                                            <span className="mt-1 flex justify-center gap-1">
                                                <button
                                                    type="button"
                                                    className={ICONO}
                                                    aria-label={`Editar rol ${r.nombre}`}
                                                    onClick={() => setFormulario({ rol: r })}
                                                >
                                                    <Pencil
                                                        className="h-4 w-4"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    className={ICONO}
                                                    aria-label={`Eliminar rol ${r.nombre}`}
                                                    onClick={() => setEliminando(r)}
                                                >
                                                    <Trash2
                                                        className="h-4 w-4"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                            </span>
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {permisos.map((p) => (
                                <tr key={p.clave} className="hover:bg-slate-50">
                                    <th
                                        scope="row"
                                        className="px-4 py-3 font-normal text-slate-800"
                                    >
                                        {p.pantalla}
                                    </th>
                                    {lista.map((r) => (
                                        <td key={r.id} className="px-4 py-1 text-center">
                                            <label className="inline-flex min-h-10 min-w-10 cursor-pointer items-center justify-center">
                                                {casilla(r, p)}
                                            </label>
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                    <div className="min-w-0 text-sm" role={fallos.length > 0 ? 'alert' : undefined}>
                        {fallos.map((f) => (
                            <p key={f.id} className="text-danger-600">
                                {f.rol}: {f.mensaje}
                            </p>
                        ))}
                    </div>
                    <div className="flex shrink-0 gap-2">
                        <Boton
                            variante="secundario"
                            tamano="chico"
                            className="min-h-10"
                            disabled={cambiados.length === 0 || guardando}
                            onClick={descartar}
                        >
                            Descartar
                        </Boton>
                        <Boton
                            tamano="chico"
                            className="min-h-10"
                            disabled={cambiados.length === 0 || guardando}
                            onClick={guardar}
                        >
                            Guardar
                        </Boton>
                    </div>
                </div>
            </EstadoCarga>

            {formulario && (
                <FormularioRol
                    key={formulario.rol?.id ?? 'nuevo'}
                    rol={formulario.rol}
                    permisos={permisos}
                    onGuardado={alGuardarRol}
                    onCerrar={() => setFormulario(null)}
                />
            )}
            {eliminando && (
                <EliminarRol
                    rol={eliminando}
                    onEliminado={alEliminar}
                    onCerrar={() => setEliminando(null)}
                />
            )}
        </Tarjeta>
    );
}

PermisosPorRol.propTypes = {
    roles: PropTypes.arrayOf(FORMA_ROL),
    permisos: FORMA_PERMISOS.isRequired,
    cargando: PropTypes.bool,
    error: PropTypes.any,
    onReintentar: PropTypes.func,
    onCambio: PropTypes.func.isRequired,
    avisar: PropTypes.func.isRequired,
};
