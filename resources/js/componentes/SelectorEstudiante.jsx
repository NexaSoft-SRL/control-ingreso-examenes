import React from 'react';
import PropTypes from 'prop-types';

export default function SelectorEstudiante({
    estudiantes,
    value,
    onChange,
    disabled = false,
    invalid = false,
    placeholder = 'Selecciona un estudiante',
    id = 'selector-estudiante',
}) {
    const [abierto, setAbierto] = React.useState(false);
    const [busqueda, setBusqueda] = React.useState('');
    const [altoLista, setAltoLista] = React.useState(240);
    const contenedor = React.useRef(null);
    const input = React.useRef(null);
    const listaId = `${id}-opciones`;
    const estudianteSeleccionado = estudiantes.find((item) => String(item.id) === String(value));
    const opciones = estudiantes.filter((item) =>
        `${item.nombre} ${item.codigo_universitario ?? ''}`
            .toLocaleLowerCase()
            .includes(busqueda.trim().toLocaleLowerCase())
    );

    React.useEffect(() => {
        if (!abierto) return undefined;

        function actualizarUbicacion() {
            const rectangulo = contenedor.current?.getBoundingClientRect();
            if (!rectangulo) return;
            const viewport = window.visualViewport;
            const inicioViewport = viewport?.offsetTop ?? 0;
            const altoViewport = viewport?.height ?? window.innerHeight;
            const espacioAbajo = inicioViewport + altoViewport - rectangulo.bottom - 12;
            setAltoLista(Math.max(88, Math.min(240, espacioAbajo)));
        }

        actualizarUbicacion();
        window.addEventListener('resize', actualizarUbicacion);
        window.addEventListener('scroll', actualizarUbicacion, true);
        window.visualViewport?.addEventListener('resize', actualizarUbicacion);
        window.visualViewport?.addEventListener('scroll', actualizarUbicacion);
        return () => {
            window.removeEventListener('resize', actualizarUbicacion);
            window.removeEventListener('scroll', actualizarUbicacion, true);
            window.visualViewport?.removeEventListener('resize', actualizarUbicacion);
            window.visualViewport?.removeEventListener('scroll', actualizarUbicacion);
        };
    }, [abierto]);

    React.useEffect(() => {
        if (!abierto) return undefined;
        function cerrarAlHacerClickAfuera(event) {
            if (!contenedor.current?.contains(event.target)) setAbierto(false);
        }
        document.addEventListener('pointerdown', cerrarAlHacerClickAfuera);
        return () => document.removeEventListener('pointerdown', cerrarAlHacerClickAfuera);
    }, [abierto]);

    function abrir() {
        if (disabled) return;
        setBusqueda('');
        const rectangulo = contenedor.current?.getBoundingClientRect();
        const altoViewport = window.visualViewport?.height ?? window.innerHeight;
        if (rectangulo && altoViewport - rectangulo.bottom < 200) {
            contenedor.current?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
        setAbierto(true);
        requestAnimationFrame(() => input.current?.focus());
    }

    function seleccionar(estudiante) {
        onChange(String(estudiante.id));
        setBusqueda('');
        setAbierto(false);
    }

    function manejarTeclado(event) {
        if (event.key === 'Escape') {
            setAbierto(false);
        } else if (event.key === 'ArrowDown' && !abierto) {
            event.preventDefault();
            abrir();
        } else if (event.key === 'Enter' && abierto && opciones.length === 1) {
            event.preventDefault();
            seleccionar(opciones[0]);
        }
    }

    return (
        <div ref={contenedor} className="relative mt-1">
            <button
                id={id}
                type="button"
                disabled={disabled}
                aria-haspopup="listbox"
                aria-expanded={abierto}
                aria-controls={listaId}
                aria-invalid={invalid}
                onClick={() => (abierto ? setAbierto(false) : abrir())}
                onKeyDown={manejarTeclado}
                className="flex min-h-10 w-full items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm font-normal text-slate-700 disabled:cursor-not-allowed disabled:bg-slate-50"
            >
                <span className="min-w-0 truncate">
                    {estudianteSeleccionado
                        ? `${estudianteSeleccionado.nombre} · ${estudianteSeleccionado.codigo_universitario ?? 'Sin código'}`
                        : placeholder}
                </span>
                <svg
                    aria-hidden="true"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    className="h-4 w-4 shrink-0 text-slate-500"
                >
                    <path
                        fillRule="evenodd"
                        d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z"
                        clipRule="evenodd"
                    />
                </svg>
            </button>
            {abierto && (
                <div
                    className="absolute top-full z-50 mt-1 flex w-full flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl"
                    style={{ maxHeight: `${altoLista}px` }}
                >
                    <div className="border-b border-slate-100 p-2">
                        <input
                            ref={input}
                            type="search"
                            value={busqueda}
                            onChange={(event) => setBusqueda(event.target.value)}
                            onKeyDown={manejarTeclado}
                            role="combobox"
                            aria-label="Buscar estudiante por nombre o código"
                            aria-autocomplete="list"
                            aria-expanded="true"
                            aria-controls={listaId}
                            placeholder="Buscar estudiante..."
                            className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                    </div>
                    <ul
                        id={listaId}
                        role="listbox"
                        className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-1"
                    >
                        {opciones.length ? (
                            opciones.map((estudiante) => (
                                <li
                                    key={estudiante.id}
                                    role="option"
                                    aria-selected={String(estudiante.id) === String(value)}
                                >
                                    <button
                                        type="button"
                                        onClick={() => seleccionar(estudiante)}
                                        className="w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50 focus:bg-blue-50 focus:outline-none"
                                    >
                                        <span className="block truncate">{estudiante.nombre}</span>
                                        <span className="block text-xs text-slate-500">
                                            {estudiante.codigo_universitario ?? 'Sin código'}
                                        </span>
                                    </button>
                                </li>
                            ))
                        ) : (
                            <li className="px-3 py-3 text-sm text-slate-500">
                                No se encontraron estudiantes.
                            </li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}

SelectorEstudiante.propTypes = {
    estudiantes: PropTypes.arrayOf(
        PropTypes.shape({
            id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
            nombre: PropTypes.string.isRequired,
            codigo_universitario: PropTypes.string,
        })
    ).isRequired,
    value: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
    onChange: PropTypes.func.isRequired,
    disabled: PropTypes.bool,
    invalid: PropTypes.bool,
    placeholder: PropTypes.string,
    id: PropTypes.string,
};

SelectorEstudiante.defaultProps = {
    value: '',
    disabled: false,
    invalid: false,
    placeholder: 'Selecciona un estudiante',
    id: 'selector-estudiante',
};
