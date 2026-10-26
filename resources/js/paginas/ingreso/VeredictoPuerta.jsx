import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import {
    ArrowRight,
    CheckCircle2,
    History,
    ScanSearch,
    SearchX,
    UserRound,
    UserX,
    XCircle,
} from 'lucide-react';
import { erroresDe, mensajeDe } from '../../api/errores';
import usarEdificios from '../../api/usarEdificios';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Insignia from '../../componentes/Insignia';
import MapaCampus from '../../componentes/MapaCampus';
import Seleccion from '../../componentes/Seleccion';

// Los veredictos de la puerta (como en el Proyecto Guía). Los intentos sin
// examen llegan con su `causa` (no inscrito, sin registro, QR no válido).
// El rechazo firme va en rojo y se distingue por el ícono y el texto.
export const VEREDICTOS = {
    ADMITIDO: { tono: 'exito', Icono: CheckCircle2, titulo: 'Ingreso admitido' },
    ADMITIDO_EXCEPCION: { tono: 'exito', Icono: CheckCircle2, titulo: 'Admitido como excepción' },
    NO_HABILITADO: { tono: 'peligro', Icono: XCircle, titulo: 'No habilitado' },
    YA_INGRESO: { tono: 'peligro', Icono: History, titulo: 'Ya ingresó' },
    EXPULSADO: { tono: 'peligro', Icono: UserX, titulo: 'Expulsado' },
    NO_CORRESPONDE: { tono: 'peligro', Icono: ScanSearch, titulo: 'No es de esta aula' },
    NO_INSCRITO: { tono: 'peligro', Icono: ScanSearch, titulo: 'No inscrito' },
    NO_REGISTRADO: { tono: 'peligro', Icono: SearchX, titulo: 'No registrado' },
    QR_NO_VALIDO: { tono: 'peligro', Icono: ScanSearch, titulo: 'Código QR no válido' },
};

const CABECERA = {
    exito: 'bg-success-600',
    peligro: 'bg-danger-600',
    advertencia: 'bg-warning-600',
};

const BORDE = {
    exito: 'border-success-600',
    peligro: 'border-danger-600',
    advertencia: 'border-warning-600',
};

// Situaciones excepcionales. Solo el cambio de aula admite.
export const TIPOS_EXCEPCION = {
    EXCEPCION_IDENTIFICACION: 'Problema de identificación',
    EXCEPCION_CAMBIO_AULA: 'Cambio de aula',
    EXCEPCION_NO_HABILITADO: 'Estudiante no habilitado',
};
const SUGERIDO = { NO_HABILITADO: 'EXCEPCION_NO_HABILITADO' };
const CON_EXCEPCION = ['NO_HABILITADO', 'NO_CORRESPONDE'];
const MOTIVO_MINIMO = 5;

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

export const ubicacionDe = (aula) => [aula?.edificio, aula?.piso].filter(Boolean).join(' · ');

// Dónde queda el aula que le corresponde, con el edificio de esta puerta
// como punto de partida.
function MapaDeDestino({ destino, origen }) {
    const { edificios, caja } = usarEdificios();
    if (edificios.length === 0) return null;

    return (
        <MapaCampus
            edificios={edificios}
            caja={caja}
            resaltados={[destino.edificio_id]}
            contorno={origen ? [origen] : []}
            rotulosEdificio={{
                ...(origen && origen !== destino.edificio_id ? { [origen]: 'Aquí' } : {}),
                [destino.edificio_id]: `Aula ${destino.nombre}`,
            }}
            alto="h-48"
        />
    );
}

MapaDeDestino.propTypes = {
    destino: PropTypes.shape({
        nombre: PropTypes.string,
        edificio_id: PropTypes.number,
    }).isRequired,
    origen: PropTypes.number,
};

function claveDe(veredicto) {
    if (veredicto.causa) return veredicto.causa;
    return veredicto.veredicto ?? 'NO_CORRESPONDE';
}

