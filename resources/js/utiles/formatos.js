// Lo que cada tipo de dato admite al escribirse. `Campo` aplica `limpiar` a
// lo que se teclea o se pega, de modo que un campo de letras no deja poner
// números, uno de números no deja poner letras, y así. `aviso` es lo que se
// muestra un instante cuando se descarta algo.
//
// La validación del servidor sigue siendo la que manda: esto solo encarrila.
const LETRAS = /[^\p{L}\s.'-]/gu;
const DIGITOS = /\D/g;
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
    // C.I.: solo dígitos, de 4 a 10, sin complemento (regla del servidor:
    // PATRON_DOCUMENTO).
    documento: {
        limpiar: (v) => v.replace(DIGITOS, '').slice(0, 10),
        aviso: 'Solo números',
        propiedades: { inputMode: 'numeric', autoComplete: 'off' },
    },
    // Usuario del sistema: en minúsculas y sin tildes, letras y números con un
    // espacio, punto, guion o guion bajo entre partes (regla de NombreDeUsuario).
    usuario: {
        limpiar: (v) =>
            v
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9\s._-]/g, '')
                .replace(ESPACIOS, ' '),
        aviso: 'En minúsculas, sin tildes ni signos',
        propiedades: { autoCapitalize: 'none', autoComplete: 'username', spellCheck: false },
    },
};
