import { useState } from 'react';
import PropTypes from 'prop-types';
import { Camera, UsersRound } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import Insignia from '../../componentes/Insignia';
import Seleccion from '../../componentes/Seleccion';
import { diaMes, fechaCorta } from '../../utiles/texto';

// El botón del Padrón con el plazo vigente («Fotos: 1 oct – 31 oct 2026») y
// su diálogo para fijarlo.
export function BotonPlazoFotos({ avisar }) {
    const plazo = usarConsulta('/estudiantes/plazo-fotos');
    const [fijando, setFijando] = useState(false);
    const datos = plazo.datos;

    return (
        <>
            <Boton variante="secundario" onClick={() => setFijando(true)}>
                <Camera className="h-4 w-4" aria-hidden="true" />
                {datos
                    ? `Fotos: ${diaMes(datos.desde)} – ${fechaCorta(datos.hasta)}`
                    : 'Plazo de fotos'}
                {datos && !datos.abierto && (
                    <Insignia tono={datos.estado === 'proximo' ? 'info' : 'neutro'}>
                        {datos.estado === 'proximo' ? 'Próximo' : 'Cerrado'}
                    </Insignia>
                )}
            </Boton>
            {fijando && (
                <PlazoFotos
                    plazo={datos}
                    onCerrar={() => setFijando(false)}
                    onGuardado={plazo.recargar}
                    avisar={avisar}
                />
            )}
        </>
    );
}

BotonPlazoFotos.propTypes = { avisar: PropTypes.func.isRequired };

export function BotonPlazosFotoGrupo({ avisar }) {
    const [abierto, setAbierto] = useState(false);

    return (
        <>
            <Boton variante="secundario" onClick={() => setAbierto(true)}>
                <UsersRound className="h-4 w-4" aria-hidden="true" />
                Plazos por grupo
            </Boton>
            {abierto && <PlazosFotoGrupo onCerrar={() => setAbierto(false)} avisar={avisar} />}
        </>
    );
}

BotonPlazosFotoGrupo.propTypes = { avisar: PropTypes.func.isRequired };

function PlazosFotoGrupo({ onCerrar, avisar }) {
    const consulta = usarConsulta('/estudiantes/plazo-fotos/grupos');
    const grupos = consulta.datos ?? [];
    const [grupoId, setGrupoId] = useState('');
    const [desde, setDesde] = useState('');
    const [hasta, setHasta] = useState('');
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);
    const grupo = grupos.find((item) => String(item.id) === grupoId);

    async function guardar(e) {
        e?.preventDefault();
        if (!grupo) return setErrores({ grupo: 'Seleccione un grupo' });
        const faltas = {
            desde: desde ? undefined : 'Obligatorio',
            hasta: hasta ? undefined : 'Obligatorio',
        };
        if (faltas.desde || faltas.hasta) return setErrores(faltas);
        if (enviando) return undefined;
        setEnviando(true);
        setErrores({});
        try {
            await api.put(`/estudiantes/plazo-fotos/grupos/${grupo.id}`, { desde, hasta });
            avisar('Plazo del grupo guardado');
            await consulta.recargar();
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (Object.keys(campos).length > 0) setErrores(campos);
            else avisar(mensajeDe(fallo, 'No se pudo guardar'), 'error');
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    async function quitar() {
        if (!grupo || enviando) return;
        setEnviando(true);
        setErrores({});
        try {
            await api.delete(`/estudiantes/plazo-fotos/grupos/${grupo.id}`);
            avisar('Plazo del grupo eliminado');
            await consulta.recargar();
        } catch (fallo) {
            avisar(mensajeDe(fallo, 'No se pudo eliminar'), 'error');
        } finally {
            setEnviando(false);
        }
    }

    return (
        <Dialogo
            titulo="Plazos de fotos por grupo"
            onCerrar={onCerrar}
            ancho="max-w-lg"
            acciones={
                <>
                    {grupo?.desde && (
                        <Boton variante="peligro" onClick={quitar} disabled={enviando}>
                            Quitar plazo
                        </Boton>
                    )}
                    <Boton variante="secundario" onClick={onCerrar}>
                        Cerrar
                    </Boton>
                    <Boton onClick={guardar} disabled={enviando || consulta.cargando}>
                        Guardar
                    </Boton>
                </>
            }
        >
            {consulta.error ? (
                <p role="alert" className="text-sm text-danger-700">
                    No se pudieron cargar los grupos.
                </p>
            ) : consulta.cargando ? (
                <p className="text-sm text-slate-600">Cargando grupos…</p>
            ) : grupos.length === 0 ? (
                <p className="text-sm text-slate-600">No hay grupos disponibles.</p>
            ) : (
                <form className="space-y-4" onSubmit={guardar} noValidate>
                    <Seleccion
                        etiqueta="Grupo"
                        requerido
                        value={grupoId}
                        onChange={(e) => {
                            const elegido = grupos.find(
                                (item) => String(item.id) === e.target.value
                            );
                            setGrupoId(e.target.value);
                            setDesde(elegido?.desde ?? '');
                            setHasta(elegido?.hasta ?? '');
                            setErrores({});
                        }}
                    >
                        <option value="">Seleccione un grupo</option>
                        {grupos.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.asignatura} · Grupo {item.grupo} · {item.periodo}
                            </option>
                        ))}
                    </Seleccion>
                    {errores.grupo && (
                        <p role="alert" className="text-sm text-danger-600">
                            {errores.grupo}
                        </p>
                    )}
                    {grupo && (
                        <>
                            <p className="text-sm text-slate-600">
                                Si está abierto el plazo general o el de este grupo, el estudiante
                                podrá cargar su fotografía.
                            </p>
                            <div className="grid grid-cols-2 gap-3">
                                <Campo
                                    etiqueta="Desde"
                                    requerido
                                    type="date"
                                    value={desde}
                                    error={errores.desde}
                                    onChange={(e) => setDesde(e.target.value)}
                                />
                                <Campo
                                    etiqueta="Hasta"
                                    requerido
                                    type="date"
                                    min={desde || undefined}
                                    value={hasta}
                                    error={errores.hasta}
                                    onChange={(e) => setHasta(e.target.value)}
                                />
                            </div>
                        </>
                    )}
                </form>
            )}
        </Dialogo>
    );
}

