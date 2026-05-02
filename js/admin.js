// ==========================================
// ส่วนที่ 1: ระบบดึงข้อมูลอัตโนมัติเมื่อโหลดหน้าเว็บ
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    // เช็กว่าอยู่หน้าไหน แล้วเรียกฟังก์ชันดึงข้อมูลของหน้านั้น
    if (document.getElementById('total_sales')) loadDashboardStats();
    if (document.getElementById('employeeTableBody')) loadEmployees();
    if (document.getElementById('tenantTableBody')) loadTenants();
    if (document.getElementById('spaceTableBody')) loadSpaces();
    if (document.getElementById('leaseTableBody')) loadContracts();
    if (document.getElementById('invoiceTableBody')) loadInvoices();
    if (document.getElementById('salesTableBody')) loadSales();
    if (document.getElementById('reportTableBody')) loadReports();
    if (document.getElementById('warehouseTableBody')) loadWarehouse();
});

// ==========================================
// ส่วนที่ 2: ฟังก์ชันกลางสำหรับคุยกับ PHP API
// ==========================================
async function callAdminAPI(action, data = null) {
    const formData = new FormData();
    formData.append('action', action); // <-- ตัวนี้แหละครับที่ PHP ร้องหา!
    
    if (data) {
        for (const key in data) formData.append(key, data[key]);
    }

    try {
        const response = await fetch('../api/admin_api.php', {
            method: 'POST',
            body: formData
        });
        return await response.json();
    } catch (error) {
        console.error("API Error:", error);
        return { status: "error", message: "การเชื่อมต่อล้มเหลว" };
    }
}

// ==========================================
// ส่วนที่ 3: ฟังก์ชันดึงข้อมูลแต่ละหน้า
// ==========================================
// --- Dashboard ---
async function loadDashboardStats() {
    const res = await callAdminAPI('get_dashboard_stats');
    if (res.status === 'success') {
        const data = res.data;

        // อัปเดตตัวเลขหลัก
        document.getElementById('total_sales').innerText = '฿ ' + Number(data.total_sales).toLocaleString();
        document.getElementById('total_tenants').innerText = data.total_tenants;
        document.getElementById('total_products').innerText = data.total_products;

        // อัปเดต % Trend ยอดขาย (เขียว/แดง)
        const trendEl = document.getElementById('sales_trend');
        if (data.sales_trend > 0) {
            trendEl.innerHTML = `▲ ${data.sales_trend}% this month`;
            trendEl.style.color = '#28a745'; // สีเขียว
        } else if (data.sales_trend < 0) {
            trendEl.innerHTML = `▼ ${Math.abs(data.sales_trend)}% this month`;
            trendEl.style.color = '#dc3545'; // สีแดง
        } else {
            trendEl.innerHTML = `- 0% this month`;
            trendEl.style.color = '#6c757d'; // สีเทา
        }

        // อัปเดตกล่องสรุปภาพรวมขวาล่าง
        document.getElementById('summary_expiring').innerText = `${data.expiring_contracts} ร้าน`;
        document.getElementById('summary_freespace').innerText = `${data.free_spaces} โซน`;
        document.getElementById('summary_unpaid').innerText = '฿ ' + Number(data.unpaid_invoices).toLocaleString();
    }
}


// --- Space ---
async function loadSpaces() {
    const res = await callAdminAPI('get_spaces');
    const tbody = document.getElementById('spaceTableBody');
    if (res.status === 'success' && tbody) {
        tbody.innerHTML = res.data.map(space => {
            // เช็กสถานะพื้นที่จากข้อมูลที่ SQL คำนวณมาให้
            const statusText = space.IsOccupied > 0 ? 'ไม่ว่าง' : 'ว่าง';
            const statusColor = space.IsOccupied > 0 ? '#dc3545' : '#28a745'; // แดง = ไม่ว่าง, เขียว = ว่าง

            return `
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;">${space.SpaceID}</td>
                <td style="padding: 12px;">${space.Floor}</td>
                <td style="padding: 12px;">${space.Location}</td>
                <td style="padding: 12px;">${space.Size}</td>
                <td style="padding: 12px;">
                    <span style="background: ${statusColor}; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px;">${statusText}</span>
                </td>
                <td style="padding: 12px;">
                    <button onclick="prepareEditSpace('${space.SpaceID}')" style="background: #ffc107; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;">แก้ไข</button>
                </td>
            </tr>
            `;
        }).join('');
    }
}

