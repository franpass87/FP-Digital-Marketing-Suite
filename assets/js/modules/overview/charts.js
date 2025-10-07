/**
 * Overview Charts Renderer
 * Gestisce il rendering dei grafici SVG (sparklines)
 */
export class ChartsRenderer {
    constructor(i18n) {
        this.i18n = i18n || {};
    }

    renderSparkline(svg, values) {
        if (!svg) return;

        // Clear existing content
        svg.innerHTML = '';

        if (!Array.isArray(values) || values.length === 0) {
            this._renderNoData(svg);
            return;
        }

        const max = Math.max(...values);
        const min = Math.min(...values);
        const range = max - min || 1;
        const height = 40;
        const width = 100;

        const points = values.map((value, index) => {
            const x = values.length === 1 
                ? width 
                : (width / (values.length - 1)) * index;
            const normalized = (value - min) / range;
            const y = height - (normalized * 32 + 4);
            
            return { x, y };
        });

        const path = this._createPath(points);
        svg.appendChild(path);
    }

    _renderNoData(svg) {
        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        text.setAttribute('x', '4');
        text.setAttribute('y', '22');
        text.setAttribute('fill', '#9ca3af');
        text.textContent = this.i18n.sparklineFallback || 'No data';
        svg.appendChild(text);
    }

    _createPath(points) {
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        
        const d = points
            .map((point, index) => {
                const command = index === 0 ? 'M' : 'L';
                return `${command}${point.x.toFixed(2)} ${point.y.toFixed(2)}`;
            })
            .join(' ');

        path.setAttribute('d', d);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', '#2563eb');
        path.setAttribute('stroke-width', '2');

        return path;
    }

    renderKPISparklines(container, kpis) {
        if (!container || !Array.isArray(kpis)) return;

        container.querySelectorAll('.fpdms-kpi-card').forEach(card => {
            const metric = card.getAttribute('data-metric');
            const kpi = kpis.find(item => item.metric === metric);
            const svg = card.querySelector('svg');

            if (!svg) return;

            if (!kpi || !Array.isArray(kpi.sparkline)) {
                this.renderSparkline(svg, []);
            } else {
                this.renderSparkline(svg, kpi.sparkline);
            }
        });
    }

    renderTrendCharts(container, kpis) {
        if (!container || !Array.isArray(kpis)) return;

        const kpiIndex = Object.fromEntries(
            kpis.filter(k => k?.metric).map(k => [k.metric, k])
        );

        container.querySelectorAll('.fpdms-trend-card').forEach(card => {
            const metric = card.getAttribute('data-metric');
            const kpi = kpiIndex[metric];
            const svg = card.querySelector('svg');

            if (svg) {
                this.renderSparkline(
                    svg, 
                    kpi?.sparkline || []
                );
            }
        });
    }
}