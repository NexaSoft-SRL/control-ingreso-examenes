import PropTypes from 'prop-types';
import { useState } from 'react';
import { TriangleAlert } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import { TIPOS_COMUNES, TIPOS_INCIDENCIA } from '../ingreso/tiposIncidencia';
import Seleccion, { AreaTexto } from '../../componentes/Seleccion';

// Los tipos que se reportan desde el aula; las excepciones de ingreso se
// asientan en la puerta.
const DETALLE_MINIMO = 5;

const errorDeCodigo = (codigo) =>
    codigo === '' || /^[0-9]{5,10}$/.test(codigo) ? null : 'Entre 5 y 10 dígitos';

// Botón «Incidencia» de «En curso» y su formulario, como en la guía: el
// examen ya está elegido y el aula se elige entre las que la cuenta opera
// (con una sola, queda fija). Registra con la misma llamada de la puerta
// (POST /api/aulas/{aula}/punto-control/incidencias-manuales).
export default function IncidenciaEnCurso({ examenId, aulas, por, hora, alRegistrar }) {
    const [aviso, avisar] = useAviso();
    const [abierto, setAbierto] = useState(false);
    const [donde, setDonde] = useState('');
    const [tipo, setTipo] = useState('');
    const [codigo, setCodigo] = useState('');
    const [detalle, setDetalle] = useState('');
    const [intento, setIntento] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [errores, setErrores] = useState({});

    const unica = aulas.length === 1 ? aulas[0] : null;
    const aulaId = unica ? unica.id : Number(donde) || null;
    const texto = detalle.trim();
    const corto = texto.length < DETALLE_MINIMO;
    const errorCodigo = errores.codigo_universitario ?? errorDeCodigo(codigo.trim());

    const cerrar = () => {
        setAbierto(false);
        setDonde('');
        setTipo('');
        setCodigo('');
        setDetalle('');
        setIntento(false);
        setErrores({});
    };

    const enviar = async () => {
        setIntento(true);
        if (!aulaId || !tipo || corto || errorCodigo || enviando) return;
        setEnviando(true);
        setErrores({});
        try {
            await api.post(`/aulas/${aulaId}/punto-control/incidencias-manuales`, {
                examen_id: examenId,
                tipo,
                detalle: texto,
                ...(codigo.trim() ? { codigo_universitario: codigo.trim() } : {}),
            });
            cerrar();
            avisar('Incidencia registrada');
            alRegistrar?.();
        } catch (error) {
            const campos = erroresDe(error);
            setErrores(campos);
            if (Object.keys(campos).length === 0) {
                avisar(mensajeDe(error, 'No se pudo registrar'), 'error');
            }
        } finally {
            setEnviando(false);
        }
    };

    const cabecera = [unica ? `Aula ${unica.nombre}` : null, hora, por].filter(Boolean).join(' · ');

    return (
        <>
            <Boton type="button" variante="secundario" onClick={() => setAbierto(true)}>
                <TriangleAlert className="h-4 w-4" /> Incidencia
            </Boton>
            <Dialogo
                titulo="Incidencia"
                abierto={abierto}
                onCerrar={cerrar}
                acciones={
                    <>
                        <Boton type="button" variante="secundario" onClick={cerrar}>
                            Cancelar
                        </Boton>
                        <Boton
                            type="button"
                            onClick={enviar}
                            disabled={!aulaId || !tipo || corto || enviando}
                        >
                            {enviando ? 'Registrando…' : 'Registrar'}
                        </Boton>
                    </>
                }
            >
                <div className="space-y-4">
                    {cabecera && <p className="text-sm text-slate-600">{cabecera}</p>}
                    {!unica && (
                        <Seleccion
                            etiqueta="Aula"
                            requerido
                            value={donde}
                            onChange={(e) => setDonde(e.target.value)}
                        >
                            <option value="" disabled>
                                Elegir
                            </option>
                            {aulas.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.nombre}
                                </option>
                            ))}
                        </Seleccion>
                    )}
                    <Seleccion
                        etiqueta="Tipo"
                        requerido
                        value={tipo}
                        onChange={(e) => setTipo(e.target.value)}
                    >
                        <option value="" disabled>
                            Elegir
                        </option>
                        {TIPOS_COMUNES.map((clave) => [clave, TIPOS_INCIDENCIA[clave]]).map(
                            ([clave, nombre]) => (
                                <option key={clave} value={clave}>
                                    {nombre}
                                </option>
                            )
                        )}
                    </Seleccion>
                    <Campo
                        etiqueta="Código SIS"
                        inputMode="numeric"
                        maxLength={10}
                        value={codigo}
                        error={codigo.trim() || errores.codigo_universitario ? errorCodigo : null}
                        onChange={(e) => {
                            setCodigo(e.target.value);
                            setErrores(({ codigo_universitario: _quitado, ...resto }) => resto);
                        }}
                    />
                    <AreaTexto
                        etiqueta="Detalle"
                        requerido
                        rows={3}
                        maxLength={2000}
                        value={detalle}
                        onChange={(e) => setDetalle(e.target.value)}
                        error={
                            intento && corto
                                ? `Mínimo ${DETALLE_MINIMO} caracteres`
                                : (errores.detalle ?? null)
                        }
                        pie={corto ? `Mínimo ${DETALLE_MINIMO} caracteres` : null}
                    />
                </div>
            </Dialogo>
            {aviso}
        </>
    );
}

IncidenciaEnCurso.propTypes = {
    examenId: PropTypes.number.isRequired,
    aulas: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            nombre: PropTypes.string.isRequired,
        })
    ).isRequired,
    por: PropTypes.string,
    hora: PropTypes.string,
    alRegistrar: PropTypes.func,
};