// --- Employee ---
async function loadEmployees() {
    const res = await callAdminAPI('get_employees');
    const tbody = document.getElementById('employeeTableBody');
    if (res.status === 'success' && tbody) {
        tbody.innerHTML = res.data.map(emp => `
            <tr>
                <td>${emp.EmployeeName}</td>
                <td>${emp.Position}</td>
                <td><span class="role-badge" style="background: #007bff; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px;">${emp.Role}</span></td>
                <td>${emp.EmTelephone}</td>
            </tr>
        `).join('');
    }
}

// --- Tenant ---
async function loadTenants() {
    const res = await callAdminAPI('get_tenants');
    const tbody = document.getElementById('tenantTableBody');
    if (res.status === 'success' && tbody) {
        tbody.innerHTML = res.data.map(tenant => `
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;">${tenant.TenantName}</td>
                <td style="padding: 12px;">${tenant.TenantCategory}</td>
                <td style="padding: 12px;">${tenant.TenantContactInfo}</td>
                <td style="padding: 12px;">-</td>
                <td style="padding: 12px;">
                <button onclick="prepareEditTenant('${tenant.TenantID}')" class="btn-edit" style="background: #ffc107; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;">แก้ไข</button>
                </tr>
        `).join('');
    }
}

// --- Contract ---
async function loadContracts() {
    const res = await callAdminAPI('get_contracts');
    const tbody = document.getElementById('leaseTableBody');
    if (res.status === 'success' && tbody) {
        tbody.innerHTML = res.data.map(con => {
            // ตรรกะคำนวณสถานะสัญญาเช่าจากวันที่
            const today = new Date();
            today.setHours(0, 0, 0, 0); // รีเซ็ตเวลาเพื่อเทียบเฉพาะวันที่
            const startDate = new Date(con.StartDateLease);
            const endDate = new Date(con.EndDateLease);
            
            let statusBadge = '';
            if (today > endDate) {
                // เลยวันสิ้นสุดไปแล้ว = หมดอายุ (สีเทา)
                statusBadge = `<span style="background: #6c757d; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px;">Expired</span>`;
            } else if (today < startDate) {
                // ยังไม่ถึงวันเริ่มสัญญา = รอเริ่มสัญญา (สีเหลือง)
                statusBadge = `<span style="background: #ffc107; color: black; padding: 5px 10px; border-radius: 20px; font-size: 12px;">Pending</span>`;
            } else {
                // อยู่ในระหว่างสัญญา = ใช้งานอยู่ (สีเขียว)
                statusBadge = `<span style="background: #28a745; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px;">Active</span>`;
            }

            return `
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;">${con.ContractID}</td>
                <td style="padding: 12px;">${con.TenantName}</td>
                <td style="padding: 12px;">${con.StartDateLease}</td>
                <td style="padding: 12px;">${con.EndDateLease}</td>
                <td style="padding: 12px;">${statusBadge}</td>
                <td style="padding: 12px;"><a href="#" style="color: #007bff;">📄 ดู PDF</a></td>
            </tr>
            `;
        }).join('');
    }
}

// --- Invoice ---
async function loadInvoices() {
    const res = await callAdminAPI('get_invoices');
    const tbody = document.getElementById('invoiceTableBody');
    if (res.status === 'success' && tbody) {
        tbody.innerHTML = res.data.map(inv => `
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;">${inv.InvoiceID}</td>
                <td style="padding: 12px;">${inv.TenantName}</td>
                <td style="padding: 12px;">${inv.InvoiceDate}</td>
                <td style="padding: 12px;">฿ ${Number(inv.Amount).toLocaleString()}</td>
                <td style="padding: 12px;"><span style="background: ${inv.Status.toLowerCase() === 'paid' ? '#28a745' : '#dc3545'}; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px;">${inv.Status}</span></td>
                <td style="padding: 12px;"><a href="#" style="color: #007bff;">พิมพ์ / ดู</a></td>
            </tr>
        `).join('');
    }
}

