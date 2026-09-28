import React from 'react';
import PropTypes from 'prop-types';
import { ShieldAlert } from 'lucide-react';

/**
 * HU-02: quien no tiene el permiso recibe una negativa explícita, con el
 * nombre de lo que le falta y a quién pedírselo. Nunca una pantalla vacía.
 */
export default function SinPermiso({ mensaje, permiso, onVolver, textoBoton = 'Volver' }) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 p-6">
            <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                    <ShieldAlert className="h-6 w-6" />
                </div>

                <h1 className="text-lg font-bold text-slate-900">Sección no habilitada</h1>

                <p role="alert" className="mt-2 text-sm text-slate-600">
                    {mensaje ?? 'Tu rol no tiene acceso a esta sección.'}
                </p>

                {permiso && (
                    <p className="mt-3 text-xs text-slate-400">
                        Permiso necesario: <span className="font-mono">{permiso}</span>
                    </p>
                )}

                <button
                    type="button"
                    onClick={onVolver}
                    className="mt-6 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    {textoBoton}
                </button>
            </div>
        </div>
    );
}

SinPermiso.propTypes = {
    mensaje: PropTypes.string,
    permiso: PropTypes.string,
    onVolver: PropTypes.func.isRequired,
    textoBoton: PropTypes.string,
};
