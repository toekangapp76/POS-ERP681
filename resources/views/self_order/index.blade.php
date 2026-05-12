<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Self Order — {{ $table->name }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f4f5f7;color:#222;min-height:100vh;padding-bottom:100px}

        /* ── Header ── */
        .so-header{background:linear-gradient(135deg,#2d3a8c,#4a56c4);color:#fff;padding:14px 16px;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.25)}
        .so-header h1{font-size:16px;font-weight:700;letter-spacing:.3px}
        .so-header .sub{font-size:12px;opacity:.8;margin-top:2px}
        .so-header .cart-btn{position:absolute;right:16px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.2);border:none;color:#fff;padding:8px 14px;border-radius:20px;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:6px}
        .so-header .cart-btn .badge{background:#ff4d4f;color:#fff;border-radius:10px;padding:0 6px;font-size:11px;font-weight:bold;min-width:18px;text-align:center}

        /* ── Pax prompt ── */
        #pax_screen{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;display:flex;align-items:center;justify-content:center}
        .pax-box{background:#fff;border-radius:16px;padding:28px 24px;width:300px;text-align:center}
        .pax-box h2{font-size:18px;color:#2d3a8c;margin-bottom:6px}
        .pax-box p{font-size:13px;color:#666;margin-bottom:20px}
        .pax-ctrl{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:24px}
        .pax-ctrl button{width:40px;height:40px;border-radius:50%;border:2px solid #2d3a8c;background:#fff;font-size:22px;cursor:pointer;color:#2d3a8c;font-weight:bold;line-height:1}
        .pax-ctrl input{width:70px;text-align:center;font-size:28px;font-weight:bold;border:2px solid #ddd;border-radius:8px;padding:4px}
        .pax-confirm{width:100%;padding:12px;background:#2d3a8c;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer}

        /* ── Category tabs ── */
        .cat-tabs{display:flex;overflow-x:auto;gap:8px;padding:12px 16px;background:#fff;border-bottom:1px solid #eee;scrollbar-width:none;position:sticky;top:58px;z-index:90}
        .cat-tabs::-webkit-scrollbar{display:none}
        .cat-tab{white-space:nowrap;padding:6px 16px;border-radius:20px;border:1.5px solid #ddd;background:#fff;font-size:13px;cursor:pointer;flex-shrink:0;transition:all .2s}
        .cat-tab.active{background:#2d3a8c;color:#fff;border-color:#2d3a8c;font-weight:600}

        /* ── Product grid ── */
        .menu-section{padding:12px 16px}
        .menu-section h2{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;margin-bottom:10px}
        .product-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
        @media(min-width:500px){.product-grid{grid-template-columns:repeat(3,1fr)}}
        @media(min-width:800px){.product-grid{grid-template-columns:repeat(4,1fr)}}

        .product-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);cursor:pointer;transition:transform .15s,box-shadow .15s;position:relative}
        .product-card:active{transform:scale(.97)}
        .product-card img{width:100%;aspect-ratio:4/3;object-fit:cover;background:#f0f0f0}
        .product-card .pc-body{padding:8px 10px 10px}
        .product-card .pc-name{font-size:13px;font-weight:600;line-height:1.3;margin-bottom:4px}
        .product-card .pc-price{font-size:13px;color:#2d3a8c;font-weight:700}
        .product-card .pc-add{position:absolute;bottom:10px;right:10px;background:#2d3a8c;color:#fff;border:none;width:28px;height:28px;border-radius:50%;font-size:18px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center}

        /* ── Variation picker modal ── */
        #var_modal{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:300;display:none;align-items:flex-end;justify-content:center}
        #var_modal.show{display:flex}
        .var-sheet{background:#fff;border-radius:16px 16px 0 0;width:100%;max-width:520px;padding:20px;max-height:80vh;overflow-y:auto}
        .var-sheet h3{font-size:16px;font-weight:700;margin-bottom:4px}
        .var-sheet .var-desc{font-size:12px;color:#888;margin-bottom:16px}
        .var-item{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f0f0f0}
        .var-item:last-child{border-bottom:none}
        .var-name{font-size:14px;font-weight:500}
        .var-price{font-size:14px;color:#2d3a8c;font-weight:700}
        .var-add{background:#2d3a8c;color:#fff;border:none;padding:6px 18px;border-radius:20px;font-size:13px;cursor:pointer}
        .note-row{margin-top:14px}
        .note-row label{font-size:12px;color:#666;display:block;margin-bottom:4px}
        .note-row input{width:100%;border:1.5px solid #ddd;border-radius:8px;padding:8px 10px;font-size:13px}
        .var-close{margin-top:14px;width:100%;padding:10px;border:1.5px solid #ddd;border-radius:8px;background:#fff;font-size:14px;cursor:pointer}

        /* ── Cart drawer ── */
        #cart_drawer{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:300;display:none;align-items:flex-end;justify-content:center}
        #cart_drawer.show{display:flex}
        .cart-sheet{background:#fff;border-radius:16px 16px 0 0;width:100%;max-width:520px;padding:0;max-height:85vh;display:flex;flex-direction:column}
        .cart-header{padding:16px 20px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between}
        .cart-header h3{font-size:16px;font-weight:700}
        .cart-header button{background:none;border:none;font-size:20px;cursor:pointer;color:#999}
        .cart-body{flex:1;overflow-y:auto;padding:12px 20px}
        .cart-item{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #f5f5f5}
        .cart-item:last-child{border-bottom:none}
        .ci-info{flex:1}
        .ci-name{font-size:13px;font-weight:600}
        .ci-note{font-size:11px;color:#999;margin-top:2px}
        .ci-price{font-size:13px;color:#2d3a8c;font-weight:700;margin-top:2px}
        .ci-qty{display:flex;align-items:center;gap:8px}
        .ci-qty button{width:28px;height:28px;border-radius:50%;border:1.5px solid #ddd;background:#fff;font-size:16px;cursor:pointer;font-weight:bold;color:#444;line-height:1;display:flex;align-items:center;justify-content:center}
        .ci-qty span{min-width:24px;text-align:center;font-weight:600;font-size:14px}
        .cart-footer{padding:16px 20px;border-top:1px solid #eee;background:#fff}
        .cart-total{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
        .cart-total .label{font-size:14px;color:#666}
        .cart-total .amount{font-size:18px;font-weight:700;color:#2d3a8c}
        .cart-submit{width:100%;padding:14px;background:#2d3a8c;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer}
        .cart-submit:disabled{background:#aaa;cursor:not-allowed}
        .cart-empty{text-align:center;padding:40px 0;color:#aaa;font-size:14px}

        /* ── Order sent ── */
        #order_sent{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:400;display:none;align-items:center;justify-content:center}
        #order_sent.show{display:flex}
        .os-box{background:#fff;border-radius:16px;padding:36px 28px;text-align:center;width:280px}
        .os-box .os-icon{font-size:48px;color:#52c41a;margin-bottom:16px}
        .os-box h3{font-size:18px;font-weight:700;color:#222;margin-bottom:8px}
        .os-box p{font-size:13px;color:#666;margin-bottom:24px}
        .os-box button{padding:10px 28px;background:#2d3a8c;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}

        /* ── Current order status bar ── */
        #order_bar{display:none;background:#fff6e0;border-top:2px solid #faad14;padding:10px 16px;position:fixed;bottom:0;left:0;right:0;z-index:80;font-size:13px}
        #order_bar.show{display:flex;align-items:center;justify-content:space-between}
        #order_bar .ob-info{color:#7c5e00;font-weight:600}
        #order_bar .ob-view{color:#2d3a8c;font-weight:600;text-decoration:underline;cursor:pointer;background:none;border:none;font-size:13px}

        .loading-spinner{display:none;text-align:center;padding:40px;color:#aaa}
    </style>
</head>
<body>

{{-- Header --}}
<div class="so-header" style="position:relative">
    <h1><i class="fas fa-utensils"></i> {{ config('app.name') }}</h1>
    <div class="sub">Meja: <strong>{{ $table->name }}</strong></div>
    <button class="cart-btn" onclick="openCart()">
        <i class="fas fa-shopping-cart"></i>
        <span class="badge" id="cart_count">0</span>
    </button>
</div>

{{-- PAX prompt (shown on first visit) --}}
<div id="pax_screen" @if($existing_transaction) style="display:none" @endif>
    <div class="pax-box">
        <h2><i class="fas fa-users" style="color:#2d3a8c"></i> Selamat datang!</h2>
        <p>Berapa jumlah tamu di meja <strong>{{ $table->name }}</strong>?</p>
        <div class="pax-ctrl">
            <button onclick="adjPax(-1)">−</button>
            <input type="number" id="pax_val" value="2" min="1" max="99">
            <button onclick="adjPax(1)">+</button>
        </div>
        <button class="pax-confirm" onclick="confirmPax()">
            <i class="fas fa-check"></i> Mulai Pesan
        </button>
    </div>
</div>

{{-- Category tabs --}}
<div class="cat-tabs" id="cat_tabs"></div>

{{-- Menu content --}}
<div id="menu_content"></div>

{{-- Variation picker modal --}}
<div id="var_modal">
    <div class="var-sheet" id="var_sheet_body"></div>
</div>

{{-- Cart drawer --}}
<div id="cart_drawer">
    <div class="cart-sheet">
        <div class="cart-header">
            <h3><i class="fas fa-shopping-cart"></i> Pesanan Saya</h3>
            <button onclick="closeCart()">&times;</button>
        </div>
        <div class="cart-body" id="cart_body"></div>
        <div class="cart-footer">
            <div class="cart-total">
                <span class="label">Total</span>
                <span class="amount" id="cart_grand_total">Rp 0</span>
            </div>
            <button class="cart-submit" id="cart_submit_btn" onclick="submitOrder()">
                <i class="fas fa-paper-plane"></i> Kirim Pesanan
            </button>
        </div>
    </div>
</div>

{{-- Order sent confirmation --}}
<div id="order_sent">
    <div class="os-box">
        <div class="os-icon"><i class="fas fa-check-circle"></i></div>
        <h3>Pesanan Terkirim!</h3>
        <p>Pesanan Anda sedang diproses oleh dapur.</p>
        <button onclick="closeOrderSent()">Pesan Lagi</button>
    </div>
</div>

{{-- Existing order bar --}}
<div id="order_bar" @if($existing_transaction) class="show" @endif>
    <span class="ob-info"><i class="fas fa-receipt"></i> Ada pesanan aktif</span>
    <button class="ob-view" onclick="viewCurrentOrder()">Lihat &rsaquo;</button>
</div>

<script>
const TOKEN        = '{{ $token }}';
const PLACE_URL    = '/self-order/' + TOKEN + '/order';
const STATUS_URL   = '/self-order/' + TOKEN + '/status';
const CSRF         = document.querySelector('meta[name="csrf-token"]').content;
const HAS_EXISTING = {{ $existing_transaction ? 'true' : 'false' }};
const EXISTING_ID  = {{ $existing_transaction ? $existing_transaction->id : 'null' }};

let cart      = [];  // [{variation_id, name, price, quantity, note}]
let pax       = {{ $existing_transaction ? ($existing_transaction->pax ?? 2) : 2 }};
let menuData  = [];
let _pendingVariation = null;

// ── Init ────────────────────────────────────────────────────────────────────
(function init() {
    loadMenu();
    if (HAS_EXISTING) updateOrderBar(true);
})();

// ── PAX ─────────────────────────────────────────────────────────────────────
function adjPax(d) {
    const inp = document.getElementById('pax_val');
    let v = parseInt(inp.value) || 1;
    v = Math.max(1, Math.min(99, v + d));
    inp.value = v;
}
function confirmPax() {
    pax = parseInt(document.getElementById('pax_val').value) || 1;
    document.getElementById('pax_screen').style.display = 'none';
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function loadMenu() {
    fetch('/self-order/' + TOKEN + '/menu')
        .then(r => r.json())
        .then(data => {
            menuData = data;
            renderTabs(data);
            renderMenu(data);
        });
}

function renderTabs(data) {
    const tabs = document.getElementById('cat_tabs');
    tabs.innerHTML = data.map((c, i) =>
        `<button class="cat-tab ${i===0?'active':''}" onclick="scrollToSection(${i},this)">${c.category}</button>`
    ).join('');
}

function renderMenu(data) {
    const mc = document.getElementById('menu_content');
    mc.innerHTML = data.map((cat, ci) => `
        <div class="menu-section" id="sec_${ci}">
            <h2>${cat.category}</h2>
            <div class="product-grid">
                ${cat.items.map(p => renderCard(p)).join('')}
            </div>
        </div>
    `).join('');
}

function renderCard(p) {
    const firstVar = p.variations[0];
    const price    = firstVar ? formatRp(firstVar.price) : '-';
    return `
        <div class="product-card" onclick="openVariation(${JSON.stringify(p).replace(/"/g,'&quot;')})">
            <img src="${p.image_url}" alt="${p.name}" loading="lazy" onerror="this.src='/img/default.png'">
            <div class="pc-body">
                <div class="pc-name">${p.name}</div>
                <div class="pc-price">${price}</div>
            </div>
            <button class="pc-add" onclick="event.stopPropagation();openVariation(${JSON.stringify(p).replace(/"/g,'&quot;')})">+</button>
        </div>
    `;
}

function scrollToSection(idx, btn) {
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    const sec = document.getElementById('sec_' + idx);
    if (sec) sec.scrollIntoView({behavior:'smooth', block:'start'});
}

// ── Variation picker ─────────────────────────────────────────────────────────
function openVariation(product) {
    _pendingVariation = product;
    const modal = document.getElementById('var_modal');
    const body  = document.getElementById('var_sheet_body');

    const hasMulti = product.variations.length > 1;
    const noteId   = 'vnote_' + product.id;

    body.innerHTML = `
        <h3>${product.name}</h3>
        ${product.description ? `<div class="var-desc">${product.description}</div>` : ''}
        ${product.variations.map(v => `
            <div class="var-item">
                <div>
                    <div class="var-name">${hasMulti ? v.name : 'Pesan'}</div>
                    <div class="var-price">${formatRp(v.price)}</div>
                </div>
                <button class="var-add" onclick="addToCart(${v.id},'${escHtml(product.name + (hasMulti?' - '+v.name:''))}',${v.price}, document.getElementById('${noteId}').value)">Tambah</button>
            </div>
        `).join('')}
        <div class="note-row">
            <label>Catatan (opsional)</label>
            <input type="text" id="${noteId}" placeholder="Misal: tidak pedas, tanpa bawang...">
        </div>
        <button class="var-close" onclick="closeVariation()">Tutup</button>
    `;
    modal.classList.add('show');
}
function closeVariation() {
    document.getElementById('var_modal').classList.remove('show');
}

// ── Cart ─────────────────────────────────────────────────────────────────────
function addToCart(variationId, name, price, note) {
    const existing = cart.find(i => i.variation_id === variationId && i.note === note);
    if (existing) {
        existing.quantity++;
    } else {
        cart.push({variation_id: variationId, name, price, quantity: 1, note: note || ''});
    }
    updateCartBadge();
    closeVariation();
    flashBadge();
}

function updateCartBadge() {
    const total = cart.reduce((s, i) => s + i.quantity, 0);
    document.getElementById('cart_count').textContent = total;
}

function flashBadge() {
    const badge = document.getElementById('cart_count');
    badge.style.transform = 'scale(1.5)';
    setTimeout(() => badge.style.transform = '', 300);
}

function openCart() {
    renderCartBody();
    document.getElementById('cart_drawer').classList.add('show');
}
function closeCart() {
    document.getElementById('cart_drawer').classList.remove('show');
}

function renderCartBody() {
    const body = document.getElementById('cart_body');
    if (cart.length === 0) {
        body.innerHTML = '<div class="cart-empty"><i class="fas fa-shopping-cart" style="font-size:36px;margin-bottom:10px;display:block"></i>Belum ada item</div>';
        document.getElementById('cart_grand_total').textContent = 'Rp 0';
        document.getElementById('cart_submit_btn').disabled = true;
        return;
    }
    document.getElementById('cart_submit_btn').disabled = false;
    let grand = 0;
    body.innerHTML = cart.map((item, idx) => {
        const sub = item.price * item.quantity;
        grand += sub;
        return `
            <div class="cart-item">
                <div class="ci-info">
                    <div class="ci-name">${item.name}</div>
                    ${item.note ? `<div class="ci-note"><i class="fas fa-comment-alt"></i> ${item.note}</div>` : ''}
                    <div class="ci-price">${formatRp(sub)}</div>
                </div>
                <div class="ci-qty">
                    <button onclick="adjustQty(${idx},-1)">−</button>
                    <span>${item.quantity}</span>
                    <button onclick="adjustQty(${idx},1)">+</button>
                </div>
            </div>
        `;
    }).join('');
    document.getElementById('cart_grand_total').textContent = formatRp(grand);
}

function adjustQty(idx, d) {
    cart[idx].quantity += d;
    if (cart[idx].quantity <= 0) cart.splice(idx, 1);
    updateCartBadge();
    renderCartBody();
}

// ── Submit order ─────────────────────────────────────────────────────────────
function submitOrder() {
    if (cart.length === 0) return;
    const btn = document.getElementById('cart_submit_btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';

    const payload = {
        pax,
        items: cart.map(i => ({
            variation_id: i.variation_id,
            quantity:     i.quantity,
            note:         i.note,
        })),
    };

    fetch(PLACE_URL, {
        method:  'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body:    JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            cart = [];
            updateCartBadge();
            closeCart();
            showOrderSent();
            updateOrderBar(true);
        } else {
            alert(data.message || 'Gagal mengirim pesanan');
        }
    })
    .catch(() => alert('Koneksi gagal. Coba lagi.'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Pesanan';
    });
}

// ── Order sent ───────────────────────────────────────────────────────────────
function showOrderSent() {
    document.getElementById('order_sent').classList.add('show');
}
function closeOrderSent() {
    document.getElementById('order_sent').classList.remove('show');
}

// ── Current order status ─────────────────────────────────────────────────────
function updateOrderBar(show) {
    const bar = document.getElementById('order_bar');
    if (show) bar.classList.add('show');
    else bar.classList.remove('show');
}

function viewCurrentOrder() {
    fetch(STATUS_URL)
        .then(r => r.json())
        .then(data => {
            if (!data.has_order) {
                alert('Belum ada pesanan aktif.');
                updateOrderBar(false);
                return;
            }
            let msg = `📋 Invoice: ${data.invoice_no}\n\n`;
            data.items.forEach(i => {
                msg += `• ${i.name} x${i.quantity}`;
                if (i.note) msg += ` (${i.note})`;
                msg += `  ${formatRp(i.subtotal)}\n`;
            });
            msg += `\n──────────────\nTotal: ${formatRp(data.final_total)}`;
            alert(msg);
        });
}

// ── Utils ─────────────────────────────────────────────────────────────────────
function formatRp(val) {
    return 'Rp ' + Math.round(val).toLocaleString('id-ID');
}
function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Close modals on backdrop click
document.getElementById('var_modal').addEventListener('click', function(e) {
    if (e.target === this) closeVariation();
});
document.getElementById('cart_drawer').addEventListener('click', function(e) {
    if (e.target === this) closeCart();
});
document.getElementById('order_sent').addEventListener('click', function(e) {
    if (e.target === this) closeOrderSent();
});
</script>
</body>
</html>
