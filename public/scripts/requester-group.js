/**
 * -------------------------------------------------------------------------
 * purchaserequest plugin for GLPI
 * Copyright (C) 2021-2026 by the purchaserequest Development Team.
 *
 * https://github.com/InfotelGLPI/purchaserequest
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of purchaserequest.
 *
 * purchaserequest is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * purchaserequest is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with purchaserequest. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

/*
 * Purchase request forms: reload the requester group dropdown when the requester changes.
 *
 * The group container carries data-purchaserequest-group="<requester field name>" and the
 * endpoint in data-url. The requester selector is a select2 widget, which triggers its
 * change event through jQuery only, hence the jQuery delegated listener.
 */

const csrfToken = () => {
    const meta = document.querySelector('meta[property="glpi:csrf_token"]');
    return meta !== null ? meta.getAttribute('content') : '';
};

/**
 * Replace the container content, running the scripts the new markup carries
 * (the select2 setup of the group dropdown): innerHTML never runs them.
 */
const replaceContent = (container, html) => {
    container.innerHTML = html;
    container.querySelectorAll('script').forEach((original) => {
        const script = document.createElement('script');
        Array.from(original.attributes).forEach((attribute) => {
            script.setAttribute(attribute.name, attribute.value);
        });
        script.textContent = original.textContent;
        original.replaceWith(script);
    });
};

const reloadGroups = async (container, users_id) => {
    const body = new URLSearchParams({users_id});
    const response = await fetch(container.dataset.url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Glpi-Csrf-Token': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body,
    });
    if (response.ok) {
        replaceContent(container, await response.text());
    }
};

$(document).on('change', 'select', (event) => {
    const select = event.target;
    const form = select.closest('form');
    if (form === null) {
        return;
    }
    const container = form.querySelector(`[data-purchaserequest-group="${CSS.escape(select.name)}"]`);
    if (container !== null) {
        reloadGroups(container, select.value);
    }
});
