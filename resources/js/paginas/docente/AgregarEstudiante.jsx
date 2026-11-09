import PropTypes from 'prop-types';
import { useState } from 'react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';

const VACIO = {
    codigo_universitario: '',
    documento_identidad: '',
    nombres: '',
    apellidos: '',
};

// Qué pasó con el estudiante, según el resumen de la carga de una fila.
function avisoDe(resumen) {
    if (resumen.ya_inscritos > 0) return 'Ya estaba en la lista';
    if (resumen.conflictos > 0) return 'Pendiente: sus datos difieren del padrón';
    return 'Estudiante agregado';
}

// Un estudiante suelto a la lista del grupo, sin archivo (POST
// /docente/grupos/{id}/inscritos). Pasa por las mismas reglas que una fila
// de la lista; si se rechaza, el motivo queda en el formulario.
export default function AgregarEstudiante({ grupoId, onAgregado, onCerrar }) {
    const [datos, setDatos] = useState(VACIO);
    const [errores, setErrores] = useState({});
    const [rechazo, setRechazo] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const campo = (clave) => ({
        value: datos[clave],
        error: errores[clave],
        onChange: (e) => {
            setDatos((previos) => ({ ...previos, [clave]: e.target.value }));
            setErrores((previos) => ({ ...previos, [clave]: undefined }));
            setRechazo(null);
        },
    });

    async function guardar(e) {
        e.preventDefault();
        if (enviando) return;
        const faltan = Object.fromEntries(
            Object.entries(datos)
                .filter(([, valor]) => !valor.trim())
                .map(([clave]) => [clave, 'Obligatorio'])
        );
        if (Object.keys(faltan).length > 0) return setErrores(faltan);
        setEnviando(true);
        try {
            const respuesta = await api.post(`/docente/grupos/${grupoId}/inscritos`, datos);
            const resultado = respuesta.data;
            if (resultado?.resumen?.rechazados > 0) {
                setRechazo(resultado.rechazos?.[0]?.motivo ?? 'No se pudo agregar');
                return setEnviando(false);
            }
            return onAgregado(resultado, avisoDe(resultado?.resumen ?? {}));
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (Object.keys(campos).length > 0) setErrores(campos);
            else setRechazo(mensajeDe(fallo, 'No se pudo agregar'));
            return setEnviando(false);
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
                    <Boton type="submit" form="formulario-agregar" cargando={enviando}>
                        Agregar
                    </Boton>
                </>
            }
        >
            <form id="formulario-agregar" className="space-y-4" onSubmit={guardar} noValidate>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo
                        etiqueta="Código SIS"
                        requerido
                        inputMode="numeric"
                        autoFocus
                        {...campo('codigo_universitario')}
                    />
                    <Campo etiqueta="C.I." requerido {...campo('documento_identidad')} />
                </div>
                <Campo etiqueta="Nombres" requerido {...campo('nombres')} />
                <Campo etiqueta="Apellidos" requerido {...campo('apellidos')} />
                {rechazo && (
                    <p role="alert" className="text-sm text-danger-700">
                        {rechazo}
                    </p>
                )}
            </form>
        </Dialogo>
    );
}

AgregarEstudiante.propTypes = {
    grupoId: PropTypes.number.isRequired,
    onAgregado: PropTypes.func.isRequired,
    onCerrar: PropTypes.func.isRequired,
};
