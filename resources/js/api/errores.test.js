import { describe, expect, it } from 'vitest';
import { codigoDe, erroresDe, estadoDe, mensajeDe } from './errores';
import { errorHttp } from '../test/apoyo';

describe('errores de la API', () => {
    it('toma el mensaje del servidor o el texto por defecto', () => {
        expect(mensajeDe(errorHttp(409, { message: 'Examen con ingresos.' }))).toBe(
            'Examen con ingresos.'
        );
        expect(mensajeDe(new Error('red'), 'No se pudo guardar')).toBe('No se pudo guardar');
    });

    it('convierte la validación 422 en un mensaje por campo', () => {
        const error = errorHttp(422, {
            message: '…',
            errors: { usuario: ['En uso', 'Otro'], correo: ['No válido'] },
        });
        expect(erroresDe(error)).toEqual({ usuario: 'En uso', correo: 'No válido' });
        expect(erroresDe(errorHttp(500))).toEqual({});
    });

    it('lee el estado y el código', () => {
        expect(estadoDe(errorHttp(423))).toBe(423);
        expect(estadoDe(new Error('red'))).toBeNull();
        expect(codigoDe(errorHttp(409, { codigo: 'SIN_AULAS' }))).toBe('SIN_AULAS');
    });
});
