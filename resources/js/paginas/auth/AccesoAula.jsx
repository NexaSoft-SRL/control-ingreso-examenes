import { useState } from 'react';
import { DoorOpen, ArrowRight, AlertCircle } from 'lucide-react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api } from '../../api/cliente';
import { mensajeDe } from '../../api/errores';
import Campo from '../../componentes/Campo';
import Boton from '../../componentes/Boton';
import Logo from '../../componentes/Logo';

export default function AccesoAula() {
    const navegar = useNavigate();
    const [parametros] = useSearchParams();
    const [codigo, setCodigo] = useState(() => parametros.get('codigo') ?? '');
    const [nombre, setNombre] = useState('');
    const [fallo, setFallo] = useState(null);
    const [falloCodigo, setFalloCodigo] = useState(null);
    const [falloNombre, setFalloNombre] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const limpiar = () => {
        setFallo(null);
        setFalloCodigo(null);
        setFalloNombre(null);
    };

    async function acceder(e) {
        e.preventDefault();
        if (enviando) return;

        const cod = codigo.trim();
        const nom = nombre.trim();

        let hayErrores = false;
        if (!cod) {
            setFalloCodigo('Obligatorio');
            hayErrores = true;
        } else if (!/^\d{6}$/.test(cod)) {
            setFalloCodigo('Seis dígitos');
            hayErrores = true;
        }

        if (!nom) {
            setFalloNombre('Obligatorio');
            hayErrores = true;
        } else if (nom.length < 3) {
            setFalloNombre('Mínimo 3 caracteres');
            hayErrores = true;
        }

        if (hayErrores) return;

        limpiar();
        setEnviando(true);

        try {
            const { data } = await api.post('/auth/codigo-aula', {
                codigo: cod,
                nombre_colaborador: nom,
            });

            const sesion = data?.data;
            if (sesion?.token) {
                localStorage.setItem('codigo_aula_token', sesion.token);
                localStorage.setItem('codigo_aula_sesion', JSON.stringify(sesion));
                navegar(`/punto-control?examen=${sesion.examen_id}&aula=${sesion.aula_id}`, {
                    replace: true,
                });
            } else {
                setFallo('No se pudo verificar el código de acceso');
            }
        } catch (error) {
            const respuesta = error?.response?.data;
            const mensaje =
                respuesta?.message ?? mensajeDe(error, 'Código de aula no válido o revocado');
            setFallo(mensaje);
        } finally {
            setEnviando(false);
        }
    }

    return (
        <div className="flex min-h-dvh flex-col items-center justify-center bg-slate-100 px-4 py-8">
            <div className="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div className="flex flex-col items-center text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-600 mb-3">
                        <DoorOpen className="h-6 w-6" strokeWidth={2.2} />
                    </div>
                    <Logo className="h-9 w-9" />
                    <h1 className="mt-3 text-lg font-semibold text-slate-900">
                        Acceso a Puerta de Aula
                    </h1>
                </div>

                <form onSubmit={acceder} className="mt-6 space-y-4" noValidate>
                    <Campo
                        etiqueta="Código de aula"
                        value={codigo}
                        onChange={(e) => {
                            setCodigo(e.target.value.replace(/\D/g, '').slice(0, 6));
                            limpiar();
                        }}
                        error={falloCodigo}
                        placeholder="Ej. 123456"
                        type="text"
                        inputMode="numeric"
                        autoFocus
                    />

                    <Campo
                        etiqueta="Nombre"
                        value={nombre}
                        onChange={(e) => {
                            setNombre(e.target.value);
                            limpiar();
                        }}
                        error={falloNombre}
                        placeholder="Ej. Juan Pérez"
                        type="text"
                    />

                    {fallo && (
                        <div
                            role="alert"
                            className="flex items-start gap-2 rounded-lg bg-danger-50 px-3.5 py-3 text-sm text-danger-700"
                        >
                            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" strokeWidth={2} />
                            <p>{fallo}</p>
                        </div>
                    )}

                    <Boton type="submit" className="w-full gap-2" disabled={enviando}>
                        Ingresar al aula <ArrowRight className="h-4 w-4" />
                    </Boton>

                    <div className="border-t border-slate-200 pt-4 text-center">
                        <p className="text-xs text-slate-600">
                            ¿Tiene cuenta institucional?{' '}
                            <Link
                                to="/login"
                                className="font-medium text-primary-600 hover:text-primary-700 hover:underline"
                            >
                                Iniciar sesión con usuario
                            </Link>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    );
}
