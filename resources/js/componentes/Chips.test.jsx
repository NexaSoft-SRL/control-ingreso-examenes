import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Chips from './Chips';

const OPCIONES = [
    { valor: null, etiqueta: 'Todas', conteo: 849 },
    { valor: 'FCyT', etiqueta: 'FCyT', color: '#B90813', conteo: 287 },
    { valor: 'FCE', etiqueta: 'FCE', conteo: 214 },
];

describe('Chips', () => {
    it('dibuja fichas con su conteo y marca la elegida', () => {
        const onCambiar = vi.fn();
        render(
            <Chips
                opciones={OPCIONES}
                valor="FCyT"
                onCambiar={onCambiar}
                etiqueta="Filtrar por facultad"
            />
        );

        expect(screen.getByRole('group', { name: 'Filtrar por facultad' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^FCyT\s*287$/ })).toHaveAttribute(
            'aria-pressed',
            'true'
        );
        expect(screen.getByRole('button', { name: /^Todas\s*849$/ })).toHaveAttribute(
            'aria-pressed',
            'false'
        );

        fireEvent.click(screen.getByRole('button', { name: /^FCE\s*214$/ }));
        expect(onCambiar).toHaveBeenCalledWith('FCE');
    });

    it('con más opciones que el máximo pasa a lista desplegable', () => {
        const onCambiar = vi.fn();
        render(
            <Chips
                opciones={OPCIONES}
                valor={null}
                onCambiar={onCambiar}
                etiqueta="Facultad"
                maximo={2}
            />
        );
        const lista = screen.getByRole('combobox', { name: 'Facultad' });

        expect(screen.queryByRole('button')).not.toBeInTheDocument();
        expect(screen.getByRole('option', { name: 'FCyT (287)' })).toBeInTheDocument();
        fireEvent.change(lista, { target: { value: '1' } });
        expect(onCambiar).toHaveBeenCalledWith('FCyT');
    });

    it('agrega lo extra al final', () => {
        render(
            <Chips
                opciones={OPCIONES}
                valor={null}
                onCambiar={() => {}}
                extra={<span>Sin cuenta</span>}
            />
        );
        expect(screen.getByText('Sin cuenta')).toBeInTheDocument();
    });
});