// --- Sales ---
// async function loadSales() {
//     const res = await callAdminAPI('get_sales');
//     const tbody = document.getElementById('salesTableBody');
//     if (res.status === 'success' && tbody) {
//         tbody.innerHTML = res.data.map(sale => `
//             <tr style="border-bottom: 1px solid #ddd;">
//                 <td style="padding: 12px;">${sale.SalesID}</td>
//                 <td style="padding: 12px;">${sale.SalesDate}</td>
//                 <td style="padding: 12px;">${sale.TenantName}</td>
//                 <td style="padding: 12px; color: green; font-weight: bold;">฿ ${Number(sale.Quantity * 100).toLocaleString()}</td> 
//                 <td style="padding: 12px;">${sale.ProductName} (x${sale.Quantity})</td>
//             </tr>
//         `).join('');
//     }
// }
// ==========================================
// ระบบการจัดการยอดขาย (Sales Management)
// ==========================================

let currentTenantProducts = []; // ตัวแปรเก็บสินค้าของร้านที่เลือก

// 1. ดึงรายชื่อร้านค้าเข้า Dropdown
async function loadTenantsForSales() {
    const res = await callAdminAPI('get_tenants_for_sales');
    const select = document.getElementById('tenant_id');
    if(res.status === 'success' && select) {
        select.innerHTML = '<option value="">-- เลือกร้านค้า --</option>';
        res.data.forEach(t => {
            select.innerHTML += `<option value="${t.TenantID}">[${t.TenantID}] ${t.TenantName}</option>`;
        });
    }
}

// 2. เมื่อเลือกร้านค้า -> โหลดสินค้าของร้านนั้นมาเตรียมไว้
async function handleTenantChange() {
    const tenantId = document.getElementById('tenant_id').value;
    const container = document.getElementById('product-lines-container');
    container.innerHTML = ''; // เคลียร์สินค้าเดิมทิ้ง
    document.getElementById('grand_total').value = '0.00';

    if (!tenantId) {
        currentTenantProducts = [];
        return;
    }

    const res = await callAdminAPI('get_products_by_tenant', { tenant_id: tenantId });
    if (res.status === 'success') {
        currentTenantProducts = res.data;
        addProductLine(); // เพิ่มช่องว่างให้กรอก 1 แถวอัตโนมัติ
    }
}

// 3. ฟังก์ชันเพิ่มแถวสินค้าใหม่
function addProductLine() {
    if (currentTenantProducts.length === 0) {
        alert("ร้านค้านี้ยังไม่มีสินค้าในระบบ กรุณาเลือกร้านอื่น");
        return;
    }

    const container = document.getElementById('product-lines-container');
    
    // สร้างตัวเลือก Dropdown พร้อมเก็บ data-price และ data-discount เอาไว้คำนวณ
    let options = '<option value="">-- เลือกสินค้า --</option>';
    currentTenantProducts.forEach(p => {
        let promoText = p.DiscountPercent > 0 ? ` (🔥ลด ${p.DiscountPercent}%)` : '';
        options += `<option value="${p.ProductID}" data-price="${p.ProductPrice}" data-discount="${p.DiscountPercent}">[${p.ProductID}] ${p.ProductName}${promoText} - ฿${p.ProductPrice}</option>`;
    });

    const row = document.createElement('div');
    row.className = 'product-line';
    row.style.cssText = 'display: grid; grid-template-columns: 3fr 1fr 1fr auto; gap: 10px; margin-bottom: 10px; align-items: center;';
    
    row.innerHTML = `
        <select class="prod-select" required style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;" onchange="calculateSalesTotal()">
            ${options}
        </select>
        <input type="number" class="prod-qty" min="1" value="1" placeholder="จำนวน" required style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;" oninput="calculateSalesTotal()">
        <input type="text" class="prod-subtotal" readonly placeholder="ราคารวม" style="padding: 8px; background: #eee; border: 1px solid #ddd; border-radius: 5px; text-align: right;">
        <button type="button" onclick="this.parentElement.remove(); calculateSalesTotal();" style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">ลบ</button>
    `;
    container.appendChild(row);
}

