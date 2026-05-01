/* ── TOGGLE PASSWORD VISIBILITY ── */
const pwInput  = document.getElementById('password');
const togglePw = document.getElementById('togglePw');
const eyeIcon  = document.getElementById('eyeIcon');

const eyeOpen = `
  <path stroke-linecap="round" stroke-linejoin="round"
    d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
  <circle cx="12" cy="12" r="3"/>
`;
const eyeClosed = `
  <path stroke-linecap="round" stroke-linejoin="round"
    d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
       a18.45 18.45 0 0 1 5.06-5.94"/>
  <path stroke-linecap="round" stroke-linejoin="round"
    d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
       a18.5 18.5 0 0 1-2.16 3.19"/>
  <line x1="1" y1="1" x2="23" y2="23" stroke-linecap="round"/>
`;

togglePw.addEventListener('click', () => {
  const isHidden = pwInput.type === 'password';
  pwInput.type = isHidden ? 'text' : 'password';
  eyeIcon.innerHTML = isHidden ? eyeClosed : eyeOpen;
});

/* ── RIPPLE EFFECT ── */
function createRipple(btn, e) {
  const r    = document.createElement('span');
  r.classList.add('ripple');
  const rect = btn.getBoundingClientRect();
  const size = Math.max(rect.width, rect.height);
  r.style.cssText = `
    width:${size}px;
    height:${size}px;
    left:${e.clientX - rect.left - size / 2}px;
    top:${e.clientY  - rect.top  - size / 2}px
  `;
  btn.appendChild(r);
  r.addEventListener('animationend', () => r.remove());
}

/* ── SIGN IN LOGIC (เชื่อมต่อ API จริง) ── */
const signInBtn = document.getElementById('signInBtn');
const errorMsg  = document.getElementById('errorMsg');
const errorText = document.getElementById('errorText');

signInBtn.addEventListener('click', async (e) => {
  createRipple(signInBtn, e);
  errorMsg.classList.remove('show');

  const user = document.getElementById('username').value.trim();
  const pass = document.getElementById('password').value;

  if (!user || !pass) {
    errorText.textContent = 'Please enter your username and password.';
    errorMsg.classList.add('show');
    return;
  }

  signInBtn.classList.add('loading');
  signInBtn.disabled = true;
  
  try {
    const response = await fetch('api/auth.php?action=login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: user, password: pass })
    });

    const text = await response.text(); 
    console.log("PHP Response:", text); 

    const result = JSON.parse(text);

    signInBtn.classList.remove('loading');
    signInBtn.disabled = false;

    // ตรวจสอบ status ว่าเป็น 200 (Success) หรือไม่
    if (result.status === 200) {
      // จุดสำคัญ: อิงตาม PHP ล่าสุด ข้อมูลอยู่ที่ result.data โดยตรง
      const userData = result.data; 
      
      // บันทึกข้อมูลลง SessionStorage เพื่อใช้ในหน้า Dashboard
      sessionStorage.setItem('isLoggedIn', 'true');
      sessionStorage.setItem('account_id', userData.account_id);
      sessionStorage.setItem('username', userData.username);
      sessionStorage.setItem('role', userData.role);

      // ถ้าเป็น Tenant ให้เก็บรหัสร้านค้าเพื่อใช้ดึงข้อมูลยอดขาย
      if (userData.role && userData.role.toLowerCase() === 'tenant') {
          sessionStorage.setItem('TenantID', userData.tenant_id);
          sessionStorage.setItem('tenant_id', userData.tenant_id);
          sessionStorage.setItem('TenantName', userData.tenant_name);
      }

      // แสดงสถานะสำเร็จก่อนเปลี่ยนหน้า
      signInBtn.style.background = '#16a34a';
      signInBtn.querySelector('.btn-text').textContent = '✓ Success!';

      // เปลี่ยนหน้าตามสิทธิ์ของผู้ใช้งาน
      setTimeout(() => {
        if (userData.role && userData.role.toLowerCase() === 'tenant') {
            window.location.href = 'tenant/index.html'; 
        } else {
            window.location.href = 'admin/dashboard.html'; 
        }
      }, 600);

    } else {
      // กรณีรหัสผิด หรือ PHP ส่ง Error อื่นๆ มา
      errorText.textContent = result.message || 'Username or password is incorrect.';
      errorMsg.classList.add('show');
      shakeCard();
    }
  } catch (error) {
    signInBtn.classList.remove('loading');
    signInBtn.disabled = false;
    console.error("Login Error:", error);
    errorText.textContent = 'พบปัญหาในการเชื่อมต่อ: ' + error.message;
    errorMsg.classList.add('show');
    shakeCard();
  }
});

/* ── SHAKE ANIMATION ON WRONG LOGIN ── */
function shakeCard() {
  const card = document.querySelector('.card');
  card.style.animation  = 'none';
  card.style.transition = 'transform .08s ease';
  card.style.transform  = 'translateX(-8px)';
  setTimeout(() => { card.style.transform = 'translateX(8px)';  }, 80);
  setTimeout(() => { card.style.transform = 'translateX(-5px)'; }, 160);
  setTimeout(() => { card.style.transform = 'translateX(0)';    }, 240);
  setTimeout(() => { card.style.transition = ''; }, 300);
}

/* ── SUBMIT ON ENTER KEY ── */
document.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') signInBtn.click();
});

/* ── LOGIN AS GUEST ── */
const guestBtn = document.getElementById('guestBtn');

guestBtn.addEventListener('click', () => {
  guestBtn.disabled = true;
  guestBtn.textContent = 'Entering as Guest…';

  setTimeout(() => {
    guestBtn.disabled = false;
    guestBtn.innerHTML = `
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 14c-5 0-8 2.5-8 4v1h16v-1c0-1.5-3-4-8-4z"/>
      </svg>
      Continue as Guest
    `;
    window.location.href = 'index.html'; // เปลี่ยนไปหน้า Public 
  }, 900);
});