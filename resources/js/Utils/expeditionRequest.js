const vehicleLabels = {
    FUSO: "Fuso",
    COLT_DIESEL: "Colt Diesel",
    TRAILER: "Trailer",
    DUMP_TRUCK: "Dump Truck",
    PICKUP: "Pickup",
    LIGHT_VEHICLE: "Light Vehicle",
    LAINNYA: "Lainnya",
};

export const expeditionVehicleLabel = (value) =>
    vehicleLabels[value] ||
    String(value || "-")
        .replaceAll("_", " ")
        .toLocaleLowerCase("id-ID")
        .replace(/\b\w/g, (letter) => letter.toLocaleUpperCase("id-ID"));
