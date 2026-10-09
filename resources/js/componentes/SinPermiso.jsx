import { Link } from 'react-router-dom';
import { ShieldOff } from 'lucide-react';
import { usarSesion } from '../sesion/SesionContexto';
import { entradaDe } from '../navegacion/vistas';

const BOTON =
    'inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';

// La negativa explícita ante una vista que la cuenta no puede abrir: título,
// cuenta y rol, y «Volver» a la entrada propia. La dibuja RutaProtegida
// dentro del armazón, en el lugar de la página.
export default function SinPermiso() {
    const { usuario } = usarSesion();
    const cuenta = [usuario?.nombre, usuario?.rol].filter(Boolean).join(' · ');

    return (
        <div
            role="alert"
            className="flex flex-col items-center gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-12 text-center"
        >
            <span className="flex h-12 w-12 items-center justify-center rounded-full bg-danger-50 text-danger-700">
                <ShieldOff className="h-6 w-6" strokeWidth={2} aria-hidden="true" />
            </span>
            <div className="min-w-0 max-w-full">
                <h1 className="text-xl font-semibold text-slate-900">Sin permiso</h1>
                {cuenta && <p className="mt-1 break-words text-sm text-slate-600">{cuenta}</p>}
            </div>
            <Link to={entradaDe(usuario) ?? '/sin-acceso'} replace className={BOTON}>
                Volver
            </Link>
        </div>
    );
}
