/* ── ฟังก์ชันส่วนกลางสำหรับดึง API (ใช้กับหน้า Public) ── */
async function fetchPublicAPI(action, params = '') {
    try {
        const url = `api/public_api.php?action=${action}${params ? '&' + params : ''}`;
        const response = await fetch(url);
        return await response.json();
    } catch (error) {
        console.error(`API Error (${action}):`, error);
        return { success: false, message: 'การเชื่อมต่อผิดพลาด' };
    }
}

/* ========================================================
   ส่วนของหน้า Homepage (index.html)
======================================================== */

// ── กลไกควบคุมการสไลด์ (Carousel Engine) ──
function initCarousel(trackId, leftId, rightId) {
    const track = document.getElementById(trackId);
    const btnL  = document.getElementById(leftId);
    const btnR  = document.getElementById(rightId);
    
    if (!track || !btnL || !btnR) return; 

    const VISIBLE = 4;
    let step = 0;

    function getCardW() {
        const c = track.children[0];
        return c ? c.offsetWidth + 12 : 0;
    }
    function maxStep() { return Math.max(0, track.children.length - VISIBLE); }

    function render() {
        const max = maxStep();
        step = Math.max(0, Math.min(step, max));
        track.style.transform = `translateX(-${step * getCardW()}px)`;
        btnL.disabled = step === 0;
        btnR.disabled = step >= max;
    }

    btnL.addEventListener('click', () => { step--; render(); });
    btnR.addEventListener('click', () => { step++; render(); });
    window.addEventListener('resize', render);
    
    setTimeout(render, 100); 
}

// ── ดึงข้อมูลโปรโมชัน (หน้าแรก) ──
async function loadHomePromotions() {
    const promoTrack = document.getElementById('promoTrack');
    if (!promoTrack) return;

    const result = await fetchPublicAPI('get_promotions');
    promoTrack.innerHTML = '';

    if (result.success && result.data.length > 0) {
        result.data.forEach(p => {
            const card = document.createElement('div');
            card.className = 'promo-card';
            card.innerHTML = `
                <div class="promo-img-area" style="display: flex; flex-direction: column; justify-content: center; align-items: center; background: #fff1f2;">
                    <div class="promo-badge" style="margin-bottom: 5px;">ลด ${parseFloat(p.Discount)}%</div>
                    <h4 style="margin: 0 10px; color: #be123c; text-align: center; font-size: 1rem;">${p.PromotionName}</h4>
                    <span style="font-size: 0.8rem; color: #881337; margin-top: 5px;">${p.TenantName}</span>
                </div>
                <div class="promo-footer">
                    <span class="exp-label">หมดเขต: ${p.EndDatePromotion}</span>
                </div>`;
            promoTrack.appendChild(card);
        });
    } else {
        promoTrack.innerHTML = '<p style="padding: 20px; color: #666;">ยังไม่มีโปรโมชันในขณะนี้</p>';
    }
    
    initCarousel('promoTrack', 'promoLeft', 'promoRight');
}

// ── ดึงข้อมูลสินค้าใส่สไลด์ (หน้าแรก) ──
async function loadHomeProducts() {
    const prodTrack = document.getElementById('prodTrack');
    if (!prodTrack) return;

    const result = await fetchPublicAPI('get_products');
    prodTrack.innerHTML = '';

    if (result.success && result.data.length > 0) {
        const displayProducts = result.data.slice(0, 10);
        displayProducts.forEach(p => {
            const card = document.createElement('div');
            card.className = 'prod-card';
            card.style.cursor = 'pointer';
            card.onclick = () => window.location.href = `product_detail.html?product_id=${p.ProductID}`;
            card.innerHTML = `
                <div class="prod-img-area" style="display: flex; justify-content: center; align-items: center; background: #f8f9fa;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                </div>
                <div class="prod-footer" style="display: flex; flex-direction: column; align-items: flex-start; padding: 10px;">
                    <span class="prod-name" style="color: #333; font-weight: 600; margin-bottom: 5px;">
                        ${p.ProductName}
                    </span>
                    <span style="color: #16a34a; font-weight: bold;">฿ ${parseFloat(p.ProductPrice).toLocaleString()}</span>
                </div>`;
            prodTrack.appendChild(card);
        });
    } else {
        prodTrack.innerHTML = '<p style="padding: 20px; color: #666;">ยังไม่มีสินค้า</p>';
    }

    initCarousel('prodTrack', 'prodLeft', 'prodRight');
}

/* ========================================================
   ส่วนของหน้า Product Catalog และ Shop Directory
======================================================== */

async function loadCatalogProducts() {
    const productGrid = document.getElementById('productGrid');
    if (!productGrid) return; 

    productGrid.innerHTML = '<p style="color: #666; text-align: center; width: 100%;">กำลังโหลดข้อมูลสินค้า...</p>';
    const result = await fetchPublicAPI('get_products');
    productGrid.innerHTML = ''; 

    if (result.success && result.data.length > 0) {
        result.data.forEach(p => {
            productGrid.innerHTML += `
                <div class="prod-card" onclick="window.location.href='product_detail.html?product_id=${p.ProductID}'" style="cursor: pointer; padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: white; transition: 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                    <div class="prod-img-area" style="display: flex; justify-content: center; align-items: center; background: #f8f9fa; height: 150px; border-radius: 6px; margin-bottom: 15px;">
                        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                    </div>
                    <div class="prod-footer" style="display: flex; flex-direction: column; align-items: flex-start;">
                        <span style="font-weight: 600; margin-bottom: 8px; color: #333; font-size: 1.1rem; display: block; width: 100%;">
                            ${p.ProductName}
                        </span>
                        <span style="color: #16a34a; font-weight: bold; font-size: 1.2rem;">฿ ${parseFloat(p.ProductPrice).toLocaleString()}</span>
                    </div>
                </div>
            `;
        });
    } else {
        productGrid.innerHTML = `<p style="color:red; text-align: center; width: 100%;">ไม่พบข้อมูลสินค้า</p>`;
    }
}