// El veredicto ocupa el lugar del visor; «Siguiente» devuelve al visor.
// `registrarExcepcion({ tipo, motivo })` hace la llamada y devuelve los datos.
export default function VeredictoPuerta({
    veredicto,
    aula,
    operador = '',
    hora = '',
    registrarExcepcion,
    conExcepciones = true,
    onSiguiente,
}) {
    const clave = claveDe(veredicto);
    const { estudiante, examen } = veredicto;
    const destino = veredicto.aula_correcta ?? null;
    const otro = veredicto.examen_correspondiente ?? null;
    const familia = ['NO_INSCRITO', 'NO_REGISTRADO', 'QR_NO_VALIDO'].includes(clave)
        ? 'NO_CORRESPONDE'
        : clave;
    // El cambio de aula exige estudiante y examen resueltos, y que esté
    // habilitado: a un no habilitado el servidor se lo rechaza siempre.
    const tipos = Object.keys(TIPOS_EXCEPCION).filter(
        (t) => t !== 'EXCEPCION_CAMBIO_AULA' || (estudiante && examen && clave !== 'NO_HABILITADO')
    );

    const [abierta, setAbierta] = useState(false);
    const [tipo, setTipo] = useState(SUGERIDO[clave] ?? 'EXCEPCION_IDENTIFICACION');
    const [motivo, setMotivo] = useState('');
    const [errorMotivo, setErrorMotivo] = useState(null);
    const [errorGeneral, setErrorGeneral] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [confirmacion, setConfirmacion] = useState(null);
    const [admitida, setAdmitida] = useState(false);
    const caja = useRef(null);
    const formulario = useRef(null);

    const forma = admitida
        ? VEREDICTOS.ADMITIDO_EXCEPCION
        : clave === 'NO_INSCRITO' && otro
          ? VEREDICTOS.NO_CORRESPONDE
          : (VEREDICTOS[clave] ?? VEREDICTOS.NO_CORRESPONDE);

    // Con el veredicto nuevo, o el cambio a admitido, la cabecera vuelve a
    // la vista.
    useEffect(() => {
        caja.current?.scrollIntoView?.({ block: 'start', behavior: 'smooth' });
    }, [veredicto, admitida]);

    // N6: al abrir la situación excepcional, el formulario a la vista y el
    // foco en el motivo (en el teléfono queda bajo el veredicto).
    useEffect(() => {
        if (!abierta) return;
        formulario.current?.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
        formulario.current?.querySelector('input')?.focus();
    }, [abierta]);

    const cerrar = () => {
        setAbierta(false);
        setMotivo('');
        setErrorMotivo(null);
        setErrorGeneral(null);
    };

    const registrar = async () => {
        if (enviando) return;
        if (motivo.trim().length < MOTIVO_MINIMO) {
            setErrorMotivo(`Mínimo ${MOTIVO_MINIMO} caracteres`);
            return;
        }
        setEnviando(true);
        setErrorGeneral(null);
        try {
            const respuesta = await registrarExcepcion({ tipo, motivo: motivo.trim() });
            const hora = respuesta?.data?.ingreso?.hora;
            if (hora) setAdmitida(true);
            setConfirmacion(
                [
                    sinPunto(respuesta?.message) ||
                        `Situación excepcional registrada: ${TIPOS_EXCEPCION[tipo].toLowerCase()}`,
                    hora && `Ingresó a las ${hora}`,
                ]
                    .filter(Boolean)
                    .join(' · ')
            );
            cerrar();
        } catch (fallo) {
            const campo = erroresDe(fallo).motivo;
            if (campo) setErrorMotivo(campo);
            else setErrorGeneral(sinPunto(mensajeDe(fallo, 'No se pudo registrar')));
        } finally {
            setEnviando(false);
        }
    };

    return (
        <div
            ref={caja}
            className={`scroll-mt-4 overflow-hidden rounded-2xl border-2 bg-white ${BORDE[forma.tono]}`}
            role="status"
        >
            <div className={`flex items-center gap-3 px-5 py-4 text-white ${CABECERA[forma.tono]}`}>
                <forma.Icono className="h-10 w-10 shrink-0" strokeWidth={2.25} />
                <p className="text-2xl font-bold leading-tight">{forma.titulo}</p>
            </div>

            <div className="p-5">
                {estudiante && (
                    <div className="flex items-start gap-4">
                        {estudiante.foto ? (
                            <img
                                src={estudiante.foto}
                                alt={`Foto de ${estudiante.nombre}`}
                                className="h-32 w-24 shrink-0 rounded-lg border border-slate-200 object-cover"
                            />
                        ) : (
                            <span className="flex h-32 w-24 shrink-0 flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-slate-300 bg-slate-50 text-xs text-slate-500">
                                <UserRound className="h-8 w-8 text-slate-400" strokeWidth={1.5} />
                                Sin foto
                            </span>
                        )}
                        <div className="min-w-0">
                            <p className="break-words text-lg font-semibold leading-snug text-slate-900">
                                {estudiante.nombre}
                            </p>
                            <p className="text-sm text-slate-600">Código SIS {estudiante.codigo}</p>
                        </div>
                    </div>
                )}
                {examen && (
                    <p className="mt-2 text-sm font-medium text-slate-800">
                        {examen.asignatura} · {examen.tipo}
                    </p>
                )}
                {veredicto.dato && (
                    <p className="mt-3 text-base font-medium text-slate-800">{veredicto.dato}</p>
                )}
                {otro && (
                    <p className="text-sm text-slate-800">
                        {otro.texto}
                        {otro.aula && ` · Aula ${otro.aula}`}
                    </p>
                )}
                {veredicto.tarde ? (
                    <p className="mt-2">
                        <Insignia tono="advertencia" punto>
                            Tarde · {veredicto.tarde} min después del inicio
                        </Insignia>
                    </p>
                ) : (
                    veredicto.pie && <p className="text-sm text-slate-600">{veredicto.pie}</p>
                )}

                {admitida && (
                    <p className="mt-3 text-2xl font-bold text-slate-900">
                        Rinde en Aula {aula.nombre}
                    </p>
                )}

                {destino && !admitida && (
                    <div className="mt-3">
                        <p className="text-2xl font-bold text-slate-900">Aula {destino.nombre}</p>
                        {ubicacionDe(destino) && (
                            <p className="text-sm text-slate-600">{ubicacionDe(destino)}</p>
                        )}
                    </div>
                )}

                {conExcepciones && !abierta && !confirmacion && CON_EXCEPCION.includes(familia) && (
                    <Boton
                        type="button"
                        variante="secundario"
                        className="mt-4 min-h-12 w-full"
                        onClick={() => setAbierta(true)}
                    >
                        Situación excepcional
                    </Boton>
                )}

                {abierta && (
                    <div
                        ref={formulario}
                        className="mt-4 scroll-mt-4 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <p className="text-sm font-semibold text-slate-800">
                            Situación excepcional
                        </p>
                        <Seleccion
                            etiqueta="Tipo"
                            value={tipo}
                            onChange={(e) => setTipo(e.target.value)}
                        >
                            {tipos.map((t) => (
                                <option key={t} value={t}>
                                    {TIPOS_EXCEPCION[t]}
                                </option>
                            ))}
                        </Seleccion>
                        <Campo
                            etiqueta="Motivo"
                            requerido
                            placeholder="Motivo"
                            maxLength={500}
                            value={motivo}
                            error={errorMotivo}
                            ayuda={`Mínimo ${MOTIVO_MINIMO} caracteres`}
                            onChange={(e) => {
                                setMotivo(e.target.value);
                                setErrorMotivo(null);
                            }}
                        />
                        <p className="text-xs text-slate-600">
                            {[operador, hora].filter(Boolean).join(' · ')}
                        </p>
                        {errorGeneral && (
                            <p role="alert" className="text-sm text-danger-700">
                                {errorGeneral}
                            </p>
                        )}
                        <div className="flex gap-2">
                            <Boton
                                type="button"
                                variante="secundario"
                                className="min-h-12 flex-1"
                                onClick={cerrar}
                            >
                                Cancelar
                            </Boton>
                            <Boton
                                type="button"
                                className="min-h-12 flex-1"
                                onClick={registrar}
                                disabled={enviando || motivo.trim().length < MOTIVO_MINIMO}
                            >
                                {enviando ? 'Registrando…' : 'Registrar'}
                            </Boton>
                        </div>
                    </div>
                )}

                {confirmacion && (
                    <p className="mt-3 flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2.5 text-sm font-medium text-slate-800">
                        <CheckCircle2 className="h-4 w-4 shrink-0 text-success-600" />
                        {confirmacion}
                    </p>
                )}

                <Boton type="button" className="mt-4 h-14 w-full text-base" onClick={onSiguiente}>
                    Siguiente <ArrowRight className="h-5 w-5" />
                </Boton>

                {/* El mapa va después de las acciones: no las empuja fuera de la pantalla. */}
                {destino?.edificio_id && !admitida && (
                    <div className="mt-4">
                        <MapaDeDestino destino={destino} origen={aula.edificio_id ?? null} />
                    </div>
                )}
            </div>
        </div>
    );
}

