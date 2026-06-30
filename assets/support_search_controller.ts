import { Controller } from '@hotwired/stimulus';

function debounce<T extends (...args: any[]) => void>(fn: T, wait: number) {
  let t: number | undefined;
  return (...args: Parameters<T>) => {
    window.clearTimeout(t);
    // @ts-ignore
    t = window.setTimeout(() => fn(...args), wait);
  };
}

export default class extends Controller<HTMLDivElement> {
  static values = {
    endpoint: String,
    associateEndpoint: String,
    associateBatch: String,
    holdingsEndpoint: String,
    updateEndpoint: String,
    userProductUrl: String,
    csrf: String,
    refreshInterval: Number,
  };
  static targets = [
    'q',
    'tab',
    'tbody',
    'status',
    'product',
    'prev',
    'next',
    'selectAll',
    'addButton',
    'selectionPanel',
    'selectionBody',
    'associateButton',
    'initialDisplay',
    'remainingDisplay',
  ];

  declare endpointValue: string;
  declare qTarget: HTMLInputElement;
  declare tabTargets: HTMLButtonElement[];
  declare tbodyTarget: HTMLTableSectionElement;
  declare statusTarget: HTMLElement;
  declare productTarget: HTMLSelectElement;
  declare associateEndpointValue: string;
  declare associateBatchValue: string;
  declare userProductUrlValue: string;
  declare csrfValue: string;
  declare refreshIntervalValue?: number;

