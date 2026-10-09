import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import descargar, { nombreDeArchivo } from './descargar';
import { api } from './cliente';
import { errorHttp } from '../test/apoyo';

vi.mock('./cliente');

describe('descargar', () => {
    beforeEach(() => {
        URL.createObjectURL = vi.fn(() => 'blob:prueba');
        URL.revokeObjectURL = vi.fn();
    });

    afterEach(() => {
        delete URL.createObjectURL;
        delete URL.revokeObjectURL;
    });

    it('lee el nombre de Content-Disposition', () => {
        expect(nombreDeArchivo('attachment; filename="asistencia_2-2026.xlsx"')).toBe(
            'asistencia_2-2026.xlsx'
        );
        expect(nombreDeArchivo("attachment; filename*=UTF-8''padr%C3%B3n.xlsx")).toBe(
            'padrón.xlsx'
        );
        expect(nombreDeArchivo(undefined)).toBeNull();
    });

    it('pide el archivo como blob y lo descarga con el nombre del servidor', async () => {
        const clic = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
        api.get.mockResolvedValue({
            data: new Blob(['x']),
            headers: { 'content-disposition': 'attachment; filename="respaldo.sql"' },
        });

        const nombre = await descargar('/respaldos/3/archivo', { parametros: { formato: 'sql' } });

        expect(nombre).toBe('respaldo.sql');
        expect(api.get).toHaveBeenCalledWith('/respaldos/3/archivo', {
            params: { formato: 'sql' },
            responseType: 'blob',
        });
        expect(clic).toHaveBeenCalledTimes(1);
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:prueba');
    });

    it('sin cabecera usa el nombre indicado', async () => {
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
        api.get.mockResolvedValue({ data: new Blob(['x']), headers: {} });

        await expect(descargar('/plantilla', { nombre: 'plantilla.xlsx' })).resolves.toBe(
            'plantilla.xlsx'
        );
    });

    it('deja legible el mensaje de un error que llega como blob', async () => {
        const cuerpo = new Blob([JSON.stringify({ message: 'Sin datos.', codigo: 'SIN_DATOS' })]);
        api.get.mockRejectedValue(errorHttp(409, cuerpo));

        await expect(descargar('/reportes/asistencia')).rejects.toMatchObject({
            response: { data: { codigo: 'SIN_DATOS' } },
        });
    });
});
