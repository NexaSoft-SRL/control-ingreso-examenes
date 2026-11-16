import PropTypes from 'prop-types';
import { useState } from 'react';
import { api } from '../api/cliente';
import { erroresDe, mensajeDe } from '../api/errores';
import Dialogo from './Dialogo';
import Campo from './Campo';
import Boton from './Boton';
import { ListaReglas, cumpleReglas } from '../paginas/auth/reglasContrasena';

// Cambio de contraseña de la propia cuenta, con sesión abierta. El servidor
// pide la actual y que la nueva sea distinta.
export default function CambiarContrasena({ onCerrar, onGuardada }) {
    const [actual, setActual] = useState('');
    const [clave, setClave] = useState('');
    const [repetida, setRepetida] = useState('');
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const listo = actual !== '' && cumpleReglas(clave) && clave === repetida;

    async function guardar(e) {
        e?.preventDefault();
        if (!listo || enviando) return;
        setEnviando(true);
        setErrores({});
        setGeneral(null);
        try {
            await api.post('/auth/contrasena', {
                contrasena_actual: actual,
                contrasena_nueva: clave,
                contrasena_nueva_confirmation: repetida,
            });
            onGuardada();
        } catch (error) {
            const campos = erroresDe(error);
            setErrores(campos);
            if (Object.keys(campos).length === 0) {
                setGeneral(mensajeDe(error, 'No se pudo guardar'));
            }
            setEnviando(false);
        }
    }

    return (
        <Dialogo
            titulo="Cambiar contraseña"
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton type="button" variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton
                        type="submit"
                        form="cambiar-contrasena"
                        disabled={!listo}
                        cargando={enviando}
                    >
                        Guardar
                    </Boton>
                </>
            }
        >
            <form id="cambiar-contrasena" className="space-y-4" noValidate onSubmit={guardar}>
                <Campo
                    etiqueta="Contraseña actual"
                    requerido
                    type="password"
                    autoComplete="current-password"
                    value={actual}
                    error={errores.contrasena_actual}
                    onChange={(e) => {
                        setActual(e.target.value);
                        setErrores({});
                    }}
                />
                <Campo
                    etiqueta="Contraseña nueva"
                    requerido
                    type="password"
                    autoComplete="new-password"
                    value={clave}
                    error={errores.contrasena_nueva}
                    onChange={(e) => {
                        setClave(e.target.value);
                        setErrores({});
                    }}
                />
                <ListaReglas clave={clave} />
                <Campo
                    etiqueta="Repetir contraseña nueva"
                    requerido
                    type="password"
                    autoComplete="new-password"
                    value={repetida}
                    error={errores.contrasena_nueva_confirmation}
                    onChange={(e) => setRepetida(e.target.value)}
                    validar={(v) => (v === clave ? null : 'No coincide')}
                />
                {general && (
                    <p role="alert" className="text-sm text-danger-700">
                        {general}
                    </p>
                )}
            </form>
        </Dialogo>
    );
}

CambiarContrasena.propTypes = {
    onCerrar: PropTypes.func.isRequired,
    onGuardada: PropTypes.func.isRequired,
};
