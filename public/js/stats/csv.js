/**
 * CSV exports of the statistics page, built in the browser from the data of the page
 */

/**
 * Separator expected by the spreadsheets of the language: ";" in French (the comma is the decimal separator)
 */
function statCsvSeparator(lang) {
    const language =
        lang || (typeof locale === 'string' ? locale : 'en') || 'en';
    return String(language).toLowerCase().startsWith('fr') ? ';' : ',';
}

/**
 * A cell: quoted when needed; a value starting like a formula is neutralised (spreadsheet formula injection)
 */
function statCsvCell(value, separator) {
    let text = value === null || value === undefined ? '' : String(value);

    if (/^[=+\-@\t\r]/.test(text) && !/^-?\d+([.,]\d+)?$/.test(text)) {
        text = "'" + text;
    }

    if (
        text.includes(separator) ||
        text.includes('"') ||
        text.includes('\n') ||
        text.includes('\r')
    ) {
        text = '"' + text.replace(/"/g, '""') + '"';
    }

    return text;
}

/**
 * @param {Array<Array<*>>} rows the first one is the header
 * @param {string} separator
 * @returns {string}
 */
function buildCsv(rows, separator = ',') {
    return rows
        .map(row =>
            row.map(cell => statCsvCell(cell, separator)).join(separator)
        )
        .join('\r\n');
}

/**
 * A file name without any character a file system would refuse
 */
function statCsvFileName(parts) {
    return (
        parts
            .filter(part => part !== null && part !== undefined && part !== '')
            .map(part =>
                String(part)
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '') // accents
                    .replace(/[^A-Za-z0-9_-]+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .toLowerCase()
            )
            .filter(Boolean)
            .join('-') + '.csv'
    );
}

/**
 * Downloads the rows as a CSV file (UTF-8 with a BOM, read as such by the spreadsheets)
 */
function downloadCsv(fileName, rows) {
    const separator = statCsvSeparator();
    const blob = new Blob(['\uFEFF' + buildCsv(rows, separator)], {
        type: 'text/csv;charset=utf-8',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    link.hidden = true;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 0);
}

/**
 * A button which downloads a CSV file
 * @param {string} label
 * @param {function(): {fileName: string, rows: Array}} getExport called on click (the data of that moment)
 */
function createCsvButton(label, getExport) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-default btn-xs stat-csv-button';

    const icon = document.createElement('span');
    icon.className = 'fas fa-download fa-sm';
    icon.setAttribute('aria-hidden', 'true');
    button.appendChild(icon);
    button.appendChild(document.createTextNode(' ' + label));

    button.addEventListener('click', () => {
        const csv = getExport();
        downloadCsv(csv.fileName, csv.rows);
    });

    return button;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        statCsvSeparator,
        statCsvCell,
        buildCsv,
        statCsvFileName,
        downloadCsv,
        createCsvButton,
    };
}