VeredictoPuerta.propTypes = {
    veredicto: PropTypes.shape({
        veredicto: PropTypes.string,
        causa: PropTypes.string,
        estudiante: PropTypes.shape({
            id: PropTypes.number,
            codigo: PropTypes.string,
            nombre: PropTypes.string,
            foto: PropTypes.string,
        }),
        examen: PropTypes.shape({
            id: PropTypes.number,
            asignatura: PropTypes.string,
            tipo: PropTypes.string,
        }),
        dato: PropTypes.string,
        pie: PropTypes.string,
        tarde: PropTypes.number,
        aula_correcta: PropTypes.shape({
            id: PropTypes.number,
            nombre: PropTypes.string,
            edificio: PropTypes.string,
            piso: PropTypes.string,
            edificio_id: PropTypes.number,
        }),
        examen_correspondiente: PropTypes.shape({
            texto: PropTypes.string,
            aula: PropTypes.string,
        }),
    }).isRequired,
    aula: PropTypes.shape({
        id: PropTypes.number,
        nombre: PropTypes.string,
        edificio_id: PropTypes.number,
    }).isRequired,
    operador: PropTypes.string,
    hora: PropTypes.string,
    registrarExcepcion: PropTypes.func.isRequired,
    conExcepciones: PropTypes.bool,
    onSiguiente: PropTypes.func.isRequired,
};
