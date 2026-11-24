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
    // C.I.: el número (4 a 10 dígitos) y, si tiene, su complemento con guion
    // («5003748-1A»): hasta dos partes de 1 a 3 letras o números, en mayúsculas.
    // Sin espacios: el complemento va con guion. Regla del servidor: PATRON_DOCUMENTO.
    documento: {
        limpiar: (v) => {
            const [numero = '', ...resto] = v
                .toUpperCase()
                .replace(/\s+/g, '')
                .replace(/[^0-9A-Z-]/g, '')
                .replace(/-+/g, '-')
                .replace(/^-/, '')
                .split('-');
            const partes = resto.slice(0, 2).map((p) => p.slice(0, 3));
            return [numero.replace(DIGITOS, '').slice(0, 10), ...partes].join('-');
        },
        aviso: 'Número y complemento: 5003748-1A',
        propiedades: { autoComplete: 'off', autoCapitalize: 'characters', spellCheck: false },
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
