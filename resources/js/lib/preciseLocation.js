export const MAX_PHOTO_LOCATION_ACCURACY = 5;

export const getPreciseLocation = ({ timeout = 45000 } = {}) =>
    new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error("GPS tidak didukung browser ini."));
            return;
        }

        let bestPosition = null;
        const finishAt = window.setTimeout(() => {
            navigator.geolocation.clearWatch(watchId);
            const error = new Error(
                bestPosition
                    ? `Akurasi GPS terbaik masih ±${Math.round(bestPosition.coords.accuracy)} meter. Diperlukan maksimal ±${MAX_PHOTO_LOCATION_ACCURACY} meter.`
                    : "Koordinat GPS belum diperoleh.",
            );
            error.code = "ACCURACY_LOW";
            reject(error);
        }, timeout);

        const watchId = navigator.geolocation.watchPosition(
            (position) => {
                if (
                    !bestPosition ||
                    position.coords.accuracy < bestPosition.coords.accuracy
                ) {
                    bestPosition = position;
                }

                if (position.coords.accuracy <= MAX_PHOTO_LOCATION_ACCURACY) {
                    window.clearTimeout(finishAt);
                    navigator.geolocation.clearWatch(watchId);
                    resolve(position);
                }
            },
            (error) => {
                window.clearTimeout(finishAt);
                navigator.geolocation.clearWatch(watchId);
                reject(error);
            },
            { enableHighAccuracy: true, timeout, maximumAge: 0 },
        );
    });
