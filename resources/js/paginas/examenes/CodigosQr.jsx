import React, { useState } from 'react';
import axios from 'axios';
import LayoutAdmin from '../../componentes/LayoutAdmin.jsx';
import { QrCode, Printer, Users } from 'lucide-react';

export default function CodigosQr({ onNavigate }) {
    const [qrs, setQrs] = useState([]);
    const [cargando, setCargando] = useState(false);
    const [mensaje, setMensaje] = useState('');
    const [examenDemoId, setExamenDemoId] = useState(1); // Examen por defecto para la demo

    const generarLote = async () => {
        setCargando(true);
        setMensaje('Generando códigos únicos e indescifrables...');
        try {
            // Usamos la ruta de demo que creamos en web.php
            const respuesta = await axios.get(`/api/demo-qr/${examenDemoId}`);
            setQrs(respuesta.data.datos);
            setMensaje(respuesta.data.mensaje);
        } catch (error) {
            setMensaje('Error al generar el lote. Revisa la consola.');
            console.error(error);
        } finally {
            setCargando(false);
        }
    };

    const imprimirCredenciales = () => {
        window.print();
    };

    return (
        <LayoutAdmin seleccionado="examenes" onNavigate={onNavigate}>
            <section className="p-4 md:p-6 lg:p-8">
                {/* Ocultamos los botones al imprimir para que solo salga la credencial */}
                <div className="print:hidden">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">Emisión de Códigos QR</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                HU-20: Generación masiva de credenciales de ingreso seguras.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={generarLote}
                                disabled={cargando}
                                className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                <QrCode className="h-4 w-4" />
                                {cargando ? 'Procesando...' : 'Emitir QRs en lote'}
                            </button>
                            {qrs.length > 0 && (
                                <button
                                    onClick={imprimirCredenciales}
                                    className="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900"
                                >
                                    <Printer className="h-4 w-4" />
                                    Imprimir Credenciales
                                </button>
                            )}
                        </div>
                    </div>

                    {mensaje && (
                        <p className="mb-4 text-sm font-medium text-emerald-700 bg-emerald-50 p-3 rounded-lg border border-emerald-200">
                            {mensaje}
                        </p>
                    )}
                </div>

                {/* Zona de impresión (Las Credenciales) */}
                {qrs.length > 0 && (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 print:grid-cols-2 print:gap-8">
                        {qrs.map((item, index) => (
                            <div key={index} className="border-2 border-slate-300 rounded-xl p-6 flex flex-col items-center bg-white shadow-sm print:shadow-none print:border-black">
                                <h2 className="text-lg font-bold text-slate-800 text-center mb-1">
                                    CREDENCIAL DE INGRESO
                                </h2>
                                <p className="text-xs text-slate-500 mb-4 text-center">Universidad Mayor de San Simón</p>
                                
                                {/* El QR en Base64 generado por Laravel */}
                                <img src={item.qr_base64} alt="Código QR" className="w-40 h-40 mb-4 border border-slate-100 p-2 rounded" />
                                
                                <div className="w-full text-center space-y-2 mt-2">
                                    <p className="text-xs text-slate-400 font-mono break-all leading-tight">
                                        Token: {item.token}
                                    </p>
                                    <div className="bg-slate-50 rounded p-2 border border-slate-100 mt-2">
                                        <p className="text-sm font-bold text-slate-800">{item.estudiante}</p>
                                        <p className="text-xs text-slate-600 font-medium mt-1">{item.aula}</p>
                                        <p className="text-xs text-slate-500">{item.edificio} • {item.piso}</p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {qrs.length === 0 && !cargando && (
                    <div className="text-center py-20 border-2 border-dashed border-slate-200 rounded-xl">
                        <Users className="mx-auto h-12 w-12 text-slate-300 mb-3" />
                        <p className="text-slate-500 text-sm">Haz clic en "Emitir QRs en lote" para comenzar la demostración.</p>
                    </div>
                )}
            </section>
        </LayoutAdmin>
    );
}