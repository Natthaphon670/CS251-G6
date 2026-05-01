
/* ─────────────────────────────────────────────
   tenant.js
   เชื่อมหน้า HTML ฝั่ง Tenant กับ API
───────────────────────────────────────────── */

const API_BASE = '../api/';

function $(selector, root = document) {
  return root.querySelector(selector);
}

function $all(selector, root = document) {
  return Array.from(root.querySelectorAll(selector));
}

function escapeHTML(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function formatMoney(value) {
  const number = Number(value || 0);
  return `฿${number.toLocaleString('th-TH')}`;
}

async function apiFetch(file, options = {}) {
  const response = await fetch(`${API_BASE}${file}`, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    },
    ...options
  });

  const text = await response.text();
  const data = text ? JSON.parse(text) : {};

  if (!response.ok || data.status === 'error') {
    throw new Error(data.message || `HTTP ${response.status}`);
  }

  return data;
}

function showError(message) {
  console.error(message);
  alert(`เกิดข้อผิดพลาด: ${message}`);
}

/* LOGOUT */
async function logout() {
  try {
    await fetch(`${API_BASE}auth.php?action=logout`, {
      method: 'POST',
      credentials: 'include'
    });
  } catch (error) {
    console.warn(error);
  }

  sessionStorage.clear();
  window.location.href = '../login.html';
}

function getPageName() {
  return window.location.pathname.split('/').pop() || 'index.html';
}

/* DASHBOARD */
async function loadDashboard() {
  const data = await apiFetch('tenantDashboard.php');

  const cards = $all('.stat-card p');
  if (cards[0]) cards[0].textContent = formatMoney(data.total_sales);
  if (cards[1]) cards[1].textContent = `${Number(data.low_stock_count || 0)} รายการ`;
  if (cards[2]) cards[2].textContent = Number(data.active_promo || 0);

  const tbody = $('.content-box tbody');
  if (!tbody) return;

  const rows = data.recent_sales || [];
  tbody.innerHTML = rows.length
    ? rows.map(sale => `
      <tr>
        <td>${escapeHTML(sale.SalesDate)}</td>
        <td>${escapeHTML(sale.ProductID)}</td>
        <td>${escapeHTML(sale.Quantity)}</td>
        <td>${formatMoney(sale.TotalPrice)}</td>
      </tr>
    `).join('')
    : '<tr><td colspan="4">ยังไม่มีรายการขาย</td></tr>';
}

/* SALES TRACKING */
async function loadSalesTracking() {
  const data = await apiFetch('tenantDashboard.php');

  const tbody = $('.sst-table tbody');
  if (!tbody) return;

  const rows = data.recent_sales || [];
  tbody.innerHTML = rows.length
    ? rows.map((sale, index) => `
      <tr>
        <td>${escapeHTML(sale.SalesID || index + 1)}</td>
        <td>${escapeHTML(sale.SalesDate)}</td>
        <td>${escapeHTML(sale.ProductID)}</td>
        <td>${escapeHTML(sale.ProductName)}</td>
        <td>${escapeHTML(sale.Quantity)}</td>
        <td>${formatMoney(sale.TotalPrice)}</td>
      </tr>
    `).join('')
    : '<tr><td colspan="6">ยังไม่มีประวัติการขาย</td></tr>';
}

/* WAREHOUSE */
async function loadWarehouse() {
  const data = await apiFetch('warehouse.php');

  const tbody = $('.content-box tbody');
  if (!tbody) return;

  const rows = data.data || [];
  tbody.innerHTML = rows.length
    ? rows.map(item => `
      <tr>
        <td>${escapeHTML(item.ProductID)}</td>
        <td>${escapeHTML(item.ProductName)}</td>
        <td>${escapeHTML(item.WarehouseQuantity)}</td>
        <td>
          <input type="number" min="1" data-warehouse-id="${escapeHTML(item.WarehouseID)}">
          <button class="btn btn-primary btn-add-stock" data-warehouse-id="${escapeHTML(item.WarehouseID)}">
            เพิ่ม
          </button>
        </td>
      </tr>
    `).join('')
    : '<tr><td colspan="4">ไม่พบข้อมูลคลังสินค้า</td></tr>';

  $all('.btn-add-stock').forEach(button => {
    button.addEventListener('click', async () => {
      const warehouseId = button.dataset.warehouseId;
      const input = $(`input[data-warehouse-id="${warehouseId}"]`);
      const addAmount = Number(input?.value || 0);

      if (addAmount <= 0) {
        alert('กรุณากรอกจำนวนสินค้าที่ต้องการเพิ่ม');
        return;
      }

      await apiFetch('warehouse.php', {
        method: 'POST',
        body: JSON.stringify({
          warehouse_id: warehouseId,
          add_amount: addAmount
        })
      });

      alert('เพิ่มสินค้าเรียบร้อย');
      loadWarehouse();
    });
  });
}

