import PropTypes from 'prop-types';
import { useEffect, useRef, useState } from 'react';
import {
    AlertTriangle,
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
import usarConsulta from '../../api/usarConsulta';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Campo from '../../componentes/Campo';
import Insignia from '../../componentes/Insignia';
import MapaCampus from '../../componentes/MapaCampus';
import Seleccion from '../../componentes/Seleccion';
import { colorDeAsignatura, ubicacionSin } from '../../utiles/texto';
import IconoAsignatura from '../../componentes/IconoAsignatura';

// Los veredictos de la puerta (ruta 64), más «No registrado» (nadie con
// ese código o C.I.) y la excepción ya admitida. El rechazo firme va en
// rojo y se distingue por el ícono y el texto, no por el color.
export const VEREDICTOS = {
    admitido_excepcion: {
        tono: 'exito',
        Icono: CheckCircle2,
        titulo: 'Admitido como excepción',
    },
    no_registrado: { tono: 'peligro', Icono: SearchX, titulo: 'No registrado' },
    admitido: { tono: 'exito', Icono: CheckCircle2, titulo: 'Ingreso admitido' },
    no_habilitado: { tono: 'peligro', Icono: XCircle, titulo: 'No habilitado' },
    otra_aula: { tono: 'advertencia', Icono: AlertTriangle, titulo: 'Otra aula' },
    ya_ingreso: { tono: 'peligro', Icono: History, titulo: 'Ya ingresó' },
    expulsado: { tono: 'peligro', Icono: UserX, titulo: 'Expulsado' },
    no_corresponde: { tono: 'peligro', Icono: ScanSearch, titulo: 'No es de esta aula' },
};

const CABECERA = {
    exito: 'bg-success-600',
    peligro: 'bg-danger-600',
    advertencia: 'bg-warning-600',
    info: 'bg-info-600',
};

const BORDE = {
    exito: 'border-success-600',
    peligro: 'border-danger-600',
    advertencia: 'border-warning-600',
    info: 'border-info-600',
};

// Situaciones excepcionales (ruta 65). Solo el cambio de aula admite.
const TIPOS_EXCEPCION = {
    PROBLEMA_IDENTIFICACION: 'Problema de identificación',
    CAMBIO_AULA: 'Cambio de aula',
    NO_HABILITADO: 'Estudiante no habilitado',
};
const SUGERIDO = { otra_aula: 'CAMBIO_AULA', no_habilitado: 'NO_HABILITADO' };
const CON_EXCEPCION = ['no_habilitado', 'otra_aula', 'no_corresponde'];
const MOTIVO_MINIMO = 5;

const sinPunto = (texto) => String(texto ?? '').replace(/\.$/, '');

// Dónde queda el aula correcta, con «Aquí» en la de esta puerta. Los
// edificios salen de la ruta propia de la puerta, que también alcanza quien
// entró con código de aula (GET /api/edificios exige cuenta).
function MapaDeDestino({ destino, origen, aulaId }) {
    const { datos, meta } = usarConsulta(`/puerta/aulas/${aulaId}/edificios`);
    const edificios = datos ?? [];
    const caja = meta?.caja ?? null;
    if (edificios.length === 0) return null;
    const facultad = edificios.find((e) => e.id === destino.edificio_id)?.facultad ?? null;

    return (
        <MapaCampus
            edificios={edificios}
            caja={caja}
            facultad={facultad}
            resaltados={[destino.edificio_id]}
            contorno={origen ? [origen] : []}
            rotulosEdificio={{
                ...(origen ? { [origen]: 'Aquí' } : {}),
                [destino.edificio_id]: `Aula ${destino.nombre}`,
            }}
            tamanoRotulo={52}
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
    aulaId: PropTypes.number.isRequired,
};

// El veredicto ocupa el lugar del visor para leerse sin desplazar la
// pantalla; «Siguiente» devuelve al visor. `registrarExcepcion({ tipo, motivo })`
// hace la llamada y devuelve su promesa.
export default function VeredictoPuerta({
    veredicto,
    aula,
    conMapa = true,
    operador,
    hora,
    registrarExcepcion,
    conExcepciones = true,
    onSiguiente,
}) {
    const clave = veredicto.veredicto;
    const { estudiante, examen } = veredicto;
    const destino = veredicto.aula_correcta ?? null;
    const otro = veredicto.examen_correspondiente ?? null;
    // El cambio de aula exige estudiante y examen resueltos, y que esté
    // habilitado: a un no habilitado el servidor se lo rechaza siempre.
    const tipos = Object.keys(TIPOS_EXCEPCION).filter(
        (t) => t !== 'CAMBIO_AULA' || (estudiante && examen && clave !== 'no_habilitado')
    );

    const [abierta, setAbierta] = useState(false);
    const [tipo, setTipo] = useState(SUGERIDO[clave] ?? 'PROBLEMA_IDENTIFICACION');
    const [motivo, setMotivo] = useState('');
    const [errorMotivo, setErrorMotivo] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [confirmacion, setConfirmacion] = useState(null);
    const [admitida, setAdmitida] = useState(false);
    const [aviso, avisar] = useAviso();
    const caja = useRef(null);
    // Una excepción que dejó entrar cambia el veredicto a la vista. Sin
    // examen que le corresponda, el estudiante no está inscrito en nada.
    const forma = admitida
        ? VEREDICTOS.admitido_excepcion
        : clave === 'no_corresponde' && estudiante && !otro
          ? { ...VEREDICTOS.no_corresponde, titulo: 'No inscrito' }
          : (VEREDICTOS[clave] ?? VEREDICTOS.no_corresponde);

    // Con el veredicto nuevo, o el cambio a admitido, la cabecera vuelve a
    // la vista.
    useEffect(() => {
        caja.current?.scrollIntoView?.({ block: 'start', behavior: 'smooth' });
    }, [veredicto, admitida]);

    const cerrar = () => {
        setAbierta(false);
        setMotivo('');
        setErrorMotivo(null);
    };
    const registrar = async () => {
        if (enviando) return;
        if (motivo.trim().length < MOTIVO_MINIMO) {
            setErrorMotivo(`Mínimo ${MOTIVO_MINIMO} caracteres`);
            return;
        }
        setEnviando(true);
        try {
            const data = await registrarExcepcion({ tipo, motivo: motivo.trim() });
            if (data?.ingreso) setAdmitida(true);
            setConfirmacion(
                [
                    sinPunto(data?.message) ||
                        `Situación excepcional registrada: ${TIPOS_EXCEPCION[tipo].toLowerCase()}`,
                    data?.ingreso?.hora && `Ingresó a las ${data.ingreso.hora}`,
                ]
                    .filter(Boolean)
                    .join(' · ')
            );
            cerrar();
        } catch (fallo) {
            const campo = erroresDe(fallo).motivo;
            if (campo) setErrorMotivo(campo);
            else avisar(sinPunto(mensajeDe(fallo, 'No se pudo registrar')), 'error');
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
                            <p className="text-lg font-semibold leading-snug text-slate-900">
                                {estudiante.nombre}
                            </p>
                            <p className="text-sm text-slate-600">Código SIS {estudiante.codigo}</p>
                        </div>
                    </div>
                )}
                {examen && (
                    <p className="mt-2 flex items-center gap-2 text-sm font-medium text-slate-800">
                        <IconoAsignatura
                            nombre={examen.asignatura}
                            color={colorDeAsignatura(examen.asignatura)}
                            tamano="xs"
                        />
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
                        <p className="text-sm text-slate-600">
                            {ubicacionSin(destino.nombre, destino.edificio, destino.piso)}
                        </p>
                    </div>
                )}

                {conExcepciones && !abierta && !confirmacion && CON_EXCEPCION.includes(clave) && (
                    <Boton
                        type="button"
                        variante="secundario"
                        className="mt-4 min-h-12 w-full"
                        onClick={() => setAbierta(true)}
                    >
                        {clave === 'otra_aula' ? 'Admitir como excepción' : 'Situación excepcional'}
                    </Boton>
                )}

                {abierta && (
                    <div className="mt-4 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
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
                                cargando={enviando}
                            >
                                Registrar
                            </Boton>
                        </div>
                    </div>
                )}

                {confirmacion && (
                    <p className="mt-3 flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-2.5 text-sm font-medium text-slate-800">
                        <CheckCircle2 className="h-4 w-4 shrink-0 text-success-600" />{' '}
                        {confirmacion}
                    </p>
                )}

                <Boton type="button" className="mt-4 h-14 w-full text-base" onClick={onSiguiente}>
                    Siguiente <ArrowRight className="h-5 w-5" />
                </Boton>

                {/* El mapa va después de las acciones: no las empuja fuera de la pantalla. */}
                {destino && !admitida && conMapa && destino.edificio_id && (
                    <div className="mt-4">
                        <MapaDeDestino
                            destino={destino}
                            origen={aula.edificio_id ?? null}
                            aulaId={aula.id}
                        />
                    </div>
                )}
            </div>
            {aviso}
        </div>
    );
}

VeredictoPuerta.propTypes = {
    veredicto: PropTypes.shape({
        veredicto: PropTypes.string.isRequired,
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
        id: PropTypes.number.isRequired,
        nombre: PropTypes.string,
        edificio_id: PropTypes.number,
    }).isRequired,
    conMapa: PropTypes.bool,
    operador: PropTypes.string,
    hora: PropTypes.string,
    registrarExcepcion: PropTypes.func.isRequired,
    conExcepciones: PropTypes.bool,
    onSiguiente: PropTypes.func.isRequired,
};
