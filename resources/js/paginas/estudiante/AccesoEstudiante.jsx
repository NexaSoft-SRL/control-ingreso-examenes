import { useEffect, useRef, useState } from 'react';
import { ArrowLeft, CheckCircle2, GraduationCap } from 'lucide-react';
import { Link, Navigate, useNavigate } from 'react-router-dom';
import { api } from '../../api/cliente';
import { erroresDe, estadoDe, mensajeDe } from '../../api/errores';
import Boton from '../../componentes/Boton';
import usarTituloPestana from '../../utiles/usarTituloPestana';
import FondoAcceso from '../../componentes/FondoAcceso';
import Campo from '../../componentes/Campo';

const FOCO =
    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
const ESPERA_REENVIO = 30;

// Primero se quitan los no dígitos y después se recorta: así un código
// pegado con espacios o guiones («2024 100 07») no pierde dígitos.
const soloDigitos = (texto, largo) => texto.replace(/\D/g, '').slice(0, largo);

function errorDeSis(valor) {
    if (valor === '') return 'Obligatorio';
    return /^\d{5,10}$/.test(valor) ? null : 'Entre 5 y 10 dígitos';
}

function errorDeCodigo(valor) {
    if (valor === '') return 'Obligatorio';
    return /^\d{6}$/.test(valor) ? null : 'Seis dígitos';
}

