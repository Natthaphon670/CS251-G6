//จัดการสัญญา (manage_contract.html)
const loadContracts = async () => {
    const res = await callAdminAPI('get_contracts');
    // รอเอาข้อมูลไปใส่ Table ใน HTML
    console.log(res);
};

//จัดการพนักงาน (manage_employee.html)
const addEmployee = async (employeeData) => {
    const res = await callAdminAPI('add_employee', employeeData);
    if(res.success) alert('เพิ่มพนักงานสำเร็จ');
};

//จัดการใบแจ้งหนี้ (manage_invoice.html)
const createInvoice = async (tenantId, amount) => {
    return await callAdminAPI('create_invoice', { tenantId, amount });
};

// เตรียมจัดการการส่ง Form
const handleAdminForm = async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());
    
    // ส่งไปหา API ตามประเภทงาน
    // const result = await callAdminAPI('submit_form', data);
};

// 1. ฟังก์ชันสำหรับสลับหน้าจอ (Menu Navigation)
function showSection(id) {
    // ซ่อนทุกหน้า
    document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
    // ลบคลาส active จากเมนูทั้งหมด
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    
    // แสดงหน้าที่เลือก
    document.getElementById(id).style.display = 'block';
    
    // เพิ่มคลาส active ให้เมนูที่ถูกคลิก
    if (window.event) {
        window.event.currentTarget.classList.add('active');
    }
    
    // เปลี่ยนชื่อหัวข้อ (Header) ให้ตรงกับเมนู
    if (window.event) {
        document.getElementById('current-page-title').innerText = window.event.currentTarget.innerText;
    }
}

// 2. ฟังก์ชันเปิด/ปิด Dropdown โปรไฟล์
function toggleDropdown(event) {
    event.stopPropagation(); // ป้องกันไม่ให้การคลิกทะลุไปโดนส่วนอื่น
    document.getElementById("profileDropdown").classList.toggle("show");
}

// 3. ฟังก์ชันปิด Dropdown อัตโนมัติเมื่อคลิกที่ว่างบนหน้าจอ
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

