// El usuario con que se inicia sesión: nombre y apellido separados por un
// espacio («jose soruco»), en minúsculas y sin tildes. Es la misma regla
// del servidor (NombreDeUsuario): allí se compara sin espacios, así que
// «josesoruco» también entra.

// Letras y números, con un espacio, punto o guion entre las partes.
export const FORMATO_USUARIO = /^[a-z0-9]+(?:[ ._-][a-z0-9]+)*$/;

export const ERROR_USUARIO = 'Nombre y apellido, separados por un espacio';

// «  Letícia   BLANCO » → «leticia blanco».
export const normalizarUsuario = (valor) =>
    String(valor ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim()
        .replace(/\s+/g, ' ');

// Para quien no tiene correo registrado o no recibe el enlace de recuperación.
export const PISTA_SIN_CORREO = 'Si no llega, pida una contraseña temporal a la administración.';

export const usuarioValido = (valor) => FORMATO_USUARIO.test(normalizarUsuario(valor));