// 4. คำนวณราคาสุทธิ (หักเปอร์เซ็นต์โปรโมชั่นให้ด้วย)
function calculateSalesTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.product-line').forEach(row => {
        const select = row.querySelector('.prod-select');
        const qtyInput = row.querySelector('.prod-qty');
        const subtotalInput = row.querySelector('.prod-subtotal');
        
        if (select.value && qtyInput.value) {
            const selectedOption = select.options[select.selectedIndex];
            const price = parseFloat(selectedOption.getAttribute('data-price'));
            const discount = parseFloat(selectedOption.getAttribute('data-discount')); // เปอร์เซ็นต์ส่วนลด
            const qty = parseInt(qtyInput.value);
            
            // คำนวณส่วนลด: ราคา * (1 - (ลด% / 100))
            const finalPrice = price * (1 - (discount / 100));
            const subtotal = finalPrice * qty;
            
            subtotalInput.value = subtotal.toFixed(2);
            grandTotal += subtotal;
        } else {
            subtotalInput.value = '0.00';
        }
    });
    // โชว์ยอดรวมสุทธิ
    document.getElementById('grand_total').value = grandTotal.toFixed(2);
}

// 5. โหลดตารางประวัติยอดขาย (อัปเดตใหม่)
async function loadSales() {
    const res = await callAdminAPI('get_sales');
    const tbody = document.getElementById('salesTableBody');
    if (res.status === 'success' && tbody) {
        tbody.innerHTML = res.data.map(sale => `
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;">${sale.SalesID}</td>
                <td style="padding: 12px;">${sale.SalesDate}</td>
                <td style="padding: 12px;">${sale.TenantName}</td>
                <td style="padding: 12px; color: green; font-weight: bold;">฿ ${Number(sale.FinalAmount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td> 
                <td style="padding: 12px;">${sale.ProductName} (x${sale.Quantity})</td>
            </tr>
        `).join('');
    }
}

// 6. กดปุ่มบันทึกยอดขาย
document.addEventListener('DOMContentLoaded', () => {
    // ถ้าอยู่หน้า Sales ให้โหลดข้อมูลเริ่มต้น
    if (document.getElementById('salesTableBody')) {
        loadSales();
        loadTenantsForSales();
        
        // เซ็ตวันที่และเวลาปัจจุบันลงในช่อง input
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('sales_date').value = now.toISOString().slice(0,16);
    }

    const salesForm = document.getElementById('addSaleForm');
    if (salesForm) {
        salesForm.onsubmit = async (e) => {
            e.preventDefault();
            const salesDate = document.getElementById('sales_date').value;
            const tenantId = document.getElementById('tenant_id').value;
            
            // รวบรวมสินค้าทั้งหมดที่ถูกเพิ่มในแถว
            const items = [];
            document.querySelectorAll('.product-line').forEach(row => {
                const pid = row.querySelector('.prod-select').value;
                const qty = row.querySelector('.prod-qty').value;
                if (pid && qty > 0) {
                    items.push({ product_id: pid, quantity: qty });
                }
            });

            if (items.length === 0) {
                alert("กรุณาเลือกสินค้าอย่างน้อย 1 รายการ");
                return;
            }

            // ยิง API
            const res = await callAdminAPI('add_multiple_sales', {
                sales_date: salesDate.replace('T', ' '), // แปลงฟอร์แมตให้ DB อ่านง่าย
                tenant_id: tenantId,
                items: JSON.stringify(items)
            });

            if (res.status === 'success') {
                alert(res.message);
                // รีเซ็ตฟอร์ม
                document.getElementById('tenant_id').value = '';
                document.getElementById('product-lines-container').innerHTML = '';
                document.getElementById('grand_total').value = '0.00';
                
                // เซ็ตเวลาปัจจุบันใหม่
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                document.getElementById('sales_date').value = now.toISOString().slice(0,16);
                
                loadSales(); // รีเฟรชตาราง
            } else {
                alert("Error: " + res.message);
            }
        };
    }
});