/* PROMOTION */
async function loadPromotion() {
  const data = await apiFetch('promotion.php');

  const formCard = $('.form-card');
  if (!formCard) return;

  let listCard = $('.promotion-list-card');

  if (!listCard) {
    listCard = document.createElement('div');
    listCard.className = 'form-card promotion-list-card';
    listCard.innerHTML = `
      <div class="form-card-title">
        <i class="fas fa-list"></i> รายการโปรโมชัน
      </div>
      <table>
        <thead>
          <tr>
            <th>ชื่อโปรโมชัน</th>
            <th>วันที่เริ่ม</th>
            <th>วันที่สิ้นสุด</th>
            <th>ส่วนลด</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    `;
    formCard.after(listCard);
  }

  const tbody = $('tbody', listCard);
  const rows = data.data || [];

  tbody.innerHTML = rows.length
    ? rows.map(promo => `
      <tr>
        <td>${escapeHTML(promo.PromotionName)}</td>
        <td>${escapeHTML(promo.StartDatePromotion)}</td>
        <td>${escapeHTML(promo.EndDatePromotion)}</td>
        <td>${escapeHTML(promo.Discount)}%</td>
        <td>${escapeHTML(promo.Status)}</td>
      </tr>
    `).join('')
    : '<tr><td colspan="5">ยังไม่มีโปรโมชัน</td></tr>';
}

function bindPromotionForm() {
  const formCard = $('.form-card');
  const button = $('.btn-submit', formCard || document);
  if (!formCard || !button) return;

  button.addEventListener('click', async () => {
    const inputs = $all('input', formCard);

    const payload = {
      p_name: inputs[0]?.value.trim(),
      s_date: inputs[1]?.value,
      e_date: inputs[2]?.value,
      discount: inputs[3]?.value
    };

    await apiFetch('promotion.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });

    alert('สร้างโปรโมชันสำเร็จ');
    inputs.forEach(input => input.value = '');
    loadPromotion();
  });
}

/* SALES CONTRACT */
async function loadSalesContract() {
  const data = await apiFetch('sales_contract.php');

  const contract = data.contract;
  const values = $all('.contract-info-grid .info-value');

  if (contract) {
    if (values[0]) values[0].textContent = contract.ContractID || '-';
    if (values[1]) values[1].textContent = `${contract.Location || '-'} ชั้น ${contract.Floor || '-'}`;
  }

  const tbody = $('.invoice-table tbody');
  if (!tbody) return;

  const rows = data.invoices || [];
  tbody.innerHTML = rows.length
    ? rows.map(inv => `
      <tr>
        <td>${escapeHTML(inv.InvoiceID)}</td>
        <td>${escapeHTML(inv.InvoiceDate)}</td>
        <td>${formatMoney(inv.Amount)}</td>
        <td>${escapeHTML(inv.Status)}</td>
      </tr>
    `).join('')
    : '<tr><td colspan="4">ยังไม่มีใบแจ้งหนี้</td></tr>';
}

/* MANAGE PRODUCT */
function bindManageProductForm() {
  const card = $('.mp-card');
  const button = $('.btn-submit', card || document);

  if (!card || !button) return;

  button.addEventListener('click', async () => {
    const inputs = $all('input', card);
    const select = $('select', card);

    const payload = {
      pid: `P${Date.now().toString().slice(-5)}`,
      pname: inputs[1]?.value.trim(),
      sid: select?.value || '',
      price: Number(inputs[2]?.value || 0)
    };

    await apiFetch('manage_product.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });

    alert('บันทึกข้อมูลสินค้าสำเร็จ');
    inputs.forEach(input => input.value = '');
    if (select) select.value = '';
  });
}

/* INIT */
document.addEventListener('DOMContentLoaded', () => {
  $all('.btn-logout').forEach(btn => {
    btn.addEventListener('click', logout);
  });

  const page = getPageName();

  const loaders = {
    'index.html': loadDashboard,
    'store_sales_tracking.html': loadSalesTracking,
    'warehouse.html': loadWarehouse,
    'promotion.html': async () => {
      bindPromotionForm();
      await loadPromotion();
    },
    'sales_contract.html': loadSalesContract,
    'manage_product.html': bindManageProductForm
  };

  const loader = loaders[page];

  if (loader) {
    loader().catch(error => showError(error.message));
  }
});