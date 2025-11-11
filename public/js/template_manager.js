/**
 * Reusable utility for managing Twig <template> fragments.
 * Works with standard DOM, no dependencies.
 */
const TemplateManager = {
    /**
     * Clone and hydrate a <template> with data.
     *
     * @param {string} id - The ID of the <template> element (e.g. "#status-pill-template").
     * @param {object} data - Key (WITHOUT "data-"" before key name, which is added bellow) / value pairs to replace inside the cloned element.
     * @returns {HTMLElement|null}
     */
    clone(id, data = {}) {
        const template = document.querySelector(id);
        if (!template) {
            console.warn(`[TemplateManager] Template ${id} not found`);
            return null;
        }

        const clone = template.content.firstElementChild.cloneNode(true);

        // Fill text placeholders or attributes dynamically
        Object.entries(data).forEach(([key, value]) => {
            // Try to replace attributes like data-status, title, etc.
            if (clone.hasAttribute(`data-${key}`)) {
                clone.setAttribute(`data-${key}`, value);
            }
            if (clone.hasAttribute(key)) {
                clone.setAttribute(key, value);
            }

            // Replace any text content placeholders
            const textNodes = Array.from(clone.childNodes).filter(n => n.nodeType === Node.TEXT_NODE);
            textNodes.forEach(node => {
                if (node.textContent.trim() === '' || node.textContent === `{{ ${key} }}`) {
                    node.textContent = value;
                }
            });

            // Handle inline styles like background-color
            if (key === 'color' || key.endsWith('Color')) {
                clone.style.backgroundColor = value;
            }
        });

        return clone;
    },

    /**
     * Replace the contents of a container with rendered templates.
     *
     * @param {string|HTMLElement} container - Target selector or element
     * @param {string} templateId - Template ID (e.g. "#status-pill-template")
     * @param {Array<object>} items - Data array
     */
    renderList(container, templateId, items = []) {
        const el = typeof container === 'string' ? document.querySelector(container) : container;
        if (!el) return;

        el.innerHTML = ''; // clear container

        if (!items.length) {
            el.innerHTML = `<span class="text-muted">Aucun élément</span>`;
            return;
        }

        items.forEach(item => {
            const element = this.clone(templateId, item);
            if (element) el.appendChild(element);
        });
    },
};