PlazosFotoGrupo.propTypes = {
    onCerrar: PropTypes.func.isRequired,
    avisar: PropTypes.func.isRequired,
};

// Las fechas en que los estudiantes suben su foto (PUT /api/estudiantes/plazo-fotos):
// a lo más 180 días, dentro del período vigente.
export default function PlazoFotos({ plazo, onCerrar, onGuardado, avisar }) {
    const [desde, setDesde] = useState(plazo?.desde ?? '');
    const [hasta, setHasta] = useState(plazo?.hasta ?? '');
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);

    async function guardar(e) {
        e?.preventDefault();
        const faltas = {
            desde: desde ? undefined : 'Obligatorio',
            hasta: hasta ? undefined : 'Obligatorio',
        };
        if (faltas.desde || faltas.hasta) return setErrores(faltas);
        if (enviando) return undefined;
        setEnviando(true);
        setErrores({});
        try {
            await api.put('/estudiantes/plazo-fotos', { desde, hasta });
            avisar('Plazo guardado');
            onGuardado();
            onCerrar();
        } catch (fallo) {
            const campos = erroresDe(fallo);
            if (Object.keys(campos).length > 0) setErrores(campos);
            else avisar(mensajeDe(fallo, 'No se pudo guardar'), 'error');
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    return (
        <Dialogo
            titulo="Plazo de fotos"
            onCerrar={onCerrar}
            acciones={
                <>
                    <Boton variante="secundario" onClick={onCerrar}>
                        Cancelar
                    </Boton>
                    <Boton onClick={guardar} disabled={enviando}>
                        Guardar
                    </Boton>
                </>
            }
        >
            <form className="grid grid-cols-2 gap-3" onSubmit={guardar} noValidate>
                <Campo
                    etiqueta="Desde"
                    requerido
                    type="date"
                    value={desde}
                    error={errores.desde}
                    onChange={(e) => setDesde(e.target.value)}
                />
                <Campo
                    etiqueta="Hasta"
                    requerido
                    type="date"
                    min={desde || undefined}
                    value={hasta}
                    error={errores.hasta}
                    onChange={(e) => setHasta(e.target.value)}
                />
            </form>
        </Dialogo>
    );
}

PlazoFotos.propTypes = {
    plazo: PropTypes.shape({ desde: PropTypes.string, hasta: PropTypes.string }),
    onCerrar: PropTypes.func.isRequired,
    onGuardado: PropTypes.func.isRequired,
    avisar: PropTypes.func.isRequired,
};
