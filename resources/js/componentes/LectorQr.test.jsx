import { act, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import LectorQr from './LectorQr';

const lectores = vi.hoisted(() => ({ creados: [], hayCamara: true, falloAlEncender: null }));

vi.mock('qr-scanner', () => {
    class QrScanner {
        static hasCamera = vi.fn(async () => lectores.hayCamara);

        constructor(video, alLeer, opciones) {
            this.video = video;
            this.alLeer = alLeer;
            this.opciones = opciones;
            this.start = vi.fn(async () => {
                if (lectores.falloAlEncender) throw lectores.falloAlEncender;
            });
            this.stop = vi.fn();
            this.destroy = vi.fn();
            lectores.creados.push(this);
        }
    }
    return { default: QrScanner };
});

const encendido = async () => {
    await waitFor(() => expect(lectores.creados.at(-1)?.start).toHaveBeenCalled());
    return lectores.creados.at(-1);
};

describe('LectorQr', () => {
    beforeEach(() => {
        lectores.creados = [];
        lectores.hayCamara = true;
        lectores.falloAlEncender = null;
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('apagado no enciende la cámara', async () => {
        render(<LectorQr activo={false} onLectura={() => {}} />);
        await act(async () => {});

        expect(lectores.creados).toHaveLength(0);
        expect(screen.getByLabelText('Cámara')).toHaveClass('hidden');
    });

    it('enciende la cámara trasera y entrega cada lectura', async () => {
        const onLectura = vi.fn();
        render(<LectorQr activo onLectura={onLectura} />);
        const lector = await encendido();

        expect(lector.video).toBe(screen.getByLabelText('Cámara'));
        expect(lector.opciones).toMatchObject({
            preferredCamera: 'environment',
            highlightScanRegion: true,
            maxScansPerSecond: 4,
        });
        lector.alLeer({ data: 'k3J-token' });
        expect(onLectura).toHaveBeenCalledWith('k3J-token');
    });

    it('ignora la misma lectura repetida durante 3 s', async () => {
        const onLectura = vi.fn();
        const reloj = vi.spyOn(Date, 'now').mockReturnValue(1_000_000);
        render(<LectorQr activo onLectura={onLectura} />);
        const lector = await encendido();

        lector.alLeer({ data: 'uno' });
        reloj.mockReturnValue(1_002_000);
        lector.alLeer({ data: 'uno' });
        lector.alLeer({ data: 'dos' });
        expect(onLectura.mock.calls).toEqual([['uno'], ['dos']]);

        reloj.mockReturnValue(1_006_000);
        lector.alLeer({ data: 'dos' });
        expect(onLectura).toHaveBeenCalledTimes(3);
    });

    it('detiene y libera la cámara al desactivarse y al desmontar', async () => {
        const { rerender, unmount } = render(<LectorQr activo onLectura={() => {}} />);
        const primero = await encendido();

        rerender(<LectorQr activo={false} onLectura={() => {}} />);
        expect(primero.stop).toHaveBeenCalledTimes(1);
        expect(primero.destroy).toHaveBeenCalledTimes(1);

        rerender(<LectorQr activo onLectura={() => {}} />);
        await waitFor(() => expect(lectores.creados).toHaveLength(2));
        const segundo = await encendido();
        unmount();
        expect(segundo.stop).toHaveBeenCalledTimes(1);
        expect(segundo.destroy).toHaveBeenCalledTimes(1);
    });

    it('enciende sin preguntar antes si hay cámara (Safari no la lista sin permiso)', async () => {
        lectores.hayCamara = false;
        render(<LectorQr activo onLectura={() => {}} />);

        await encendido();
    });

    it('avisa si la cámara no enciende', async () => {
        lectores.falloAlEncender = new Error('NotAllowedError');
        const onError = vi.fn();
        render(<LectorQr activo onLectura={() => {}} onError={onError} />);

        await waitFor(() => expect(onError).toHaveBeenCalledWith(lectores.falloAlEncender));
    });
});
