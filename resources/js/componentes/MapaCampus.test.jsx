import { fireEvent, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import MapaCampus, { edificioDeAula, pisoDeAula, proyectar } from './MapaCampus';
import { renderConSesion } from '../test/apoyo';

const CAJA = { lon: [-66.15, -66.14], lat: [-17.4, -17.39] };
const EDIFICIOS = [
    {
        id: 3,
        facultad: 'FCyT',
        nombre: 'Edificio Académico 2',
        poligono: [
            [-66.149, -17.399],
            [-66.148, -17.399],
            [-66.148, -17.398],
            [-66.149, -17.398],
        ],
        centro: [-66.1485, -17.3985],
        aulas: ['617', '624'],
        pisos: [{ nombre: '1° Piso', aulas: ['624'] }],
    },
    {
        id: 9,
        facultad: 'FCE',
        nombre: 'Bloque Central',
        poligono: [
            [-66.142, -17.392],
            [-66.141, -17.392],
            [-66.141, -17.391],
        ],
        centro: [-66.1415, -17.3915],
        aulas: ['617'],
        pisos: [],
    },
];

describe('MapaCampus', () => {
    it('proyecta longitud y latitud al lienzo de 1000 × 644', () => {
        expect(proyectar([-66.15, -17.39], CAJA)).toEqual([0, 0]);
        expect(proyectar([-66.14, -17.4], CAJA)).toEqual([1000, 644]);
        expect(proyectar([-66.145, -17.395], CAJA)).toEqual([500, 322]);
    });

    it('busca el edificio y el piso de un aula, dentro de su facultad', () => {
        expect(edificioDeAula(EDIFICIOS, '617', 'FCE').id).toBe(9);
        expect(edificioDeAula(EDIFICIOS, '624').nombre).toBe('Edificio Académico 2');
        expect(edificioDeAula(EDIFICIOS, '999')).toBeNull();
        expect(pisoDeAula(EDIFICIOS, '624', 'FCyT')).toBe('1° Piso');
        expect(pisoDeAula(EDIFICIOS, '617', 'FCyT')).toBeNull();
    });

    it('dibuja un polígono por edificio con el color de su facultad', () => {
        const { container } = renderConSesion(<MapaCampus edificios={EDIFICIOS} caja={CAJA} />);
        const poligonos = container.querySelectorAll('polygon');

        expect(
            screen.getByRole('img', { name: 'Mapa de edificios del campus' })
        ).toBeInTheDocument();
        expect(poligonos).toHaveLength(2);
        expect(poligonos[0]).toHaveAttribute('fill', '#B90813');
        expect(poligonos[1]).toHaveAttribute('fill', '#107C41');
    });

    it('limita a una facultad y resalta por aula o por id de edificio', () => {
        const { container, rerender } = renderConSesion(
            <MapaCampus
                edificios={EDIFICIOS}
                caja={CAJA}
                facultad="FCyT"
                resaltados={['624']}
                rotulos={{ 624: 'Aula 624' }}
            />
        );
        expect(container.querySelectorAll('polygon')).toHaveLength(1);
        expect(container.querySelector('polygon')).toHaveAttribute('fill', '#2563eb');
        expect(screen.getByText('Aula 624')).toBeInTheDocument();

        rerender(<MapaCampus edificios={EDIFICIOS} caja={CAJA} resaltados={[9]} />);
        const [atenuado, marcado] = container.querySelectorAll('polygon');
        expect(atenuado).toHaveAttribute('fill', '#e2e8f0');
        expect(marcado).toHaveAttribute('fill', '#2563eb');
        expect(screen.getByText('Bloque Central')).toBeInTheDocument();
    });

    it('avisa el edificio elegido y no falla sin edificios', () => {
        const onElegir = vi.fn();
        const { container, rerender } = renderConSesion(
            <MapaCampus edificios={EDIFICIOS} caja={CAJA} onElegir={onElegir} seleccionado={3} />
        );
        fireEvent.click(container.querySelector('polygon'));
        expect(onElegir).toHaveBeenCalledWith(expect.objectContaining({ id: 3 }));

        rerender(<MapaCampus />);
        expect(container.querySelectorAll('polygon')).toHaveLength(0);
    });
});
