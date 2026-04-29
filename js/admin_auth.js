// admin/admin_auth.js
document.addEventListener("DOMContentLoaded", () => {
    // ดึงค่า Role จาก sessionStorage (ตั้งค่าตอน Login)
    const userRole = sessionStorage.getItem('role') || 'Employee'; 
    const userName = sessionStorage.getItem('username') || 'User';

    // อัปเดตชื่อผู้ใช้ในหน้าจอ
    const adminNameElements = document.querySelectorAll('.admin-name');
    adminNameElements.forEach(el => el.innerText = userName);

    // จัดการการแสดงผลเมนูตามสิทธิ์
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        const allowed = link.getAttribute('data-allowed');
        
        if (userRole === 'Employee') {
            // Employee ธรรมดา: ห้ามจัดการพนักงาน, ร้านค้าเช่า, และสัญญา
            if (allowed === 'admin-only') link.style.display = 'none';
        } else if (userRole === 'Admin') {
            // Admin: ทำได้หมด ยกเว้น Report
            if (allowed === 'employee-only') link.style.display = 'none';
        }
    });

    // ระบบป้องกันการเข้าหน้าโดยตรงผ่าน URL
    const pageRole = document.body.getAttribute('data-page-role');
    if (pageRole === 'admin-only' && userRole !== 'Admin') {
        alert('เข้าถึงไม่ได้: หน้านี้สำหรับผู้ดูแลระบบ (Admin) เท่านั้น');
        window.location.href = 'dashboard.html';
    } else if (pageRole === 'employee-only' && userRole === 'Admin') {
        alert('เข้าถึงไม่ได้: ผู้ดูแลระบบ (Admin) ไม่ได้รับอนุญาตให้เข้าหน้า Report');
        window.location.href = 'dashboard.html';
    }
});

// ฟังก์ชัน Logout
function handleLogout() {
    sessionStorage.clear();
    window.location.href = '../login.html';
}