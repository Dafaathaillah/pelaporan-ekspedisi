export const unitP2hStage = (p2h, arrivedAt) => {
    if (arrivedAt) return 'ARRIVED';
    if (!p2h) return 'AWAITING_P2H';
    if (p2h.status === 'REJECTED') return 'NEEDS_REVISION';
    if (p2h.status === 'PENDING') return 'AWAITING_VENDOR';
    if (p2h.status === 'APPROVED') return p2h.departed_at ? 'IN_TRANSIT' : 'AWAITING_DEPARTURE';
    return 'AWAITING_P2H';
};
