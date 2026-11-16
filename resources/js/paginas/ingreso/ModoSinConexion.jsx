import { useCallback, useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';
import rutaDelExamen from '../examenes/rutaDelExamen';
import { CloudDownload, RefreshCw, Send, WifiOff } from 'lucide-react';
import { api } from '../../api/cliente';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Insignia from '../../componentes/Insignia';
import {
    almacenamientoDisponible,
    esNavegacionPrivada,
    pedirPersistencia,
} from '../../sinConexion/entorno';
import { estadoSinConexion, prepararSinConexion, sincronizar } from '../../sinConexion/flujo';

const REVISION_MS = 3000;
const REENVIO_MS = 15000;
// La instantánea se renueva con conexión para no perder las habilitaciones
// que cambiaron: cada veinte minutos.
const RENOVAR_MS = 20 * 60 * 1000;

const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;

// El modo sin conexión del puesto: se prepara solo mientras hay internet,
// envía lo decidido al volver la conexión y cierra su sesión al salir de la
// puerta. `cambio` sube cada vez que el puesto decide sin conexión.
export default function ModoSinConexion({ aulaId, enLinea, cambio, examenId, conRevision }) {
    const [estado, setEstado] = useState(() => estadoSinConexion(aulaId));
    const [resumen, setResumen] = useState(null);
    const [trabajando, setTrabajando] = useState(false);
    const [entorno, setEntorno] = useState({
        sinAlmacen: false,
        privada: false,
        persistente: null,
    });
    const [aviso, avisar] = useAviso();
    const ultimaCarga = useRef(0);
    const ultimo = useRef({ enLinea, aulaId });
    ultimo.current = { enLinea, aulaId };

    const refrescar = useCallback(() => setEstado(estadoSinConexion(aulaId)), [aulaId]);

    const enviar = useCallback(async () => {
        if (!ultimo.current.enLinea) return;
        try {
            const hecho = await sincronizar(api, aulaId);
            if (hecho && hecho.aplicados > 0) setResumen(hecho);
        } catch {
            // Sin conexión todavía: se reintenta.
        }
        refrescar();
    }, [aulaId, refrescar]);

    const preparar = useCallback(
        async (manual = false) => {
            if (!ultimo.current.enLinea) return;
            setTrabajando(true);
            try {
                const hecho = await prepararSinConexion(api, aulaId);
                ultimaCarga.current = Date.now();
                if (manual && hecho.guardada) avisar('Listo para trabajar sin conexión');
                if (manual && hecho.motivo === 'PENDIENTES')
                    avisar('Primero se envía lo pendiente', 'error');
                if (hecho.motivo === 'ALMACENAMIENTO')
                    avisar('El navegador no pudo guardar la lista', 'error');
            } catch {
                if (manual) avisar('No se pudo preparar', 'error');
            } finally {
                setTrabajando(false);
                refrescar();
            }
        },
        [aulaId, avisar, refrescar]
    );

    // Qué deja guardar este navegador: se revisa una vez al abrir.
    useEffect(() => {
        let vivo = true;
        const sinAlmacen = !almacenamientoDisponible();
        setEntorno((previo) => ({ ...previo, sinAlmacen }));
        if (!sinAlmacen) {
            esNavegacionPrivada().then((privada) => vivo && setEntorno((p) => ({ ...p, privada })));
            pedirPersistencia().then(
                (persistente) => vivo && setEntorno((p) => ({ ...p, persistente }))
            );
        }
        return () => {
            vivo = false;
        };
    }, []);

    // Lo que decidió el puesto cambia su cola.
    useEffect(refrescar, [cambio, refrescar]);

    useEffect(() => {
        const reloj = setInterval(refrescar, REVISION_MS);
        return () => clearInterval(reloj);
    }, [refrescar]);

    // Con conexión: prepara si falta o se venció, y envía lo pendiente.
    useEffect(() => {
        if (!enLinea) return undefined;
        const estadoActual = estadoSinConexion(aulaId);
        const vencida = Date.now() - ultimaCarga.current > RENOVAR_MS;
        if (
            (!estadoActual.preparado || !estadoActual.vigente || vencida) &&
            estadoActual.pendientes === 0
        ) {
            preparar();
        }
        if (estadoActual.pendientes > 0) enviar();
        const reloj = setInterval(() => {
            const actual = estadoSinConexion(aulaId);
            if (actual.pendientes > 0) enviar();
            else if (Date.now() - ultimaCarga.current > RENOVAR_MS) preparar();
        }, REENVIO_MS);
        window.addEventListener('online', enviar);
        return () => {
            clearInterval(reloj);
            window.removeEventListener('online', enviar);
        };
    }, [enLinea, aulaId, enviar, preparar]);

    // Al salir de la puerta con conexión y sin haber decidido nada sin ella,
    // el puesto cierra su sesión: así el servidor no lo toma por perdido.
    useEffect(
        () => () => {
            const { enLinea: conectado, aulaId: aula } = ultimo.current;
            const actual = estadoSinConexion(aula);
            if (conectado && actual.preparado && !actual.huboUso && actual.pendientes === 0) {
                sincronizar(api, aula, { cerrar: true }).catch(() => {});
            }
        },
        []
    );

    return (
        <div className="rounded-xl border border-slate-200 bg-white p-3">
            <div className="flex items-center justify-between gap-2">
                <p className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                    <WifiOff className="h-4 w-4 text-slate-500" aria-hidden="true" /> Modo sin
                    conexión
                </p>
                {estado.preparado && estado.vigente ? (
                    <Insignia tono="exito" punto>
                        Listo
                    </Insignia>
                ) : (
                    <Insignia tono="advertencia">
                        {estado.preparado ? 'De otro día' : 'Sin preparar'}
                    </Insignia>
                )}
            </div>
            {entorno.sinAlmacen && (
                <p
                    role="alert"
                    className="mt-2 rounded-lg bg-danger-50 px-3 py-2 text-xs text-danger-800"
                >
                    Este navegador no guarda datos · el modo sin conexión no funciona
                </p>
            )}
            {!entorno.sinAlmacen && entorno.privada && (
                <p
                    role="alert"
                    className="mt-2 rounded-lg bg-warning-50 px-3 py-2 text-xs text-warning-800"
                >
                    Navegación privada · se pierde al cerrar la ventana
                </p>
            )}
            {!entorno.sinAlmacen && !entorno.privada && entorno.persistente === false && (
                <p
                    role="status"
                    className="mt-2 rounded-lg bg-warning-50 px-3 py-2 text-xs text-warning-800"
                >
                    El navegador puede borrar lo guardado si falta espacio
                </p>
            )}
            {estado.preparado && (
                <p className="mt-1 text-xs text-slate-600">
                    {plural(estado.estudiantes, 'estudiante guardado', 'estudiantes guardados')}
                    {estado.pendientes > 0 &&
                        ` · ${plural(estado.pendientes, 'lectura por enviar', 'lecturas por enviar')}`}
                </p>
            )}
            {resumen && (
                <p
                    role="status"
                    className="mt-2 rounded-lg bg-success-50 px-3 py-2 text-xs text-success-800"
                >
                    Enviado · {plural(resumen.ingresos, 'ingreso', 'ingresos')}
                    {resumen.irregularidades > 0 &&
                        ` · ${plural(resumen.irregularidades, 'irregularidad', 'irregularidades')} por revisar`}
                    {conRevision && resumen.irregularidades > 0 && examenId && (
                        <>
                            {' · '}
                            <Link
                                className="font-medium underline"
                                to={rutaDelExamen(examenId, 'sin-conexion')}
                            >
                                Revisar
                            </Link>
                        </>
                    )}
                </p>
            )}
            <div className="mt-2 flex flex-wrap gap-2">
                <Boton
                    type="button"
                    variante="secundario"
                    tamano="chico"
                    disabled={!enLinea || trabajando}
                    cargando={trabajando}
                    onClick={() => preparar(true)}
                >
                    {!trabajando &&
                        (estado.preparado ? (
                            <RefreshCw className="h-4 w-4" aria-hidden="true" />
                        ) : (
                            <CloudDownload className="h-4 w-4" aria-hidden="true" />
                        ))}
                    {estado.preparado ? 'Actualizar' : 'Preparar'}
                </Boton>
                {estado.pendientes > 0 && (
                    <Boton type="button" tamano="chico" disabled={!enLinea} onClick={enviar}>
                        <Send className="h-4 w-4" aria-hidden="true" /> Enviar ahora
                    </Boton>
                )}
            </div>
            {aviso}
        </div>
    );
}

ModoSinConexion.propTypes = {
    aulaId: PropTypes.number.isRequired,
    enLinea: PropTypes.bool.isRequired,
    cambio: PropTypes.number,
    examenId: PropTypes.number,
    conRevision: PropTypes.bool,
};
