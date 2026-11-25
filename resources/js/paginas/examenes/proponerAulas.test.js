import { describe, expect, it } from 'vitest';
import proponerAulas from './proponerAulas';

const aula = (id, nombre, edificio_id, facultad = 'FCyT') => ({
    id,
    nombre,
    edificio_id,
    facultad,
});
const AULAS = [
    aula(1, '690A', 2),
    aula(2, '691B', 2),
    aula(3, '606', 18),
    aula(4, '607', 18),
    aula(5, '004', 43, 'FHCE'),
];

describe('proponerAulas', () => {
    it('toma solo las libres', () => {
        const ids = proponerAulas({
            aulas: AULAS,
            libre: (id) => id !== 1,
            facultad: 'FCyT',
            cantidad: 4,
        });
        expect(ids).not.toContain(1);
    });

    it('prefiere las sugeridas y deja las demás en el mismo edificio', () => {
        const ids = proponerAulas({
            aulas: AULAS,
            libre: () => true,
            sugeridas: [4],
            facultad: 'FCyT',
            cantidad: 2,
        });
        // La 607 (sugerida) ancla el edificio 18; la segunda es la 606, del mismo edificio.
        expect(ids).toEqual([4, 3]);
    });

    it('prefiere la facultad de la asignatura y deja al final las de otra', () => {
        const ids = proponerAulas({
            aulas: AULAS,
            libre: () => true,
            facultad: 'FCyT',
            cantidad: 5,
        });
        expect(ids.at(-1)).toBe(5);
    });

    it('sin libres o sin cantidad no propone nada', () => {
        expect(
            proponerAulas({ aulas: AULAS, libre: () => false, facultad: 'FCyT', cantidad: 2 })
        ).toEqual([]);
        expect(
            proponerAulas({ aulas: AULAS, libre: () => true, facultad: 'FCyT', cantidad: 0 })
        ).toEqual([]);
    });

    it('no propone más de las que hay', () => {
        expect(
            proponerAulas({
                aulas: AULAS.slice(0, 2),
                libre: () => true,
                facultad: 'FCyT',
                cantidad: 9,
            })
        ).toHaveLength(2);
    });
});
