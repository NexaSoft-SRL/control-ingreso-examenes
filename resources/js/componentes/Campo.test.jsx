import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Campo from './Campo';

describe('Campo', () => {
    it('marca el obligatorio y valida al tocar', () => {
        render(<Campo etiqueta="Usuario" requerido />);
        const campo = screen.getByLabelText(/Usuario/);

        expect(campo).toHaveAttribute('aria-required', 'true');
        expect(screen.queryByText('Obligatorio')).not.toBeInTheDocument();

        fireEvent.blur(campo);
        expect(screen.getByText('Obligatorio')).toBeInTheDocument();
        expect(campo).toHaveAttribute('aria-invalid', 'true');
    });

    it('valida mientras se escribe y avisa el cambio', () => {
        const onChange = vi.fn();
        render(
            <Campo
                etiqueta="Código"
                validar={(v) => (/^\d+$/.test(v) ? null : 'Solo dígitos')}
                onChange={onChange}
            />
        );
        const campo = screen.getByLabelText('Código');

        fireEvent.change(campo, { target: { value: '12a' } });
        expect(screen.getByText('Solo dígitos')).toBeInTheDocument();
        expect(onChange).toHaveBeenCalledTimes(1);

        fireEvent.change(campo, { target: { value: '123' } });
        expect(screen.queryByText('Solo dígitos')).not.toBeInTheDocument();
    });

    it('muestra el error del servidor por encima de la ayuda', () => {
        const { rerender } = render(<Campo etiqueta="Usuario" ayuda="nombre.apellido" />);
        expect(screen.getByText('nombre.apellido')).toBeInTheDocument();

        rerender(<Campo etiqueta="Usuario" ayuda="nombre.apellido" error="En uso" />);
        expect(screen.getByText('En uso')).toBeInTheDocument();
        expect(screen.queryByText('nombre.apellido')).not.toBeInTheDocument();
    });

    it('valida el valor controlado y muestra el sufijo', () => {
        render(
            <Campo etiqueta="Correo" requerido value="" sufijo="@umss.edu" onChange={() => {}} />
        );

        expect(screen.getByText('@umss.edu')).toBeInTheDocument();
        fireEvent.blur(screen.getByLabelText(/Correo/));
        expect(screen.getByText('Obligatorio')).toBeInTheDocument();
    });
});
