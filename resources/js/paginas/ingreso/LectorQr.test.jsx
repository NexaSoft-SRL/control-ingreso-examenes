import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import LectorQr from './LectorQr';

const motor = { terminate: vi.fn() };
const escanear = vi.fn();

vi.mock('qr-scanner', () => ({
    default: {
        createQrEngine: vi.fn(async () => motor),
        scanImage: (...args) => escanear(...args),
    },
}));

const pista = { stop: vi.fn() };

const flujo = {
    getTracks: vi.fn(() => [pista]),
};

let detectar;

beforeEach(() => {
    vi.clearAllMocks();

    detectar = vi.fn().mockResolvedValue([]);

    vi.stubGlobal(
        'BarcodeDetector',
        class {
            detect = detectar;
        }
    );

    Object.defineProperty(navigator, 'mediaDevices', {
        configurable: true,
        value: {
            getUserMedia: vi.fn().mockResolvedValue(flujo),
        },
    });

    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue();
    vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {});

    vi.stubGlobal(
        'requestAnimationFrame',
        vi.fn(() => 1)
    );

    vi.stubGlobal('cancelAnimationFrame', vi.fn());
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('LectorQr', () => {
    it('solicita cámara posterior y libera pistas al cerrar', async () => {
        const alCerrar = vi.fn();

        const { unmount } = render(<LectorQr alDetectar={vi.fn()} alCerrar={alCerrar} />);

        await waitFor(() => {
            expect(navigator.mediaDevices.getUserMedia).toHaveBeenCalledWith({
                audio: false,
                video: { facingMode: { ideal: 'environment' } },
            });
        });

        fireEvent.click(screen.getByRole('button', { name: 'Cerrar cámara' }));

        expect(alCerrar).toHaveBeenCalledOnce();

        unmount();

        expect(pista.stop).toHaveBeenCalled();
    });

    it('entrega la lectura QR y detiene la cámara', async () => {
        detectar.mockResolvedValue([{ rawValue: 'SCIQR1:contenido-cifrado' }]);

        const alDetectar = vi.fn();

        render(<LectorQr alDetectar={alDetectar} alCerrar={vi.fn()} />);

        await waitFor(() => {
            expect(screen.queryByText('Iniciando cámara…')).not.toBeInTheDocument();
        });

        const llamada = requestAnimationFrame.mock.calls[0][0];
        await llamada();

        expect(alDetectar).toHaveBeenCalledWith('SCIQR1:contenido-cifrado');
        expect(pista.stop).toHaveBeenCalled();
    });

    it('sin lector nativo (Safari en iPhone) lee con qr-scanner', async () => {
        vi.stubGlobal('BarcodeDetector', undefined);
        escanear
            .mockRejectedValueOnce(new Error('No QR code found'))
            .mockResolvedValue({ data: 'SCIQR1:contenido-cifrado' });
        const alDetectar = vi.fn();

        render(<LectorQr alDetectar={alDetectar} alCerrar={vi.fn()} />);

        await waitFor(() => {
            expect(screen.queryByText('Iniciando cámara…')).not.toBeInTheDocument();
        });
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();

        // Un cuadro sin código sigue explorando; el siguiente lo lee.
        await requestAnimationFrame.mock.calls[0][0]();
        expect(alDetectar).not.toHaveBeenCalled();
        await requestAnimationFrame.mock.calls[1][0]();

        expect(alDetectar).toHaveBeenCalledWith('SCIQR1:contenido-cifrado');
        expect(escanear).toHaveBeenCalledWith(expect.any(HTMLVideoElement), {
            qrEngine: motor,
            returnDetailedScanResult: true,
        });
        expect(pista.stop).toHaveBeenCalled();
        expect(motor.terminate).toHaveBeenCalled();
    });

    it('informa cuando el navegador no da acceso a cámaras', async () => {
        Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: undefined });

        render(<LectorQr alDetectar={vi.fn()} alCerrar={vi.fn()} />);

        expect(
            await screen.findByText('Cámara no disponible en este navegador')
        ).toBeInTheDocument();
    });

    it('distingue el teléfono sin cámara del permiso denegado', async () => {
        navigator.mediaDevices.getUserMedia.mockRejectedValue(
            Object.assign(new Error('x'), { name: 'NotFoundError' })
        );

        render(<LectorQr alDetectar={vi.fn()} alCerrar={vi.fn()} />);

        expect(
            await screen.findByText('Cámara no disponible: no se encontró una cámara')
        ).toBeInTheDocument();
    });

    it('informa cuando se deniega acceso a la cámara', async () => {
        navigator.mediaDevices.getUserMedia.mockRejectedValue(new Error('Permission denied'));

        const alFallar = vi.fn();
        render(<LectorQr alDetectar={vi.fn()} alCerrar={vi.fn()} alFallar={alFallar} />);

        expect(
            await screen.findByText('Cámara no disponible: sin permiso de acceso')
        ).toBeInTheDocument();
        expect(alFallar).toHaveBeenCalledOnce();
    });

    it('BUG-21: sin HTTPS pide conexión segura y avisa para abrir el ingreso manual', async () => {
        vi.stubGlobal('isSecureContext', false);
        const alFallar = vi.fn();

        render(<LectorQr alDetectar={vi.fn()} alCerrar={vi.fn()} alFallar={alFallar} />);

        expect(
            await screen.findByText('Cámara no disponible: requiere conexión segura (HTTPS)')
        ).toBeInTheDocument();
        expect(alFallar).toHaveBeenCalledWith(
            'Cámara no disponible: requiere conexión segura (HTTPS)'
        );
        expect(navigator.mediaDevices.getUserMedia).not.toHaveBeenCalled();
    });

    it('libera la cámara cuando el permiso llega después de cerrar', async () => {
        let concederPermiso;

        const permisoPendiente = new Promise((resolver) => {
            concederPermiso = resolver;
        });

        navigator.mediaDevices.getUserMedia.mockReturnValue(permisoPendiente);

        const alDetectar = vi.fn();

        const vista = render(<LectorQr alDetectar={alDetectar} alCerrar={vi.fn()} />);

        await waitFor(() => {
            expect(navigator.mediaDevices.getUserMedia).toHaveBeenCalledOnce();
        });

        vista.unmount();

        concederPermiso(flujo);

        await waitFor(() => {
            expect(pista.stop).toHaveBeenCalledOnce();
        });

        expect(alDetectar).not.toHaveBeenCalled();
    });
});
