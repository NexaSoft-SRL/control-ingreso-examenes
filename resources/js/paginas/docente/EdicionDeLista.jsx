import PropTypes from 'prop-types';
import { useState } from 'react';
import { Pencil, UserMinus, UserPlus } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Chips from '../../componentes/Chips';
import Dialogo from '../../componentes/Dialogo';
import { AreaTexto } from '../../componentes/Seleccion';
import { errorDeNombre } from '../../utiles/texto';

// La lista del grupo sin volver a cargar el archivo: agregar a un estudiante
// suelto (mismas reglas que una fila del archivo) y quitar a un inscrito con
// motivo. Las reglas del padrón las aplica el servidor.

const CAMPOS = ['codigo', 'documento', 'apellidos', 'nombres'];
const VACIO = { codigo: '', documento: '', apellidos: '', nombres: '' };

const MAXIMOS = { codigo: 10, documento: 30, apellidos: 100, nombres: 100 };

const validarCodigo = (valor) =>
    /^\d{5,10}$/.test(String(valor).trim()) ? null : 'Entre 5 y 10 dígitos';

// Los mismos formatos que exige el servidor (DatosDeInscrito).
const DOCUMENTO = /^\d{4,10}([\s-][0-9A-Za-z]{1,3}){0,2}$/;

function errorDe(campo, valor) {
    const texto = String(valor).trim();
    if (!texto) return 'Obligatorio';
    if (campo === 'codigo') return validarCodigo(texto);
    if (campo === 'documento' && !DOCUMENTO.test(texto))
        return 'De 4 a 10 dígitos y complemento («5003748-1A»)';
    if (campo === 'nombres' || campo === 'apellidos') return errorDeNombre(texto);
    return null;
}

const AVISOS = {
    NUEVO: 'Estudiante agregado al padrón e inscrito',
    INSCRITO: 'Estudiante inscrito',
    REINSCRITO: 'Estudiante inscrito de nuevo',
    CONFLICTO: 'Datos distintos al padrón · inscripción pendiente',
};

export function AgregarEstudiante({ grupo, onAgregado, onCerrar }) {
    const [datos, setDatos] = useState(VACIO);
    const [errores, setErrores] = useState({});
    const [negativa, setNegativa] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const cambiar = (campo) => (evento) => {
        const valor = evento.target.value;
        setDatos((previos) => ({ ...previos, [campo]: valor }));
        setErrores((previos) => ({ ...previos, [campo]: null }));
        setNegativa(null);
    };

    async function guardar(evento) {
        evento.preventDefault();
        const nuevos = Object.fromEntries(CAMPOS.map((c) => [c, errorDe(c, datos[c])]));
        setErrores(nuevos);
        if (Object.values(nuevos).some(Boolean)) return;

        setEnviando(true);
        setNegativa(null);
        try {
            const respuesta = await api.post(
                `/docente/grupos/${grupo.id}/inscritos`,
                Object.fromEntries(CAMPOS.map((c) => [c, datos[c].trim()]))
            );
            const resultado = respuesta.data?.resultado;
            onAgregado(AVISOS[resultado] ?? mensajeDe({ response: respuesta }, 'Listo'));
        } catch (error) {
            const delServidor = erroresDe(error);
            if (Object.keys(delServidor).length > 0) {
                setErrores(delServidor);
            } else {
                setNegativa(mensajeDe(error, 'No se pudo agregar'));
            }
        } finally {
            setEnviando(false);
        }
    }

    return (
        <Dialogo
            titulo="Agregar estudiante"
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton type="submit" form="agregar-estudiante" cargando={enviando}>
                        <UserPlus className="h-4 w-4" /> {enviando ? 'Agregando' : 'Agregar'}
                    </Boton>
                </>
            }
        >
            <p className="mb-4 break-words text-sm text-slate-600">
                {grupo.asignatura.nombre} · Grupo {grupo.codigo}
            </p>
            <form
                id="agregar-estudiante"
                noValidate
                onSubmit={guardar}
                className="grid gap-3 sm:grid-cols-2"
            >
                <Campo
                    etiqueta="Código SIS"
                    requerido
                    inputMode="numeric"
                    autoComplete="off"
                    maxLength={MAXIMOS.codigo}
                    value={datos.codigo}
                    onChange={cambiar('codigo')}
                    validar={validarCodigo}
                    error={errores.codigo ?? undefined}
                    autoFocus
                />
                <Campo
                    etiqueta="C.I."
                    requerido
                    autoComplete="off"
                    maxLength={MAXIMOS.documento}
                    value={datos.documento}
                    onChange={cambiar('documento')}
                    error={errores.documento ?? undefined}
                />
                <Campo
                    etiqueta="Apellidos"
                    requerido
                    autoComplete="off"
                    maxLength={MAXIMOS.apellidos}
                    value={datos.apellidos}
                    onChange={cambiar('apellidos')}
                    error={errores.apellidos ?? undefined}
                />
                <Campo
                    etiqueta="Nombres"
                    requerido
                    autoComplete="off"
                    maxLength={MAXIMOS.nombres}
                    value={datos.nombres}
                    onChange={cambiar('nombres')}
                    error={errores.nombres ?? undefined}
                />
            </form>
            {negativa && (
                <p
                    role="alert"
                    className="mt-3 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700"
                >
                    {negativa}
                </p>
            )}
        </Dialogo>
    );
}

