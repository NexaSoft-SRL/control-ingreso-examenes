import PropTypes from 'prop-types';
import { useState } from 'react';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import usarSondeo from '../../api/usarSondeo';
import { useAviso } from '../../componentes/Aviso';
import Chips from '../../componentes/Chips';
import { ListaIncidencias } from '../../componentes/Incidencias';
import Tarjeta from '../../componentes/Tarjeta';

const INTERVALO = 10000;

// Las incidencias de un examen (`aulaId` nulo) o de un aula: «Sin atender»
// de entrada, cinco a la vista. El docente del examen las atiende y las
// corrige (`puedeAtender`). Tras reportar una desde la misma pantalla, la
// página la monta de nuevo (`key`) para pedirlas otra vez.
export default function IncidenciasDelExamen({
    examenId,
    aulaId = null,
    puedeAtender = false,
    conAula = false,
}) {
    const [aviso, avisar] = useAviso();
    const [soloSinAtender, setSoloSinAtender] = useState(true);
    const consulta = usarSondeo(`/examenes/${examenId}/incidencias`, {
        parametros: aulaId ? { aula: aulaId } : undefined,
        intervalo: INTERVALO,
    });
    const incidencias = Array.isArray(consulta.datos) ? consulta.datos : [];
    const sinAtender = incidencias.filter((i) => !i.atendida);

    const atender = async (id) => {
        try {
            await api.post(`/incidencias/${id}/atencion`);
            avisar('Incidencia atendida');
        } catch (error) {
            avisar(mensajeDe(error, 'No se pudo atender'), 'error');
        } finally {
            consulta.recargar();
        }
    };

    const corregir = async (id, cuerpo) => {
        await api.post(`/incidencias/${id}/correccion`, cuerpo);
        avisar('Incidencia corregida');
        consulta.recargar();
    };

    if (incidencias.length === 0) return aviso;

    return (
        <>
            <Tarjeta
                titulo={`Incidencias · ${incidencias.length}`}
                sinRelleno
                acciones={
                    <Chips
                        etiqueta="Incidencias"
                        valor={soloSinAtender}
                        onCambiar={setSoloSinAtender}
                        opciones={[
                            { valor: true, etiqueta: 'Sin atender', conteo: sinAtender.length },
                            { valor: false, etiqueta: 'Todas', conteo: incidencias.length },
                        ]}
                    />
                }
            >
                <ListaIncidencias
                    items={soloSinAtender ? sinAtender : incidencias}
                    atender={puedeAtender ? atender : undefined}
                    corregir={puedeAtender ? corregir : undefined}
                    conAula={conAula}
                    limite={5}
                />
            </Tarjeta>
            {aviso}
        </>
    );
}

IncidenciasDelExamen.propTypes = {
    examenId: PropTypes.number.isRequired,
    aulaId: PropTypes.number,
    puedeAtender: PropTypes.bool,
    conAula: PropTypes.bool,
};
