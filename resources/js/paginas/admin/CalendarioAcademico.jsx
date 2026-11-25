import PropTypes from 'prop-types';
import { useState } from 'react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import Insignia from '../../componentes/Insignia';
import { diaMes, fechaCorta } from '../../utiles/texto';

// Las ventanas del calendario de exámenes: dentro de cada una los docentes
// pueden registrar exámenes de ese tipo.
const VENTANAS = [
    ['primeros_parciales', 'Primeros parciales'],
    ['segundos_parciales', 'Segundos parciales'],
    ['finales_y_segunda_instancia', 'Finales y segunda instancia'],
    ['examenes_de_mesa', 'Exámenes de mesa'],
];

const hoyISO = () => new Date().toLocaleDateString('en-CA');

// Cada ventana respecto de hoy.
function estadoDe(rango, hoy) {
    if (!rango?.[0] || !rango?.[1]) return { texto: 'Sin fechas', tono: 'advertencia' };
    if (hoy < rango[0]) return { texto: 'Próxima', tono: 'info' };
    if (hoy > rango[1]) return { texto: 'Cerrada', tono: 'neutro' };
    return { texto: 'Abierta ahora', tono: 'exito' };
}

// «12 oct – 31 oct 2026» (con los dos años si no son el mismo).
function rangoTexto([desde, hasta]) {
    return desde.slice(0, 4) === hasta.slice(0, 4)
        ? `${diaMes(desde)} – ${fechaCorta(hasta)}`
        : `${fechaCorta(desde)} – ${fechaCorta(hasta)}`;
}

// En el orden del calendario: primero lo que empieza antes; sin fechas, al final.
function enOrden(periodo) {
    return [...VENTANAS].sort(([a], [b]) => {
        const da = periodo.calendario?.[a]?.[0] ?? '9999';
        const db = periodo.calendario?.[b]?.[0] ?? '9999';
        return da.localeCompare(db);
    });
}

