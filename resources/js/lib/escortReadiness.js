export const remainingEscortSeconds = (readyAt, nowMs = Date.now()) => {
    if (!readyAt) return null;

    return Math.max(
        0,
        Math.ceil((new Date(readyAt).getTime() - nowMs) / 1000),
    );
};

export const formatEscortCountdown = (seconds) => {
    const value = Math.max(0, seconds || 0);
    const hours = Math.floor(value / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    const secs = value % 60;

    return [hours, minutes, secs]
        .map((part) => String(part).padStart(2, "0"))
        .join(":");
};

export const isEscortReady = (item, nowMs = Date.now()) =>
    Boolean(
        item.internal_ijin_lintas_id ||
            item.internal_ijin_lintas ||
            item.is_ready_for_escort ||
            remainingEscortSeconds(item.ready_escort_at, nowMs) === 0,
    );
