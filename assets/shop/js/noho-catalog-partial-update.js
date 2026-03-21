/**
 * Replace only #noho-catalog-product-list via fetch (sort, price, rooms, travelers, dates).
 */
let nohoCatalogUpdateAbort = null;

const NOHO_PRICE_CRITERIA_KEY = 'criteria[noho_price][value]';

function nohoCriteriaValueKey(fieldName) {
    return 'criteria[' + fieldName + '][value]';
}

function nohoGetPriceBucketFromSearch(search) {
    const q = search.startsWith('?') ? search.slice(1) : search;
    const sp = new URLSearchParams(q);
    return sp.get(NOHO_PRICE_CRITERIA_KEY) || '';
}

function nohoGetCriteriaValueFromSearch(search, fieldName) {
    const q = search.startsWith('?') ? search.slice(1) : search;
    const sp = new URLSearchParams(q);
    return sp.get(nohoCriteriaValueKey(fieldName)) || '';
}

function nohoRemoveCriteriaFieldParams(sp, fieldName) {
    const prefix = 'criteria[' + fieldName + ']';
    const toDrop = [];
    sp.forEach(function (_, k) {
        if (k.indexOf(prefix) === 0) {
            toDrop.push(k);
        }
    });
    [...new Set(toDrop)].forEach(function (k) {
        sp.delete(k);
    });
}

function nohoRemovePriceCriteriaParams(sp) {
    const toDrop = [];
    sp.forEach(function (_, k) {
        if (k.indexOf('criteria[noho_price]') === 0) {
            toDrop.push(k);
        }
    });
    [...new Set(toDrop)].forEach(function (k) {
        sp.delete(k);
    });
}

function nohoCatalogUrlTogglePriceBucket(bucket) {
    const u = new URL(window.location.href);
    const current = nohoGetPriceBucketFromSearch(u.search);
    const sp = u.searchParams;
    nohoRemovePriceCriteriaParams(sp);

    const next = current === bucket ? '' : bucket;
    if (next !== '') {
        sp.set(NOHO_PRICE_CRITERIA_KEY, next);
    }
    sp.set('page', '1');
    return u.pathname + (sp.toString() ? '?' + sp.toString() : '');
}

/**
 * @param {string} fieldName  e.g. voyageurs, chambres
 * @param {string} value
 */
function nohoCatalogUrlToggleCriteriaField(fieldName, value) {
    const u = new URL(window.location.href);
    const current = nohoGetCriteriaValueFromSearch(u.search, fieldName);
    const sp = u.searchParams;
    nohoRemoveCriteriaFieldParams(sp, fieldName);

    const next = current === value ? '' : value;
    if (next !== '') {
        sp.set(nohoCriteriaValueKey(fieldName), next);
    }
    sp.set('page', '1');
    return u.pathname + (sp.toString() ? '?' + sp.toString() : '');
}

/**
 * @param {string} dateStart  YYYY-MM-DD or ''
 * @param {string} dateEnd
 */
function nohoCatalogUrlWithDateRange(dateStart, dateEnd) {
    const u = new URL(window.location.href);
    const sp = u.searchParams;
    if (dateStart) {
        sp.set('noho_date_start', dateStart);
    } else {
        sp.delete('noho_date_start');
    }
    if (dateEnd) {
        sp.set('noho_date_end', dateEnd);
    } else {
        sp.delete('noho_date_end');
    }
    sp.set('page', '1');
    return u.pathname + (sp.toString() ? '?' + sp.toString() : '');
}

function nohoSyncPriceBucketButtons() {
    const current = nohoGetPriceBucketFromSearch(window.location.search);
    document.querySelectorAll('[data-noho-price-bucket]').forEach(function (btn) {
        const b = btn.getAttribute('data-noho-price-bucket') || '';
        btn.classList.toggle('is-active', b !== '' && b === current);
    });
}

function nohoSyncVoyageursChambresButtons() {
    const v = nohoGetCriteriaValueFromSearch(window.location.search, 'voyageurs');
    const c = nohoGetCriteriaValueFromSearch(window.location.search, 'chambres');
    document.querySelectorAll('[data-noho-voyageurs]').forEach(function (btn) {
        const val = btn.getAttribute('data-noho-voyageurs') || '';
        btn.classList.toggle('is-active', val !== '' && val === v);
    });
    document.querySelectorAll('[data-noho-chambres]').forEach(function (btn) {
        const val = btn.getAttribute('data-noho-chambres') || '';
        btn.classList.toggle('is-active', val !== '' && val === c);
    });
}

