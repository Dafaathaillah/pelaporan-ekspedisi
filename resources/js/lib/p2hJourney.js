export const journeyDuration = (startedAt, arrivedAt, nowMs = Date.now()) => {
    if (!startedAt) return null;
    const start = new Date(startedAt).getTime();
    const end = arrivedAt ? new Date(arrivedAt).getTime() : nowMs;
    if (!Number.isFinite(start) || !Number.isFinite(end) || end < start) return null;
    const seconds = Math.floor((end - start) / 1000);
    return [Math.floor(seconds / 3600), Math.floor(seconds % 3600 / 60), seconds % 60]
        .map(value => String(value).padStart(2, '0')).join(':');
};

export const journeyTime = value => {
    if (!value || !Number.isFinite(new Date(value).getTime())) return '-';
    return `${new Date(value).toLocaleString('id-ID', {
        timeZone: 'Asia/Makassar', dateStyle: 'medium', timeStyle: 'medium',
    })} WITA`;
};
