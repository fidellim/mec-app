/* Resize the plotting coordinates, never the chart's typography or geometry. */
(() => {
    'use strict';
    const observers = new Map();
    function initialize() {
        for (const [container, observer] of observers) {
            if (!container.isConnected) { observer.disconnect(); observers.delete(container); }
        }
        document.querySelectorAll('[data-chart-months]').forEach(container => {
            if (observers.has(container)) return;
            const svg = container.querySelector('svg');
            const count = Number(container.dataset.chartMonths);
            const layout = () => {
                const width = Math.max(280, count * 80 + 110, Math.floor(container.clientWidth));
                const x = index => count === 1 ? (width + 45) / 2 : 90 + index * (width - 140) / Math.max(1, count - 1);
                svg.style.width = `${width}px`;
                svg.setAttribute('width', width);
                svg.setAttribute('viewBox', `0 0 ${width} 260`);
                svg.querySelectorAll('[data-chart-rule]').forEach(line => line.setAttribute('x2', width - 20));
                svg.querySelectorAll('[data-chart-index]').forEach(mark => {
                    const position = x(Number(mark.dataset.chartIndex));
                    mark.setAttribute(mark.tagName === 'circle' ? 'cx' : 'x', mark.tagName === 'rect' ? position - 18 : position);
                });
                svg.querySelectorAll('polyline').forEach(line => {
                    const points = [...line.points].map((point, index) => `${x(index)},${point.y}`);
                    line.setAttribute('points', points.join(' '));
                });
            };
            layout();
            const observer = new ResizeObserver(layout);
            observer.observe(container);
            observers.set(container, observer);
        });
    }
    initialize();
    document.addEventListener('project-analytics:updated', initialize);
})();
