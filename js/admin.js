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
                    <button style="background: #ffc107; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;">แก้ไข</button>
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
                <td style="padding: 12px;"><button class="btn-edit" style="background: #ffc107; border: none; padding: 5px 10px; cursor: pointer; border-radius: 3px;">แก้ไข</button></td>
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