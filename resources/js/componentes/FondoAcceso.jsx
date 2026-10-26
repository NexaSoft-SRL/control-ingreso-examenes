import PropTypes from 'prop-types';

// El fondo de las pantallas de acceso (login, estudiante y código de aula):
// dos manchas de color difuminadas y una trama de puntos que recuerda a un
// código QR. Es decoración: queda detrás del contenido y fuera del lector
// de pantalla.
export default function FondoAcceso({ children }) {
    return (
        <div className="relative isolate flex min-h-dvh items-center justify-center overflow-hidden bg-slate-100 px-4 py-10">
            <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10">
                <div className="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-primary-400/30 blur-3xl" />
                <div className="absolute -bottom-40 -right-24 h-[28rem] w-[28rem] rounded-full bg-sky-300/30 blur-3xl" />
                <svg className="absolute inset-0 h-full w-full text-primary-900/[0.07]">
                    <defs>
                        <pattern
                            id="fondo-acceso-puntos"
                            width="22"
                            height="22"
                            patternUnits="userSpaceOnUse"
                        >
                            <circle cx="2" cy="2" r="1.6" fill="currentColor" />
                        </pattern>
                        {/* La trama se ve al centro y se desvanece hacia los bordes. */}
                        <radialGradient id="fondo-acceso-desvanecer">
                            <stop offset="0" stopColor="white" stopOpacity="1" />
                            <stop offset="1" stopColor="white" stopOpacity="0" />
                        </radialGradient>
                        <mask id="fondo-acceso-mascara">
                            <rect width="100%" height="100%" fill="url(#fondo-acceso-desvanecer)" />
                        </mask>
                    </defs>
                    <rect
                        width="100%"
                        height="100%"
                        fill="url(#fondo-acceso-puntos)"
                        mask="url(#fondo-acceso-mascara)"
                    />
                </svg>
                {/* Marcas de esquina de un QR, grandes y tenues. */}
                {['left-6 top-6', 'right-6 top-6', 'bottom-6 left-6'].map((lugar) => (
                    <div
                        key={lugar}
                        className={`absolute hidden h-20 w-20 rounded-2xl border-[10px] border-primary-600/10 sm:block ${lugar}`}
                    >
                        <div className="m-2.5 h-5 w-5 rounded-md bg-primary-600/10" />
                    </div>
                ))}
            </div>
            {children}
            <p className="absolute inset-x-0 bottom-4 text-center text-xs text-slate-500">
                Universidad Mayor de San Simón
            </p>
        </div>
    );
}

FondoAcceso.propTypes = { children: PropTypes.node };
