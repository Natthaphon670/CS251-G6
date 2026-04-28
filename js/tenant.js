
/* ── LOGOUT FUNCTION ── */
function logout() {
  // ลบข้อมูล session
  sessionStorage.removeItem('isLoggedIn');
  sessionStorage.removeItem('username');
  // Redirect กลับไปหน้า Login
  window.location.href = '../login.html';
}

/* ── ผูก Event กับปุ่มออกจากระบบทุกปุ่มในหน้า ── */
document.addEventListener('DOMContentLoaded', () => {
  const logoutBtns = document.querySelectorAll('.btn-logout');
  logoutBtns.forEach(btn => {
    btn.addEventListener('click', logout);
  });
});