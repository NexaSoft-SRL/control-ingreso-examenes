import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CamaraFoto from './CamaraFoto';

const original = navigator.mediaDevices;

function sinPermiso() {
    Object.defineProperty(navigator, 'mediaDevices', {
        configurable: true,
        value: {
            getUserMedia: vi.fn(() =>
                Promise.reject(Object.assign(new Error('no'), { name: 'NotAllowedError' }))
            ),
        },
    });
}

afterEach(() => {
    Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: original });
});

describe('CamaraFoto', () => {
    it('sin acceso a la cámara ofrece elegir una foto ahí mismo, sin «Tomar»', async () => {
        sinPermiso();
        const onTomar = vi.fn();
        render(<CamaraFoto onTomar={onTomar} onCerrar={() => {}} />);

        expect(await screen.findByText('Sin acceso a la cámara')).toBeInTheDocument();
        expect(screen.getByText('Use «Elegir foto»')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Tomar/ })).toBeNull();

        const foto = new File(['jpeg'], 'yo.jpg', { type: 'image/jpeg' });
        fireEvent.change(screen.getByLabelText(/Elegir foto/), { target: { files: [foto] } });
        expect(onTomar).toHaveBeenCalledWith(foto);
    });
});
