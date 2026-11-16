import { describe, expect, it } from 'vitest';
import solapamientoDe from './solapamientoDe';

describe('EV-03 · solapamiento de horarios', () => {
    it('calcula una coincidencia parcial', () => {
        expect(solapamientoDe('08:15', 90, '09:00', '10:30')).toEqual({
            inicio: '09:00',
            fin: '09:45',
            minutos: 45,
        });
    });

    it('calcula una coincidencia total', () => {
        expect(solapamientoDe('08:15', 90, '08:15', '09:45')).toEqual({
            inicio: '08:15',
            fin: '09:45',
            minutos: 90,
        });
    });

    it('no confunde horarios consecutivos con conflictos', () => {
        expect(solapamientoDe('08:15', 90, '09:45', '11:00')).toBeNull();
        expect(solapamientoDe('09:45', 90, '08:15', '09:45')).toBeNull();
    });

    it('rechaza datos horarios inválidos', () => {
        expect(solapamientoDe('25:00', 90, '09:00', '10:30')).toBeNull();
        expect(solapamientoDe('08:15', 90, '10:30', '09:00')).toBeNull();
        expect(solapamientoDe('23:30', 90, '23:00', '23:59')).toBeNull();
    });
});
