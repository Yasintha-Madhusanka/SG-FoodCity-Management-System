// POS සහ මෘදුකාංගයේ ක්‍රියාකාරකම් සක්‍රීය කරන ප්‍රධාන විචල්‍යයන්
let cart = [];
let activeInputId = 'item-code';

// ඕනෑම input එකක් ක්ලික් කළ විට numpad එක එම input එක ඉලක්ක කිරීමට focus එක ලබා ගනී
document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', () => {
            activeInputId = input.id;
        });
    });
});

// ස්පර්ශක සංඛ්‍යා පුවරුවෙන් (Numpad) අගයන් ඇතුළත් කිරීමේ ශ්‍රිතය
function pressNum(val) {
    const input = document.getElementById(activeInputId);
    if (!input) return;
    
    if (val === 'back') {
        input.value = input.value.slice(0, -1);
    } else {
        input.value += val;
    }
    
    // චේන්ජ් (Change Due) ගණනය කිරීමට සහය වීම
    if (activeInputId === 'cash-paid') {
        calculateChange();
    }
}

// භාණ්ඩ ඉක්මනින් තේරීමේ dropdown එක ක්‍රියාත්මක කිරීම
function selectPOSProduct(select) {
    const opt = select.options[select.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('selected-product-id').value = opt.value;
        document.getElementById('selected-product-name').value = opt.getAttribute('data-name');
        document.getElementById('item-price').value = opt.getAttribute('data-price');
        document.getElementById('selected-product-stock').value = opt.getAttribute('data-stock');
        document.getElementById('item-qty').value = 1;
        document.getElementById('item-code').value = opt.value;
        activeInputId = 'item-qty';
    }
}

// භාණ්ඩ සෙවීමේ සෙවුම් කොටුව ක්‍රියාත්මක කිරීම (Barcode/ID search)
function lookupBarcode() {
    const query = document.getElementById('item-code').value.trim();
    if (!query) return;
    
    fetch(`cashier_dashbord.php?api=get_product&query=${query}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('selected-product-id').value = data.product.product_id;
                document.getElementById('selected-product-name').value = data.product.product_name;
                document.getElementById('item-price').value = data.product.price;
                document.getElementById('selected-product-stock').value = data.product.quantity;
                document.getElementById('item-qty').value = 1;
                document.getElementById('item-qty').focus();
                activeInputId = 'item-qty';
            } else {
                alert('භාණ්ඩය සොයාගත නොහැකි විය. කරුණාකර නැවත උත්සාහ කරන්න!');
            }
        })
        .catch(err => {
            console.error('භාණ්ඩ සෙවීමේදී දෝෂයක් ඇති විය:', err);
        });
}

// කරත්තයට (Cart) භාණ්ඩ ඇතුළත් කිරීමේ ශ්‍රිතය
function addCartItem() {
    const pId = parseInt(document.getElementById('selected-product-id').value);
    const pName = document.getElementById('selected-product-name').value;
    const price = parseFloat(document.getElementById('item-price').value);
    const stock = parseInt(document.getElementById('selected-product-stock').value);
    const qty = parseInt(document.getElementById('item-qty').value) || 1;
    
    if (!pId || !pName || isNaN(price)) {
        alert('කරුණාකර ප්‍රථමයෙන් භාණ්ඩයක් තෝරාගෙන හෝ සොයාගෙන සිටින්න.');
        return;
    }
    
    // දැනටමත් කරත්තයේ මෙම භාණ්ඩය තිබේදැයි බැලීම
    const existingIndex = cart.findIndex(item => item.product_id === pId);
    
    if (existingIndex > -1) {
        const newQty = cart[existingIndex].qty + qty;
        if (newQty > stock) {
            alert(`තොග සීමාව ඉක්මවා ඇත! පවතින උපරිම තොගය: ${stock}`);
            return;
        }
        cart[existingIndex].qty = newQty;
    } else {
        if (qty > stock) {
            alert(`තොග සීමාව ඉක්මවා ඇත! පවතින උපරිම තොගය: ${stock}`);
            return;
        }
        cart.push({
            product_id: pId,
            name: pName,
            price: price,
            qty: qty
        });
    }
    
    // පෝරමයේ අගයන් නැවත මුල් තත්වයට පත් කිරීම
    document.getElementById('selected-product-id').value = '';
    document.getElementById('selected-product-name').value = '';
    document.getElementById('item-price').value = '';
    document.getElementById('selected-product-stock').value = '0';
    document.getElementById('item-qty').value = '1';
    document.getElementById('item-code').value = '';
    document.getElementById('pos-product-select').selectedIndex = 0;
    
    renderPOSCart();
}

// සාප්පු සවාරි කරත්තය වගුවක් ලෙස පෙන්වීම
function renderPOSCart() {
    const cartRows = document.getElementById('cart-rows');
    if (!cartRows) return;
    
    cartRows.innerHTML = '';
    let grandTotal = 0;
    
    if (cart.length === 0) {
        cartRows.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted);" id="empty-cart-row">Cart is empty</td></tr>';
        document.getElementById('grand-total').innerText = '0.00';
        calculateChange();
        return;
    }
    
    cart.forEach((item, index) => {
        const subtotal = item.qty * item.price;
        grandTotal += subtotal;
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${item.name}</strong><br><small style="color:var(--text-muted);">ID: PRD-${item.product_id}</small></td>
            <td>Rs. ${item.price.toFixed(2)}</td>
            <td>${item.qty}</td>
            <td>Rs. ${subtotal.toFixed(2)}</td>
            <td>
                <button onclick="removeCart(${index})" style="background:none; border:none; color:var(--accent-color); cursor:pointer;">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;
        cartRows.appendChild(tr);
    });
    
    document.getElementById('grand-total').innerText = grandTotal.toFixed(2);
    calculateChange();
}

// කරත්තයෙන් භාණ්ඩයක් ඉවත් කිරීම
function removeCart(index) {
    cart.splice(index, 1);
    renderPOSCart();
}

// පාරිභෝගිකයාට ඉතිරි මුදල (Change) ගණනය කිරීම
function calculateChange() {
    const total = parseFloat(document.getElementById('grand-total').innerText) || 0;
    const paid = parseFloat(document.getElementById('cash-paid').value) || 0;
    const balanceDue = document.getElementById('balance-due');
    
    if (paid >= total) {
        balanceDue.value = (paid - total).toFixed(2);
    } else {
        balanceDue.value = '0.00';
    }
}

// POS ගෙවීම් කිරීම සහ තොග වාර්තා යාවත්කාලීන කිරීම (Checkout execution)
function processPOSCheckout() {
    const total = parseFloat(document.getElementById('grand-total').innerText) || 0;
    const cash = parseFloat(document.getElementById('cash-paid').value) || 0;
    
    if (cart.length === 0) {
        alert('සාප්පු සවාරි කරත්තය හිස්ය!');
        return;
    }
    
    if (cash < total) {
        alert('පාරිභෝගිකයා ගෙවූ මුදල ප්‍රමාණවත් නොවේ!');
        return;
    }
    
    const checkoutData = {
        cart_items: cart,
        total_amount: total,
        cash_paid: cash
    };
    
    fetch('cashier_dashbord.php?api=checkout', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(checkoutData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // මුද්‍රණ රිසිට්පත් තොරතුරු පිරවීම
            document.getElementById('r-inv').innerText = data.bill_code;
            document.getElementById('r-cashier').innerText = data.cashier_name;
            document.getElementById('r-date').innerText = data.date_time;
            
            const rItems = document.getElementById('r-items');
            rItems.innerHTML = '';
            
            cart.forEach(item => {
                const sub = item.qty * item.price;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="color:#000; font-family: monospace;">${item.name}</td>
                    <td style="color:#000; text-align:right; font-family: monospace;">${item.qty}</td>
                    <td style="color:#000; text-align:right; font-family: monospace;">Rs. ${sub.toFixed(2)}</td>
                `;
                rItems.appendChild(tr);
            });
            
            document.getElementById('r-total').innerText = data.total_amount.toFixed(2);
            document.getElementById('r-cash').innerText = data.cash_paid.toFixed(2);
            document.getElementById('r-balance').innerText = data.change_due.toFixed(2);
            
            // රිසිට්පත් මෝඩල් එක පෙන්වීම
            document.getElementById('receipt-modal').style.display = 'flex';
        } else {
            alert('බිල්පත සැකසීමේදී ගැටලුවක් මතු විය: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Checkout error:', err);
    });
}

