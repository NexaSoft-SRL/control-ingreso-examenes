import React, { useState } from 'react';
import AsignaturasAmbientes from '../paginas/admin/AsignaturasAmbientes';
import RegistroEstudiantes from '../paginas/estudiantes/RegistroEstudiantes';

export default function Aplicacion() {
    // Estado para controlar qué vista se muestra ('asignaturas' o 'estudiantes')
    const [vistaActiva, setVistaActiva] = useState('asignaturas');

    return (
        <div className="flex min-h-screen bg-gray-50 text-gray-900 font-sans">
            {/* 1. MENÚ LATERAL FIJO */}
            <aside className="w-64 bg-white border-r border-gray-200 flex flex-col">
                <div className="h-16 flex items-center gap-3 px-6 border-b border-gray-200">
                    <div className="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        ✓
                    </div>
                    <div>
                        <div className="text-xs font-bold text-gray-800">UMSS FCyT</div>
                        <div className="text-[9px] tracking-wider text-gray-400 font-semibold">
                            CONTROL DE INGRESO
                        </div>
                    </div>
                </div>

                <div className="p-4 flex-1">
                    <div className="text-[10px] font-bold text-gray-400 tracking-wider px-3 mb-2">
                        ADMINISTRADOR
                    </div>

                    {/* Botón Padrón */}
                    <button
                        onClick={() => setVistaActiva('estudiantes')}
                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition ${
                            vistaActiva === 'estudiantes'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-100'
                        }`}
                    >
                        <span>♙</span> Padrón
                    </button>

                    {/* Botón Asignaturas y ambientes */}
                    <button
                        onClick={() => setVistaActiva('asignaturas')}
                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition mt-1 ${
                            vistaActiva === 'asignaturas'
                                ? 'bg-blue-600 text-white shadow-sm'
                                : 'text-gray-600 hover:bg-gray-100'
                        }`}
                    >
                        <span>▤</span> Asignaturas y ambientes
                    </button>

                    {/* Resto de opciones con su funcionalidad original */}
                    <button
                        onClick={() => alert('Códigos QR: próximamente')}
                        className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition mt-1"
                    >
                        <span>#</span> Códigos QR
                    </button>

                    <button
                        onClick={() => alert('Usuarios y roles: próximamente')}
                        className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition mt-1"
                    >
                        <span>👤</span> Usuarios y roles
                    </button>

                    <button
                        onClick={() => alert('Bitácora: próximamente')}
                        className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition mt-1"
                    >
                        <span>📋</span> Bitácora
                    </button>

                    <button
                        onClick={() => alert('Respaldo: próximamente')}
                        className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-gray-600 hover:bg-gray-100 transition mt-1"
                    >
                        <span>🔄</span> Respaldo
                    </button>
                </div>
            </aside>

            {/* 2. CONTENIDO DINÁMICO DE LA DERECHA */}
            <main className="flex-1 flex flex-col min-w-0">
                {/* Cabecera superior común */}
                <header className="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8">
                    <div className="text-xs font-medium text-gray-700 flex items-center gap-2">
                        <span className="text-blue-600 text-lg">◇</span> Sistema Institucional de
                        Verificación
                    </div>
                    <div className="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                        ●
                    </div>
                </header>

                {/* Renderizado condicional de las vistas */}
                <div className="flex-1 overflow-y-auto">
                    {vistaActiva === 'asignaturas' && <AsignaturasAmbientes />}
                    {vistaActiva === 'estudiantes' && <RegistroEstudiantes />}
                </div>
            </main>
        </div>
    );
}
