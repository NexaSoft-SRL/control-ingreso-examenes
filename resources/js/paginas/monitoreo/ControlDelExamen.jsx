import PropTypes from 'prop-types';
import { useState } from 'react';
import { ArrowRightLeft, KeyRound, ShieldCheck, UserRound, UsersRound } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarSondeo from '../../api/usarSondeo';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Dialogo from '../../componentes/Dialogo';
import Insignia from '../../componentes/Insignia';
import Seleccion from '../../componentes/Seleccion';
import Tarjeta from '../../componentes/Tarjeta';

// Con el examen abierto se pregunta seguido: quien pidió entrar con código
// espera en la puerta.
const INTERVALO = 5000;

const ROL = { a_cargo: 'A cargo', apoyo: 'Apoyo', otro: 'En la puerta' };
const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');
// '2026-10-23T10:12:40-04:00' → '10:12'.
const horaDe = (iso) => String(iso ?? '').slice(11, 16);

function EnLinea({ visto }) {
    return visto ? (
        <span className="inline-flex items-center gap-1 text-xs text-success-700">
            <span className="h-2 w-2 rounded-full bg-success-600" aria-hidden="true" /> En la puerta
            · {horaDe(visto)}
        </span>
    ) : (
        <span className="inline-flex items-center gap-1 text-xs text-slate-500">
            <span className="h-2 w-2 rounded-full bg-slate-300" aria-hidden="true" /> Sin conectar
        </span>
    );
}

EnLinea.propTypes = { visto: PropTypes.string };

