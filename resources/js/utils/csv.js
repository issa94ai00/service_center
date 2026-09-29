/**
 * Client-side CSV export.
 *
 * Report numbers almost always end their life in a spreadsheet, and every
 * report on screen is already a fully-formed table — exporting in the browser
 * keeps that one click away without a second server round-trip per report.
 */

/** Excel only reads a UTF-8 CSV as Arabic when the file opens with a BOM. */
const BOM = '\uFEFF';

function cell(value) {
    if (value === null || value === undefined) return '';
    const text = String(value);
    return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

/**
 * @param  {Array<{label: string, value: (row: any) => unknown}>}  columns
 * @param  {Array<any>}  rows
 */
export function toCsv(columns, rows) {
    const lines = [columns.map((column) => cell(column.label)).join(',')];

    for (const row of rows) {
        lines.push(columns.map((column) => cell(column.value(row))).join(','));
    }

    return lines.join('\r\n');
}

export function downloadCsv(filename, columns, rows) {
    const blob = new Blob([BOM + toCsv(columns, rows)], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename.endsWith('.csv') ? filename : `${filename}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();

    // Safari cancels the download if the object URL dies in the same tick.
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/** Spreadsheets can only sum a number that carries no grouping separators. */
export function csvNumber(value, fraction = 2) {
    const number = Number(value);
    return Number.isFinite(number) ? number.toFixed(fraction) : '';
}
