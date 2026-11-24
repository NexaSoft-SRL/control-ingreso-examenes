// Lo que cada tipo de dato admite al escribirse. `Campo` aplica `limpiar` a
// lo que se teclea o se pega, de modo que un campo de letras no deja poner
// números, uno de números no deja poner letras, y así. `aviso` es lo que se
// muestra un instante cuando se descarta algo.
//
// La validación del servidor sigue siendo la que manda: esto solo encarrila.
const LETRAS = /[^\p{L}\s.'-]/gu;
const DIGITOS = /\D/g;
const DOCUMENTO = /[^0-9A-Za-z\s-]/g;
const USUARIO = /[^\p{L}\p{N}\s]/gu;
const ESPACIOS = /\s{2,}/g;

export const FORMATOS = {
    // Nombres y apellidos: letras, con espacio, punto, apóstrofo y guion.
    letras: {
        limpiar: (v) => v.replace(LETRAS, '').replace(ESPACIOS, ' '),
        aviso: 'Solo letras',
        propiedades: { autoComplete: 'off', spellCheck: false },
    },
    // Códigos y números: solo dígitos (código SIS, código de aula, seis dígitos).
    digitos: {
        limpiar: (v) => v.replace(DIGITOS, ''),
        aviso: 'Solo números',
        propiedades: { inputMode: 'numeric', autoComplete: 'off' },
    },
    // C.I.: número y, si tiene, su complemento («5003748-1A»).
    documento: {
        limpiar: (v) => v.replace(DOCUMENTO, '').replace(ESPACIOS, ' '),
        aviso: 'Solo número y complemento',
        propiedades: { autoComplete: 'off', autoCapitalize: 'characters', spellCheck: false },
    },
    // Usuario del sistema: letras y números separados por un espacio.
    usuario: {
        limpiar: (v) => v.replace(USUARIO, '').replace(ESPACIOS, ' '),
        aviso: 'Solo letras y números',
        propiedades: { autoCapitalize: 'none', autoComplete: 'username', spellCheck: false },
    },
};
