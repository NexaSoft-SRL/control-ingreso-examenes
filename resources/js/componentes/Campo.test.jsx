import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Campo from './Campo';

describe('Campo', () => {
    it('marca el obligatorio sin acusar al que solo pasó por el campo', () => {
        render(<Campo etiqueta="Usuario" requerido />);
        const campo = screen.getByLabelText(/Usuario/);

        expect(campo).toHaveAttribute('aria-required', 'true');
        fireEvent.focus(campo);
        fireEvent.blur(campo);
        expect(screen.queryByText('Obligatorio')).not.toBeInTheDocument();

        // Escribir y borrar sí lo marca.
        fireEvent.change(campo, { target: { value: 'ana' } });
        fireEvent.change(campo, { target: { value: '' } });
        expect(screen.getByText('Obligatorio')).toBeInTheDocument();
        expect(campo).toHaveAttribute('aria-invalid', 'true');
    });

    it('al salir de un campo con algo escrito lo valida', () => {
        render(
            <Campo
                etiqueta="Código"
                defaultValue="12a"
                validar={(v) => (/^\d+$/.test(v) ? null : 'Solo dígitos')}
            />
        );
        fireEvent.blur(screen.getByLabelText('Código'));
        expect(screen.getByText('Solo dígitos')).toBeInTheDocument();
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
        expect(screen.queryByText('Obligatorio')).not.toBeInTheDocument();
    });

    it('muestra y oculta la contraseña con el ojito, sin perder lo escrito', () => {
        render(<Campo etiqueta="Contraseña" type="password" />);
        const campo = screen.getByLabelText('Contraseña');
        fireEvent.change(campo, { target: { value: 'Clave-segura1' } });

        expect(campo).toHaveAttribute('type', 'password');
        const ojito = screen.getByRole('button', { name: 'Mostrar contraseña' });
        expect(ojito).toHaveAttribute('aria-pressed', 'false');

        fireEvent.click(ojito);
        expect(campo).toHaveAttribute('type', 'text');
        expect(campo).toHaveValue('Clave-segura1');
        expect(screen.getByRole('button', { name: 'Ocultar contraseña' })).toHaveAttribute(
            'aria-pressed',
            'true'
        );

        fireEvent.click(screen.getByRole('button', { name: 'Ocultar contraseña' }));
        expect(campo).toHaveAttribute('type', 'password');
    });

    it('solo las contraseñas llevan el ojito', () => {
        render(<Campo etiqueta="Usuario" />);
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});
