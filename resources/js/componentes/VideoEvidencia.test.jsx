import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import VideoEvidencia, { MAXIMO_BYTES_VIDEO, SEGUNDOS_VIDEO } from './VideoEvidencia';

const video = (tipo = 'video/mp4', bytes = 1024, nombre = 'aula.mp4') =>
    new File([new Uint8Array(bytes)], nombre, { type: tipo });

// Sin metadatos en jsdom: la duración se simula con el `<video>` que se crea.
function simularDuracion(segundos) {
    const crear = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation((etiqueta, ...resto) => {
        const elemento = crear(etiqueta, ...resto);
        if (etiqueta === 'video') {
            Object.defineProperty(elemento, 'duration', { value: segundos });
            Object.defineProperty(elemento, 'src', {
                set() {
                    setTimeout(() => elemento.onloadedmetadata?.(), 0);
                },
            });
        }
        return elemento;
    });
}

beforeEach(() => {
    globalThis.URL.createObjectURL = vi.fn(() => 'blob:video');
    globalThis.URL.revokeObjectURL = vi.fn();
});

afterEach(() => {
    vi.restoreAllMocks();
    delete navigator.mediaDevices;
    delete globalThis.MediaRecorder;
});

describe('VideoEvidencia', () => {
    it('acepta un video corto del dispositivo, lo muestra y lo quita', async () => {
        simularDuracion(25);
        const onCambiar = vi.fn();
        const { rerender } = render(<VideoEvidencia video={null} onCambiar={onCambiar} />);

        expect(screen.getByText(`Hasta ${SEGUNDOS_VIDEO} s · 8 MB`)).toBeInTheDocument();
        const elegido = video();
        fireEvent.change(screen.getByLabelText('Archivo de video'), {
            target: { files: [elegido] },
        });
        await waitFor(() => expect(onCambiar).toHaveBeenCalledWith(elegido));

        rerender(<VideoEvidencia video={elegido} onCambiar={onCambiar} />);
        expect(screen.getByLabelText('Video elegido')).toHaveAttribute('src', 'blob:video');
        fireEvent.click(screen.getByRole('button', { name: 'Quitar el video' }));
        expect(onCambiar).toHaveBeenLastCalledWith(null);
    });

    it('rechaza otro formato, más de 8 MB o más de un minuto', async () => {
        const onCambiar = vi.fn();
        render(<VideoEvidencia video={null} onCambiar={onCambiar} />);
        const entrada = screen.getByLabelText('Archivo de video');

        fireEvent.change(entrada, { target: { files: [video('video/x-msvideo', 10, 'a.avi')] } });
        expect(await screen.findByRole('alert')).toHaveTextContent('Solo MP4, WebM o MOV');

        fireEvent.change(entrada, {
            target: { files: [video('video/mp4', MAXIMO_BYTES_VIDEO + 1)] },
        });
        expect(await screen.findByRole('alert')).toHaveTextContent('Máximo 8 MB');

        simularDuracion(95);
        fireEvent.change(entrada, { target: { files: [video('video/quicktime', 10, 'b.mov')] } });
        expect(await screen.findByRole('alert')).toHaveTextContent('Máximo 60 segundos');
        expect(onCambiar).not.toHaveBeenCalled();
    });

    it('graba con la cámara de atrás y corta solo al minuto', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const pista = { stop: vi.fn() };
        const flujo = { getTracks: () => [pista] };
        navigator.mediaDevices = { getUserMedia: vi.fn(() => Promise.resolve(flujo)) };
        const grabadoras = [];
        globalThis.MediaRecorder = class {
            static isTypeSupported = (tipo) => tipo.startsWith('video/webm');
            constructor(origen, opciones) {
                this.opciones = opciones;
                this.state = 'inactive';
                grabadoras.push(this);
            }
            start() {
                this.state = 'recording';
            }
            stop() {
                this.state = 'inactive';
                this.ondataavailable?.({ data: new Blob(['x'.repeat(50)]) });
                this.onstop?.();
            }
        };
        const onCambiar = vi.fn();
        render(<VideoEvidencia video={null} onCambiar={onCambiar} />);

        fireEvent.click(screen.getByRole('button', { name: 'Grabar video' }));
        const dialogo = within(screen.getByRole('dialog', { name: 'Grabar video' }));
        expect(navigator.mediaDevices.getUserMedia).toHaveBeenCalledWith({
            video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } },
            audio: true,
        });
        await act(async () => {});
        fireEvent.loadedData(dialogo.getByLabelText('Vista de la cámara'));
        fireEvent.click(dialogo.getByRole('button', { name: 'Grabar' }));
        expect(grabadoras[0].opciones).toMatchObject({
            mimeType: 'video/webm;codecs=vp8,opus',
            videoBitsPerSecond: 450_000,
        });
        expect(dialogo.getByText('0:00 / 1:00')).toBeInTheDocument();

        for (let s = 0; s < SEGUNDOS_VIDEO; s += 1) {
            await act(() => vi.advanceTimersByTimeAsync(1000));
        }
        await waitFor(() => expect(onCambiar).toHaveBeenCalledTimes(1));
        const grabado = onCambiar.mock.calls[0][0];
        expect(grabado.name).toBe('evidencia.webm');
        expect(grabado.type).toBe('video/webm');
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(pista.stop).toHaveBeenCalled();
        vi.useRealTimers();
    });
});
