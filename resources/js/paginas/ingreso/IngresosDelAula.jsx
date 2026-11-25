import PropTypes from 'prop-types';
import { useState } from 'react';
import { Undo2, UserX } from 'lucide-react';
import { codigoDe, erroresDe, mensajeDe } from '../../api/errores';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Buscador from '../../componentes/Buscador';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import Insignia from '../../componentes/Insignia';
import { colorDeAsignatura } from '../../utiles/texto';

// Cuántos ingresos devuelve la ruta 63 sin pedir `ingresos=todos`.
const RECIENTES = 5;
// La expulsión pide 5 caracteres de motivo; la anulación, 3.
const MOTIVO_MINIMO = { expulsion: 5, anulacion: 3 };
// Igual que en la puerta: pasados 15 minutos del inicio, el ingreso es tarde.
const TOLERANCIA_MINUTOS = 15;

// '08:21' → 501.
const minutosDe = (hora) => {
    const [h, m] = String(hora ?? '')
        .split(':')
        .map(Number);
    return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
};

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

// Los ingresos del aula, del más reciente al más antiguo (ruta 63), la
// expulsión sobre uno de ellos (ruta 66) y su anulación, si se hizo por error.
// `expulsar(ingreso, motivo)` y `anular(ingreso, motivo)` hacen la llamada y
// devuelven su promesa; sin ellas no se ofrece la acción (código de aula, o un
// auxiliar, que no anula).