// --- Reports ---
async function loadReports() {
    const res = await callAdminAPI('get_reports');
    const tbody = document.getElementById('reportTableBody');
    if (res.status === 'success' && tbody) {
        tbody.innerHTML = res.data.map(rep => `
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;">${rep.ReportName}</td>
                <td style="padding: 12px;">${rep.ReportDate}</td>
                <td style="padding: 12px;">${rep.EmployeeName}</td>
                <td style="padding: 12px;"><button style="background: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">ดาวน์โหลด</button></td>
            </tr>
        `).join('');
    }
}
//Warehouse
async function loadWarehouse() {
    const res = await callAdminAPI('get_warehouse');
    const tbody = document.getElementById('warehouseTableBody');
    if (res.status === 'success' && tbody) {
        tbody.innerHTML = res.data.map(item => {
            // เช็คว่ามี ProductID ไหม ถ้าไม่มีแปลว่าเป็นคลังเปล่าๆ
            const isCode = item.ProductID ? item.ProductID : item.WarehouseID;
            const isName = item.ProductName ? item.ProductName : '<span style="color:#aaa;">รอเชื่อมระบบ...</span>';
            const isCat = item.CategoryName ? item.CategoryName : '<span style="color:#aaa;">-</span>';
            const isSup = item.SupplierName ? item.SupplierName : '<span style="color:#aaa;">-</span>';
            
            // เตรียมพารามิเตอร์สำหรับปุ่มลบ
            const delParams = item.ProductID 
                ? `'${item.WarehouseID}', '${item.ProductID}'` 
                : `'${item.WarehouseID}', ''`;

            return `
            <tr style="border-bottom: 1px solid #ddd; ${!item.ProductID ? 'background-color: #fffbcc;' : ''}">
                <td style="padding: 12px;"><b>${isCode}</b> <br><small style="color:#888;">${item.ProductID ? `(คลัง: ${item.WarehouseID})` : '(คลังว่าง)'}</small></td>
                <td style="padding: 12px;">${isName}</td>
                <td style="padding: 12px;">${isCat}</td>
                <td style="padding: 12px;"><strong>${item.Quantity}</strong></td>
                <td style="padding: 12px;">${isSup}</td>
                <td style="padding: 12px;">
                    <button onclick="deleteWarehouseRow(this, ${delParams})" style="background: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;">ลบ (Delete)</button>
                </td>
            </tr>
            `;
        }).join('');
    }
}

// 2. New Function: Add new editable row directly to the table
async function addWarehouseRowInline() {
    // ให้ปุ่มมันโหลดชั่วคราว ป้องกันคนกดย้ำๆ
    const btn = document.querySelector('.btn-primary') || document.querySelector('.btn-add-stock');
    if (btn) btn.disabled = true;

    const res = await callAdminAPI('add_empty_warehouse');
    if (res.status === 'success') {
        loadWarehouse(); // โหลดตารางใหม่ทันที จะเห็นคลังโผล่ขึ้นมาเลย
    } else {
        alert("Error: " + res.message);
    }
    
    if (btn) btn.disabled = false;
}

// 3. New Function: Remove the row from the table
async function deleteWarehouseRow(btn, warehouseID, productID) {
    let confirmMsg = productID 
        ? `ยืนยันการลบสินค้า ${productID} ออกจากคลัง?`
        : `ยืนยันการลบ "คลังสินค้าว่าง ${warehouseID}" ใช่หรือไม่?`;

    if(confirm(confirmMsg)) {
        const res = await callAdminAPI('delete_warehouse_item', { 
            warehouse_id: warehouseID,
            product_id: productID 
        });
        
        if(res.status === 'success') {
            btn.closest('tr').remove(); // เอาออกจากหน้าจอ
        } else {
            alert(res.message); // โชว์ข้อความ Error จาก PHP (เช่น ลบไม่ได้เพราะมีสินค้าอยู่)
        }
    }
}

   // --- เตรียมข้อมูลแก้ไข Tenant ---
