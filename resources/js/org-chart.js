import { OrgChart } from 'd3-org-chart';

/** Escape user-supplied text before injecting it into node HTML. */
const esc = (value) =>
    String(value ?? '').replace(
        /[&<>"']/g,
        (c) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[c],
    );

function nodeCard(node) {
    const avatar = node.imageUrl
        ? `<img src="${esc(node.imageUrl)}" alt="" style="width:44px;height:44px;border-radius:9999px;object-fit:cover;flex:none;" />`
        : `<div style="width:44px;height:44px;border-radius:9999px;background:#271A3D;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex:none;">${esc(node.initials)}</div>`;

    const dept = node.department
        ? `<div style="margin-top:5px;"><span style="display:inline-block;padding:1px 8px;border-radius:9999px;background:#f3f4f6;font-size:11px;color:#4b5563;">${esc(node.department)}</span></div>`
        : '';

    return `
        <div style="height:100%;box-sizing:border-box;display:flex;gap:12px;align-items:center;padding:14px 16px;border:1px solid #e5e7eb;border-radius:16px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.06);">
            ${avatar}
            <div style="min-width:0;">
                <div style="font-weight:700;font-size:14px;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(node.name)}</div>
                <div style="font-size:12px;color:#6b7280;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(node.title) || '—'}</div>
                ${dept}
            </div>
        </div>`;
}

function build(container, nodes) {
    if (!container || !Array.isArray(nodes) || nodes.length === 0) {
        return null;
    }

    return new OrgChart()
        .container(container)
        .data(nodes)
        .nodeWidth(() => 250)
        .nodeHeight(() => 120)
        .childrenMargin(() => 50)
        .compactMarginBetween(() => 35)
        .siblingsMargin(() => 25)
        .nodeContent((d) => nodeCard(d.data))
        .render();
}

const register = (Alpine) => {
    Alpine.data('orgChart', (nodes) => ({
        chart: null,
        init() {
            // Wait a frame so the container has measurable width.
            requestAnimationFrame(() => {
                this.chart = build(this.$refs.canvas, nodes);
            });
        },
        fit() {
            this.chart?.fit();
        },
        expandAll() {
            this.chart?.expandAll().fit();
        },
        collapseAll() {
            this.chart?.collapseAll().fit();
        },
    }));
};

if (window.Alpine) {
    register(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => register(window.Alpine));
}