async function loadShopsDirectory() {
    const shopGrid = document.getElementById('shopGrid');
    if (!shopGrid) return;

    shopGrid.innerHTML = '<p style="color: #666; text-align: center; width: 100%;">กำลังโหลดข้อมูลร้านค้า...</p>';
    const result = await fetchPublicAPI('get_tenants');
    shopGrid.innerHTML = ''; 

    if (result.success && result.data.length > 0) {
        result.data.forEach(shop => {
            shopGrid.innerHTML += `
                <div class="shop-card" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #eee; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <h3 style="margin: 0 0 8px 0; color: #333; font-size: 1.3rem;">${shop.TenantName}</h3>
                    <span style="display: inline-block; padding: 4px 10px; background: #e0f2fe; color: #0284c7; border-radius: 20px; font-size: 13px; margin-bottom: 15px; font-weight: 500;">
                        ${shop.TenantCategory || 'ทั่วไป'}
                    </span>
                    <div style="font-size: 15px; color: #555; line-height: 1.6;">
                        <p style="margin: 5px 0;"><strong>สถานที่ ชั้น:</strong> ${shop.Floor ? shop.Floor : '-'}</p>
                        <p style="margin: 5px 0;"><strong>โซน:</strong> ${shop.Location ? shop.Location : 'ไม่มีข้อมูลระบุตำแหน่ง'}</p>
                    </div>
                </div>
            `;
        });
    } else {
        shopGrid.innerHTML = `<p style="color:red; text-align: center; width: 100%;">ไม่พบข้อมูลร้านค้า</p>`;
    }
}

function applyHoverEffects() {
    document.addEventListener('mouseover', function(e) {
        const card = e.target.closest('.prod-card, .shop-card');
        if(card) {
            card.style.transform = 'translateY(-5px)';
            card.style.boxShadow = '0 8px 15px rgba(0,0,0,0.1)';
        }
    });
    
    document.addEventListener('mouseout', function(e) {
        const card = e.target.closest('.prod-card, .shop-card');
        if(card) {
            card.style.transform = 'translateY(0)';
            card.style.boxShadow = '0 2px 5px rgba(0,0,0,0.05)';
        }
    });
}

/* ========================================================
   ส่วนของหน้า Product Detail
======================================================== */

async function loadProductDetail() {
    const productContainer = document.getElementById('productContainer');
    if (!productContainer) return; 

    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('product_id');

    if (!productId) {
        productContainer.innerHTML = '<h3 style="color:red; text-align:center;">ไม่พบรหัสสินค้า กรุณากลับไปเลือกสินค้าใหม่</h3>';
        return;
    }

    const result = await fetchPublicAPI('get_product_detail', `product_id=${productId}`);

    if (result.success) {
        const p = result.data;
        let promoHtml = '';
        
        if (p.ActivePromotions && p.ActivePromotions.length > 0) {
            promoHtml = `
            <div class="promo-box" style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 20px; border: 1px solid #ffeeba;">
                <strong style="color: #856404;">โปรโมชันที่ร่วมรายการ:</strong>
                <ul style="margin: 10px 0 0 20px; color: #856404;">
                    ${p.ActivePromotions.map(promo => `
                        <li>${promo.PromotionName} (ลด ${promo.Discount}%) - <strong>เหลือเพียง ฿ ${parseFloat(promo.PriceAfterDiscount).toLocaleString()}</strong></li>
                    `).join('')}
                </ul>
            </div>`;
        }

        productContainer.innerHTML = `
            <h1 style="margin-top: 0; color: #333; font-size: 2rem;">${p.ProductName}</h1>
            <div style="margin: 20px 0; line-height: 1.8; color: #444; font-size: 16px;">
                <p><strong>รายละเอียด:</strong> <br>${p.ProductDescription || 'ไม่มีคำอธิบาย'}</p>
                <p style="font-size: 28px; font-weight: bold; color: #16a34a; margin: 30px 0;">฿ ${parseFloat(p.ProductPrice).toLocaleString()}</p>
                <p style="padding: 10px; background: #f8f9fa; border-left: 4px solid ${p.InStock ? '#16a34a' : '#ef4444'};">
                    <strong>สถานะสินค้า:</strong> 
                    <span style="color: ${p.InStock ? '#16a34a' : '#ef4444'}; font-weight: bold; margin-left: 10px;">
                        ${p.InStock ? `มีสินค้าในสต็อก (${p.TotalStock} ชิ้น)` : 'สินค้าหมด'}
                    </span>
                </p>
            </div>
            ${promoHtml}
        `;
    } else {
        productContainer.innerHTML = `<h3 style="color:red; text-align:center;">${result.message}</h3>`;
    }
}

// ── ทำงานอัตโนมัติเมื่อโหลดหน้าเว็บเสร็จ ──
document.addEventListener('DOMContentLoaded', () => {
    loadHomePromotions();
    loadHomeProducts();
    loadCatalogProducts();
    loadShopsDirectory();
    loadProductDetail();
    applyHoverEffects();
});