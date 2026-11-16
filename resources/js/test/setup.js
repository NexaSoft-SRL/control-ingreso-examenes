import '@testing-library/jest-dom/vitest';
import * as matchers from '@testing-library/jest-dom/matchers';
import { cleanup, fireEvent } from '@testing-library/react';
import { afterEach, expect } from 'vitest';

afterEach(() => {
    cleanup();
});

// Las listas desplegables de la aplicación (Seleccion) son propias, no el
// `<select>` del navegador. Para que una prueba siga escribiendo
// `fireEvent.change(lista, { target: { value: 'b' } })`, el cambio sobre una
// de ellas abre la lista y pulsa la opción de ese valor.
const cambiarNativo = fireEvent.change;
fireEvent.change = (elemento, init) => {
    if (elemento?.tagName === 'BUTTON' && elemento.getAttribute('role') === 'combobox') {
        const valor = String(init?.target?.value ?? '');
        if (elemento.getAttribute('aria-expanded') !== 'true') fireEvent.click(elemento);
        const lista = document.getElementById(elemento.getAttribute('aria-controls'));
        const opcion = [...(lista?.querySelectorAll('[role="option"]') ?? [])].find(
            (o) => o.dataset.valor === valor
        );
        if (!opcion) throw new Error(`La lista no tiene una opción con valor «${valor}»`);
        return fireEvent.click(opcion);
    }
    return cambiarNativo(elemento, init);
};

// `toHaveValue` sobre una de esas listas compara el valor de la opción elegida.
const esLista = (elemento) =>
    elemento?.tagName === 'BUTTON' && elemento.getAttribute('role') === 'combobox';

expect.extend({
    toHaveValue(elemento, esperado) {
        if (!esLista(elemento)) return matchers.toHaveValue.call(this, elemento, esperado);
        const actual = elemento.dataset.valor ?? '';
        const pasa = actual === String(esperado);
        return {
            pass: pasa,
            message: () =>
                `Se esperaba la lista ${pasa ? 'sin' : 'con'} el valor «${esperado}»; tiene «${actual}»`,
        };
    },
});
