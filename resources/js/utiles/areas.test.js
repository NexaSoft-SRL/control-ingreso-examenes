import { describe, expect, it } from 'vitest';
import { areaDeAsignatura } from './areas';

const area = (nombre) => areaDeAsignatura(nombre).area;

describe('areaDeAsignatura', () => {
    it('reconoce el área sin tildes ni mayúsculas', () => {
        expect(area('Cálculo I')).toBe('Matemáticas');
        expect(area('ALGEBRA II')).toBe('Matemáticas');
        expect(area('Introduccion a la Programacion')).toBe('Programación');
        expect(area('Inglés III')).toBe('Idiomas');
    });

    it('da prioridad a la regla más específica', () => {
        expect(area('Arquitectura de Computadoras I')).toBe('Hardware');
        expect(area('Taller de Diseño IV')).toBe('Arquitectura y diseño');
        expect(area('Taller de Base de Datos')).toBe('Datos');
        expect(area('Evaluación y Auditoría de Sistemas')).toBe('Seguridad');
        expect(area('Auditoria Tributaria II')).toBe('Empresa');
        expect(area('Psicologia Forense')).toBe('Psicología');
        expect(area('Biomecanica de la Act. Fisica y Deporte')).toBe('Deporte');
        expect(area('Fisica General')).toBe('Física');
    });

    it('compara palabras completas donde hace falta', () => {
        expect(area('Transmision Ip')).toBe('Redes');
        expect(area('Analisis II')).toBe('Matemáticas');
        expect(area('Analisis Instrumental')).toBe('Química y alimentos');
    });

    it('sin coincidencia ni nombre devuelve el área genérica', () => {
        expect(area('Taller I')).toBe('Asignatura');
        expect(area(undefined)).toBe('Asignatura');
    });
});
