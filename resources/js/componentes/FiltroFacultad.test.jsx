import { fireEvent, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import FiltroFacultad, { PuntoFacultad, colorDeFacultad } from './FiltroFacultad';
import { FACULTADES, renderConSesion } from '../test/apoyo';

describe('FiltroFacultad', () => {
    it('ofrece «Todas» y cada facultad de la sesión, con su conteo', () => {
        const onCambiar = vi.fn();
        const conteos = { FCyT: 287, FCE: 214, FHCE: 211, FACH: 169 };
        renderConSesion(
            <FiltroFacultad
                valor={null}
                onCambiar={onCambiar}
                conteo={(sigla) => (sigla ? conteos[sigla] : 849)}
            />
        );

        expect(screen.getByRole('button', { name: /^Todas\s*849$/ })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        fireEvent.click(screen.getByRole('button', { name: /^FHCE\s*211$/ }));
        expect(onCambiar).toHaveBeenCalledWith('FHCE');
    });

    it('puede entregar la clave que espera la API', () => {
        const onCambiar = vi.fn();
        renderConSesion(<FiltroFacultad valor="fcyt" onCambiar={onCambiar} campo="clave" />);

        expect(screen.getByRole('button', { name: 'FCyT' })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        fireEvent.click(screen.getByRole('button', { name: 'FACH' }));
        expect(onCambiar).toHaveBeenCalledWith('fach');
    });

    it('pinta el punto con el color de la facultad', () => {
        renderConSesion(<PuntoFacultad sigla="FCE" />);

        expect(screen.getByText('FCE').querySelector('span')).toHaveStyle({
            backgroundColor: '#107C41',
        });
        expect(colorDeFacultad('FCyT', FACULTADES)).toBe('#B90813');
        expect(colorDeFacultad('OTRA', FACULTADES)).toBe('#475569');
    });
});
