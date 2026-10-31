import { unitP2hStage } from './p2hFollowUp.js';

export const buildFollowUpRows = permits => permits.flatMap(permit => {
    const units = permit.units?.length ? permit.units : [null];
    return units.map(unit => ({ key: `${permit.id}-${unit?.id || 'empty'}`, permit, unit, stage: unit ? unitP2hStage(unit.p2h, unit.arrived_at) : 'NO_UNIT' }));
});

export const filterFollowUpRows = (rows, search = '', stage = '') => {
    const query = search.trim().toLocaleLowerCase('id-ID');
    return rows.filter(row => (!stage || row.stage === stage) && (!query || [row.permit.vendor_name, row.permit.submission_number, row.unit?.plate_number, row.unit?.driver_name, ...(row.permit.expedition_accounts || []).map(account => account.name)].join(' ').toLocaleLowerCase('id-ID').includes(query)));
};

export { paginateRows as followUpPage } from './pagination.js';
