document.addEventListener('DOMContentLoaded', function () {
    const tables = document.querySelectorAll('table');
    tables.forEach((table) => {
        table.classList.add('table-hover');
    });
});

