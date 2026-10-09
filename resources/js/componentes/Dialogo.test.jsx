import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Dialogo from './Dialogo';

describe('Dialogo', () => {
    it('muestra título, contenido y acciones', () => {
        render(
            <Dialogo
                titulo="Restaurar"
                onCerrar={() => {}}
                acciones={<button type="button">Confirmar</button>}
            >
                <p>Sobrescribe los datos actuales</p>
            </Dialogo>
        );

        expect(screen.getByRole('dialog', { name: 'Restaurar' })).toHaveAttribute(
            'aria-modal',
            'true'
        );
        expect(screen.getByText('Sobrescribe los datos actuales')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Confirmar' })).toBeInTheDocument();
    });

    it('se cierra con Escape, con la equis y tocando fuera', () => {
        const onCerrar = vi.fn();
        render(
            <Dialogo titulo="Aula" onCerrar={onCerrar}>
                contenido
            </Dialogo>
        );

        fireEvent.keyDown(window, { key: 'Escape' });
        screen
            .getAllByRole('button', { name: 'Cerrar' })
            .forEach((boton) => fireEvent.click(boton));
        expect(onCerrar).toHaveBeenCalledTimes(3);
    });

    it('cerrado no dibuja nada ni escucha el teclado', () => {
        const onCerrar = vi.fn();
        render(
            <Dialogo titulo="Aula" abierto={false} onCerrar={onCerrar}>
                contenido
            </Dialogo>
        );

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        fireEvent.keyDown(window, { key: 'Escape' });
        expect(onCerrar).not.toHaveBeenCalled();
    });
});