// Acceso del estudiante, sin cuenta: su código SIS y el código de seis
// dígitos que le llega al correo institucional.
export default function AccesoEstudiante() {
    usarTituloPestana('Acceso de estudiantes');
    const navegar = useNavigate();
    const [conSesion, setConSesion] = useState(false);
    const [sis, setSis] = useState('');
    const [codigo, setCodigo] = useState('');
    const [envio, setEnvio] = useState(null);
    const [errores, setErrores] = useState({});
    const [general, setGeneral] = useState(null);
    const [reenviado, setReenviado] = useState(false);
    // Hasta cuándo espera cada SIS para reenviar: «Otro código SIS» no
    // salta la espera del que ya pidió.
    const [esperas, setEsperas] = useState({});
    const [ahora, setAhora] = useState(() => Date.now());
    const [enviando, setEnviando] = useState(false);
    const campoCodigo = useRef(null);
    const enviosPorSis = useRef({});
    const espera = Math.max(0, Math.ceil(((esperas[sis] ?? 0) - ahora) / 1000));

    // Con la sesión de estudiante abierta, el acceso lleva al portal.
    useEffect(() => {
        let vigente = true;
        api.get('/estudiante/sesion', { silencioso: true })
            .then(() => vigente && setConSesion(true))
            .catch(() => {});
        return () => {
            vigente = false;
        };
    }, []);

    useEffect(() => {
        if (espera <= 0) return undefined;
        const reloj = setTimeout(() => setAhora(Date.now()), 1000);
        return () => clearTimeout(reloj);
    }, [espera, ahora]);

    if (conSesion) return <Navigate to="/estudiante" replace />;

    async function pedirCodigo(e, reenvio = false) {
        e?.preventDefault();
        const falta = errorDeSis(sis);
        if (falta) return setErrores({ sis: falta });
        if (enviando) return undefined;
        // Ya pidió hace menos de 30 s: se vuelve al código, sin otro correo.
        if (espera > 0 && envio === null && esperas[sis]) {
            setEnvio(enviosPorSis.current[sis] ?? null);
            return undefined;
        }
        setEnviando(true);
        setErrores({});
        setGeneral(null);
        setReenviado(false);
        try {
            const { data } = await api.post('/auth/estudiante/codigo', {
                codigo_universitario: sis,
            });
            setEnvio(data);
            enviosPorSis.current[sis] = data;
            setCodigo('');
            setReenviado(reenvio);
            setEsperas((previas) => ({ ...previas, [sis]: Date.now() + ESPERA_REENVIO * 1000 }));
            setAhora(Date.now());
            if (reenvio) setTimeout(() => campoCodigo.current?.focus(), 0);
        } catch (error) {
            const mensaje =
                estadoDe(error) === 429
                    ? mensajeDe(error, 'Demasiados pedidos')
                    : (erroresDe(error).codigo_universitario ??
                      mensajeDe(error, 'No se pudo enviar'));
            if (envio) setGeneral(mensaje);
            else setErrores({ sis: mensaje });
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    async function entrar(e) {
        e.preventDefault();
        const falta = errorDeCodigo(codigo);
        if (falta) return setErrores({ codigo: falta });
        if (enviando) return undefined;
        setEnviando(true);
        setErrores({});
        setGeneral(null);
        setReenviado(false);
        try {
            await api.post('/auth/estudiante', { codigo_universitario: sis, codigo });
            navegar('/estudiante', { replace: true });
        } catch (error) {
            if (estadoDe(error) === 429) setGeneral(mensajeDe(error, 'Demasiados intentos'));
            else setErrores({ codigo: erroresDe(error).codigo ?? 'Código no válido o vencido' });
        } finally {
            setEnviando(false);
        }
        return undefined;
    }

    function otroSis() {
        setEnvio(null);
        setErrores({});
        setGeneral(null);
        setReenviado(false);
    }

    return (
        <FondoAcceso>
            <div className="w-full max-w-sm">
                <div className="mb-6 flex flex-col items-center text-center">
                    <div className="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-white">
                        <GraduationCap className="h-6 w-6" />
                    </div>
                    <h1 className="text-lg font-semibold text-slate-900">Estudiantes</h1>
                    <p className="mt-1 text-sm text-slate-600">Exámenes y foto</p>
                </div>

                {envio ? (
                    <form
                        className="space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-lg shadow-primary-900/5 backdrop-blur"
                        noValidate
                        onSubmit={entrar}
                    >
                        <div className="text-sm text-slate-700">
                            <p>
                                Código SIS <span className="font-medium text-slate-900">{sis}</span>
                            </p>
                            <p>
                                Enviado a{' '}
                                <span className="break-all font-medium text-slate-900">
                                    {envio.correo}
                                </span>
                            </p>
                        </div>
                        {reenviado && (
                            <p
                                role="status"
                                className="flex items-center gap-2 rounded-lg bg-success-50 px-3 py-2 text-sm font-medium text-success-700"
                            >
                                <CheckCircle2 className="h-4 w-4 shrink-0" /> Código reenviado
                            </p>
                        )}
                        {general && (
                            <p
                                role="alert"
                                className="rounded-lg bg-danger-50 px-3 py-2 text-sm font-medium text-danger-700"
                            >
                                {general}
                            </p>
                        )}
                        {envio.codigo_prueba && (
                            <p className="rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-700">
                                Modo de prueba · {envio.codigo_prueba}
                            </p>
                        )}
                        <Campo
                            ref={campoCodigo}
                            etiqueta="Código"
                            ayuda={`Vence en ${envio.minutos ?? 10} min`}
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            placeholder="000000"
                            autoFocus
                            value={codigo}
                            error={errores.codigo}
                            onChange={(e) => {
                                setCodigo(soloDigitos(e.target.value, 6));
                                setErrores({});
                            }}
                        />
                        <Boton type="submit" className="w-full" disabled={enviando}>
                            Iniciar sesión
                        </Boton>
                        <div className="flex justify-between gap-2">
                            <Boton type="button" variante="enlace" tamano="chico" onClick={otroSis}>
                                Otro código SIS
                            </Boton>
                            <Boton
                                type="button"
                                variante="enlace"
                                tamano="chico"
                                disabled={enviando || espera > 0}
                                onClick={() => pedirCodigo(null, true)}
                            >
                                {espera > 0 ? `Reenviar en ${espera} s` : 'Reenviar'}
                            </Boton>
                        </div>
                    </form>
                ) : (
                    <form
                        className="space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-lg shadow-primary-900/5 backdrop-blur"
                        noValidate
                        onSubmit={pedirCodigo}
                    >
                        <Campo
                            etiqueta="Código SIS"
                            inputMode="numeric"
                            autoComplete="username"
                            placeholder="202100417"
                            autoFocus
                            value={sis}
                            error={errores.sis}
                            onChange={(e) => {
                                setSis(soloDigitos(e.target.value, 10));
                                setErrores({});
                            }}
                        />
                        <Boton type="submit" className="w-full" disabled={enviando}>
                            Enviar código al correo
                        </Boton>
                    </form>
                )}

                <Link
                    to="/login"
                    className={`mt-4 flex min-h-11 w-full items-center justify-center rounded-lg text-sm font-medium text-primary-700 hover:underline ${FOCO}`}
                >
                    <ArrowLeft className="mr-1.5 h-4 w-4" aria-hidden="true" /> Cuenta institucional
                </Link>
            </div>
        </FondoAcceso>
    );
}
