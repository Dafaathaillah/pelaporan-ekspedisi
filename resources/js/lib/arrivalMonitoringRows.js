export const buildArrivalRows = items => items.flatMap(item => {
    const units = new Map((item.submission?.units || []).map(unit => [unit.id, unit]));
    const pending = (item.pending_units || []).map(unit => ({ key: `${item.id}-pending-${unit.id}`, item, unit, report: null, state: 'PENDING' }));
    const arrived = (item.arrived_units || []).map(report => ({ key: `${item.id}-arrived-${report.id}`, item, unit: units.get(report.vendor_submission_unit_id) || null, report, state: 'ARRIVED' }));
    return pending.length || arrived.length ? [...pending, ...arrived] : [{ key: `${item.id}-empty`, item, unit: null, report: null, state: 'NO_UNIT' }];
});

export const filterArrivalRows = (rows, search = '', onlyPending = true) => {
    const query = search.trim().toLocaleLowerCase('id-ID');
    return rows.filter(row => (!onlyPending || row.state === 'PENDING') && (!query || [row.item.submission?.vendor_name, row.item.submission?.submission_number, row.item.submission?.destination, row.unit?.plate_number, row.unit?.driver_name, row.unit?.driver_phone, row.report?.vehicle_plate, row.report?.driver_name, row.item.planned_date].join(' ').toLocaleLowerCase('id-ID').includes(query)));
};
