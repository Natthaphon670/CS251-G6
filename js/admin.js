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
        tbody.innerHTML = res.data.map(space => `
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;">${space.SpaceID}</td>
                <td style="padding: 12px;">${space.Floor}</td>
                <td style="padding: 12px;">${space.Location}</td>
                <td style="padding: 12px;">${space.Size}</td>
                <td style="padding: 12px;"><span style="background: #28a745; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px;">ว่าง</span></td>
                <td style="padding: 12px;">
                    <button onclick="prepareEditSpace('${space.SpaceID}')" style="background: #ffc107; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;">แก้ไข</button>
                </td>
            </tr>
        `).join('');
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
        tbody.innerHTML = res.data.map(con => `
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;">${con.ContractID}</td>
                <td style="padding: 12px;">${con.TenantName}</td>
                <td style="padding: 12px;">${con.StartDateLease}</td>
                <td style="padding: 12px;">${con.EndDateLease}</td>
                <td style="padding: 12px;"><span style="background: #28a745; color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px;">Active</span></td>
                <td style="padding: 12px;"><a href="#" style="color: #007bff;">📄 ดู PDF</a></td>
            </tr>
        `).join('');
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
async function loadSales() {
    const res = await callAdminAPI('get_sales');
    const tbody = document.getElementById('salesTableBody');
    if (res.status === 'success' && tbody) {
        tbody.innerHTML = res.data.map(sale => `
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;">${sale.SalesID}</td>
                <td style="padding: 12px;">${sale.SalesDate}</td>
                <td style="padding: 12px;">${sale.TenantName}</td>
                <td style="padding: 12px; color: green; font-weight: bold;">฿ ${Number(sale.Quantity * 100).toLocaleString()}</td> 
                <td style="padding: 12px;">${sale.ProductName} (x${sale.Quantity})</td>
            </tr>
        `).join('');
    }
}

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

// --- Warehouse (คลังสินค้าส่วนกลาง) ---
async function loadWarehouse() {
    const res = await callAdminAPI('get_warehouse');
    const tbody = document.getElementById('warehouseTableBody');
    if (res.status === 'success' && tbody) {
        tbody.innerHTML = res.data.map(item => `
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px;">${item.ProductID}</td>
                <td style="padding: 12px;">${item.ProductName}</td>
                <td style="padding: 12px;">${item.CategoryName || '-'}</td>
                <td style="padding: 12px;"><strong style="${item.WarehouseQuantity < 50 ? 'color: red;' : ''}">${item.WarehouseQuantity}</strong></td>
                <td style="padding: 12px;">${item.SupplierName || 'ทั่วไป'}</td>
            </tr>
        `).join('');
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
            else if (document.getElementById('warehouseTableBody')) openModal('warehouseModal');
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
    const saleForm = document.getElementById('addSaleForm');
    if (saleForm) {
        saleForm.onsubmit = async (e) => {
            e.preventDefault();
            const res = await callAdminAPI('add_sale', Object.fromEntries(new FormData(saleForm)));
            if (res.status === 'success') {
                alert(res.message); saleForm.reset(); loadSales();
            } else { alert("Error: " + res.message); }
        };
    }

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