export default function IngresosDelAula({
    ingresos,
    total,
    examenes,
    todos,
    onAlternar,
    buscar,
    onBuscar,
    aula,
    operador,
    hora,
    expulsar,
    anular,
}) {
    // Qué se hace sobre qué ingreso: { tipo: 'expulsion' | 'anulacion', ingreso }.
    const [accion, setAccion] = useState(null);
    const elegido = accion?.ingreso ?? null;
    const tipo = accion?.tipo ?? 'expulsion';
    const minimo = MOTIVO_MINIMO[tipo];
    const [motivo, setMotivo] = useState('');
    const [errorMotivo, setErrorMotivo] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [aviso, avisar] = useAviso();
    // Con más de un examen en el aula, cada ingreso lleva el color del suyo.
    const conColor = examenes.length > 1;
    const colorDe = (examenId) => {
        const examen = examenes.find((x) => x.id === examenId);
        return examen ? colorDeAsignatura(examen.asignatura) : null;
    };
    const esTarde = (ingreso) => {
        const inicio = minutosDe(examenes.find((x) => x.id === ingreso.examen_id)?.hora);
        const llegada = minutosDe(ingreso.hora);
        return inicio !== null && llegada !== null && llegada - inicio > TOLERANCIA_MINUTOS;
    };

    const cerrar = () => {
        setAccion(null);
        setMotivo('');
        setErrorMotivo(null);
    };
    const registrar = async () => {
        if (enviando || !elegido) return;
        if (motivo.trim().length < minimo) {
            setErrorMotivo(`Mínimo ${minimo} caracteres`);
            return;
        }
        setEnviando(true);
        try {
            await (tipo === 'anulacion' ? anular : expulsar)(elegido, motivo.trim());
            cerrar();
            avisar(tipo === 'anulacion' ? 'Ingreso anulado' : 'Expulsión registrada');
        } catch (fallo) {
            const campo = erroresDe(fallo).motivo;
            if (campo) {
                setErrorMotivo(campo);
            } else {
                const codigo = codigoDe(fallo);
                cerrar();
                avisar(
                    codigo === 'YA_EXPULSADO'
                        ? 'Ya estaba expulsado'
                        : codigo === 'YA_ANULADO'
                          ? 'Ya estaba anulado'
                          : sinPunto(mensajeDe(fallo, 'No se pudo registrar')),
                    'error'
                );
            }
        } finally {
            setEnviando(false);
        }
    };

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="mb-1 flex min-h-9 items-center justify-between gap-2">
                <p className="text-sm font-semibold text-slate-800">
                    {todos ? `Ingresos · ${total}` : 'Ingresos recientes'}
                </p>
                {total > RECIENTES && (
                    <Boton
                        type="button"
                        variante="enlace"
                        tamano="chico"
                        className="-mr-2"
                        aria-expanded={todos}
                        onClick={onAlternar}
                    >
                        {todos ? 'Ver menos' : `Ver los ${total}`}
                    </Boton>
                )}
            </div>
            {todos && (
                <Buscador
                    valor={buscar}
                    onCambiar={onBuscar}
                    placeholder="Nombre o código SIS"
                    etiqueta="Buscar ingreso"
                    className="mb-1"
                />
            )}
            <ul
                aria-label="Ingresos"
                className={`divide-y divide-slate-100 ${todos ? '-mr-2 max-h-80 overflow-y-auto pr-2' : ''}`}
            >
                {ingresos.map((i) => (
                    <li key={i.id} className={`flex min-h-12 items-center gap-2`}>
                        {conColor && (
                            <span
                                className="h-2.5 w-2.5 shrink-0 rounded-full"
                                style={{ backgroundColor: colorDe(i.examen_id) ?? undefined }}
                            />
                        )}
                        <span className="min-w-0 flex-1 break-words py-1.5 text-sm leading-snug">
                            <span
                                className={
                                    i.anulado ? 'text-slate-500 line-through' : 'text-slate-800'
                                }
                            >
                                {i.nombre}
                            </span>
                            {i.anulado && (
                                <span className="block text-xs text-slate-600">
                                    {i.motivo_anulacion
                                        ? `Anulado · ${i.motivo_anulacion}`
                                        : 'Anulado'}
                                </span>
                            )}
                        </span>
                        <span className="flex shrink-0 flex-wrap items-center justify-end gap-1">
                            {i.expulsado && <Insignia tono="peligro">Expulsado</Insignia>}
                            {i.excepcion && <Insignia tono="advertencia">Excepción</Insignia>}
                            {esTarde(i) && <Insignia tono="advertencia">Tarde</Insignia>}
                        </span>
                        <span className="shrink-0 text-sm tabular-nums text-slate-600">
                            {i.hora}
                        </span>
                        {anular &&
                            (i.anulado ? (
                                <span className="h-11 w-11 shrink-0" aria-hidden="true" />
                            ) : (
                                <button
                                    type="button"
                                    aria-label={`Anular el ingreso de ${i.nombre}`}
                                    title="Anular ingreso"
                                    onClick={() => setAccion({ tipo: 'anulacion', ingreso: i })}
                                    className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                                >
                                    <Undo2 className="h-5 w-5" />
                                </button>
                            ))}
                        {!expulsar ? null : i.expulsado || i.anulado ? (
                            <span className="h-11 w-11 shrink-0" aria-hidden="true" />
                        ) : (
                            <button
                                type="button"
                                aria-label={`Registrar expulsión de ${i.nombre}`}
                                onClick={() => setAccion({ tipo: 'expulsion', ingreso: i })}
                                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-danger-50 hover:text-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600"
                            >
                                <UserX className="h-5 w-5" />
                            </button>
                        )}
                    </li>
                ))}
                {total === 0 && (
                    <li className="py-6 text-center text-sm text-slate-600">Sin ingresos</li>
                )}
                {total > 0 && ingresos.length === 0 && (
                    <li className="py-6 text-center text-sm text-slate-600">Sin resultados</li>
                )}
            </ul>

            <Dialogo
                titulo={tipo === 'anulacion' ? 'Anular ingreso' : 'Expulsión'}
                abierto={elegido !== null}
                onCerrar={cerrar}
                acciones={
                    <>
                        <Boton type="button" variante="secundario" onClick={cerrar}>
                            Cancelar
                        </Boton>
                        <Boton
                            type="button"
                            variante={tipo === 'anulacion' ? 'primario' : 'peligro'}
                            onClick={registrar}
                            disabled={enviando || motivo.trim().length < minimo}
                            cargando={enviando}
                        >
                            {tipo === 'anulacion' ? 'Anular ingreso' : 'Expulsar'}
                        </Boton>
                    </>
                }
            >
                {elegido && (
                    <div className="space-y-3">
                        <div>
                            <p className="text-base font-semibold text-slate-900">
                                {elegido.nombre}
                            </p>
                            <p className="text-sm text-slate-600">
                                {[
                                    elegido.codigo && `Código SIS ${elegido.codigo}`,
                                    `Aula ${aula}`,
                                    `Ingresó ${elegido.hora}`,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </div>
                        <Campo
                            etiqueta="Motivo"
                            requerido
                            placeholder="Motivo"
                            maxLength={500}
                            value={motivo}
                            error={errorMotivo}
                            ayuda={`Mínimo ${minimo} caracteres`}
                            onChange={(e) => {
                                setMotivo(e.target.value);
                                setErrorMotivo(null);
                            }}
                            autoFocus
                        />
                        {tipo === 'anulacion' && (
                            <p className="text-xs text-slate-600">
                                Podrá volver a pasar por la puerta
                            </p>
                        )}
                        <p className="text-xs text-slate-600">
                            {[operador, hora].filter(Boolean).join(' · ')}
                        </p>
                    </div>
                )}
            </Dialogo>
            {aviso}
        </div>
    );
}

IngresosDelAula.propTypes = {
    ingresos: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number.isRequired,
            nombre: PropTypes.string,
            codigo: PropTypes.string,
            hora: PropTypes.string,
            examen_id: PropTypes.number,
            excepcion: PropTypes.bool,
            expulsado: PropTypes.bool,
            anulado: PropTypes.bool,
            motivo_anulacion: PropTypes.string,
        })
    ).isRequired,
    total: PropTypes.number.isRequired,
    examenes: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.number,
            asignatura: PropTypes.string,
            hora: PropTypes.string,
        })
    ).isRequired,
    todos: PropTypes.bool.isRequired,
    onAlternar: PropTypes.func.isRequired,
    buscar: PropTypes.string.isRequired,
    onBuscar: PropTypes.func.isRequired,
    aula: PropTypes.string,
    operador: PropTypes.string,
    hora: PropTypes.string,
    // Sin ella (código de aula) no se ofrece expulsar.
    expulsar: PropTypes.func,
    // Solo el docente del examen anula un ingreso hecho por error.
    anular: PropTypes.func,
};
