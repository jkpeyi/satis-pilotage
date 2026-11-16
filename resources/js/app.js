import Sortable from 'sortablejs';

/**
 * x-sortable="{ group: 'modules' | 'features', parent: <module id or null> }"
 *
 * Drag and drop inside the catalog tree. The DOM change is reverted right away and the
 * move is sent to Livewire, which re-renders the tree from the server (single source of truth).
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.directive('sortable', (el, { expression }, { evaluate }) => {
        const options = evaluate(expression);

        Sortable.create(el, {
            group: options.group,
            draggable: '[data-sortable-item]',
            handle: '[data-drag-handle]',
            animation: 150,
            emptyInsertThreshold: 12,
            onEnd(event) {
                const { item, from, to, oldIndex, newIndex } = event;

                if (from === to && oldIndex === newIndex) {
                    return;
                }

                const reference = from === to && oldIndex > newIndex ? from.children[oldIndex + 1] : from.children[oldIndex];
                from.insertBefore(item, reference ?? null);

                const parent = to.dataset.parent ? Number(to.dataset.parent) : null;
                window.Alpine.evaluate(el, '$wire').move(options.group, Number(item.dataset.id), parent, event.newDraggableIndex);
            },
        });
    });
});