AgregarEstudiante.propTypes = {
    grupo: PropTypes.shape({
        id: PropTypes.number.isRequired,
        codigo: PropTypes.string.isRequired,
        asignatura: PropTypes.shape({ nombre: PropTypes.string.isRequired }).isRequired,
    }).isRequired,
    onAgregado: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};

// Corrige los nombres y el C.I. de un estudiante. El código SIS no cambia. El
// estudiante es uno solo en el padrón: el cambio vale en todos sus grupos y
// queda en la bitácora. `ruta` es la del docente (`/docente/grupos/{g}/inscritos/{e}`)
// o la de la administración (`/estudiantes/{e}`).
export function EditarEstudiante({ estudiante, ruta, onEditado, onCerrar }) {
    const [datos, setDatos] = useState({
        documento: estudiante.documento ?? '',
        apellidos: estudiante.apellidos ?? '',
        nombres: estudiante.nombres ?? '',
    });
    const [errores, setErrores] = useState({});
    const [negativa, setNegativa] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const campos = ['documento', 'apellidos', 'nombres'];

    const cambiar = (campo) => (evento) => {
        const valor = evento.target.value;
        setDatos((previos) => ({ ...previos, [campo]: valor }));
        setErrores((previos) => ({ ...previos, [campo]: null }));
        setNegativa(null);
    };

    async function guardar(evento) {
        evento.preventDefault();
        const nuevos = Object.fromEntries(campos.map((c) => [c, errorDe(c, datos[c])]));
        setErrores(nuevos);
        if (Object.values(nuevos).some(Boolean)) return;

        setEnviando(true);
        setNegativa(null);
        try {
            await api.put(ruta, Object.fromEntries(campos.map((c) => [c, datos[c].trim()])));
            onEditado('Estudiante actualizado');
        } catch (error) {
            const delServidor = erroresDe(error);
            if (Object.keys(delServidor).length > 0) {
                setErrores(delServidor);
            } else {
                setNegativa(mensajeDe(error, 'No se pudo guardar'));
            }
        } finally {
            setEnviando(false);
        }
    }

    return (
        <Dialogo
            titulo="Editar estudiante"
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton type="submit" form="editar-estudiante" cargando={enviando}>
                        <Pencil className="h-4 w-4" /> {enviando ? 'Guardando' : 'Guardar'}
                    </Boton>
                </>
            }
        >
            <p className="mb-4 text-sm text-slate-600">
                Código SIS <span className="font-mono">{estudiante.codigo}</span> · se corrige en
                todas sus materias
            </p>
            <form
                id="editar-estudiante"
                noValidate
                onSubmit={guardar}
                className="grid gap-3 sm:grid-cols-2"
            >
                <Campo
                    etiqueta="C.I."
                    requerido
                    autoComplete="off"
                    maxLength={MAXIMOS.documento}
                    value={datos.documento}
                    onChange={cambiar('documento')}
                    error={errores.documento ?? undefined}
                    autoFocus
                />
                <Campo
                    etiqueta="Apellidos"
                    requerido
                    autoComplete="off"
                    maxLength={MAXIMOS.apellidos}
                    value={datos.apellidos}
                    onChange={cambiar('apellidos')}
                    error={errores.apellidos ?? undefined}
                />
                <Campo
                    etiqueta="Nombres"
                    requerido
                    autoComplete="off"
                    maxLength={MAXIMOS.nombres}
                    value={datos.nombres}
                    onChange={cambiar('nombres')}
                    error={errores.nombres ?? undefined}
                />
            </form>
            {negativa && (
                <p
                    role="alert"
                    className="mt-3 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700"
                >
                    {negativa}
                </p>
            )}
        </Dialogo>
    );
}

