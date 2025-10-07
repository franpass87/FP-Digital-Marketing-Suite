/**
 * Overview Date Presets
 * Gestisce i preset di date e range personalizzati
 */
export class DatePresets {
    constructor(presetOptions) {
        this.presetOptions = presetOptions || [];
    }

    normalizePreset(value) {
        return this.presetOptions.includes(value) ? value : 'last7';
    }

    formatDate(date) {
        const pad = n => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    computePresetRange(preset) {
        const today = new Date();
        let from, to;

        switch (preset) {
            case 'last14':
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 13);
                break;
            
            case 'last28':
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 27);
                break;
            
            case 'last30':
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 29);
                break;
            
            case 'this_month':
                to = new Date(today);
                from = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            
            case 'last_month': {
                const firstDayCurrent = new Date(today.getFullYear(), today.getMonth(), 1);
                to = new Date(firstDayCurrent);
                to.setDate(0);
                from = new Date(firstDayCurrent);
                from.setMonth(from.getMonth() - 1);
                break;
            }
            
            case 'last7':
            default:
                to = new Date(today);
                from = new Date(today);
                from.setDate(from.getDate() - 6);
                break;
        }

        return {
            from: from ? this.formatDate(from) : '',
            to: to ? this.formatDate(to) : ''
        };
    }

    computeRange(preset, customFrom, customTo) {
        if (preset === 'custom') {
            const from = customFrom ? new Date(customFrom + 'T00:00:00') : null;
            const to = customTo ? new Date(customTo + 'T00:00:00') : null;

            return {
                from: from ? this.formatDate(from) : '',
                to: to ? this.formatDate(to) : ''
            };
        }

        return this.computePresetRange(preset);
    }
}