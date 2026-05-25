(function () {
    'use strict';

    function visibleRows(table) {
        var tbody = table.querySelector('tbody');
        if (!tbody) {
            return [];
        }
        return Array.prototype.filter.call(tbody.querySelectorAll('tr'), function (tr) {
            return !tr.classList.contains('d-none') && tr.offsetParent !== null && !tr.classList.contains('sales-empty');
        });
    }

    function rowCells(tr) {
        return Array.prototype.map.call(tr.querySelectorAll('th, td'), function (cell) {
            return (cell.textContent || '').trim().replace(/\s+/g, ' ');
        });
    }

    function tableData(table) {
        var headers = [];
        var headRow = table.querySelector('thead tr');
        if (headRow) {
            headers = rowCells(headRow);
        }
        var rows = visibleRows(table).map(rowCells);
        return { headers: headers, rows: rows };
    }

    function toCsv(data) {
        var lines = [data.headers.join(',')];
        data.rows.forEach(function (row) {
            lines.push(row.map(function (c) {
                return '"' + String(c).replace(/"/g, '""') + '"';
            }).join(','));
        });
        return lines.join('\n');
    }

    function downloadCsv(filename, csv) {
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function openPrint(title, data) {
        var w = window.open('', '_blank');
        if (!w) {
            return;
        }
        var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + title + '</title>';
        html += '<style>body{font-family:system-ui,sans-serif;font-size:11px;margin:16px}';
        html += 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #334155;padding:5px 7px}';
        html += 'th{background:#1e293b;color:#fff}</style></head><body>';
        html += '<h1>' + title + '</h1><table><thead><tr>';
        data.headers.forEach(function (h) {
            html += '<th>' + h + '</th>';
        });
        html += '</tr></thead><tbody>';
        data.rows.forEach(function (row) {
            html += '<tr>';
            row.forEach(function (c) {
                html += '<td>' + c + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></body></html>';
        w.document.write(html);
        w.document.close();
        w.focus();
        w.print();
    }

    document.querySelectorAll('.erp-export-toolbar').forEach(function (bar) {
        var tableId = bar.getAttribute('data-erp-export-table');
        var base = bar.getAttribute('data-erp-export-base') || 'export';
        var table = document.getElementById(tableId);
        if (!table) {
            return;
        }
        var pdfBtn = bar.querySelector('.erp-export-pdf');
        var xlsBtn = bar.querySelector('.erp-export-excel');
        var prtBtn = bar.querySelector('.erp-export-print');
        var stamp = new Date().toISOString().slice(0, 10);

        if (pdfBtn) {
            pdfBtn.addEventListener('click', function () {
                openPrint(base, tableData(table));
            });
        }
        if (xlsBtn) {
            xlsBtn.addEventListener('click', function () {
                downloadCsv(base + '-' + stamp + '.csv', toCsv(tableData(table)));
            });
        }
        if (prtBtn) {
            prtBtn.addEventListener('click', function () {
                openPrint(base, tableData(table));
            });
        }
    });
})();
