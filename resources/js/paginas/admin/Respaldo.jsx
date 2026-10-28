import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from '../../api/cliente';
import { useAviso } from '../../componentes/Aviso';
import Boton from '../../componentes/Boton';
import Encabezado from '../../componentes/Encabezado';
import EstadoCarga from '../../componentes/EstadoCarga';
import SinPermiso from '../../componentes/SinPermiso';
import { usarSesion } from '../../sesion/SesionContexto';
import { fechaCorta } from '../../utiles/texto';

export default function Respaldo() {
    const { puede } = usarSesion();
    const tienePermiso = puede('respaldo_restauracion');

    const [respaldos, setRespaldos] = useState([]);
    const [ultimoCorrecto, setUltimoCorrecto] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [errorCarga, setErrorCarga] = useState(false);
    const [respaldando, setRespaldando] = useState(false);
    const [aviso, avisar] = useAviso();

    const ultimaConsulta = useRef(0);

    const cargarHistorial = useCallback(async () => {
        if (!tienePermiso) return;

        const idConsulta = ++ultimaConsulta.current;
        setCargando(true);
        setErrorCarga(false);

        try {
            const respuesta = await api.get('/respaldos');
            if (idConsulta !== ultimaConsulta.current) return;

            const datos =
                respuesta.data?.data ?? (Array.isArray(respuesta.data) ? respuesta.data : []);
            setRespaldos(datos);

            // Obtener fecha del último correcto desde la API o calculándolo
            const ultimoApi = respuesta.data?.ultimo ?? respuesta.data?.ultimo_correcto;
            if (ultimoApi) {
                setUltimoCorrecto(ultimoApi);
            } else {
                const primerCorrecto = datos.find(
                    (r) =>
                        r.estado?.toLowerCase() === 'correcto' ||
                        r.estado?.toLowerCase() === 'restaurado'
                );
                setUltimoCorrecto(primerCorrecto?.fecha ?? primerCorrecto?.created_at ?? null);
            }
        } catch {
            if (idConsulta !== ultimaConsulta.current) return;
            setErrorCarga(true);
        } finally {
            if (idConsulta === ultimaConsulta.current) {
                setCargando(false);
            }
        }
    }, [tienePermiso]);

    useEffect(() => {
        if (tienePermiso) {
            cargarHistorial();
        }
    }, [tienePermiso, cargarHistorial]);

    if (!tienePermiso) {
        return <SinPermiso />;
    }

    async function manejarRespaldar() {
        if (respaldando) return;
        setRespaldando(true);

        try {
            const respuesta = await api.post('/respaldos');
            const nuevo = respuesta.data?.data ?? respuesta.data;

            avisar('Respaldo generado');

            if (nuevo && typeof nuevo === 'object') {
                setRespaldos((anteriores) => [nuevo, ...anteriores]);
                const fechaNuevo = nuevo.fecha ?? nuevo.created_at;
                if (fechaNuevo && nuevo.estado?.toLowerCase() !== 'fallido') {
                    setUltimoCorrecto(fechaNuevo);
                }
            } else {
                await cargarHistorial();
            }
        } catch {
            avisar('No se pudo generar el respaldo', 'error');
            await cargarHistorial();
        } finally {
            setRespaldando(false);
        }
    }

    const subtitulo = (() => {
        if (respaldos.length === 0 && !ultimoCorrecto) {
            return 'Sin respaldos';
        }
        if (ultimoCorrecto) {
            return `Diario, 03:00 · último: ${fechaCorta(ultimoCorrecto)}`;
        }
        return 'Diario, 03:00';
    })();

    return (
        <div className="flex flex-col gap-6 p-4 sm:p-6">
            <Encabezado titulo="Respaldo" subtitulo={subtitulo}>
                <Boton
                    type="button"
                    onClick={manejarRespaldar}
                    cargando={respaldando}
                    disabled={respaldando}
                >
                    {respaldando ? 'Respaldando' : 'Respaldar ahora'}
                </Boton>
            </Encabezado>

            {cargando ? (
                <EstadoCarga cargando />
            ) : errorCarga ? (
                <div
                    role="alert"
                    className="flex flex-col items-center gap-3 rounded-xl border border-slate-200 bg-white p-8 text-center"
                >
                    <p className="text-sm font-medium text-slate-800">No se pudo cargar</p>
                    <Boton type="button" variante="secundario" onClick={cargarHistorial}>
                        Reintentar
                    </Boton>
                </div>
            ) : respaldos.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
                    <p className="text-sm text-slate-500">Sin respaldos</p>
                </div>
            ) : (
                <section className="flex flex-col gap-4">
                    <h2 className="text-lg font-semibold text-slate-900">Historial</h2>
                </section>
            )}

            {aviso}
        </div>
    );
}