function nohoSyncAllSidebarFilters() {
    nohoSyncPriceBucketButtons();
    nohoSyncVoyageursChambresButtons();
    if (window.NohoCatalog && typeof window.NohoCatalog.applyDatesFromUrl === 'function') {
        window.NohoCatalog.applyDatesFromUrl();
    }
}

function nohoRunCatalogPartialUpdate(url) {
    if (!url || typeof url !== 'string') {
        return;
    }

    const listRoot =
        document.getElementById('noho-catalog-product-list') ||
        document.querySelector('.noho-list-main');
    if (!listRoot) {
        window.location.assign(url);
        return;
    }

    if (nohoCatalogUpdateAbort) {
        nohoCatalogUpdateAbort.abort();
    }
    nohoCatalogUpdateAbort = new AbortController();

    listRoot.classList.add('noho-catalog-product-list--loading');

    fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        signal: nohoCatalogUpdateAbort.signal,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'text/html',
        },
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Bad response');
            }
            return response.text();
        })
        .then(function (html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const next =
                doc.getElementById('noho-catalog-product-list') ||
                doc.querySelector('.noho-list-main');
            if (!next || !listRoot.parentNode) {
                throw new Error('Missing list fragment');
            }
            listRoot.replaceWith(next);
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', url);
            }
            const titleEl = doc.querySelector('title');
            if (titleEl && titleEl.textContent) {
                document.title = titleEl.textContent.trim();
            }
            nohoSyncAllSidebarFilters();
        })
        .catch(function (err) {
            if (err && err.name === 'AbortError') {
                return;
            }
            window.location.assign(url);
        })
        .finally(function () {
            const el =
                document.getElementById('noho-catalog-product-list') ||
                document.querySelector('.noho-list-main');
            if (el) {
                el.classList.remove('noho-catalog-product-list--loading');
            }
        });
}

window.NohoCatalog = window.NohoCatalog || {};
window.NohoCatalog.partialUpdateFromDates = function (dateStart, dateEnd) {
    nohoRunCatalogPartialUpdate(nohoCatalogUrlWithDateRange(dateStart || '', dateEnd || ''));
};

document.addEventListener('change', function (e) {
    const sel = e.target;
    if (!sel || sel.id !== 'noho-catalog-sort') {
        return;
    }
    const opt = sel.selectedOptions && sel.selectedOptions[0];
    const url = opt && opt.getAttribute('data-url');
    if (!url) {
        return;
    }
    nohoRunCatalogPartialUpdate(url);
});

document.addEventListener('click', function (e) {
    const t = e.target;
    if (!t || !t.closest) {
        return;
    }
    const priceBtn = t.closest('[data-noho-price-bucket]');
    if (priceBtn) {
        e.preventDefault();
        e.stopPropagation();
        const bucket = priceBtn.getAttribute('data-noho-price-bucket');
        if (bucket !== null) {
            nohoRunCatalogPartialUpdate(nohoCatalogUrlTogglePriceBucket(bucket));
        }
        return;
    }
    const vBtn = t.closest('[data-noho-voyageurs]');
    if (vBtn) {
        e.preventDefault();
        e.stopPropagation();
        const val = vBtn.getAttribute('data-noho-voyageurs');
        if (val !== null) {
            nohoRunCatalogPartialUpdate(nohoCatalogUrlToggleCriteriaField('voyageurs', val));
        }
        return;
    }
    const cBtn = t.closest('[data-noho-chambres]');
    if (cBtn) {
        e.preventDefault();
        e.stopPropagation();
        const val = cBtn.getAttribute('data-noho-chambres');
        if (val !== null) {
            nohoRunCatalogPartialUpdate(nohoCatalogUrlToggleCriteriaField('chambres', val));
        }
    }
});

document.addEventListener('noho:catalog:update', function (event) {
    const url = event.detail && event.detail.url;
    nohoRunCatalogPartialUpdate(url);
});

function nohoScheduleInitialSidebarSync() {
    window.setTimeout(nohoSyncAllSidebarFilters, 10);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', nohoScheduleInitialSidebarSync);
} else {
    nohoScheduleInitialSidebarSync();
}