async function prepareEditTenant(id) {
    const res = await callAdminAPI('get_tenant_by_id', { tenant_id: id });
    if (res.status === 'success') {
        const t = res.data;
        const form = document.getElementById('editTenantForm');
        form.tenant_id.value = t.TenantID;
        form.tenant_name.value = t.TenantName;
        form.category.value = t.TenantCategory;
        form.contact_info.value = t.TenantContactInfo;
        openModal('editTenantModal');
    }
}

// --- เตรียมข้อมูลแก้ไข Space ---
async function prepareEditSpace(id) {
    const res = await callAdminAPI('get_space_by_id', { space_id: id });
    if (res.status === 'success') {
        const s = res.data;
        const form = document.getElementById('editSpaceForm');
        form.space_id.value = s.SpaceID;
        form.floor.value = s.Floor;
        form.location.value = s.Location;
        form.size.value = s.Size;
        openModal('editSpaceModal');
    }
}

// ==========================================
// ส่วนที่ 4: ฟังก์ชัน UI เดิม
// ==========================================
function showSection(id) {
    document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    document.getElementById(id).style.display = 'block';
    if (window.event) {
        window.event.currentTarget.classList.add('active');
        document.getElementById('current-page-title').innerText = window.event.currentTarget.innerText;
    }
}

function toggleDropdown(event) {
    event.stopPropagation();
    document.getElementById("profileDropdown").classList.toggle("show");
}

window.onclick = function(event) {
    if (!event.target.closest('.profile-menu')) {
        var dropdowns = document.getElementsByClassName("dropdown-content");
        for (var i = 0; i < dropdowns.length; i++) {
            var openDropdown = dropdowns[i];
            if (openDropdown.classList.contains('show')) {
                openDropdown.classList.remove('show');
            }
        }
    }
}


// --- ฟังก์ชันเปิด/ปิด Modal ---
function openModal(id) {
    document.getElementById(id).style.display = 'block';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    if(id === 'employeeModal') document.getElementById('addEmployeeForm').reset();
}

