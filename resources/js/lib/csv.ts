/**
 * Tiny, dependency-free CSV parser (RFC 4180-ish: quoted fields, "" as an
 * escaped quote, quoted fields may contain commas/newlines). Returns one
 * object per data row keyed by the header row — this is deliberately not a
 * general-purpose CSV library, just enough to read a Collection Runner's
 * data file.
 */
export function parseCsv(text: string): Record<string, string>[] {
    const rows: string[][] = [];
    let row: string[] = [];
    let field = '';
    let inQuotes = false;

    for (let i = 0; i < text.length; i++) {
        const char = text[i];

        if (inQuotes) {
            if (char === '"') {
                if (text[i + 1] === '"') {
                    field += '"';
                    i++;
                } else {
                    inQuotes = false;
                }
            } else {
                field += char;
            }

            continue;
        }

        if (char === '"') {
            inQuotes = true;
        } else if (char === ',') {
            row.push(field);
            field = '';
        } else if (char === '\n' || char === '\r') {
            if (char === '\r' && text[i + 1] === '\n') {
                i++;
            }

            row.push(field);
            field = '';

            if (row.some((value) => value !== '')) {
                rows.push(row);
            }

            row = [];
        } else {
            field += char;
        }
    }

    if (field !== '' || row.length) {
        row.push(field);
        rows.push(row);
    }

    const [header, ...body] = rows;

    if (!header) {
        return [];
    }

    return body.map((cells) =>
        Object.fromEntries(
            header.map((key, index) => [key.trim(), cells[index] ?? '']),
        ),
    );
}
