/* North Mountain Media build: 20260727-visual-site-builder-v61 */
(() => {
  'use strict';
  const boot = window.NMM_MENU_MANAGER || { items: [] };
  const state = { items: structuredClone(boot.items || []) };
  const list = document.querySelector('[data-menu-list]');
  const json = document.querySelector('[data-menu-json]');
  const esc = (value) => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
  const uid = () => `new-${Math.random().toString(16).slice(2)}`;
  const saveJson = () => { if (json) json.value = JSON.stringify(state.items); };
  const render = () => {
    if (!list) return; list.replaceChildren();
    state.items.forEach((item, index) => {
      const row = document.createElement('li'); row.className = 'menu-item-card'; row.draggable = true; row.style.marginLeft = `${Math.min(4, Number(item.depth || 0)) * 28}px`; row.dataset.index = index;
      row.innerHTML = `<header><span class="menu-drag">⋮⋮</span><strong>${esc(item.label || 'Menu item')}</strong><small>${esc(item.item_type)}</small><button type="button" data-toggle>▾</button></header><div class="menu-item-details" hidden><label>Navigation label<input data-field="label" value="${esc(item.label || '')}"></label>${item.item_type === 'custom' ? `<label>URL<input data-field="url" value="${esc(item.url || '')}"></label>` : ''}<label>Open link<select data-field="target"><option value="_self" ${item.target !== '_blank' ? 'selected' : ''}>Same tab</option><option value="_blank" ${item.target === '_blank' ? 'selected' : ''}>New tab</option></select></label><label>CSS class<input data-field="css_class" value="${esc(item.css_class || '')}"></label><label>Description<textarea data-field="description">${esc(item.description || '')}</textarea></label><div class="menu-item-controls"><button type="button" data-indent>Indent</button><button type="button" data-outdent>Outdent</button><button type="button" data-up>Move up</button><button type="button" data-down>Move down</button><button type="button" data-remove>Remove</button></div></div>`;
      row.querySelector('[data-toggle]').addEventListener('click', () => { const details = row.querySelector('.menu-item-details'); details.hidden = !details.hidden; });
      row.querySelectorAll('[data-field]').forEach((field) => field.addEventListener('input', () => { item[field.dataset.field] = field.value; row.querySelector('strong').textContent = item.label; saveJson(); }));
      row.querySelector('[data-indent]').addEventListener('click', () => { if (index > 0) item.depth = Math.min(4, Number(item.depth || 0) + 1); render(); });
      row.querySelector('[data-outdent]').addEventListener('click', () => { item.depth = Math.max(0, Number(item.depth || 0) - 1); render(); });
      row.querySelector('[data-up]').addEventListener('click', () => { if (index < 1) return; [state.items[index - 1], state.items[index]] = [state.items[index], state.items[index - 1]]; render(); });
      row.querySelector('[data-down]').addEventListener('click', () => { if (index >= state.items.length - 1) return; [state.items[index + 1], state.items[index]] = [state.items[index], state.items[index + 1]]; render(); });
      row.querySelector('[data-remove]').addEventListener('click', () => { state.items.splice(index, 1); render(); });
      row.addEventListener('dragstart', (event) => event.dataTransfer.setData('text/plain', String(index)));
      row.addEventListener('dragover', (event) => event.preventDefault());
      row.addEventListener('drop', (event) => { event.preventDefault(); const from = Number(event.dataTransfer.getData('text/plain')); if (!Number.isInteger(from) || from === index) return; const [moved] = state.items.splice(from, 1); state.items.splice(index, 0, moved); render(); });
      list.append(row);
    }); saveJson();
  };
  document.querySelectorAll('[data-add-selected]').forEach((button) => button.addEventListener('click', () => {
    const panel = button.closest('.menu-source-panel'); panel.querySelectorAll('[data-menu-source]:checked').forEach((source) => { state.items.push({ id: uid(), depth: 0, item_type: source.dataset.itemType, label: source.dataset.label, page_id: Number(source.dataset.pageId || 0), module_key: source.dataset.moduleKey || '', url: '', target: '_self', css_class: '', description: '' }); source.checked = false; }); render();
  }));
  document.querySelector('[data-add-custom]')?.addEventListener('click', () => { const label = document.querySelector('[data-custom-label]'); const url = document.querySelector('[data-custom-url]'); if (!label.value.trim() || !url.value.trim()) return alert('Enter a link label and URL.'); state.items.push({ id: uid(), depth: 0, item_type: 'custom', label: label.value.trim(), url: url.value.trim(), page_id: 0, module_key: '', target: '_self', css_class: '', description: '' }); label.value = ''; url.value = ''; render(); });
  document.querySelector('[data-menu-selector]')?.addEventListener('change', (event) => { location.href = `admin.php?view=menus&menu=${encodeURIComponent(event.target.value)}`; });
  document.querySelector('[data-menu-form]')?.addEventListener('submit', saveJson);
  render();
})();