EditarEstudiante.propTypes = {
    estudiante: PropTypes.shape({
        codigo: PropTypes.string,
        documento: PropTypes.string,
        nombres: PropTypes.string,
        apellidos: PropTypes.string,
    }).isRequired,
    ruta: PropTypes.string.isRequired,
    onEditado: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};

const MOTIVOS = [
    { valor: 'RETIRO_MATERIA', etiqueta: 'Retiro de la materia' },
    { valor: 'CARGADO_POR_ERROR', etiqueta: 'Cargado por error' },
    { valor: 'OTRO', etiqueta: 'Otro' },
];
const DETALLE_MINIMO = 5;
const DETALLE_MAXIMO = 200;

const errorDelDetalle = (detalle) =>
    detalle.trim().length < DETALLE_MINIMO ? `Mínimo ${DETALLE_MINIMO} caracteres` : null;

export function QuitarInscrito({ grupoId, estudiante, onQuitado, onCerrar }) {
    const [motivo, setMotivo] = useState(MOTIVOS[0].valor);
    const [detalle, setDetalle] = useState('');
    const [tocado, setTocado] = useState(false);
    const [errorDetalle, setErrorDetalle] = useState(null);
    const [negativa, setNegativa] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const otro = motivo === 'OTRO';
    const errorVivo = errorDetalle ?? (otro && tocado ? errorDelDetalle(detalle) : null);

    function elegir(valor) {
        setMotivo(valor);
        setErrorDetalle(null);
        setNegativa(null);
    }

    async function quitar() {
        if (otro && errorDelDetalle(detalle)) {
            setTocado(true);
            return;
        }

        setEnviando(true);
        setNegativa(null);
        try {
            await api.post(
                `/docente/grupos/${grupoId}/inscritos/${estudiante.id}/baja`,
                otro ? { motivo, detalle: detalle.trim() } : { motivo }
            );
            onQuitado(`${estudiante.nombre} quitado del grupo`);
        } catch (error) {
            const delServidor = erroresDe(error);
            if (delServidor.detalle) {
                setErrorDetalle(delServidor.detalle);
            } else {
                setNegativa(mensajeDe(error, 'No se pudo quitar'));
            }
        } finally {
            setEnviando(false);
        }
    }

    return (
        <Dialogo
            titulo="Quitar del grupo"
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton type="button" variante="peligro" cargando={enviando} onClick={quitar}>
                        <UserMinus className="h-4 w-4" /> {enviando ? 'Quitando' : 'Quitar'}
                    </Boton>
                </>
            }
        >
            <p className="break-words text-sm font-medium text-slate-800">{estudiante.nombre}</p>
            <p className="mb-4 text-xs text-slate-600">
                <span className="font-mono">{estudiante.codigo}</span> · C.I. {estudiante.documento}
            </p>
            <span className="mb-1.5 block text-sm font-medium text-slate-700">Motivo</span>
            <Chips etiqueta="Motivo" opciones={MOTIVOS} valor={motivo} onCambiar={elegir} />
            {otro && (
                <AreaTexto
                    className="mt-3"
                    etiqueta="Detalle"
                    requerido
                    rows={2}
                    maxLength={DETALLE_MAXIMO}
                    value={detalle}
                    error={errorVivo ?? undefined}
                    pie={`${detalle.length}/${DETALLE_MAXIMO}`}
                    onChange={(evento) => {
                        setDetalle(evento.target.value);
                        setTocado(true);
                        setErrorDetalle(null);
                    }}
                />
            )}
            {negativa && (
                <p
                    role="alert"
                    className="mt-3 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700"
                >
                    {negativa}
                </p>
            )}
        </Dialogo>
    );
}

QuitarInscrito.propTypes = {
    grupoId: PropTypes.number.isRequired,
    estudiante: PropTypes.shape({
        id: PropTypes.number.isRequired,
        codigo: PropTypes.string,
        nombre: PropTypes.string.isRequired,
        documento: PropTypes.string,
    }).isRequired,
    onQuitado: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};
