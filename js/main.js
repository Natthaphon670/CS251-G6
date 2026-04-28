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

/* ── SIGN IN LOGIC ── */
const signInBtn = document.getElementById('signInBtn');
const errorMsg  = document.getElementById('errorMsg');
const errorText = document.getElementById('errorText');

// Demo credentials — replace with real API auth
const DEMO_USER = 'admin';
const DEMO_PASS = '1234';

signInBtn.addEventListener('click', async (e) => {
  createRipple(signInBtn, e);
  errorMsg.classList.remove('show');

  const user = document.getElementById('username').value.trim();
  const pass = document.getElementById('password').value;

  // Validation
  if (!user || !pass) {
    errorText.textContent = 'Please enter your username and password.';
    errorMsg.classList.add('show');
    return;
  }

  // Show loading state
  signInBtn.classList.add('loading');
  signInBtn.disabled = true;

  // Simulate network delay (replace with actual fetch/axios call)
  await new Promise(resolve => setTimeout(resolve, 1200));

  signInBtn.classList.remove('loading');
  signInBtn.disabled = false;

  if (user === DEMO_USER && pass === DEMO_PASS) {
    // ✅ Login success — บันทึก session และ redirect ไปหน้า Tenant
    sessionStorage.setItem('isLoggedIn', 'true');
    sessionStorage.setItem('username', user);

    signInBtn.style.background = '#16a34a';
    signInBtn.querySelector('.btn-text').textContent = '✓  Success!';

    setTimeout(() => {
      window.location.href = 'tenant/index.html';
    }, 600);
  } else {
    // ❌ Login failed
    errorText.textContent = 'Username or password is incorrect.';
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
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M12 14c-5 0-8 2.5-8 4v1h16v-1c0-1.5-3-4-8-4z"/>
      </svg>
      Continue as Guest
    `;
    // Replace with actual guest redirect, e.g.: window.location.href = '/guest-dashboard';
    alert('Continuing as Guest! (demo)');
  }, 900);
});