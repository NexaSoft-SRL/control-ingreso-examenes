import { useState } from 'react';
import PropTypes from 'prop-types';
import { Camera } from 'lucide-react';
import { api } from '../../api/cliente';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarConsulta from '../../api/usarConsulta';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Dialogo from '../../componentes/Dialogo';
import Insignia from '../../componentes/Insignia';
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
