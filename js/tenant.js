
// ── 1. ฟังก์ชันช่วยเหลือ ──
// ดึงรหัสร้านค้า (รองรับทั้งตัวพิมพ์เล็กและใหญ่ เผื่อระบบ Login เซฟมาไม่ตรงกัน)
function getTenantId() {
    return sessionStorage.getItem('TenantID') || sessionStorage.getItem('tenant_id');
}

// ── 2. ตรวจสอบสิทธิ์ (Authentication & Setup) ──
document.addEventListener('DOMContentLoaded', () => {
    const isLoggedIn = sessionStorage.getItem('isLoggedIn') || sessionStorage.getItem('account_id');
    const role = sessionStorage.getItem('role') || sessionStorage.getItem('Role');
    const tenantId = getTenantId();

    // แสดงชื่อร้านค้าบน Header
    const storeNameHeader = document.querySelector('header h2');
    if (storeNameHeader && storeNameHeader.textContent.includes('ยินดีต้อนรับ')) {
        const tenantName = sessionStorage.getItem('TenantName') || sessionStorage.getItem('tenant_name') || 'ร้านค้าของคุณ';
        storeNameHeader.innerHTML = `ยินดีต้อนรับ <strong>${tenantName}</strong>`;
    }

    // ระบบออกจากระบบ (Logout)
    const logoutBtns = document.querySelectorAll('.btn-logout');
    logoutBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            sessionStorage.clear();
            window.location.href = '../login.html';
        });
    });

    // ── Router: ตรวจสอบหน้าปัจจุบันแล้วรันฟังก์ชันให้ตรงหน้า ──
    const path = window.location.pathname.toLowerCase();
    
    // ใช้ includes ตรวจสอบชื่อไฟล์ให้ครอบคลุม
    if (path.includes('index')) {
        loadDashboard();
    } else if (path.includes('manage_product')) {
        loadSuppliersDropdown();
        loadProducts();
        setupProductForm();
    } else if (path.includes('supplier')) {
        loadSuppliers();
        setupSupplierForm();
    } else if (path.includes('warehouse')) {
        loadWarehouse();
    } else if (path.includes('promotion')) {
        setupPromotionForm();
    } else if (path.includes('store_sales_tracking')) {
        setupSalesTracking();
    } else if (path.includes('sales_contract')) {
        loadContractAndInvoices();
    }
});

