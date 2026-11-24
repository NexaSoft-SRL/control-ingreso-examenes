import { useEffect, useRef } from 'react';
import { CheckCircle2, RefreshCw, WifiOff } from 'lucide-react';
import { usarSesion } from '../sesion/SesionContexto';
import usarConexion from '../sinConexion/usarConexion';

const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;

// Qué dice la banda en cada fase. `conPuerta`: la cuenta controla el ingreso
// (o ya tiene lecturas guardadas), y lo que decide sin conexión se guarda en
// este equipo; para el resto, lo que haga no se guarda hasta que vuelva la red.
function textoDe({ fase, pendientes, enviadas, conPuerta }) {
    if (fase === 'sin_conexion') {
        return {
            titulo: 'Sin conexión',
            detalle: conPuerta
                ? 'Las lecturas se guardan en este equipo. Conéctese pronto para sincronizar.'
                : 'Lo que haga no se guarda hasta que vuelva la red.',
            cuenta:
                pendientes > 0
                    ? plural(pendientes, 'lectura por enviar', 'lecturas por enviar')
                    : null,
        };
    }
    if (fase === 'enviando') {
        return {
            titulo: 'Conexión recuperada',
            detalle: `Sincronizando ${plural(pendientes, 'lectura', 'lecturas')}…`,
        };
    }
    if (fase === 'pendiente') {
        return {
            titulo: plural(pendientes, 'lectura por enviar', 'lecturas por enviar'),
            detalle: 'Abra el punto de control con conexión para enviarlas.',
        };
    }
    return {
        titulo: 'Conexión recuperada',
        detalle:
            enviadas > 0 ? `${plural(enviadas, 'lectura enviada', 'lecturas enviadas')}.` : null,
    };
}

const ESTILOS = {
    sin_conexion: 'border-warning-300 bg-warning-100 text-warning-900',
    pendiente: 'border-warning-300 bg-warning-100 text-warning-900',
    enviando: 'border-success-200 bg-success-100 text-success-900',
    sincronizado: 'border-success-200 bg-success-100 text-success-900',
};

// Banda fija arriba, a la vista en toda la aplicación, que avisa cuando se cae
// el internet, cuántas lecturas esperan en este equipo y cuándo se enviaron.
// Mide su alto y lo deja en `--banda` para que los encabezados fijos de la
// página bajen debajo de ella.
export default function BandaSinConexion() {
    const { fase, pendientes, enviadas } = usarConexion();
    const { puede } = usarSesion();
    const raiz = useRef(null);
    const visible = fase !== 'conectado';

    useEffect(() => {
        const elemento = raiz.current;
        const estilo = document.documentElement.style;
        if (!visible || !elemento) {
            estilo.removeProperty('--banda');
            return undefined;
        }
        const medir = () => estilo.setProperty('--banda', `${elemento.offsetHeight}px`);
        medir();
        const observador = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(medir);
        observador?.observe(elemento);
        window.addEventListener('resize', medir);
        return () => {
            observador?.disconnect();
            window.removeEventListener('resize', medir);
            estilo.removeProperty('--banda');
        };
    }, [visible]);

    const texto = textoDe({
        fase,
        pendientes,
        enviadas,
        conPuerta: puede('punto_control') || pendientes > 0,
    });
    const Icono =
        fase === 'enviando' ? RefreshCw : fase === 'sincronizado' ? CheckCircle2 : WifiOff;

    return (
        <div ref={raiz} role="status" aria-live="polite" className="sticky top-0 z-50">
            {visible && (
                <div
                    data-fase={fase}
                    className={`flex items-start gap-2.5 border-b px-4 py-2 text-sm ${ESTILOS[fase]}`}
                >
                    <Icono
                        className={`mt-0.5 h-4 w-4 shrink-0 ${fase === 'enviando' ? 'animate-spin' : ''}`}
                        aria-hidden="true"
                    />
                    <p className="min-w-0">
                        <strong className="font-semibold">{texto.titulo}</strong>
                        {texto.detalle && <> · {texto.detalle}</>}
                        {texto.cuenta && (
                            <span className="ml-1 font-semibold whitespace-nowrap">
                                · {texto.cuenta}
                            </span>
                        )}
                    </p>
                </div>
            )}
        </div>
    );
}