// El panel en vivo del docente, en «En curso»: quién controla cada aula, los
// accesos con código que esperan su autorización y los cambios de aula de
// personas y de estudiantes. Todo lo que cambia queda en la bitácora.
export default function ControlDelExamen({ examenId, abierto, onCambio }) {
    const [aviso, avisar] = useAviso();
    const [moviendo, setMoviendo] = useState(null);
    const [destino, setDestino] = useState('');
    const [elegidas, setElegidas] = useState({});
    const [enCurso, setEnCurso] = useState(null);
    const [error, setError] = useState(null);
    const control = usarSondeo(`/examenes/${examenId}/control`, {
        intervalo: INTERVALO,
        activa: abierto,
    });
    const aulas = control.datos ?? [];
    const pendientes = aulas.flatMap((a) =>
        a.accesos.filter((x) => x.estado === 'pendiente').map((x) => ({ ...x }))
    );
    const otrasDe = (aulaId) => aulas.filter((a) => a.aula_id !== aulaId);

    const resolver = async (acceso, accion, aulaId) => {
        if (enCurso) return;
        setEnCurso(`${acceso.id}-${accion}`);
        try {
            const { data } = await api.post(
                `/examenes/${examenId}/accesos/${acceso.id}/${accion}`,
                aulaId ? { aula_id: aulaId } : {}
            );
            avisar(sinPunto(data?.message));
            control.recargar();
        } catch (fallo) {
            avisar(sinPunto(mensajeDe(fallo, 'No se pudo guardar')), 'error');
        } finally {
            setEnCurso(null);
        }
    };

    const abrirMover = (aula, persona = null, acceso = null) => {
        setMoviendo({ aula, persona, acceso });
        setDestino(String(otrasDe(aula.aula_id)[0]?.aula_id ?? ''));
        setError(null);
    };

    const mover = async () => {
        if (!moviendo || !destino || enCurso) return;
        setEnCurso('mover');
        setError(null);
        try {
            if (moviendo.acceso) {
                const { data } = await api.post(
                    `/examenes/${examenId}/accesos/${moviendo.acceso.id}/trasladar`,
                    { aula_id: Number(destino) }
                );
                avisar(sinPunto(data?.message));
            } else {
                const { data } = await api.post(`/examenes/${examenId}/movimientos`, {
                    de_aula_id: moviendo.aula.aula_id,
                    a_aula_id: Number(destino),
                    ...(moviendo.persona ? { usuario_id: moviendo.persona.usuario_id } : {}),
                });
                avisar(sinPunto(data?.message));
            }
            setMoviendo(null);
            control.recargar();
            onCambio?.();
        } catch (fallo) {
            const campos = erroresDe(fallo);
            setError(
                sinPunto(
                    campos.a_aula_id ??
                        campos.usuario_id ??
                        campos.aula_id ??
                        mensajeDe(fallo, 'No se pudo guardar')
                )
            );
        } finally {
            setEnCurso(null);
        }
    };

    if (!abierto || aulas.length === 0) return aviso;

    const quien = moviendo?.persona?.nombre ?? moviendo?.acceso?.nombre ?? null;

    return (
        <>
            {pendientes.length > 0 && (
                <section
                    aria-label="Accesos por autorizar"
                    className="space-y-2 rounded-xl border-2 border-warning-300 bg-warning-50 p-4"
                >
                    <h2 className="flex items-center gap-2 text-sm font-semibold text-warning-800">
                        <KeyRound className="h-4 w-4" aria-hidden="true" />
                        Piden controlar con código · {pendientes.length}
                    </h2>
                    <ul className="space-y-2">
                        {pendientes.map((p) => (
                            <li
                                key={p.id}
                                className="flex flex-wrap items-end gap-2 rounded-lg bg-white p-3"
                            >
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold text-slate-900">
                                        {p.nombre}
                                    </span>
                                    <span className="block text-xs text-slate-600">
                                        Pidió a las {horaDe(p.pedido_en)} · código del aula {p.aula}
                                    </span>
                                </span>
                                <Seleccion
                                    aria-label={`Aula para ${p.nombre}`}
                                    className="w-32"
                                    value={elegidas[p.id] ?? String(p.aula_id)}
                                    onChange={(e) =>
                                        setElegidas((v) => ({ ...v, [p.id]: e.target.value }))
                                    }
                                >
                                    {aulas.map((a) => (
                                        <option key={a.aula_id} value={a.aula_id}>
                                            Aula {a.nombre}
                                        </option>
                                    ))}
                                </Seleccion>
                                <Boton
                                    type="button"
                                    variante="secundario"
                                    disabled={enCurso !== null}
                                    onClick={() => resolver(p, 'rechazar')}
                                >
                                    Rechazar
                                </Boton>
                                <Boton
                                    type="button"
                                    disabled={enCurso !== null}
                                    cargando={enCurso === `${p.id}-autorizar`}
                                    onClick={() =>
                                        resolver(
                                            p,
                                            'autorizar',
                                            Number(elegidas[p.id] ?? p.aula_id)
                                        )
                                    }
                                >
                                    <ShieldCheck className="h-4 w-4" /> Autorizar
                                </Boton>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <Tarjeta titulo="Quién controla" sinRelleno>
                <ul className="divide-y divide-slate-200">
                    {aulas.map((a) => {
                        const autorizados = a.accesos.filter((x) => x.estado === 'autorizado');
                        return (
                            <li key={a.aula_id} className="px-4 py-3 sm:px-5">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <h3 className="text-sm font-semibold text-slate-900">
                                        Aula {a.nombre}
                                    </h3>
                                    {aulas.length > 1 && (
                                        <Boton
                                            type="button"
                                            variante="enlace"
                                            tamano="chico"
                                            onClick={() => abrirMover(a)}
                                        >
                                            <UsersRound className="h-4 w-4" /> Mover estudiantes
                                        </Boton>
                                    )}
                                </div>
                                <ul className="mt-1 space-y-1">
                                    {a.personal.map((p) => (
                                        <li
                                            key={`u${p.usuario_id}`}
                                            className="flex flex-wrap items-center gap-x-3 gap-y-0.5"
                                        >
                                            <UserRound
                                                className="h-4 w-4 shrink-0 text-slate-400"
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0 text-sm text-slate-800">
                                                {p.nombre}
                                            </span>
                                            <Insignia tono="neutro">{ROL[p.rol] ?? p.rol}</Insignia>
                                            <EnLinea visto={p.visto_en} />
                                            {aulas.length > 1 && p.rol !== 'otro' && (
                                                <Boton
                                                    type="button"
                                                    variante="enlace"
                                                    tamano="chico"
                                                    className="ml-auto"
                                                    aria-label={`Mover a ${p.nombre} a otra aula`}
                                                    onClick={() => abrirMover(a, p)}
                                                >
                                                    <ArrowRightLeft className="h-4 w-4" /> Mover
                                                </Boton>
                                            )}
                                        </li>
                                    ))}
                                    {autorizados.map((x) => (
                                        <li
                                            key={`a${x.id}`}
                                            className="flex flex-wrap items-center gap-x-3 gap-y-0.5"
                                        >
                                            <KeyRound
                                                className="h-4 w-4 shrink-0 text-warning-700"
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0 text-sm text-slate-800">
                                                {x.nombre}
                                            </span>
                                            <Insignia tono="advertencia">Código de aula</Insignia>
                                            <EnLinea visto={x.visto_en} />
                                            <span className="ml-auto flex gap-1">
                                                {aulas.length > 1 && (
                                                    <Boton
                                                        type="button"
                                                        variante="enlace"
                                                        tamano="chico"
                                                        aria-label={`Mover a ${x.nombre} a otra aula`}
                                                        onClick={() => abrirMover(a, null, x)}
                                                    >
                                                        <ArrowRightLeft className="h-4 w-4" /> Mover
                                                    </Boton>
                                                )}
                                                <Boton
                                                    type="button"
                                                    variante="enlace"
                                                    tamano="chico"
                                                    className="text-danger-700"
                                                    disabled={enCurso !== null}
                                                    aria-label={`Cortar el acceso de ${x.nombre}`}
                                                    onClick={() => resolver(x, 'cortar')}
                                                >
                                                    Cortar
                                                </Boton>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </li>
                        );
                    })}
                </ul>
            </Tarjeta>

            {moviendo && (
                <Dialogo
                    titulo={
                        quien
                            ? `Mover a ${quien}`
                            : `Mover estudiantes del aula ${moviendo.aula.nombre}`
                    }
                    onCerrar={() => setMoviendo(null)}
                    acciones={
                        <>
                            <Boton
                                type="button"
                                variante="secundario"
                                onClick={() => setMoviendo(null)}
                            >
                                Cancelar
                            </Boton>
                            <Boton
                                type="button"
                                disabled={!destino}
                                cargando={enCurso === 'mover'}
                                onClick={mover}
                            >
                                Mover
                            </Boton>
                        </>
                    }
                >
                    <div className="space-y-3">
                        <p className="text-sm text-slate-700">
                            {quien
                                ? `Deja el aula ${moviendo.aula.nombre}.`
                                : 'Los que todavía no entraron; quienes ya entraron se quedan.'}
                        </p>
                        <Seleccion
                            etiqueta="Aula de destino"
                            requerido
                            value={destino}
                            onChange={(e) => setDestino(e.target.value)}
                        >
                            {otrasDe(moviendo.aula.aula_id).map((a) => (
                                <option key={a.aula_id} value={a.aula_id}>
                                    Aula {a.nombre}
                                </option>
                            ))}
                        </Seleccion>
                        {error && (
                            <p role="alert" className="text-sm text-danger-600">
                                {error}
                            </p>
                        )}
                    </div>
                </Dialogo>
            )}
            {aviso}
        </>
    );
}

ControlDelExamen.propTypes = {
    examenId: PropTypes.number.isRequired,
    abierto: PropTypes.bool,
    onCambio: PropTypes.func,
};