function AjustarCalendario({ periodo, onCerrar, onGuardado, onRestablecido }) {
    const [fechas, setFechas] = useState(() =>
        Object.fromEntries(
            VENTANAS.map(([clave]) => [
                clave,
                [periodo.calendario?.[clave]?.[0] ?? '', periodo.calendario?.[clave]?.[1] ?? ''],
            ])
        )
    );
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [guardando, setGuardando] = useState(false);
    // «Restablecer» vuelve a las fechas de la universidad y pide confirmar.
    const [confirmando, setConfirmando] = useState(false);
    const [restableciendo, setRestableciendo] = useState(false);
    const ajustado = Boolean(periodo.calendario_ajustado);
    // Las fechas caben dentro del período.
    const minimo = periodo.fecha_inicio ?? undefined;
    const maximo = periodo.fecha_fin ?? undefined;
    const fuera = (fecha) => fecha && ((minimo && fecha < minimo) || (maximo && fecha > maximo));
    // En el orden del calendario (se fija al abrir).
    const [orden] = useState(() => enOrden(periodo));

    function poner(clave, posicion, valor) {
        setFechas((previas) => {
            const rango = [...previas[clave]];
            rango[posicion] = valor;
            return { ...previas, [clave]: rango };
        });
        setErrores((previos) => ({ ...previos, [`ventanas.${clave}.${posicion}`]: undefined }));
    }

    function restablecer() {
        setRestableciendo(true);
        setGeneral(null);
        api.delete(`/periodos/${periodo.id}/calendario`)
            .then(() => onRestablecido())
            .catch((falla) => {
                setGeneral(mensajeDe(falla, 'No se pudo restablecer'));
                setConfirmando(false);
                setRestableciendo(false);
            });
    }

    function guardar(evento) {
        evento.preventDefault();
        const locales = {};
        const ventanas = {};
        VENTANAS.forEach(([clave]) => {
            const [desde, hasta] = fechas[clave];
            if (!desde && !hasta) return;
            if (!desde) locales[`ventanas.${clave}.0`] = 'Obligatorio';
            if (!hasta) locales[`ventanas.${clave}.1`] = 'Obligatorio';
            if (fuera(desde)) locales[`ventanas.${clave}.0`] = 'Fuera del período';
            if (fuera(hasta)) locales[`ventanas.${clave}.1`] = 'Fuera del período';
            else if (desde && hasta && hasta < desde)
                locales[`ventanas.${clave}.1`] = 'Debe ser después de Desde';
            ventanas[clave] = [desde, hasta];
        });
        if (Object.keys(ventanas).length === 0) {
            setGeneral('Indique al menos una ventana');
            return;
        }
        setErrores(locales);
        setGeneral(null);
        if (Object.keys(locales).length > 0) return;

        setGuardando(true);
        api.put(`/periodos/${periodo.id}/calendario`, { ventanas })
            .then(() => onGuardado())
            .catch((error) => {
                const deCampo = erroresDe(error);
                setErrores(deCampo);
                if (Object.keys(deCampo).length === 0)
                    setGeneral(mensajeDe(error, 'No se pudo guardar'));
                setGuardando(false);
            });
    }

    return (
        <Dialogo
            titulo={`Ajustar calendario ${periodo.codigo}`}
            onCerrar={onCerrar}
            ancho="max-w-xl"
            acciones={
                confirmando ? (
                    <>
                        <Boton
                            type="button"
                            variante="secundario"
                            onClick={() => setConfirmando(false)}
                        >
                            Cancelar
                        </Boton>
                        <Boton type="button" cargando={restableciendo} onClick={restablecer}>
                            Sí, restablecer
                        </Boton>
                    </>
                ) : (
                    <>
                        {ajustado && (
                            <Boton
                                type="button"
                                variante="secundario"
                                onClick={() => setConfirmando(true)}
                            >
                                Restablecer
                            </Boton>
                        )}
                        <Boton type="button" variante="secundario" onClick={onCerrar}>
                            Cancelar
                        </Boton>
                        <Boton type="submit" form="ajustar-calendario" cargando={guardando}>
                            {guardando ? 'Guardando' : 'Guardar ajuste'}
                        </Boton>
                    </>
                )
            }
        >
            {confirmando && (
                <div className="space-y-2 text-sm text-slate-700">
                    <p className="font-semibold text-slate-900">¿Restablecer el calendario?</p>
                    <p>Se pierden los ajustes y vuelven las fechas de la universidad.</p>
                </div>
            )}
            <form
                id="ajustar-calendario"
                onSubmit={guardar}
                noValidate
                className={`space-y-4 ${confirmando ? 'hidden' : ''}`}
            >
                {orden.map(([clave, nombre]) => (
                    <fieldset key={clave} className="grid gap-3 sm:grid-cols-2">
                        <legend className="mb-1 text-sm font-semibold text-slate-800">
                            {nombre}
                        </legend>
                        <Campo
                            etiqueta="Desde"
                            type="date"
                            min={minimo}
                            max={maximo}
                            value={fechas[clave][0]}
                            onChange={(e) => poner(clave, 0, e.target.value)}
                            error={errores[`ventanas.${clave}.0`]}
                        />
                        <Campo
                            etiqueta="Hasta"
                            type="date"
                            min={fechas[clave][0] || minimo}
                            max={maximo}
                            value={fechas[clave][1]}
                            onChange={(e) => poner(clave, 1, e.target.value)}
                            error={errores[`ventanas.${clave}.1`]}
                        />
                    </fieldset>
                ))}
                {general && (
                    <p role="alert" className="text-sm text-danger-600">
                        {general}
                    </p>
                )}
            </form>
        </Dialogo>
    );
}

AjustarCalendario.propTypes = {
    periodo: PropTypes.shape({
        id: PropTypes.number.isRequired,
        codigo: PropTypes.string.isRequired,
        fecha_inicio: PropTypes.string,
        fecha_fin: PropTypes.string,
        calendario: PropTypes.object,
        calendario_ajustado: PropTypes.bool,
    }).isRequired,
    onCerrar: PropTypes.func.isRequired,
    onGuardado: PropTypes.func.isRequired,
    onRestablecido: PropTypes.func.isRequired,
};

