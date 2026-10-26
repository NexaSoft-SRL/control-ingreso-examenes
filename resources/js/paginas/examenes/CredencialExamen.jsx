import PropTypes from 'prop-types';
import { Mail, MapPin, Send } from 'lucide-react';
import Boton from '../../componentes/Boton';
import EstadoCarga from '../../componentes/EstadoCarga';
import Insignia from '../../componentes/Insignia';
import MapaCampus from '../../componentes/MapaCampus';
import Tarjeta from '../../componentes/Tarjeta';
import { fechaCorta, fechaLarga, normalizar } from '../../utiles/texto';

// «Edificio · piso» sin repetir el nombre del aula: en la oferta algunos
// edificios se llaman como el aula («Aula 624»).
function ubicacionDe(aula) {
    const propio = [normalizar(`Aula ${aula.nombre}`), normalizar(String(aula.nombre))];
    return [aula.edificio, aula.piso]
        .filter(Boolean)
        .filter((parte) => !propio.includes(normalizar(String(parte).trim())))
        .join(' · ');
}

// Estado del código de un estudiante: «Sin emitir», «Sin enviar», «Fallido»
// o «Enviado». `fallido`: el último correo no salió y aún no lo recibió.
export function EstadoEnvio({ emitido, enviadoEn, fallido = false, conFecha = false }) {
    if (!emitido) return <Insignia tono="neutro">Sin emitir</Insignia>;
    if (!enviadoEn && fallido) return <Insignia tono="peligro">Fallido</Insignia>;
    if (!enviadoEn) return <Insignia tono="advertencia">Sin enviar</Insignia>;
    return (
        <Insignia tono="exito">
            {conFecha ? `Enviado · ${fechaCorta(enviadoEn)}` : 'Enviado'}
        </Insignia>
    );
}

EstadoEnvio.propTypes = {
    emitido: PropTypes.bool,
    enviadoEn: PropTypes.string,
    fallido: PropTypes.bool,
    conFecha: PropTypes.bool,
};

// La credencial de un estudiante (GET /examenes/{examen}/codigos-qr/{estudiante})
// y dónde rinde. `enviadoEn`, `fallido` y `errorEnvio` salen de la fila de la
// lista; `onEnviar` envía o reenvía solo esa credencial.
export default function CredencialExamen({
    credencial,
    cargando,
    error,
    onReintentar,
    enviadoEn,
    fallido = false,
    errorEnvio,
    onEnviar,
    enviando = false,
    edificios,
    caja,
}) {
    if (!credencial) {
        return (
            <Tarjeta titulo="Credencial" sinRelleno>
                <EstadoCarga
                    cargando={cargando}
                    error={error}
                    onReintentar={onReintentar}
                    filas={3}
                />
            </Tarjeta>
        );
    }

    const { aula } = credencial;
    const lugar = aula ? ubicacionDe(aula) : '';

    return (
        <>
            <Tarjeta titulo="Credencial">
                <div className="mx-auto max-w-sm rounded-xl border border-slate-300 bg-white p-4 shadow-sm">
                    <p className="text-xs font-semibold uppercase tracking-wide text-primary-700">
                        UMSS
                        {credencial.facultad && (
                            <>
                                {' · '}
                                <span className="normal-case">{credencial.facultad}</span>
                            </>
                        )}
                    </p>
                    <p className="text-sm font-semibold text-slate-800">{credencial.examen}</p>
                    <div className="mt-3 flex gap-4">
                        {credencial.qr ? (
                            <img
                                src={credencial.qr}
                                alt="Código QR"
                                className="h-28 w-28 shrink-0 rounded bg-white p-1 ring-1 ring-slate-300"
                            />
                        ) : (
                            <div className="flex h-28 w-28 shrink-0 items-center justify-center rounded bg-slate-50 text-center text-xs text-slate-600 ring-1 ring-slate-300">
                                Sin emitir
                            </div>
                        )}
                        <div className="min-w-0 text-sm">
                            <p className="break-words font-semibold text-slate-800">
                                {credencial.estudiante}
                            </p>
                            <p className="text-slate-600">Código SIS {credencial.codigo}</p>
                            <p className="mt-2 text-slate-600">{fechaLarga(credencial.fecha)}</p>
                            <p className="text-slate-600">
                                {credencial.hora} · {credencial.duracion} min
                            </p>
                        </div>
                    </div>
                    <div className="mt-3 flex items-start gap-2 rounded-lg bg-primary-50 p-3 text-sm text-primary-900">
                        <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-primary-700" />
                        <p className="min-w-0">
                            <strong>{aula ? `Aula ${aula.nombre}` : 'Sin aula'}</strong>
                            {lugar && (
                                <>
                                    <br />
                                    {lugar}
                                </>
                            )}
                        </p>
                    </div>
                </div>
                {credencial.correo && (
                    <p className="mx-auto mt-4 flex max-w-sm items-center gap-2 text-sm text-slate-600">
                        <Mail className="h-4 w-4 shrink-0" />
                        <span className="min-w-0 break-all">{credencial.correo}</span>
                    </p>
                )}
                <div className="mx-auto mt-3 flex max-w-sm flex-wrap items-center justify-between gap-2">
                    <EstadoEnvio
                        emitido={credencial.emitido}
                        enviadoEn={enviadoEn}
                        fallido={fallido}
                        conFecha
                    />
                    {credencial.emitido && (
                        <Boton
                            type="button"
                            variante="secundario"
                            tamano="chico"
                            disabled={enviando}
                            onClick={onEnviar}
                        >
                            <Send className="h-4 w-4" />
                            {enviadoEn || fallido ? 'Reenviar' : 'Enviar'}
                        </Boton>
                    )}
                </div>
                {fallido && !enviadoEn && errorEnvio && (
                    <p
                        role="alert"
                        className="mx-auto mt-2 max-w-sm break-words text-sm text-danger-700"
                    >
                        {errorEnvio}
                    </p>
                )}
            </Tarjeta>

            {aula?.edificio_id && edificios.length > 0 && (
                <Tarjeta titulo={`Ubicación · aula ${aula.nombre}`}>
                    <MapaCampus
                        edificios={edificios}
                        caja={caja}
                        facultad={credencial.facultad}
                        resaltados={[aula.edificio_id]}
                        alto="h-56"
                    />
                </Tarjeta>
            )}
        </>
    );
}

CredencialExamen.propTypes = {
    credencial: PropTypes.shape({
        facultad: PropTypes.string,
        examen: PropTypes.string,
        fecha: PropTypes.string,
        hora: PropTypes.string,
        duracion: PropTypes.number,
        estudiante: PropTypes.string,
        codigo: PropTypes.string,
        aula: PropTypes.shape({
            nombre: PropTypes.string,
            edificio: PropTypes.string,
            piso: PropTypes.string,
            edificio_id: PropTypes.number,
        }),
        correo: PropTypes.string,
        emitido: PropTypes.bool,
        qr: PropTypes.string,
    }),
    cargando: PropTypes.bool,
    error: PropTypes.any,
    onReintentar: PropTypes.func,
    enviadoEn: PropTypes.string,
    fallido: PropTypes.bool,
    errorEnvio: PropTypes.string,
    onEnviar: PropTypes.func.isRequired,
    enviando: PropTypes.bool,
    edificios: PropTypes.array.isRequired,
    caja: PropTypes.object,
};
