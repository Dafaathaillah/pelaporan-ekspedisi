export const paginateRows = (rows, requestedPage, pageSize = 10) => {
    const pages = Math.max(1, Math.ceil(rows.length / pageSize));
    const page = Math.max(1, Math.min(pages, requestedPage));
    const offset = (page - 1) * pageSize;
    return { page, pages, rows: rows.slice(offset, offset + pageSize), from: rows.length ? offset + 1 : 0, to: Math.min(rows.length, offset + pageSize) };
};