// El calendario académico de un período: se lee tal como lo publica la
// universidad y no se edita a la primera. Ajustarlo es una decisión (pide
// confirmar); dentro del ajuste, «Restablecer» vuelve al de la universidad.
export default function CalendarioAcademico({ periodo, onCerrar, onCambio }) {
    // 'ver' | 'confirmar-ajuste' | 'ajustar'
    const [modo, setModo] = useState('ver');
    const hoy = hoyISO();
    const ajustado = Boolean(periodo.calendario_ajustado);

    if (modo === 'ajustar') {
        return (
            <AjustarCalendario
                periodo={periodo}
                onCerrar={onCerrar}
                onGuardado={() => onCambio('Calendario ajustado')}
                onRestablecido={() => onCambio('Calendario de la universidad')}
            />
        );
    }

    const confirmando = modo === 'confirmar-ajuste';

    return (
        <Dialogo
            titulo={`Calendario académico ${periodo.codigo}`}
            onCerrar={onCerrar}
            ancho="max-w-lg"
            acciones={
                confirmando ? (
                    <>
                        <Boton type="button" variante="secundario" onClick={() => setModo('ver')}>
                            Cancelar
                        </Boton>
                        <Boton type="button" onClick={() => setModo('ajustar')}>
                            Sí, ajustar
                        </Boton>
                    </>
                ) : (
                    <>
                        <Boton
                            type="button"
                            variante="secundario"
                            onClick={() => setModo(ajustado ? 'ajustar' : 'confirmar-ajuste')}
                        >
                            Ajustar fechas
                        </Boton>
                        <Boton type="button" onClick={onCerrar}>
                            Cerrar
                        </Boton>
                    </>
                )
            }
        >
            {confirmando ? (
                <div className="space-y-2 text-sm text-slate-700">
                    <p className="font-semibold text-slate-900">¿Ajustar fechas?</p>
                    <p>
                        Estas fechas vienen del calendario de la universidad. ¿Ajustarlas solo en
                        este sistema?
                    </p>
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Insignia tono={ajustado ? 'advertencia' : 'info'}>
                            {ajustado ? 'Ajustado por administración' : 'Según la universidad'}
                        </Insignia>
                        {ajustado &&
                            (periodo.calendario_ajustado_por || periodo.calendario_ajustado_en) && (
                                <span className="text-xs text-slate-600">
                                    {[
                                        periodo.calendario_ajustado_por,
                                        periodo.calendario_ajustado_en
                                            ? fechaCorta(periodo.calendario_ajustado_en)
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                            )}
                    </div>
                    <ul className="divide-y divide-slate-200 rounded-lg border border-slate-200">
                        {enOrden(periodo).map(([clave, nombre]) => {
                            const rango = periodo.calendario?.[clave];
                            const estado = estadoDe(rango, hoy);
                            return (
                                <li
                                    key={clave}
                                    className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-slate-900">
                                            {nombre}
                                        </p>
                                        <p className="text-sm text-slate-600">
                                            {rango?.[0] && rango?.[1] ? rangoTexto(rango) : '—'}
                                        </p>
                                    </div>
                                    <Insignia tono={estado.tono} punto>
                                        {estado.texto}
                                    </Insignia>
                                </li>
                            );
                        })}
                    </ul>
                    <p className="text-xs text-slate-600">
                        Dentro de cada ventana el docente puede registrar exámenes de ese tipo.
                    </p>
                </div>
            )}
        </Dialogo>
    );
}

CalendarioAcademico.propTypes = {
    periodo: PropTypes.shape({
        id: PropTypes.number.isRequired,
        codigo: PropTypes.string.isRequired,
        fecha_inicio: PropTypes.string,
        fecha_fin: PropTypes.string,
        calendario: PropTypes.object,
        calendario_ajustado: PropTypes.bool,
        calendario_ajustado_por: PropTypes.string,
        calendario_ajustado_en: PropTypes.string,
    }).isRequired,
    onCerrar: PropTypes.func.isRequired,
    onCambio: PropTypes.func.isRequired,
};