// රිසිට්පත් මෝඩල් එක වසා දමා කරත්තය හිස් කිරීම
function closeReceiptModal() {
    document.getElementById('receipt-modal').style.display = 'none';
    cart = [];
    document.getElementById('cash-paid').value = '';
    renderPOSCart();
    
    // තොග වාර්තා යාවත්කාලීන නිරූපණය සඳහා පිටුව reload කිරීම
    window.location.reload();
}

// හදිසි ලොක්ඩවුන් (System Lockdown) ක්‍රියාවලිය
function triggerSystemLockdown() {
    if (confirm('අවධානයයි: පද්ධතිය හදිසි ලොක්ඩවුන් (Lockdown) තත්වයකට පත් කිරීමට අවශ්‍යද?')) {
        
        // රතු breathing ඇලම් එක සක්‍රීය කිරීම
        const overlay = document.getElementById('alarm-overlay');
        overlay.style.background = 'radial-gradient(circle, rgba(255,0,0,0.5) 0%, rgba(0,0,0,0.95) 100%)';
        overlay.style.pointerEvents = 'all';
        
        // Pulses visual effect setup
        const style = document.createElement('style');
        style.type = 'text/css';
        style.innerHTML = `
            @keyframes pulseAlarm {
                0% { opacity: 0.7; }
                50% { opacity: 0.95; }
                100% { opacity: 0.7; }
            }
            #alarm-overlay {
                animation: pulseAlarm 1.5s infinite ease-in-out;
            }
        `;
        document.head.appendChild(style);
        
        // API එක මඟින් දත්ත සමුදායට සටහන් කිරීම
        fetch('security_dashbord.php?api=lockdown')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('පද්ධතිය සාර්ථකව අගුළුලන ලදී (LOCKED DOWN)!);');
                    window.location.reload();
                }
            })
            .catch(err => {
                console.error('Lockdown API logs error:', err);
            });
    }
}
