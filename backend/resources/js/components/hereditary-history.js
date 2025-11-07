const normalizeBoolean = (value) => {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value === 1;
    }

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();

        if (normalized === '1') {
            return true;
        }

        if (['0', 'false', ''].includes(normalized)) {
            return false;
        }

        return ['true', 'si', 'sí', 'on', 'yes'].includes(normalized);
    }

    return false;
};

export default function hereditaryHistory({ conditions = {}, members = {}, initialState = {} } = {}) {
    return {
        conditions,
        members,
        state: {},
        init() {
            this.state = this.normalizeState(initialState);
        },
        normalizeState(initial) {
            const normalized = {};
            const conditionKeys = Object.keys(this.conditions || {});
            const memberKeys = Object.keys(this.members || {});

            conditionKeys.forEach((conditionKey) => {
                normalized[conditionKey] = {};

                memberKeys.forEach((memberKey) => {
                    const value = initial?.[conditionKey]?.[memberKey];
                    normalized[conditionKey][memberKey] = normalizeBoolean(value);
                });
            });

            return normalized;
        },
        toggle(conditionKey, memberKey, checked) {
            if (!this.state[conditionKey]) {
                this.state[conditionKey] = {};
            }

            this.state[conditionKey][memberKey] = Boolean(checked);
        },
        isChecked(conditionKey, memberKey) {
            return Boolean(this.state?.[conditionKey]?.[memberKey]);
        },
        inputName(conditionKey, memberKey) {
            return `antecedentes_familiares[${conditionKey}][${memberKey}]`;
        },
        checkboxId(conditionKey, memberKey) {
            return `antecedentes_familiares_${conditionKey}_${memberKey}`;
        },
    };
}
