/**
 * Comparison matrix renderer (EF-302 to EF-304).
 *
 * The data comes once from Livewire ($wire.payload()); pivot, filters and collapse are applied
 * here and the table body is rebuilt as one HTML string with short CSS classes (.mx in app.css),
 * which stays fast with tens of thousands of cells. All text is escaped. Clicks are delegated:
 * a cell opens the panel, a row name opens the detailed comparison, ▾ collapses a module.
 *
 * Only the rows in view (plus a margin) are in the DOM: two spacer rows stand for the others, so
 * scrolling and painting cost the same with 50 or 5 000 rows (ENF-06).
 *
 * A cell is [implementation id, status index, has deviation, rules, issues]; the status index
 * follows App\Enums\ImplementationStatus::cases().
 */
const STATUS_LABELS = ['Non évalué', 'Opérationnel', 'À améliorer', 'Défaillant', 'Absent'];
const STATUS_VALUES = ['not_assessed', 'operational', 'needs_improvement', 'defective', 'missing'];
const OVERSCAN = 20;

const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

// What is compared with the pivot: attached or not, status, presence of a deviation.
const signature = (cell) => (cell ? cell[1] * 2 + cell[2] : -1);

export default () => ({
    data: null,
    pivot: null,
    loading: false,
    collapsed: {},
    filters: { diffOnly: false, status: '', withDeviation: false, withIssue: false },
    visibleKeys: [],
    visibleRows: [],
    rowHeight: 30,
    stats: { rows: 0, differing: 0 },
    scheduled: false,

    async init() {
        await this.load();
        this.$watch('filters', () => this.render(), { deep: true });
        this.$watch('pivot', () => {
            this.$wire.setPivot(this.pivot);
            this.render();
        });
    },

    async load() {
        this.loading = true;
        const payload = await this.$wire.payload();
        this.data = payload;
        this.byKey = Object.fromEntries(payload.rows.map((row) => [row.key, row]));
        this.parents = new Set(payload.rows.map((row) => row.parent).filter(Boolean));
        this.pivot = payload.versions.some((version) => version.id === payload.pivot) ? payload.pivot : payload.versions[0]?.id ?? null;
        this.collapsed = {};
        this.loading = false;
        this.render();
    },

    cell(row, versionId) {
        return this.data.cells[row.key]?.[versionId] ?? null;
    },

    rowDiffers(row) {
        if (this.pivot === null) {
            return false;
        }
        const reference = signature(this.cell(row, this.pivot));

        return this.data.versions.some((version) => signature(this.cell(row, version.id)) !== reference);
    },

    rowMatches(row) {
        const cells = this.data.versions.map((version) => this.cell(row, version.id)).filter(Boolean);
        const { diffOnly, status, withDeviation, withIssue } = this.filters;
        const statusIndex = STATUS_VALUES.indexOf(status);

        return (!diffOnly || this.rowDiffers(row))
            && (!status || cells.some((cell) => cell[1] === statusIndex))
            && (!withDeviation || cells.some((cell) => cell[2] === 1))
            && (!withIssue || cells.some((cell) => cell[4] > 0));
    },

    /**
     * Matching rows plus their ancestors (for context), minus the descendants of collapsed modules.
     */
    computeVisible() {
        const filtering = Object.values(this.filters).some(Boolean);
        const keep = new Set();

        for (const row of this.data.rows) {
            if (!filtering || this.rowMatches(row)) {
                for (let current = row; current && !keep.has(current.key); current = this.byKey[current.parent]) {
                    keep.add(current.key);
                }
            }
        }

        return this.data.rows.filter((row) => {
            if (!keep.has(row.key)) {
                return false;
            }
            for (let parent = this.byKey[row.parent]; parent; parent = this.byKey[parent.parent]) {
                if (this.collapsed[parent.key]) {
                    return false;
                }
            }

            return true;
        });
    },

    /**
     * Applies filters and collapse, then paints the rows in view.
     */
    render() {
        if (!this.data) {
            return;
        }

        this.visibleRows = this.computeVisible();
        this.visibleKeys = this.visibleRows.map((row) => row.key);
        this.stats = { rows: this.visibleRows.length, differing: this.visibleRows.filter((row) => this.rowDiffers(row)).length };
        this.paint();
    },

    onScroll() {
        if (this.scheduled) {
            return;
        }
        this.scheduled = true;
        requestAnimationFrame(() => {
            this.scheduled = false;
            this.paint();
        });
    },

    /**
     * Writes the rows currently in view between two spacer rows.
     */
    paint() {
        const rows = this.visibleRows;
        const versions = this.data.versions;
        const scroller = this.$refs.scroller;
        const columns = versions.length + 1;

        if (rows.length === 0) {
            this.$refs.body.innerHTML = `<tr><td colspan="${columns}" class="e" style="padding:2.5rem 1rem">Aucun élément ne correspond à ces filtres.</td></tr>`;

            return;
        }

        const inView = Math.ceil(scroller.clientHeight / this.rowHeight);
        const first = Math.max(0, Math.floor(scroller.scrollTop / this.rowHeight) - OVERSCAN);
        const last = Math.min(rows.length, first + inView + OVERSCAN * 2);
        const parts = [];

        if (first > 0) {
            parts.push(`<tr class="sp" style="height:${first * this.rowHeight}px"><td colspan="${columns}"></td></tr>`);
        }

        for (let index = first; index < last; index++) {
            parts.push(this.rowHtml(rows[index], versions));
        }

        if (last < rows.length) {
            parts.push(`<tr class="sp" style="height:${(rows.length - last) * this.rowHeight}px"><td colspan="${columns}"></td></tr>`);
        }

        this.$refs.body.innerHTML = parts.join('');

        // The spacer heights rely on the row height: measure it once on a real row.
        const sample = this.$refs.body.querySelector('tr:not(.sp)');
        if (sample && sample.offsetHeight > 0 && sample.offsetHeight !== this.rowHeight) {
            this.rowHeight = sample.offsetHeight;
            this.paint();
        }
    },

    rowHtml(row, versions) {
        const reference = this.pivot !== null ? signature(this.cell(row, this.pivot)) : null;
        const key = escape(row.key);
        const toggle = this.parents.has(row.key)
            ? `<button type="button" data-toggle="${key}" class="t" aria-label="Déplier ou replier">${this.collapsed[row.key] ? '▸' : '▾'}</button>`
            : '<span class="t"></span>';

        let cells = '';
        for (const version of versions) {
            const cell = this.cell(row, version.id);
            const isPivot = version.id === this.pivot;
            const differs = reference !== null && !isPivot && signature(cell) !== reference;
            const css = (isPivot ? ' p' : '') + (differs ? ' x' : '');

            if (!cell) {
                cells += `<td class="e${css}" title="Non rattaché">—</td>`;
            } else {
                const marks = (cell[2] ? '≠ ' : '') + (cell[3] ? `${cell[3]}RM ` : '') + (cell[4] ? `${cell[4]}PB` : '');
                cells += `<td class="${css}"><button type="button" data-cell="${cell[0]}" class="c s${cell[1]}"><span>${STATUS_LABELS[cell[1]]}</span><span class="k">${marks}</span></button></td>`;
            }
        }

        return `<tr><th scope="row" class="r"><div style="padding-left:${row.depth * 1.1}rem">${toggle}<button type="button" data-compare="${key}" class="n${row.type === 'module' ? ' m' : ''}" title="Comparer cet élément entre les versions">${escape(row.name)}</button></div></th>${cells}</tr>`;
    },

    onClick(event) {
        const target = event.target.closest('[data-cell],[data-compare],[data-toggle]');

        if (!target) {
            return;
        }
        if (target.dataset.toggle) {
            this.collapsed[target.dataset.toggle] = !this.collapsed[target.dataset.toggle];
            this.render();
        } else if (target.dataset.cell) {
            this.$wire.openCell(Number(target.dataset.cell));
        } else if (target.dataset.compare) {
            this.$wire.compareRow(target.dataset.compare);
        }
    },

    collapseAll(collapse) {
        this.collapsed = {};
        if (collapse) {
            for (const row of this.data.rows) {
                if (row.type === 'module') {
                    this.collapsed[row.key] = true;
                }
            }
        }
        this.render();
    },

    /**
     * Link to the two-version comparison: the pivot against the first other version.
     */
    compareUrl() {
        const other = this.data?.versions.find((version) => version.id !== this.pivot);

        return other ? `/versions/compare?a=${this.pivot}&b=${other.id}` : '#';
    },

    exportExcel() {
        this.$wire.export(this.visibleKeys, this.pivot);
    },
});