  private currentExchange: string = '';
  private page: number = 1;
  private limit: number = 20;
  private refreshTimer: number | undefined;
  private currentAbort: AbortController | null = null;
  private lastSignature: string = '';
  private initialAmount: number = 0;
  private search = debounce(async () => {
    const q = this.qTarget.value.trim();
    // Éviter les appels inutiles: signature des paramètres
    const sig = JSON.stringify({ q: q || '', ex: this.currentExchange || '', page: this.page, limit: this.limit });
    if (sig === this.lastSignature) {
      return;
    }
    this.lastSignature = sig;
    // Annuler le fetch précédent si encore en vol
    try { this.currentAbort?.abort(); } catch {}
    this.currentAbort = new AbortController();
    const signal = this.currentAbort.signal;
    if (!q) {
      // q vide: on affiche la curation
      try {
        this.statusTarget.textContent = 'Chargement…';
        const url = new URL(this.endpointValue, window.location.origin);
        if (this.currentExchange) url.searchParams.set('exchange', this.currentExchange);
        url.searchParams.set('page', String(this.page));
        url.searchParams.set('limit', String(this.limit));
        const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal });
        const json = await res.json();
        this.renderRows(json.data || []);
        const meta = json.meta || { total: (json.data || []).length, page: this.page, limit: this.limit };
        this.statusTarget.textContent = `${(json.data || []).length} / ${meta.total} — page ${meta.page}`;
      } catch (e) {
        if ((e as any)?.name === 'AbortError') return;
        this.statusTarget.textContent = 'Erreur de chargement';
      }
      return;
    }
    try {
      this.statusTarget.textContent = 'Recherche…';
      const url = new URL(this.endpointValue, window.location.origin);
      url.searchParams.set('q', q);
      if (this.currentExchange) url.searchParams.set('exchange', this.currentExchange);
      url.searchParams.set('page', String(this.page));
      url.searchParams.set('limit', String(this.limit));
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      this.renderRows(json.data || []);
      const meta = json.meta || { total: (json.data || []).length, page: this.page, limit: this.limit };
      this.statusTarget.textContent = `${(json.data || []).length} / ${meta.total} — page ${meta.page}`;
    } catch (e) {
      if ((e as any)?.name === 'AbortError') return;
      this.statusTarget.textContent = 'Erreur ou rate-limit. Réessayez.';
    }
  }, 300);

  connect() {
    this.tabTargets.forEach(btn => {
      btn.addEventListener('click', () => {
        this.currentExchange = btn.dataset.exchange || '';
        this.highlightTabs();
        this.search();
      });
    });
    this.highlightTabs();
    this.qTarget.addEventListener('input', () => this.search());
    // Initial/remaining when product changes
    this.productTarget.addEventListener('change', () => {
      const opt = this.productTarget.selectedOptions[0];
      const initStr = opt?.dataset.initial || '0';
      this.initialAmount = parseFloat(String(initStr).replace(',', '.')) || 0;
      this.updateBudgetSummary();
      const pid = this.productTarget.value;
      if (pid) { this.loadExistingHoldings(pid); }
    });
    // Bootstrap initial amount from preselected option if any
    (() => {
      const opt = this.productTarget.selectedOptions[0];
      if (opt) {
        const initStr = opt?.dataset.initial || '0';
        this.initialAmount = parseFloat(String(initStr).replace(',', '.')) || 0;
        this.updateBudgetSummary();
        const pid = this.productTarget.value;
        if (pid) { this.loadExistingHoldings(pid); }
      }
    })();
    // Lancer une première recherche (q vide => liste curatée)
    this.search();

    // Auto-refresh toutes les 60s
    const intervalMs = ((this as any).hasRefreshIntervalValue ? (this.refreshIntervalValue as number) : 60) * 1000;
    this.refreshTimer = window.setInterval(() => {
      // Ne relance que si aucun fetch en cours
      this.search();
    }, intervalMs);

    // Pagination controls
    const prevBtn = (this as any).prevTarget as HTMLButtonElement | undefined;
    const nextBtn = (this as any).nextTarget as HTMLButtonElement | undefined;
    prevBtn && prevBtn.addEventListener('click', () => {
      if (this.page > 1) { this.page--; this.search(); }
    });
    nextBtn && nextBtn.addEventListener('click', () => {
      this.page++; this.search();
    });
    // Select limit supprimé: on fige à 20
    this.limit = 20;

    // Ajouter à la sélection
    const addBtn = (this as any).addButtonTarget as HTMLButtonElement | undefined;
    const panel = (this as any).selectionPanelTarget as HTMLDivElement | undefined;
    const body = (this as any).selectionBodyTarget as HTMLTableSectionElement | undefined;
    const assocBtn = (this as any).associateButtonTarget as HTMLButtonElement | undefined;

    addBtn && addBtn.addEventListener('click', () => {
      const checked = Array.from(this.tbodyTarget.querySelectorAll('input[type="checkbox"]')) as HTMLInputElement[];
      const selection = checked.filter(cb => cb.checked);
      if (!selection.length || !panel || !body) return;
      panel.style.display = '';
      // Construire les lignes sélectionnées
      body.innerHTML = selection.map(cb => {
        const price = cb.dataset.price ? Number(cb.dataset.price) : 0;
        const priceDisp = price ? price.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
        const date = cb.dataset.date || '';
        const isin = cb.dataset.isin || '';
        const name = cb.dataset.name || cb.dataset.symbol || '';
        const ex = cb.dataset.exchange || '';
        const ccy = cb.dataset.ccy || '';
        return `
          <tr>
            <td class="px-4 py-2 text-sm" data-label="ISIN">${isin}</td>
            <td class="px-4 py-2 text-sm" data-label="Libellé">${name}</td>
            <td class="px-4 py-2 text-sm" data-label="Cours" data-symbol="${cb.dataset.symbol}" data-exchange="${ex}" data-ccy="${ccy}"><input type="number" step="0.000001" value="${price || ''}" class="w-full lg:w-28 px-2 py-1 border border-gray-300 rounded price-input"></td>
            <td class="px-4 py-2 text-sm" data-label="Date cours"><input type="date" value="${date ? date.substring(0,10) : ''}" class="w-full lg:w-36 px-2 py-1 border border-gray-300 rounded date-input"></td>
            <td class="px-4 py-2 text-sm" data-label="Nb parts"><input type="number" step="0.000001" value="" placeholder="0" class="w-full lg:w-28 px-2 py-1 border border-gray-300 rounded units-input"></td>
            <td class="px-4 py-2 text-sm font-semibold amount-cell" data-label="Montant">${price ? priceDisp : ''}</td>
            <td class="px-4 py-2 text-sm font-semibold weight-cell" data-label="Poids (%)">0%</td>
          </tr>`;
      }).join('');

      // Recalcul montant = nb parts * prix et poids
      body.querySelectorAll('tr').forEach((tr) => {
        const priceInput = tr.querySelector('.price-input') as HTMLInputElement;
        const unitsInput = tr.querySelector('.units-input') as HTMLInputElement;
        const amountCell = tr.querySelector('.amount-cell') as HTMLTableCellElement;
        const weightCell = tr.querySelector('.weight-cell') as HTMLTableCellElement;
        const update = () => {
          const p = parseFloat(priceInput.value || '0');
          const u = parseFloat(unitsInput.value || '0');
          const amt = p * u;
          amountCell.textContent = isFinite(amt) ? amt.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
          
          // Calculer le poids en %
          const total = this.sumSelectionAmounts();
          const weight = total > 0 ? (amt / total) * 100 : 0;
          weightCell.textContent = isFinite(weight) ? weight.toFixed(2) + '%' : '0%';
          
          this.updateBudgetSummary();
          
          // Mettre à jour tous les poids
          body.querySelectorAll('tr').forEach((row) => {
            const rowAmountCell = row.querySelector('.amount-cell') as HTMLTableCellElement;
            const rowWeightCell = row.querySelector('.weight-cell') as HTMLTableCellElement;
            const rowAmount = parseFloat(rowAmountCell.textContent?.replace(/[^0-9,-]/g, '').replace(',', '.') || '0');
            const rowWeight = total > 0 ? (rowAmount / total) * 100 : 0;
            rowWeightCell.textContent = isFinite(rowWeight) ? rowWeight.toFixed(2) + '%' : '0%';
          });
        };
        priceInput.addEventListener('input', update);
        unitsInput.addEventListener('input', update);
      });
      // Update summary and scroll into view
      this.updateBudgetSummary();
      try { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch {}
    });

    // Associer (batch)
    assocBtn && assocBtn.addEventListener('click', async () => {
      if (!panel || !body) return;
      const productId = this.productTarget.value;
      if (!productId) { alert('Sélectionnez un produit.'); return; }
      const rows = Array.from(body.querySelectorAll('tr')) as HTMLTableRowElement[];
      if (rows.length === 0) { alert('Aucune ligne à associer.'); return; }
      const items = rows.map(tr => {
        const tds = tr.querySelectorAll('td');
        const isin = tds[0]?.textContent?.trim() || '';
        const name = tds[1]?.textContent?.trim() || '';
        const price = parseFloat((tr.querySelector('.price-input') as HTMLInputElement)?.value || '0');
        const date = (tr.querySelector('.date-input') as HTMLInputElement)?.value || '';
        const units = parseFloat((tr.querySelector('.units-input') as HTMLInputElement)?.value || '0');
        const symbolCell = (tr.querySelector('[data-symbol]') as HTMLElement);
        const symbol = symbolCell?.dataset.symbol || '';
        const exchange = symbolCell?.dataset.exchange || this.currentExchange || '';
        const currency = symbolCell?.dataset.ccy || 'EUR';
        return { isin, name, price, date, units, symbol, exchange, currency };
      });
      const form = new FormData();
      form.set('_token', this.csrfValue);
      form.set('product', productId);
      items.forEach((it, i) => {
        Object.entries(it).forEach(([k,v]) => form.append(`items[${i}][${k}]`, String(v ?? '')));
      });
      try {
        const endpoint = (this as any).hasUpdateEndpointValue ? (this as any).updateEndpointValue : this.associateBatchValue;
        const res = await fetch(endpoint, { method: 'POST', body: form, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        if (json.success) {
          const url = (this.userProductUrlValue || '/user/produit/__ID__').replace('__ID__', productId);
          this.statusTarget.innerHTML = `Supports associés avec succès. <a href="${url}" target="_blank" class="text-indigo-600 underline">Voir le produit</a>`;
          try { window.open(url, '_blank'); } catch {}
        } else {
          alert(json.error || 'Erreur lors de l\'association.');
        }
      } catch (e) {
        alert('Erreur réseau lors de l\'association.');
      }
    });
  }

  private sumSelectionAmounts(): number {
    const body = (this as any).selectionBodyTarget as HTMLTableSectionElement | undefined;
    if (!body) return 0;
    let total = 0;
    body.querySelectorAll('tr').forEach((tr) => {
      const priceInput = tr.querySelector('.price-input') as HTMLInputElement | null;
      const unitsInput = tr.querySelector('.units-input') as HTMLInputElement | null;
      const p = parseFloat(priceInput?.value || '0');
      const u = parseFloat(unitsInput?.value || '0');
      const amt = p * u;
      if (isFinite(amt)) total += amt;
    });
    return total;
  }

  private updateBudgetSummary() {
    const initialEl = (this as any).initialDisplayTarget as HTMLElement | undefined;
    const remainingEl = (this as any).remainingDisplayTarget as HTMLElement | undefined;
    if (!initialEl || !remainingEl) return;
    const total = this.sumSelectionAmounts();
    const remaining = this.initialAmount - total;
    initialEl.textContent = this.initialAmount.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
    remainingEl.textContent = remaining.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
    remainingEl.className = 'text-lg font-semibold ' + (remaining < 0 ? 'text-red-600' : 'text-gray-900');
  }
  private async loadExistingHoldings(productId: string | number) {
    const panel = (this as any).selectionPanelTarget as HTMLDivElement | undefined;
    const body = (this as any).selectionBodyTarget as HTMLTableSectionElement | undefined;
    if (!panel || !body) return;
    try {
      const url = new URL((this as any).holdingsEndpointValue as string, window.location.origin);
      url.searchParams.set('product', String(productId));
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      const items = Array.isArray(json?.items) ? json.items : [];
      if (items.length === 0) return;
      panel.style.display = '';
      body.innerHTML = items.map((it: any) => {
        const price = Number(it.price || 0);
        const units = Number(it.units || 0);
        const amount = price * units;
        const priceDisp = Number.isFinite(price) && price > 0
          ? price.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 })
          : '';
        return `
          <tr>
            <td class="px-4 py-2 text-sm" data-label="ISIN">${it.isin || ''}</td>
            <td class="px-4 py-2 text-sm" data-label="Libellé">${it.name || ''}</td>
            <td class="px-4 py-2 text-sm" data-label="Cours" data-symbol="${it.symbol || ''}" data-exchange="${it.exchange || ''}" data-ccy="${it.currency || 'EUR'}">
              <input type="number" step="0.000001" value="${Number.isFinite(price) ? price : ''}" class="w-full lg:w-28 px-2 py-1 border border-gray-300 rounded price-input">
            </td>
            <td class="px-4 py-2 text-sm" data-label="Date cours"><input type="date" value="${(it.date || '').substring(0,10)}" class="w-full lg:w-36 px-2 py-1 border border-gray-300 rounded date-input"></td>
            <td class="px-4 py-2 text-sm" data-label="Nb parts"><input type="number" step="0.000001" value="${Number.isFinite(units) ? units : ''}" class="w-full lg:w-28 px-2 py-1 border border-gray-300 rounded units-input"></td>
            <td class="px-4 py-2 text-sm font-semibold amount-cell" data-label="Montant">${Number.isFinite(amount) && amount > 0 ? amount.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 }) : ''}</td>
            <td class="px-4 py-2 text-sm font-semibold weight-cell" data-label="Poids (%)">0%</td>
          </tr>
        `;
      }).join('');
      // binder
      body.querySelectorAll('tr').forEach((tr) => {
        const priceInput = tr.querySelector('.price-input') as HTMLInputElement;
        const unitsInput = tr.querySelector('.units-input') as HTMLInputElement;
        const amountCell = tr.querySelector('.amount-cell') as HTMLTableCellElement;
        const weightCell = tr.querySelector('.weight-cell') as HTMLTableCellElement;
        const update = () => {
          const p = parseFloat(priceInput.value || '0');
          const u = parseFloat(unitsInput.value || '0');
          const amt = p * u;
          amountCell.textContent = isFinite(amt) ? amt.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
          const total = this.sumSelectionAmounts();
          const weight = total > 0 ? (amt / total) * 100 : 0;
          weightCell.textContent = isFinite(weight) ? weight.toFixed(2) + '%' : '0%';
          this.updateBudgetSummary();
          // refresh all weights
          body.querySelectorAll('tr').forEach((row) => {
            const rowAmountCell = row.querySelector('.amount-cell') as HTMLTableCellElement;
            const rowWeightCell = row.querySelector('.weight-cell') as HTMLTableCellElement;
            const rowAmount = parseFloat(rowAmountCell.textContent?.replace(/[^0-9,-]/g, '').replace(',', '.') || '0');
            const rowWeight = total > 0 ? (rowAmount / total) * 100 : 0;
            rowWeightCell.textContent = isFinite(rowWeight) ? rowWeight.toFixed(2) + '%' : '0%';
          });
        };
        priceInput.addEventListener('input', update);
        unitsInput.addEventListener('input', update);
      });
      this.updateBudgetSummary();
      try { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch {}
    } catch {}
  }
  disconnect() {
    // Nettoyage pour éviter les timers multiples au retour/visite
    try { if (this.refreshTimer) window.clearInterval(this.refreshTimer); } catch {}
    try { this.currentAbort?.abort(); } catch {}
  }

  private highlightTabs() {
    this.tabTargets.forEach(btn => {
      const active = (btn.dataset.exchange === this.currentExchange);
      btn.className = `px-3 py-2 text-sm ${active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700'}`;
    });
  }

  private renderRows(rows: any[]) {
    const html = rows.map(r => {
      const symbol = r.symbol || '';
      const name = r.name || '';
      const isin = r.isin || '';
      const type = r.type || '';
      const ex = r.exchange || '';
      const ccy = r.currency || '';
      const nav = r.nav || '';
      const navDate = r.navDate || '';
      const payload = encodeURIComponent(JSON.stringify(r));
      const navNum = typeof nav === 'number' ? nav : parseFloat(String(nav));
      const navDisp = Number.isFinite(navNum)
        ? navNum.toLocaleString('fr-FR', { style: 'currency', currency: (ccy || 'EUR'), minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : '';
      return `
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-2 text-sm"><input type="checkbox" data-symbol="${symbol}" data-name="${name}" data-isin="${isin}" data-price="${Number.isFinite(navNum) ? navNum : ''}" data-date="${navDate}" data-exchange="${ex}" data-ccy="${ccy}"></td>
          <td class="px-4 py-2 text-sm" data-label="Libellé">${name}</td>
          <td class="px-4 py-2 text-sm" data-label="ISIN">${isin}</td>
          <td class="px-4 py-2 text-sm" data-label="Type"><span class="badge">${type}</span></td>
          <td class="px-4 py-2 text-sm" data-label="Exchange"><span class="badge">${ex}</span></td>
          <td class="px-4 py-2 text-sm" data-label="Devise"><span class="badge">${ccy}</span></td>
          <td class="px-4 py-2 text-sm" data-label="Dernière VL">${navDisp}</td>
          <td class="px-4 py-2 text-sm" data-label="Date VL">${navDate}</td>
        </tr>`;
    }).join('');
    this.tbodyTarget.innerHTML = html;
    const all = (this as any).selectAllTarget as HTMLInputElement | undefined;
    if (all) {
      all.checked = false;
      all.addEventListener('change', () => {
        this.tbodyTarget.querySelectorAll('input[type="checkbox"]').forEach((cb: any) => { cb.checked = all.checked; });
      });
    }
  }

  private async associate(payload: string) {
    if (!this.productTarget.value) {
      alert('Sélectionnez un produit d’abord.');
      return;
    }
    const selected = Array.from(this.productTarget.selectedOptions).map(o => o.value).filter(Boolean);
    if (selected.length === 0) {
      alert('Sélectionnez au moins un produit.');
      return;
    }
    const r = JSON.parse(decodeURIComponent(payload));
    for (const productId of selected) {
      const url = this.associateEndpointValue.replace('/__ID__/', `/${productId}/`);
      const form = new FormData();
      form.set('symbol', r.symbol || '');
      form.set('exchange', r.exchange || '');
      form.set('currency', r.currency || '');
      form.set('name', r.name || '');
      if (r.isin) form.set('isin', r.isin);
      form.set('_token', this.csrfValue);
      try {
        const res = await fetch(url, { method: 'POST', body: form, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        if (json.success) {
          this.statusTarget.textContent = `Support associé (#${productId}).`;
        } else {
          this.statusTarget.textContent = json.error || `Échec association (#${productId})`;
        }
      } catch (e) {
        this.statusTarget.textContent = `Erreur réseau (#${productId}).`;
      }
    }
  }
}


