(() => {
    'use strict';
    const root = document.querySelector('[data-page-builder]');
    if (!root) return;
    const form = root.querySelector('[data-builder-form]');
    const source = form.querySelector('[data-builder-source]');
    const editor = form.querySelector('[data-builder-editor]');
    const canvas = form.querySelector('[data-builder-canvas]');
    const status = root.querySelector('[data-builder-status]');
    const assets = [...document.querySelectorAll('[data-asset-id]')].map(n => ({id: Number(n.dataset.assetId), url: n.dataset.assetUrl, alt: n.dataset.assetAlt}));
    const labels = {hero: 'معرفی اصلی', text: 'متن', image: 'تصویر', split: 'تصویر و متن', services: 'خدمات واقعی', articles: 'مقالات منتشرشده', contact: 'اطلاعات تماس', cta: 'دعوت به اقدام', features: 'کارت‌های ویژگی', faq: 'پرسش و پاسخ', spacer: 'فاصله'};
    let blocks;
    try { blocks = JSON.parse(source.value); } catch { status.textContent = 'داده صفحه قابل خواندن نیست؛ برای جلوگیری از حذف اطلاعات ذخیره نکنید.'; return; }
    let history = [], future = [], dirty = false, busy = false, dragIndex = null;
    const clone = value => JSON.parse(JSON.stringify(value));
    function node(tag, text, className) {
        const n = document.createElement(tag);
        if (text !== undefined) n.textContent = text;
        if (className) n.className = className;
        return n;
    }
    function checkpoint() { history.push(clone(blocks)); if (history.length > 50) history.shift(); future = []; }
    function changed() { dirty = true; source.value = JSON.stringify(blocks); preview(); status.textContent = 'تغییرات ذخیره‌نشده دارید.'; root.querySelector('[data-builder-undo]').disabled = false; }
    function mutate(callback) { checkpoint(); callback(); changed(); render(); }
    function button(text, callback, disabled = false) {
        const b = node('button', text, 'btn btn-sm btn-outline-secondary'); b.type = 'button'; b.disabled = disabled; b.addEventListener('click', callback); return b;
    }
    function field(parent, label, value, callback, options = {}) {
        const wrapper = node('label', label, 'builder-field');
        const input = node(options.choices ? 'select' : options.multiline ? 'textarea' : 'input');
        input.className = 'form-control';
        if (options.choices) for (const [key, title] of options.choices) { const option = node('option', title); option.value = key; input.append(option); }
        else { if (!options.multiline) input.type = options.number ? 'number' : 'text'; if (options.max) input.maxLength = options.max; if (options.number) { input.min = '1'; input.max = '12'; } }
        input.value = value ?? '';
        input.addEventListener('focus', () => { input.dataset.before = JSON.stringify(blocks); });
        input.addEventListener('input', () => { callback(options.number ? Number(input.value) : input.value); changed(); });
        input.addEventListener('change', () => { if (input.dataset.before) { history.push(JSON.parse(input.dataset.before)); future = []; delete input.dataset.before; } });
        wrapper.append(input); parent.append(wrapper); return input;
    }
    function render() {
        editor.replaceChildren();
        blocks.forEach((block, index) => {
            const card = node('article', undefined, 'builder-block');
            const bar = node('div', undefined, 'builder-block-bar');
            const handle = button('↕ جابه‌جایی', () => {}); handle.draggable = true;
            handle.setAttribute('aria-label', `جابه‌جایی بلوک ${index + 1} با کشیدن؛ دکمه‌های بالا و پایین هم در دسترس هستند`);
            handle.addEventListener('dragstart', event => { dragIndex = index; event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', String(index)); });
            handle.addEventListener('dragend', () => { dragIndex = null; });
            card.addEventListener('dragover', e => { if (dragIndex !== null) e.preventDefault(); });
            card.addEventListener('drop', e => { e.preventDefault(); if (dragIndex === null || dragIndex === index) return; const from = dragIndex; dragIndex = null; mutate(() => blocks.splice(index, 0, blocks.splice(from, 1)[0])); });
            bar.append(node('h3', `${index + 1}. ${labels[block.type]}`, 'h5'), handle,
                button('بالا', () => mutate(() => { [blocks[index - 1], blocks[index]] = [blocks[index], blocks[index - 1]]; }), index === 0),
                button('پایین', () => mutate(() => { [blocks[index + 1], blocks[index]] = [blocks[index], blocks[index + 1]]; }), index === blocks.length - 1),
                button('تکثیر', () => mutate(() => { const copy = clone(block); copy.id = crypto.randomUUID(); delete copy.anchor; blocks.splice(index + 1, 0, copy); }), blocks.length >= 40),
                button('حذف', () => mutate(() => blocks.splice(index, 1)), blocks.length === 1));
            card.append(bar);
            const fields = node('div', undefined, 'builder-fields');
            const bind = (name, label, options = {}) => field(fields, label, block[name], value => { if (value === '' && ['anchor', 'asset_id', 'button_url', 'button_label'].includes(name)) delete block[name]; else block[name] = name === 'asset_id' ? Number(value) : value; }, options);
            if (block.type !== 'spacer') {
                bind('heading', 'عنوان بخش', {max: 180}); bind('eyebrow', 'متن کوتاه بالای عنوان', {max: 180}); bind('text', 'متن (بدون HTML)', {multiline: true, max: 4000});
                bind('button_label', 'متن دکمه', {max: 80}); bind('button_url', 'لینک داخلی دکمه، مانند /calendar', {max: 255});
                bind('layout', 'چیدمان', {choices: [['stacked', 'عمودی'], ['centered', 'وسط‌چین'], ['split-left', 'تصویر چپ'], ['split-right', 'تصویر راست'], ['grid', 'شبکه‌ای']]});
                if (['hero', 'image', 'split'].includes(block.type)) bind('asset_id', 'تصویر کتابخانه همین صفحه', {choices: [['', 'بدون تصویر'], ...assets.map(a => [String(a.id), `${a.id}: ${a.alt}`])]});
                if (['services', 'articles'].includes(block.type)) bind('count', 'تعداد نمایش (۱ تا ۱۲)', {number: true});
                if (['features', 'faq'].includes(block.type)) {
                    const list = node('div', undefined, 'builder-items');
                    (block.items || []).forEach((item, i) => {
                        const row = node('div', undefined, 'builder-item');
                        field(row, 'عنوان مورد', item.title, v => { item.title = v; }, {max: 180});
                        field(row, 'شرح مورد', item.text, v => { item.text = v; }, {multiline: true, max: 1000});
                        row.append(button('حذف مورد', () => mutate(() => block.items.splice(i, 1)))); list.append(row);
                    });
                    list.append(button('افزودن مورد', () => mutate(() => { (block.items ??= []).push({title: 'عنوان تازه', text: ''}); }), (block.items || []).length >= 12)); fields.append(list);
                }
            } else bind('height', 'ارتفاع فاصله', {choices: [['small', 'کم'], ['medium', 'متوسط'], ['large', 'زیاد']]});
            bind('background', 'زمینه', {choices: [['plain', 'ساده'], ['blush', 'صورتی روشن'], ['lilac', 'بنفش روشن']]});
            bind('anchor', 'شناسه لینک بخش (حروف کوچک لاتین)', {max: 40}); card.append(fields); editor.append(card);
        });
        source.value = JSON.stringify(blocks); preview();
        root.querySelector('[data-builder-undo]').disabled = !history.length;
        root.querySelector('[data-builder-redo]').disabled = !future.length;
    }
    function preview() {
        canvas.replaceChildren();
        blocks.forEach(block => {
            const section = node('section', undefined, `builder-preview-block builder-bg-${block.background} builder-layout-${block.layout}`);
            if (block.type === 'spacer') { section.append(node('p', `فاصله ${block.height || 'medium'}`)); }
            else {
                const copy = node('div'); if (block.eyebrow) copy.append(node('small', block.eyebrow));
                if (block.heading) copy.append(node('h3', block.heading)); if (block.text) copy.append(node('p', block.text));
                if (block.button_label) copy.append(node('span', block.button_label, 'builder-preview-button')); section.append(copy);
                const asset = assets.find(a => a.id === Number(block.asset_id));
                if (asset) { const img = node('img'); img.src = asset.url; img.alt = asset.alt; section.append(img); }
                if (['services', 'articles', 'contact'].includes(block.type)) section.append(node('p', `${labels[block.type]} — داده‌های واقعی در پیش‌نمایش ذخیره‌شده نمایش داده می‌شود.`));
                for (const item of block.items || []) { const row = node('div'); row.append(node('h4', item.title), node('p', item.text || '')); section.append(row); }
            }
            canvas.append(section);
        });
    }
    root.querySelector('[data-block-add]').addEventListener('click', () => {
        if (blocks.length >= 40) { status.textContent = 'حداکثر ۴۰ بلوک مجاز است.'; return; }
        const type = root.querySelector('[data-new-block-type]').value;
        mutate(() => blocks.push({id: crypto.randomUUID(), type, heading: labels[type], layout: 'stacked', background: 'plain', ...(['features', 'faq'].includes(type) ? {items: [{title: 'عنوان مورد', text: ''}]} : {})}));
    });
    root.querySelector('[data-builder-undo]').addEventListener('click', () => { if (!history.length) return; future.push(clone(blocks)); blocks = history.pop(); changed(); render(); });
    root.querySelector('[data-builder-redo]').addEventListener('click', () => { if (!future.length) return; history.push(clone(blocks)); blocks = future.pop(); changed(); render(); });
    root.querySelector('[data-canvas-size]').addEventListener('change', e => canvas.classList.toggle('builder-mobile', e.target.value === 'mobile'));
    form.querySelectorAll('[name=title], [name=meta_description]').forEach(n => n.addEventListener('input', () => { dirty = true; status.textContent = 'تغییرات ذخیره‌نشده دارید.'; }));
    async function send(url, method, data, success) {
        if (busy) return; busy = true;
        root.querySelectorAll('button').forEach(b => { b.dataset.wasDisabled = String(b.disabled); b.disabled = true; });
        try {
            const response = await fetch(url, {method, credentials: 'same-origin', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value}, body: JSON.stringify(data)});
            const body = await response.json();
            if (!response.ok) throw new Error(body.errors ? Object.values(body.errors).flat().join(' — ') : body.message || 'ذخیره ناموفق بود.');
            document.querySelectorAll('[name=expected_version]').forEach(n => { n.value = body.version; });
            success(); status.textContent = `نسخه ${body.version}؛ ${body.published_version ? `نسخه عمومی ${body.published_version}` : 'بدون انتشار عمومی'}`;
        } catch (error) { status.textContent = `خطا: ${error.message} تغییرات محلی شما حفظ شده‌اند.`; status.focus(); }
        finally { busy = false; root.querySelectorAll('button').forEach(b => { b.disabled = b.dataset.wasDisabled === 'true'; }); }
    }
    form.addEventListener('submit', e => { e.preventDefault(); send(form.action, 'PUT', {title: form.elements.title.value, meta_description: form.elements.meta_description.value, blocks, expected_version: Number(form.elements.expected_version.value)}, () => { dirty = false; }); });
    for (const [selector, url] of [['[data-page-publish]', root.dataset.publishUrl], ['[data-page-unpublish]', root.dataset.unpublishUrl]]) root.querySelector(selector).addEventListener('click', () => {
        if (dirty) { status.textContent = 'ابتدا تغییرات پیش‌نویس را ذخیره کنید.'; return; }
        if (!window.confirm(selector === '[data-page-publish]' ? 'نسخه ذخیره‌شده برای عموم منتشر شود؟' : 'انتشار عمومی این صفحه لغو شود؟')) return;
        send(url, 'POST', {expected_version: Number(form.elements.expected_version.value)}, () => {});
    });
    document.querySelectorAll('form:not([data-builder-form])').forEach(other => other.addEventListener('submit', e => { if (dirty || busy) { e.preventDefault(); status.textContent = 'پیش از بارگذاری، حذف یا بازگردانی، تغییرات پیش‌نویس را ذخیره کنید.'; } }));
    window.addEventListener('beforeunload', e => { if (dirty || busy) { e.preventDefault(); e.returnValue = ''; } });
    status.tabIndex = -1; render();
})();
