import { act, fireEvent, render, screen } from '@testing-library/react';
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

    describe('formato', () => {
        const escribir = (formato, texto, extra = {}) => {
            const onChange = vi.fn();
            render(<Campo etiqueta="Dato" formato={formato} onChange={onChange} {...extra} />);
            const campo = screen.getByLabelText('Dato');
            fireEvent.change(campo, { target: { value: texto } });
            return { campo, onChange };
        };

        it('letras: no deja poner números ni signos y avisa', () => {
            const { campo, onChange } = escribir('letras', 'Ana2 María#');

            expect(campo).toHaveValue('Ana María');
            expect(onChange.mock.calls[0][0].target.value).toBe('Ana María');
            expect(screen.getByText('Solo letras')).toBeInTheDocument();
        });

        it('letras: admite tildes, ñ, punto, apóstrofo y guion', () => {
            const { campo } = escribir('letras', "Ma. José D'Alessandro Pérez-Ñúñez");

            expect(campo).toHaveValue("Ma. José D'Alessandro Pérez-Ñúñez");
            expect(screen.queryByText('Solo letras')).toBeNull();
        });

        it('dígitos: solo números, también si se pega texto', () => {
            const { campo } = escribir('digitos', 'SIS 2021-04821');

            expect(campo).toHaveValue('202104821');
            expect(campo).toHaveAttribute('inputmode', 'numeric');
            expect(screen.getByText('Solo números')).toBeInTheDocument();
        });

        it('documento: solo dígitos, sin letras, guion ni espacios', () => {
            expect(escribir('documento', '5003 748-1A!?').campo).toHaveValue('50037481');
        });

        it('documento: no pasa de 10 dígitos y avisa «Solo números»', () => {
            const { campo } = escribir('documento', '50037481234567');

            expect(campo).toHaveValue('5003748123');
        });

        it('documento: una letra se descarta y el campo lo avisa', () => {
            const { campo } = escribir('documento', '5003748A');

            expect(campo).toHaveValue('5003748');
            expect(screen.getByText('Solo números')).toBeInTheDocument();
        });

        it('usuario: pasa a minúsculas y quita tildes', () => {
            expect(escribir('usuario', 'Leticia Ñuñez').campo).toHaveValue('leticia nunez');
        });

        it('usuario: letras y números separados por un espacio', () => {
            const { campo } = escribir('usuario', 'leticia@blanco  coca');

            expect(campo).toHaveValue('leticiablanco coca');
        });

        it('usuario: conserva el punto, el guion y el guion bajo que admite el servidor', () => {
            const { campo } = escribir('usuario', 'ana.perez_gomez-2');

            expect(campo).toHaveValue('ana.perez_gomez-2');
        });

        it('el aviso del formato se va solo y deja paso a un error', () => {
            vi.useFakeTimers();
            try {
                const { campo } = escribir('digitos', '12a');
                expect(screen.getByText('Solo números')).toBeInTheDocument();
                act(() => vi.advanceTimersByTime(2100));
                expect(screen.queryByText('Solo números')).toBeNull();
                expect(campo).toHaveValue('12');
            } finally {
                vi.useRealTimers();
            }
        });

        it('sin formato no toca lo escrito', () => {
            render(<Campo etiqueta="Libre" />);
            const campo = screen.getByLabelText('Libre');
            fireEvent.change(campo, { target: { value: 'a1-#' } });

            expect(campo).toHaveValue('a1-#');
        });
    });

    it('al reutilizarse para otro campo no hereda el «tocado» del anterior', () => {
        const { rerender } = render(<Campo etiqueta="Código" requerido />);
        const campo = screen.getByLabelText(/Código/);
        fireEvent.change(campo, { target: { value: 'a' } });
        fireEvent.change(campo, { target: { value: '' } });
        expect(screen.getByText('Obligatorio')).toBeInTheDocument();

        rerender(<Campo etiqueta="Carnet" requerido />);

        expect(screen.queryByText('Obligatorio')).toBeNull();
    });
});
