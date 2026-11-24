import { useState } from 'react';
import PropTypes from 'prop-types';
import { ClipboardList } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import { AreaTexto } from '../../componentes/Seleccion';

const MOTIVO_MINIMO = 5;
const MOTIVO_MAXIMO = 1000;
const CODIGO = /^\d{5,10}$/;
// De la planilla solo valen los dígitos y lo que separa un código de otro.
const SOLO_CODIGOS = /[^\d\s,;]/g;

// Los códigos escritos o pegados, separados por salto de línea, espacio, coma
// o punto y coma: cuántos sirven y cuáles no tienen la forma de un código SIS.
function leerCodigos(texto) {
    const todos = texto.split(/[\s,;]+/).filter(Boolean);
    const validos = todos.filter((c) => CODIGO.test(c));
    return { validos, invalidos: todos.filter((c) => !CODIGO.test(c)) };
}

// Habilita o inhabilita a quienes estan en una lista de códigos SIS pegada
// desde una planilla armada afuera. Los códigos que no son del examen se
// devuelven para revisarlos.
export default function PorListaDeCodigos({ examenId, alCambiar }) {
    const [abierto, setAbierto] = useState(false);
    const [codigos, setCodigos] = useState('');
    // Casi siempre se viene a habilitar: es lo que se ofrece primero.
    const [habilitado, setHabilitado] = useState(true);
    const [motivo, setMotivo] = useState('');
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [resultado, setResultado] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();
    const leidos = leerCodigos(codigos);
    const verbo = habilitado ? 'Habilitar' : 'Inhabilitar';

    const cerrar = () => {
        setAbierto(false);
        setHabilitado(true);
        setCodigos('');
        setMotivo('');
        setErrores({});
        setGeneral(null);
        setResultado(null);
    };

    async function aplicar() {
        const faltas = {};
        if (leidos.validos.length === 0) faltas.codigos = 'Pegue al menos un código válido';
        if (!habilitado && motivo.trim().length < MOTIVO_MINIMO) {
            faltas.motivo = `Mínimo ${MOTIVO_MINIMO} caracteres`;
        }
        if (Object.keys(faltas).length > 0) return setErrores(faltas);
        if (enviando) return undefined;
        setEnviando(true);
        setErrores({});
        setGeneral(null);
        try {
            const { data } = await api.post(`/examenes/${examenId}/habilitaciones/por-codigos`, {
                habilitado,
                // Solo los que tienen forma de código SIS.
                codigos: leidos.validos.join('\n'),
                ...(habilitado ? {} : { motivo: motivo.trim() }),
            });
            setResultado(data);
            await alCambiar?.();
            avisar(`${data.afectados} ${habilitado ? 'habilitados' : 'inhabilitados'}`);
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (campos.codigos || campos.motivo) setErrores(campos);
            else setGeneral(mensajeDe(fallo, 'No se pudo aplicar'));
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    return (
        <>
            <Boton variante="secundario" onClick={() => setAbierto(true)}>
                <ClipboardList className="h-4 w-4" aria-hidden="true" /> Por lista de códigos
            </Boton>
            {abierto && (
                <Dialogo
                    titulo="Por lista de códigos"
                    onCerrar={cerrar}
                    acciones={
                        resultado ? (
                            <Boton onClick={cerrar}>Cerrar</Boton>
                        ) : (
                            <>
                                <Boton variante="secundario" onClick={cerrar}>
                                    Cancelar
                                </Boton>
                                <Boton
                                    variante={habilitado ? 'primario' : 'peligro'}
                                    onClick={aplicar}
                                    cargando={enviando}
                                    disabled={leidos.validos.length === 0}
                                >
                                    {leidos.validos.length > 0
                                        ? `${verbo} ${leidos.validos.length}`
                                        : verbo}
                                </Boton>
                            </>
                        )
                    }
                >
                    {resultado ? (
                        <div className="space-y-3 text-sm text-slate-800" role="status">
                            <p
                                className={`rounded-lg px-3 py-2 font-medium ${resultado.afectados > 0 ? 'bg-success-50 text-success-800' : 'bg-warning-50 text-warning-800'}`}
                            >
                                {resultado.afectados > 0
                                    ? resultado.message
                                    : `Ninguno cambió. ${resultado.message}`}
                            </p>
                            <dl className="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 text-sm">
                                <dt className="text-slate-600">En el examen</dt>
                                <dd className="font-semibold">{resultado.encontrados}</dd>
                                <dt className="text-slate-600">
                                    {habilitado ? 'Habilitados' : 'Inhabilitados'}
                                </dt>
                                <dd className="font-semibold">{resultado.afectados}</dd>
                                {resultado.sin_carnet > 0 && (
                                    <>
                                        <dt className="text-slate-600">
                                            Sin carnet · no se habilitaron
                                        </dt>
                                        <dd className="font-semibold">{resultado.sin_carnet}</dd>
                                    </>
                                )}
                                {resultado.no_encontrados.length > 0 && (
                                    <>
                                        <dt className="text-slate-600">No son del examen</dt>
                                        <dd className="font-semibold">
                                            {resultado.no_encontrados.length}
                                        </dd>
                                    </>
                                )}
                            </dl>
                            {resultado.no_encontrados.length > 0 && (
                                <div>
                                    <p className="mb-1 text-xs text-slate-600">
                                        Códigos que no son del examen
                                    </p>
                                    <p className="break-words font-mono text-xs text-slate-700">
                                        {resultado.no_encontrados.join(', ')}
                                    </p>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {general && (
                                <p
                                    role="alert"
                                    className="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700"
                                >
                                    {general}
                                </p>
                            )}
                            <div
                                role="group"
                                aria-label="Acción"
                                className="grid grid-cols-2 gap-2"
                            >
                                <Boton
                                    variante={habilitado ? 'primario' : 'secundario'}
                                    aria-pressed={habilitado}
                                    onClick={() => setHabilitado(true)}
                                >
                                    Habilitar
                                </Boton>
                                <Boton
                                    variante={habilitado ? 'secundario' : 'peligro'}
                                    aria-pressed={!habilitado}
                                    onClick={() => setHabilitado(false)}
                                >
                                    Inhabilitar
                                </Boton>
                            </div>
                            <div>
                                <AreaTexto
                                    etiqueta="Códigos SIS (uno por línea o separados por coma)"
                                    rows={6}
                                    inputMode="numeric"
                                    autoFocus
                                    placeholder={'202100417\n202101256'}
                                    value={codigos}
                                    error={errores.codigos}
                                    onChange={(e) => {
                                        setCodigos(e.target.value.replace(SOLO_CODIGOS, ''));
                                        setErrores({});
                                    }}
                                />
                                <p
                                    aria-live="polite"
                                    className={`mt-1 text-xs ${leidos.invalidos.length > 0 ? 'text-warning-700' : 'text-slate-600'}`}
                                >
                                    {leidos.validos.length + leidos.invalidos.length === 0
                                        ? 'Pegue los códigos de la planilla'
                                        : `${leidos.validos.length} ${leidos.validos.length === 1 ? 'válido' : 'válidos'}${
                                              leidos.invalidos.length > 0
                                                  ? ` · ${leidos.invalidos.length} ${leidos.invalidos.length === 1 ? 'inválido' : 'inválidos'} (de 5 a 10 dígitos), no se envían`
                                                  : ''
                                          }`}
                                </p>
                            </div>
                            {!habilitado && (
                                <AreaTexto
                                    etiqueta="Motivo"
                                    rows={2}
                                    placeholder="Por qué no rinde"
                                    maxLength={MOTIVO_MAXIMO}
                                    value={motivo}
                                    error={errores.motivo}
                                    pie={`${motivo.length}/${MOTIVO_MAXIMO}`}
                                    onChange={(e) => {
                                        setMotivo(e.target.value);
                                        setErrores({});
                                    }}
                                />
                            )}
                        </div>
                    )}
                </Dialogo>
            )}
            {aviso}
        </>
    );
}

PorListaDeCodigos.propTypes = {
    examenId: PropTypes.number.isRequired,
    alCambiar: PropTypes.func,
};
