import { describe, expect, it } from 'vitest';
import { createHash } from 'node:crypto';
import sha256 from './sha256';

describe('sha256', () => {
    it('coincide con los vectores conocidos', () => {
        expect(sha256('')).toBe('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855');
        expect(sha256('abc')).toBe(
            'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad'
        );
        expect(sha256('abcdbcdecdefdefgefghfghighijhijkijkljklmklmnlmnomnopnopq')).toBe(
            '248d6a61d20638b8e5c026930c3e6039a33ce45964ff2167f6ecedd419db06c1'
        );
    });

    it('coincide con el de PHP para un token de 48 caracteres, con tildes y largos distintos', () => {
        const entradas = [
            'a'.repeat(48),
            'Zk3Q9xLmPw7RtYv2NcB5HdGfJsAe8UoIq1WzX4Ty6MbVnKlC',
            'ñandú · 123',
            'x'.repeat(55),
            'x'.repeat(56),
            'x'.repeat(64),
            'x'.repeat(1000),
        ];
        for (const entrada of entradas) {
            expect(sha256(entrada)).toBe(createHash('sha256').update(entrada).digest('hex'));
        }
    });
});
