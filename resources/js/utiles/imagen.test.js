import { describe, expect, it } from 'vitest';
import { ImagenNoValida, reducirImagen } from './imagen';

describe('reducirImagen', () => {
    it('rechaza lo que no es imagen', async () => {
        const pdf = new File(['%PDF'], 'doc.pdf', { type: 'application/pdf' });
        await expect(reducirImagen(pdf)).rejects.toThrow('Solo JPG, PNG o WebP');
    });

    it('sin poder leerla, una foto HEIC pide JPG o PNG', async () => {
        const heic = new File(['x'], 'IMG_0001.HEIC', { type: 'image/heic' });
        await expect(reducirImagen(heic)).rejects.toBeInstanceOf(ImagenNoValida);
        await expect(reducirImagen(heic)).rejects.toThrow(
            'HEIC no compatible aquí · use JPG o PNG'
        );
    });

    it('una imagen más grande que el límite se rechaza', async () => {
        const grande = new File([new Uint8Array(9 * 1024 * 1024)], 'grande.jpg', {
            type: 'image/jpeg',
        });
        await expect(reducirImagen(grande)).rejects.toThrow('Máximo 8 MB');
    });
});
