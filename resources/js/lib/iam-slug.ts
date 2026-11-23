const STANDARD_PCRE_DELIMITERS = new Set(['/', '#', '~', '%', '@', '!', ';']);

/**
 * Mengonversi pola regex PCRE PHP (mis. `/^[a-z]...$/i`) atau pola regex JS
 * mentah menjadi objek RegExp JavaScript yang valid.
 */
export function compileIamSlugPattern(pattern: string): RegExp {
    try {
        if (pattern.length >= 2) {
            const delimiter = pattern.charAt(0);

            if (STANDARD_PCRE_DELIMITERS.has(delimiter)) {
                for (let i = pattern.length - 1; i > 0; i--) {
                    if (pattern[i] !== delimiter) {
                        continue;
                    }

                    let backslashCount = 0;

                    for (let j = i - 1; j > 0 && pattern[j] === '\\'; j--) {
                        backslashCount++;
                    }

                    if (backslashCount % 2 === 0) {
                        const rawBody = pattern.slice(1, i);
                        const pcreFlags = pattern.slice(i + 1);
                        const jsFlags = pcreFlags.replace(/[^dgimsuvy]/g, '');

                        return new RegExp(rawBody, jsFlags);
                    }
                }
            }
        }

        return new RegExp(pattern);
    } catch {
        return /(?!)/;
    }
}
