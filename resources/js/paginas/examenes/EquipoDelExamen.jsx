import PropTypes from 'prop-types';
import { useEffect, useState } from 'react';
import { Plus, UserCheck, X } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Buscador from '../../componentes/Buscador';
import Insignia from '../../componentes/Insignia';
import Tarjeta from '../../componentes/Tarjeta';
import { plural } from '../../utiles/texto';
import { AuxiliarNuevo } from './QuienAtiende';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
// La búsqueda entre todos los auxiliares pide al menos tres letras.
const MINIMO_BUSQUEDA = 3;
const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

// El equipo de un examen ya registrado: cada docente del examen suma a sus
// auxiliares y saca a los que sumó; quien lo registró puede sacar a cualquiera.
// Cualquiera del equipo atiende cualquier aula del examen y todo queda con su
// nombre.
export default function EquipoDelExamen({ examenId, onCambio }) {
    const detalle = usarConsulta(`/examenes/${examenId}`);
    const propios = usarConsulta('/auxiliares');
    const [buscar, setBuscar] = useState('');
    const [buscado, setBuscado] = useState('');
    const [ocupado, setOcupado] = useState(null);
    const [aviso, avisar] = useAviso();
    useEffect(() => {
        const espera = setTimeout(() => setBuscado(buscar.trim()), 300);
        return () => clearTimeout(espera);
    }, [buscar]);
    const buscando = buscado.length >= MINIMO_BUSQUEDA;
    const hallados = usarConsulta('/auxiliares', {
        parametros: { buscar: buscado },
        activa: buscando,
    });

    const equipo = detalle.datos?.equipo ?? [];
    const enEquipo = (id) => equipo.some((m) => m.id === id);
    const disponibles = [
        ...(propios.datos ?? []),
        ...(buscando ? (hallados.datos ?? []) : []),
    ].filter((p, i, todos) => !enEquipo(p.id) && todos.findIndex((q) => q.id === p.id) === i);

    async function cambiar(accion, persona, textoExito) {
        setOcupado(persona.id);
        try {
            if (accion === 'sumar') {
                await api.post(`/examenes/${examenId}/equipo`, { usuario_id: persona.id });
            } else {
                await api.delete(`/examenes/${examenId}/equipo/${persona.id}`);
            }
            avisar(textoExito);
            detalle.recargar();
            onCambio?.();
        } catch (fallo) {
            avisar(
                sinPunto(
                    erroresDe(fallo).usuario_id ?? mensajeDe(fallo, 'No se pudo cambiar el equipo')
                ),
                'error'
            );
        } finally {
            setOcupado(null);
        }
    }

    return (
        <Tarjeta
            titulo="Equipo"
            icono={UserCheck}
            acciones={<Insignia tono="primario">{plural(equipo.length, 'persona')}</Insignia>}
        >
            {equipo.length > 0 ? (
                <ul aria-label="Equipo del examen" className="mb-4 space-y-2">
                    {equipo.map((m) => (
                        <li
                            key={m.id}
                            className="flex min-w-0 items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5"
                        >
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium text-slate-900">
                                    {m.nombre}
                                </span>
                                <span className="block truncate text-xs text-slate-600">
                                    {m.a_cargo ? 'A cargo de un aula · ' : ''}
                                    {m.es_docente
                                        ? 'docente'
                                        : m.agregado_por_nombre
                                          ? `lo sumó ${m.agregado_por_nombre}`
                                          : 'auxiliar'}
                                </span>
                            </span>
                            {m.puede_quitar && (
                                <button
                                    type="button"
                                    aria-label={`Sacar a ${m.nombre} del equipo`}
                                    disabled={ocupado === m.id}
                                    onClick={() =>
                                        cambiar('quitar', m, `${m.nombre} salió del equipo`)
                                    }
                                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 disabled:opacity-50 ${FOCO}`}
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mb-4 text-sm text-slate-600">Sin auxiliares en el equipo</p>
            )}

            <div className="rounded-lg bg-slate-50 p-4">
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h3 className="text-sm font-semibold text-slate-800">Sumar auxiliares</h3>
                    <Buscador
                        valor={buscar}
                        onCambiar={setBuscar}
                        placeholder="Buscar auxiliar"
                        className="w-full sm:w-64"
                    />
                </div>
                <ul className="mb-4 flex flex-wrap gap-2" aria-label="Auxiliares disponibles">
                    {disponibles.map((p) => (
                        <li key={p.id} className="min-w-0 max-w-full">
                            <button
                                type="button"
                                aria-label={`Sumar a ${p.nombre}`}
                                disabled={ocupado === p.id}
                                onClick={() => cambiar('sumar', p, `${p.nombre} sumado al equipo`)}
                                className={`inline-flex min-h-10 max-w-full items-center gap-1.5 rounded-full border border-dashed border-primary-500 bg-white px-3 text-sm font-medium text-primary-700 hover:bg-primary-50 disabled:opacity-50 ${FOCO}`}
                            >
                                <Plus className="h-4 w-4 shrink-0" />
                                <span className="truncate">{p.nombre}</span>
                            </button>
                        </li>
                    ))}
                    {disponibles.length === 0 && !propios.cargando && (
                        <li className="text-sm text-slate-600">Sin auxiliares para sumar</li>
                    )}
                </ul>
                <AuxiliarNuevo
                    onAgregado={(persona) => {
                        propios.recargar();
                        cambiar('sumar', persona, `${persona.nombre} sumado al equipo`);
                    }}
                    onAviso={avisar}
                />
            </div>
            {aviso}
        </Tarjeta>
    );
}

EquipoDelExamen.propTypes = {
    examenId: PropTypes.number.isRequired,
    onCambio: PropTypes.func,
};
