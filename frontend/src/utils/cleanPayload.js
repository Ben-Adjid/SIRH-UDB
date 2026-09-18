const cleanPayload = (data, { stripNull = true, stripEmpty = true } = {}) => {
    const cleaned = { ...data };
    for (const [key, value] of Object.entries(cleaned)) {
        if (stripEmpty && (value === '' || value === undefined)) {
            delete cleaned[key];
        } else if (stripNull && value === null) {
            delete cleaned[key];
        }
    }
    return cleaned;
};

export default cleanPayload;
