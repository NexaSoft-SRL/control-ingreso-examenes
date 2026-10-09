import { useRef, useState } from 'react';
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import Tarjeta from '../../componentes/Tarjeta';
import { plural } from '../../utiles/texto';
import InscritosDelGrupo from './InscritosDelGrupo';
import SelectorDeGrupos, { TarjetasDeGrupos } from './SelectorDeGrupos';

// Con más grupos que estos, las tarjetas pasan a una lista por asignatura.
const MAXIMO_TARJETAS = 4;

// HU-14 · El docente ve solo sus grupos del período vigente y carga y
// consulta la lista de inscritos de cada uno.
export default function MisGrupos() {
    const { datos, meta, cargando, error, recargar } = usarConsulta('/docente/grupos');
    const [elegidoId, setElegidoId] = useState(null);
    const [resumen, setResumen] = useState(null);
    const [aviso, avisar] = useAviso();
    const detalle = useRef(null);

    const grupos = datos ?? [];
    const denso = grupos.length > MAXIMO_TARJETAS;
    const grupo = grupos.find((g) => g.id === elegidoId) ?? grupos[0] ?? null;
    const sinLista = meta?.sin_lista ?? grupos.filter((g) => !g.con_lista).length;
    const asignaturas = meta?.asignaturas ?? new Set(grupos.map((g) => g.asignatura.nombre)).size;

    function elegir(id) {
        setElegidoId(id);
        // En el teléfono la lista de inscritos queda debajo de los grupos.
        if (window.matchMedia?.('(max-width: 1023px)').matches) {
            requestAnimationFrame(() =>
                detalle.current?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
            );
        }
    }

    function cargada(resultado) {
        setResumen({ grupoId: grupo.id, resultado });
        avisar('Lista cargada');
        recargar();
    }

    const subtitulo = [
        meta?.periodo && `Período ${meta.periodo}`,
        datos && plural(grupos.length, 'grupo'),
        denso && plural(asignaturas, 'asignatura'),
        denso && sinLista > 0 && `${sinLista} sin lista`,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <div className="space-y-6">
            <Encabezado titulo="Mis grupos" subtitulo={subtitulo || undefined} />

            {!grupo ? (
                <Tarjeta sinRelleno>
                    <EstadoCarga
                        cargando={cargando}
                        error={error}
                        vacio={!cargando && !error}
                        textoVacio="Sin grupos"
                        onReintentar={recargar}
                    />
                </Tarjeta>
            ) : (
                <>
                    {!denso && (
                        <TarjetasDeGrupos grupos={grupos} elegido={grupo.id} onElegir={elegir} />
                    )}
                    <div
                        className={
                            denso ? 'grid items-start gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]' : ''
                        }
                    >
                        {denso && (
                            <SelectorDeGrupos
                                grupos={grupos}
                                elegido={grupo.id}
                                onElegir={elegir}
                            />
                        )}
                        <div ref={detalle} className="min-w-0 scroll-mt-4">
                            <InscritosDelGrupo
                                key={grupo.id}
                                grupo={grupo}
                                resumen={resumen?.grupoId === grupo.id ? resumen.resultado : null}
                                onCargada={cargada}
                                onCerrarResumen={() => setResumen(null)}
                                onAvisar={avisar}
                            />
                        </div>
                    </div>
                </>
            )}
            {aviso}
        </div>
    );
}
