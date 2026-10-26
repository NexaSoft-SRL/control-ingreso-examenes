import PropTypes from 'prop-types';
import { useState } from 'react';
import { TriangleAlert } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import Seleccion, { AreaTexto } from '../../componentes/Seleccion';
import { TIPOS_COMUNES, TIPOS_INCIDENCIA } from './tiposIncidencia';

const DETALLE_MINIMO = 5;

// Botón «Incidencia» de la lista del aula (HU-21) y su formulario: el mismo
// registro de la puerta (HU-19, POST /api/aulas/{aula}/punto-control/
// incidencias-manuales) con el examen y el aula ya fijos.
export default function IncidenciaAula({ examenId, aula, por, hora, alRegistrar }) {
    const [abierto, setAbierto] = useState(false);
    const [tipo, setTipo] = useState('');
    const [codigo, setCodigo] = useState('');
    const [detalle, setDetalle] = useState('');
    const [intento, setIntento] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [errores, setErrores] = useState({});
    const [fallo, setFallo] = useState('');
    const corto = detalle.trim().length < DETALLE_MINIMO;

    const cerrar = () => {
        setAbierto(false);
        setTipo('');
        setCodigo('');
        setDetalle('');
        setIntento(false);
        setErrores({});
        setFallo('');
    };

    const enviar = async () => {
        setIntento(true);
        if (!tipo || corto || enviando) return;
        const sis = codigo.trim();
        if (sis !== '' && !/^[0-9]{5,10}$/.test(sis)) {
            setErrores({ codigo_universitario: 'Entre 5 y 10 dígitos' });
            return;
        }
        setEnviando(true);
        setErrores({});
        setFallo('');
        try {
            await api.post(`/aulas/${aula.id}/punto-control/incidencias-manuales`, {
                examen_id: examenId,
                tipo,
                detalle: detalle.trim(),
                ...(sis !== '' ? { codigo_universitario: sis } : {}),
            });
            cerrar();
            alRegistrar?.();
        } catch (error) {
            const campos = erroresDe(error);
            setErrores(campos);
            if (Object.keys(campos).length === 0) {
                setFallo(mensajeDe(error, 'No se pudo registrar'));
            }
        } finally {
            setEnviando(false);
        }
    };

    const cabecera = [`Aula ${aula.nombre}`, hora, por].filter(Boolean).join(' · ');

    return (
        <>
            <Boton type="button" variante="secundario" onClick={() => setAbierto(true)}>
                <TriangleAlert className="h-4 w-4" aria-hidden="true" /> Incidencia
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
                        <Boton type="button" onClick={enviar} disabled={!tipo || corto || enviando}>
                            {enviando ? 'Registrando…' : 'Registrar'}
                        </Boton>
                    </>
                }
            >
                <div className="space-y-4">
                    <p className="text-sm text-slate-600">{cabecera}</p>
                    <Seleccion
                        etiqueta="Tipo"
                        requerido
                        value={tipo}
                        onChange={(evento) => setTipo(evento.target.value)}
                    >
                        <option value="" disabled>
                            Elegir
                        </option>
                        {TIPOS_COMUNES.map((clave) => [clave, TIPOS_INCIDENCIA[clave]]).map(
                            ([clave, etiqueta]) => (
                                <option key={clave} value={clave}>
                                    {etiqueta}
                                </option>
                            )
                        )}
                    </Seleccion>
                    <Campo
                        etiqueta="Código universitario"
                        inputMode="numeric"
                        maxLength={10}
                        value={codigo}
                        error={errores.codigo_universitario ?? errores.estudiante_id}
                        onChange={(evento) => {
                            setCodigo(evento.target.value);
                            setErrores(({ codigo_universitario: _quitado, ...resto }) => resto);
                        }}
                    />
                    <AreaTexto
                        etiqueta="Detalle"
                        requerido
                        rows={3}
                        maxLength={2000}
                        value={detalle}
                        onChange={(evento) => setDetalle(evento.target.value)}
                        error={
                            intento && corto
                                ? `Mínimo ${DETALLE_MINIMO} caracteres`
                                : errores.detalle
                        }
                        pie={corto ? `Mínimo ${DETALLE_MINIMO} caracteres` : null}
                    />
                    {fallo && (
                        <p role="alert" className="text-sm text-danger-600">
                            {fallo}
                        </p>
                    )}
                </div>
            </Dialogo>
        </>
    );
}

IncidenciaAula.propTypes = {
    examenId: PropTypes.number.isRequired,
    aula: PropTypes.shape({
        id: PropTypes.number.isRequired,
        nombre: PropTypes.string.isRequired,
    }).isRequired,
    por: PropTypes.string,
    hora: PropTypes.string,
    alRegistrar: PropTypes.func,
};