// --- ผูกปุ่มเข้ากับหน้าต่าง Modal (ใส่ใน DOMContentLoaded) ---
document.addEventListener('DOMContentLoaded', () => {
    // 1. ดึงข้อมูลตารางตามหน้าเว็บที่เปิดอยู่
    if (document.getElementById('total_sales')) loadDashboardStats();
    if (document.getElementById('employeeTableBody')) loadEmployees();
    if (document.getElementById('tenantTableBody')) loadTenants();
    if (document.getElementById('spaceTableBody')) loadSpaces();
    if (document.getElementById('leaseTableBody')) loadContracts();
    if (document.getElementById('invoiceTableBody')) loadInvoices();
    if (document.getElementById('salesTableBody')) loadSales();
    if (document.getElementById('reportTableBody')) loadReports();
    if (document.getElementById('warehouseTableBody')) loadWarehouse();

    // 2. จัดการปุ่ม "+ Add" ของทุกหน้า (ประกาศ addBtn แค่ครั้งเดียว)
    const addBtn = document.querySelector('.btn-primary');
    if (addBtn) {
        addBtn.onclick = () => {
            if (document.getElementById('employeeTableBody')) openModal('employeeModal');
            else if (document.getElementById('tenantTableBody')) openModal('tenantModal');
            else if (document.getElementById('spaceTableBody')) openModal('spaceModal');
            else if (document.getElementById('leaseTableBody')) openModal('leaseModal');
            else if (document.getElementById('invoiceTableBody')) openModal('invoiceModal');
        };
    }

    // 3. จัดการการส่งฟอร์ม (Submit) แยกของใครของมัน

    // --- ฟอร์มพนักงาน ---
    const addEmpForm = document.getElementById('addEmployeeForm');
    if (addEmpForm) {
        addEmpForm.onsubmit = async (e) => {
            e.preventDefault(); 
            const res = await callAdminAPI('add_employee', Object.fromEntries(new FormData(addEmpForm)));
            if (res.status === 'success') {
                alert(res.message); closeModal('employeeModal'); loadEmployees();
            } else { alert("Error: " + res.message); }
        };
    }

    // --- ฟอร์มร้านค้า ---
    const tenantForm = document.getElementById('addTenantForm');
    if (tenantForm) {
        tenantForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await callAdminAPI('add_tenant', Object.fromEntries(new FormData(tenantForm)));
            if (res.status === 'success') {
                alert(res.message); closeModal('tenantModal'); loadTenants();
            } else { alert("Error: " + res.message); }
        };
    }

    // --- ฟอร์มพื้นที่ ---
    const spaceForm = document.getElementById('addSpaceForm');
    if (spaceForm) {
        spaceForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await callAdminAPI('add_space', Object.fromEntries(new FormData(spaceForm)));
            if (res.status === 'success') {
                alert(res.message); closeModal('spaceModal'); loadSpaces();
            } else { alert("Error: " + res.message); }
        };
    }

    // --- ฟอร์มสัญญาเช่า ---
    const leaseForm = document.getElementById('addLeaseForm');
    if (leaseForm) {
        leaseForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await callAdminAPI('add_contract', Object.fromEntries(new FormData(leaseForm)));
            if (res.status === 'success') {
                alert(res.message); closeModal('leaseModal'); loadContracts();
            } else { alert("Error: " + res.message); }
        };
    }

    // --- ฟอร์มใบแจ้งหนี้ ---
    const invoiceForm = document.getElementById('addInvoiceForm');
    if (invoiceForm) {
        invoiceForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await callAdminAPI('add_invoice', Object.fromEntries(new FormData(invoiceForm)));
            if (res.status === 'success') {
                alert(res.message); closeModal('invoiceModal'); loadInvoices();
            } else { alert("Error: " + res.message); }
        };
    }

    // --- ฟอร์มบันทึกการขาย (ไม่มี Modal) ---
    // const saleForm = document.getElementById('addSaleForm');
    // if (saleForm) {
    //     saleForm.onsubmit = async (e) => {
    //         e.preventDefault();
    //         const res = await callAdminAPI('add_sale', Object.fromEntries(new FormData(saleForm)));
    //         if (res.status === 'success') {
    //             alert(res.message); saleForm.reset(); loadSales();
    //         } else { alert("Error: " + res.message); }
    //     };
    // }

    // --- ฟอร์มเพิ่มสต็อกสินค้า (Warehouse) ---
    const warehouseForm = document.getElementById('addWarehouseForm');
    if (warehouseForm) {
        warehouseForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await callAdminAPI('add_warehouse_stock', Object.fromEntries(new FormData(warehouseForm)));
            if (res.status === 'success') {
                alert(res.message); closeModal('warehouseModal'); loadWarehouse();
            } else { alert("Error: " + res.message); }
        };
    }

    // --- ฟอร์มสร้างรายงาน ---
    const reportForm = document.getElementById('addReportForm');
    if (reportForm) {
        reportForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await callAdminAPI('add_report', Object.fromEntries(new FormData(reportForm)));
            if (res.status === 'success') {
                alert("ระบบได้สร้างรายงานและดาวน์โหลดเรียบร้อยแล้ว!"); 
                reportForm.reset(); 
                loadReports(); // อัปเดตตารางประวัติด้านล่างทันที
            } else { 
                alert("Error: " + res.message); 
            }
        };
    }

    // --- ส่งข้อมูลแก้ไขร้านค้า ---
    const editTenantForm = document.getElementById('editTenantForm');
    if (editTenantForm) {
        editTenantForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await callAdminAPI('update_tenant', Object.fromEntries(new FormData(editTenantForm)));
            if (res.status === 'success') {
                alert(res.message); closeModal('editTenantModal'); loadTenants();
            } else { alert("Error: " + res.message); }
        };
    }

    // --- ส่งข้อมูลแก้ไขพื้นที่ ---
    const editSpaceForm = document.getElementById('editSpaceForm');
    if (editSpaceForm) {
        editSpaceForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await callAdminAPI('update_space', Object.fromEntries(new FormData(editSpaceForm)));
            if (res.status === 'success') {
                alert(res.message); closeModal('editSpaceModal'); loadSpaces();
            } else { alert("Error: " + res.message); }
        };
    }
});