// ── 3. ฟังก์ชันหลักสำหรับเรียก API (API Wrapper) ──
async function callTenantAPI(action, data = {}) {
    try {
        // แนบ TenantID เข้าไปในทุกคำขอโดยอัตโนมัติ
        data.tenant_id = getTenantId();

        const response = await fetch('../api/tenant_api.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        // ปริ้นผลลัพธ์ใน Console เพื่อให้หาบั๊กได้ง่ายขึ้น
        console.log(`[API Response - ${action}]:`, result); 
        return result;
    } catch (error) {
        console.error(`API Error (${action}):`, error);
        return { status: 'error', message: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ หรือ API ส่งค่ากลับมาไม่ใช่ JSON' };
    }
}

/* ========================================================
   หน้า 1: แผงควบคุม (Dashboard - index.html)
======================================================== */
async function loadDashboard() {
    console.log("กำลังโหลดข้อมูล Dashboard...");
    const result = await callTenantAPI('get_dashboard');
    
    if (result.status === 'success') {
        const data = result.data;
        
        // อัปเดตสถิติด้านบน (ใช้ ID ที่ตั้งไว้ใน HTML อย่างแม่นยำ)
        const salesEl = document.getElementById('dash-sales');
        const lowStockEl = document.getElementById('dash-low-stock');
        const promoEl = document.getElementById('dash-active-promo');

        if (salesEl) salesEl.textContent = `฿${parseFloat(data.today_sales || 0).toLocaleString()}`;
        if (lowStockEl) lowStockEl.textContent = `${data.low_stock_count || 0} รายการ`;
        if (promoEl) promoEl.textContent = data.active_promotions || 0;

        // อัปเดตตารางรายการขายล่าสุด
        const tbody = document.querySelector('.content-box table tbody');
        if (tbody) {
            tbody.innerHTML = '';
            if (data.recent_sales && data.recent_sales.length > 0) {
                data.recent_sales.forEach(sale => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${sale.SaleTime}</td>
                            <td>${sale.ProductID}</td>
                            <td>${sale.Quantity}</td>
                            <td>฿${parseFloat(sale.TotalAmount).toLocaleString()}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 20px; color: #666;">ยังไม่มีรายการขายวันนี้</td></tr>';
            }
        }
    } else {
        console.error('เกิดข้อผิดพลาดในการโหลด Dashboard:', result.message);
    }
}

/* ========================================================
   หน้า 2: จัดการสินค้า (manage_product.html)
======================================================== */
async function loadProducts() {
    const tbody = document.querySelector('.mp-table-wrap table tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">กำลังโหลดข้อมูล...</td></tr>';
    const result = await callTenantAPI('get_products');
    
    if (result.status === 'success' && result.data.length > 0) {
        tbody.innerHTML = '';
        result.data.forEach(p => {
            tbody.innerHTML += `
                <tr>
                    <td><span class="mp-code">${p.ProductID}</span></td>
                    <td>${p.ProductName}</td>
                    <td><span class="mp-badge-cat">${p.CategoryName}</span></td>
                    <td class="mp-price">฿${parseFloat(p.ProductPrice).toLocaleString()}</td>
                    <td><span class="mp-code">${p.SupplierID}</span></td>
                </tr>
            `;
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">ไม่พบข้อมูลสินค้า</td></tr>';
    }
}

async function loadSuppliersDropdown() {
    const select = document.querySelector('.mp-select');
    if (!select) return;

    // เปลี่ยนมาเรียก 'get_all_suppliers' เพื่อดึงข้อมูลทุกเจ้า
    const result = await callTenantAPI('get_all_suppliers');
    
    if (result.status === 'success') {
        select.innerHTML = '<option value="">— เลือกผู้จำหน่าย —</option>';
        result.data.forEach(s => {
            // แสดงรหัสและชื่อให้เลือกง่ายขึ้น
            select.innerHTML += `<option value="${s.SupplierID}">[${s.SupplierID}] ${s.SupplierName}</option>`;
        });
    } else {
        console.error("โหลด Dropdown Supplier ไม่สำเร็จ:", result.message);
        select.innerHTML = '<option value="">— โหลดข้อมูลผิดพลาด —</option>';
    }
}

function setupProductForm() {
    const submitBtn = document.querySelector('.mp-card .btn-submit');
    if (!submitBtn) return;

    submitBtn.addEventListener('click', async () => {
        // ดึงค่าตาม ID จะปลอดภัยกว่าการใช้ index [0], [1] 
        const data = {
            category_name: document.getElementById('cat_name').value.trim(),
            product_name: document.getElementById('prod_name').value.trim(),
            product_price: document.getElementById('prod_price').value,
            supplier_id: document.getElementById('sup_id').value,
            description: document.getElementById('prod_desc').value.trim()
        };

        if (!data.product_name || !data.product_price || !data.category_name) {
            alert('กรุณากรอกข้อมูลสำคัญให้ครบถ้วน (ชื่อหมวดหมู่, ชื่อสินค้า, ราคา)');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

        const result = await callTenantAPI('add_product', data);
        if (result.status === 'success') {
            alert('เพิ่มสินค้าสำเร็จ! รหัส: ' + result.product_id);
            // เคลียร์ค่าในช่องกรอก
            document.getElementById('cat_name').value = '';
            document.getElementById('prod_name').value = '';
            document.getElementById('prod_price').value = '';
            document.getElementById('sup_id').value = '';
            document.getElementById('prod_desc').value = '';
            
            loadProducts(); 
        } else {
            alert('เกิดข้อผิดพลาด: ' + result.message);
        }

        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> บันทึกข้อมูลและสร้าง ProductID';
    });
}

/* ========================================================
   หน้า 3: จัดการ Supplier (supplier.html)
======================================================== */
async function loadSuppliers() {
    const tbody = document.querySelector('.mp-table-card .mp-table tbody') || document.querySelector('.mp-supplier-card .mp-table tbody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">กำลังโหลดข้อมูล...</td></tr>';
    const result = await callTenantAPI('get_suppliers');
    
    if (result.status === 'success' && result.data.length > 0) {
        tbody.innerHTML = '';
        result.data.forEach(s => {
            tbody.innerHTML += `
                <tr>
                    <td><span class="mp-code">${s.SupplierID}</span></td>
                    <td>${s.SupplierName}</td>
                    <td>${s.ContactInfo}</td>
                </tr>
            `;
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;">ยังไม่มีผู้จัดจำหน่าย</td></tr>';
    }
}

function setupSupplierForm() {
    const submitBtn = document.querySelector('.mp-supplier-card .btn-submit');
    if (!submitBtn) return;

    submitBtn.addEventListener('click', async () => {
        const inputs = document.querySelectorAll('.mp-supplier-card input');
        const data = {
            supplier_name: inputs[0].value.trim(),
            contact_info: inputs[1].value.trim()
        };

        if (!data.supplier_name) {
            alert('กรุณากรอกชื่อผู้จัดจำหน่าย');
            return;
        }

        const result = await callTenantAPI('add_supplier', data);
        if (result.status === 'success') {
            alert('เพิ่ม Supplier สำเร็จ! รหัส: ' + result.supplier_id);
            inputs[0].value = '';
            inputs[1].value = '';
            loadSuppliers();
        } else {
            alert('เกิดข้อผิดพลาด: ' + result.message);
        }
    });
}

/* ========================================================
   หน้า 4: คลังสินค้า (warehouse.html)
======================================================== */
async function loadWarehouse() {
    console.log("กำลังโหลดหน้าคลังสินค้า...");
    loadWarehouseDropdowns();
    loadAvailableWarehouses(); //  เพิ่มการเรียกฟังก์ชันโหลด Dropdown คลังสินค้า
    loadWarehouseStock();
    setupWarehouseForm();
}

// 1. โหลดรายการสินค้า (Dropdown สินค้า)
async function loadWarehouseDropdowns() {
    const select = document.getElementById('select-product');
    if (!select) return;

    const result = await callTenantAPI('get_products');
    
    if (result.status === 'success') {
        select.innerHTML = '<option value="">— เลือกสินค้า —</option>';
        result.data.forEach(p => {
            select.innerHTML += `<option value="${p.ProductID}">[${p.ProductID}] ${p.ProductName}</option>`;
        });
    } else {
        console.error("โหลด Dropdown สินค้าไม่สำเร็จ:", result.message);
    }
}

// 1.5 โหลดรายการคลังสินค้า (Dropdown คลังสินค้า)
async function loadAvailableWarehouses() {
    const selectW = document.getElementById('warehouse-id');
    if (!selectW) return;

    // เรียกใช้ action ใหม่ที่ดึงรายชื่อ Warehouse ทั้งหมด
    const result = await callTenantAPI('get_all_warehouses');
    
    if (result.status === 'success') {
        selectW.innerHTML = '<option value="">— เลือกคลังสินค้า (Warehouse) —</option>';
        result.data.forEach(w => {
            // แสดงรหัสคลังสินค้าใน Dropdown
            selectW.innerHTML += `<option value="${w.WarehouseID}">${w.WarehouseID}</option>`;
        });
    } else {
        console.error("โหลด Dropdown คลังสินค้าไม่สำเร็จ:", result.message);
    }
}

// โหลดตารางสต็อก 
async function loadWarehouseStock() {
    const tbody = document.querySelector('table tbody'); 
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">กำลังโหลดสต็อก...</td></tr>';
    
    const result = await callTenantAPI('get_warehouse');

    if (result.status === 'success' && result.data.length > 0) {
        tbody.innerHTML = '';
        result.data.forEach(item => {
            // สร้าง Dropdown คลังสินค้า
            let warehouseOptions = '';
            // ตรวจสอบว่ามีข้อมูล Warehouses ไหม
            if (item.Warehouses && item.Warehouses.length > 0) {
                item.Warehouses.forEach(w => {
                     warehouseOptions += `<option value="${w.WarehouseID}" data-qty="${w.Quantity}">${w.WarehouseID}</option>`;
                });
            } else {
                warehouseOptions = '<option value="">- ไม่มีคลัง -</option>';
            }

            // ค่า Quantity เริ่มต้น จะดึงจากคลังแรกในลิสต์
            const defaultQty = item.Warehouses && item.Warehouses.length > 0 ? item.Warehouses[0].Quantity : 0;
            const defaultWid = item.Warehouses && item.Warehouses.length > 0 ? item.Warehouses[0].WarehouseID : '';

            tbody.innerHTML += `
                <tr>
                    <td><span class="mp-code">${item.ProductID}</span></td>
                    <td>${item.ProductName}</td>
                    <td><strong>${item.TotalQuantity}</strong></td>
                    <td style="display: flex; gap: 10px; align-items: center;">
                        <select id="select_wid_${item.ProductID}" style="padding: 5px; border-radius: 4px;" 
                                onchange="updateQtyInput('${item.ProductID}')">
                            ${warehouseOptions}
                        </select>
                        <input type="number" id="qty_${item.ProductID}" 
                               style="width: 80px; padding: 5px; text-align: center; font-weight: bold;" 
                               min="0" value="${defaultQty}"> 
                    </td>
                    <td> <button class="btn btn-warning" style="background-color: #85ceff; color: #000; border: none; padding: 6px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;" 
                                onclick="updateProductStock('${item.ProductID}')">
                        อัปเดต
                        </button>
                    </td>
                </tr>
            `;
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding: 20px;">ไม่พบข้อมูลสินค้าในคลัง</td></tr>';
    }
}
window.updateQtyInput = function(pid) {
    const select = document.getElementById(`select_wid_${pid}`);
    const qtyInput = document.getElementById(`qty_${pid}`);
    
    // ดึงค่า Quantity จาก attribute data-qty ของ option ที่ถูกเลือก
    const selectedOption = select.options[select.selectedIndex];
    const qty = selectedOption.getAttribute('data-qty');
    
    qtyInput.value = qty;
};
window.updateProductStock = async function(pid) {
    const select = document.getElementById(`select_wid_${pid}`);
    const wid = select.value;
    const qtyInput = document.getElementById(`qty_${pid}`);
    const newQty = parseInt(qtyInput.value);

    if (!wid) {
         alert('ไม่พบคลังสินค้าที่ต้องการอัปเดต');
         return;
    }

    if (isNaN(newQty) || newQty < 0) {
        alert('กรุณาระบุจำนวนให้ถูกต้อง (ต้องไม่ติดลบ)');
        return;
    }

    const result = await callTenantAPI('update_store_quantity', { 
        product_id: pid,
        warehouse_id: wid, 
        quantity: newQty 
    });

    if (result.status === 'success') {
        alert('อัปเดตสต็อกสำเร็จ!');
        loadWarehouseStock(); 
    } else {
        alert('ผิดพลาด: ' + result.message);
    }
};

// 3. จัดการตอนกดปุ่ม "เพิ่มเข้าคลัง" (ผูกคลังใหม่ / ย้ายคลัง)
function setupWarehouseForm() {
    const btn = document.getElementById('btn-add-to-store');
    if (!btn) return;

    btn.onclick = async () => {
        const pid = document.getElementById('select-product').value;
        const wid = document.getElementById('warehouse-id').value; // ดึงค่าจาก Dropdown
        const qty = document.getElementById('new-qty').value;

        if (!pid || !wid || !qty || qty <= 0) {
            alert('กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง (เลือกรหัสสินค้า, เลือกคลังสินค้า, จำนวน)');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

        // เปลี่ยน action ให้ตรงกับ API ที่เราสร้างใน PHP
        const result = await callTenantAPI('bind_product_to_warehouse', {
            product_id: pid,
            warehouse_id: wid,
            quantity: qty // ส่งพารามิเตอร์ให้ตรงกับที่ PHP รับค่า
        });

        if (result.status === 'success') {
            alert(result.message); // แจ้งเตือนว่าผูกคลังใหม่ หรืออัปเดตของเดิม
            
            // ล้างค่าฟอร์ม
            document.getElementById('select-product').value = '';
            document.getElementById('warehouse-id').value = '';
            document.getElementById('new-qty').value = '';
            
            // โหลดตารางใหม่
            loadWarehouseStock(); 
        } else {
            alert('เกิดข้อผิดพลาด: ' + result.message);
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus"></i> เพิ่มเข้าคลัง';
    };
}

// 4. จัดการปุ่ม "เพิ่ม" ในตาราง (เติมสต็อก)
window.restockProduct = async function(wid, pid) {
    const qtyInput = document.getElementById(`qty_${wid}_${pid}`);
    const qty = parseInt(qtyInput.value);

    if (!qty || qty <= 0) {
        alert('กรุณาระบุจำนวนที่ต้องการเพิ่มให้ถูกต้อง');
        return;
    }

    // เปลี่ยนมาใช้ bind_product_to_warehouse ด้วยเลย
    // เพราะ API ตัวนี้ถูกออกแบบมาให้ทำได้ทั้ง สร้าง Row ใหม่ และ อัปเดต Row เดิม (ที่มีอยู่แล้วในตาราง Store) 
    // ช่วยให้โค้ดส่วน Backend สะอาดขึ้นโดยไม่ต้องมีหลายฟังก์ชัน
    const result = await callTenantAPI('bind_product_to_warehouse', { 
        product_id: pid,
        warehouse_id: wid, 
        quantity: qty 
    });

    if (result.status === 'success') {
        alert('เติมสต็อกสำเร็จ!');
        qtyInput.value = ''; // ล้างช่องกรอกตัวเลข
        loadWarehouseStock(); 
    } else {
        alert('ผิดพลาด: ' + result.message);
    }
};

/* ========================================================
   หน้า 5: โปรโมชัน (promotion.html)
======================================================== */
function setupPromotionForm() {
    const submitBtn = document.querySelector('.form-card .btn-submit');
    if (!submitBtn) return;

    submitBtn.addEventListener('click', async () => {
        const inputs = document.querySelectorAll('.form-card input');
        const data = {
            promo_name: inputs[0].value.trim(),
            start_date: inputs[1].value,
            end_date: inputs[2].value,
            discount: inputs[3].value
        };

        if (!data.promo_name || !data.start_date || !data.end_date || !data.discount) {
            alert('กรุณากรอกข้อมูลโปรโมชันให้ครบถ้วน');
            return;
        }

        const result = await callTenantAPI('add_promotion', data);
        if (result.status === 'success') {
            alert('สร้างโปรโมชันสำเร็จ!');
            inputs.forEach(input => input.value = ''); 
        } else {
            alert('ผิดพลาด: ' + result.message);
        }
    });
}

/* ========================================================
   หน้า 6: ประวัติการขาย (store_sales_tracking.html)
======================================================== */
async function setupSalesTracking() {
    const searchBtn = document.querySelector('.sst-btn-search');
    const dateInputs = document.querySelectorAll('input[type="date"]');
    
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
    const currentDate = today.toISOString().split('T')[0];
    
    if (dateInputs.length === 2) {
        dateInputs[0].value = firstDay;
        dateInputs[1].value = currentDate;
    }

    const fetchSales = async () => {
        const tbody = document.querySelector('.sst-table tbody');
        const totalSpan = document.querySelector('.sst-total-value');
        
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">กำลังค้นหา...</td></tr>';
        
        const result = await callTenantAPI('get_sales_history', {
            start_date: dateInputs[0].value,
            end_date: dateInputs[1].value
        });

        if (result.status === 'success' && result.data.length > 0) {
            tbody.innerHTML = '';
            let grandTotal = 0;

            result.data.forEach(sale => {
                grandTotal += parseFloat(sale.TotalAmount);
                tbody.innerHTML += `
                    <tr>
                        <td><span class="sst-code">${sale.ReceiptID}</span></td>
                        <td>${sale.SaleTime}</td>
                        <td><span class="sst-code">${sale.ProductID}</span></td>
                        <td>${sale.ProductName}</td>
                        <td class="sst-qty">${sale.Quantity}</td>
                        <td class="sst-amount">${parseFloat(sale.TotalAmount).toLocaleString()}</td>
                    </tr>
                `;
            });
            totalSpan.textContent = grandTotal.toLocaleString();
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 20px;">ไม่พบประวัติการขายในช่วงเวลานี้</td></tr>';
            totalSpan.textContent = '0';
        }
    };

    if (searchBtn) {
        searchBtn.addEventListener('click', fetchSales);
        fetchSales(); 
    }
}

/* ========================================================
   หน้า 7: ยอดขายและสัญญา (sales_contract.html)
======================================================== */
async function loadContractAndInvoices() {
    const result = await callTenantAPI('get_contract_invoices');
    if (result.status === 'success') {
        const data = result.data;

        const infoValues = document.querySelectorAll('.contract-info-grid .info-value, .contract-info-grid .badge-active');
        if (infoValues.length >= 3 && data.contract) {
            infoValues[0].textContent = data.contract.ContractID || '-';
            infoValues[1].textContent = `${data.contract.Zone} – ชั้น ${data.contract.Floor}`;
            infoValues[2].textContent = data.contract.Status || 'Active';
        }

        const tbody = document.querySelector('.invoice-table tbody');
        if (tbody) {
            tbody.innerHTML = '';
            if (data.invoices && data.invoices.length > 0) {
                data.invoices.forEach(inv => {
                    const statusBadge = inv.Status === 'Paid' 
                        ? `<span class="badge-paid"><i class="fas fa-check"></i> ชำระแล้ว</span>`
                        : `<span class="badge-unpaid"><i class="fas fa-clock"></i> ยังไม่ได้ชำระ</span>`;

                    tbody.innerHTML += `
                        <tr>
                            <td><span class="inv-id">${inv.InvoiceID}</span></td>
                            <td>${inv.BillingMonth}</td>
                            <td><span class="amount">฿${parseFloat(inv.TotalAmount).toLocaleString()}</span></td>
                            <td>${statusBadge}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">ไม่มีใบแจ้งหนี้</td></tr>';
            }
        }
    }
}