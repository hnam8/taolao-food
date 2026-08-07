// js/menu.js
// Module 4 - Menu CRUD: quản lý danh mục + món ăn (categories_api.php, menu_api.php, upload_image.php)

let categories = [];
let menuItems = [];
let selectedImageFile = null; // file ảnh đang chờ upload cho form hiện tại

// ---------- 1. LOAD DỮ LIỆU ----------
async function loadCategories() {
    try {
        const res = await fetch('categories_api.php');
        const result = await res.json();
        if (!result.success) throw new Error(result.message);
        categories = result.data;
        renderCategories();
        renderCategoryOptions();
    } catch (err) {
        console.error('Lỗi tải danh mục:', err);
        document.getElementById('category-list').innerHTML =
            '<p class="error-row">Lỗi tải danh mục.</p>';
    }
}

async function loadMenuItems() {
    const includeDeleted = document.getElementById('show-deleted').checked ? '1' : '0';
    try {
        const res = await fetch(`menu_api.php?include_deleted=${includeDeleted}`);
        const result = await res.json();
        if (!result.success) throw new Error(result.message);
        menuItems = result.data;
        renderMenuItems();
    } catch (err) {
        console.error('Lỗi tải menu:', err);
        document.getElementById('menu-tbody').innerHTML =
            '<tr><td colspan="6" class="error-row">Lỗi kết nối đến server.</td></tr>';
    }
}

// ---------- 2. RENDER DANH MỤC ----------
function renderCategories() {
    const container = document.getElementById('category-list');
    if (categories.length === 0) {
        container.innerHTML = '<p class="text-muted">Chưa có danh mục nào.</p>';
        return;
    }
    container.innerHTML = categories.map(cat => `
        <div class="category-chip">
            <span>${escapeHtml(cat.category_name)} <small>(${cat.item_count} món)</small></span>
            <button type="button" class="chip-delete-btn" onclick="deleteCategory(${cat.category_id}, '${escapeHtml(cat.category_name)}')">×</button>
        </div>
    `).join('');
}

// Đổ danh mục vào 2 dropdown: filter và form thêm/sửa món
function renderCategoryOptions() {
    const options = categories
        .map(cat => `<option value="${cat.category_id}">${escapeHtml(cat.category_name)}</option>`)
        .join('');

    const filterSelect = document.getElementById('filter-category');
    const currentFilter = filterSelect.value;
    filterSelect.innerHTML = '<option value="">Tất cả danh mục</option>' + options;
    filterSelect.value = currentFilter;

    const itemSelect = document.getElementById('item-category');
    const currentItemCat = itemSelect.value;
    itemSelect.innerHTML = '<option value="">-- Không thuộc danh mục --</option>' + options;
    itemSelect.value = currentItemCat;
}

// ---------- 3. RENDER BẢNG MÓN ĂN ----------
function renderMenuItems() {
    const tbody = document.getElementById('menu-tbody');
    const filterCategoryId = document.getElementById('filter-category').value;

    let filtered = menuItems;
    if (filterCategoryId) {
        filtered = filtered.filter(item => String(item.category_id) === filterCategoryId);
    }

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-row">Không có món nào.</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(item => {
        const thumb = item.image_url
            ? `<img src="../${escapeHtml(item.image_url)}" alt="${escapeHtml(item.item_name)}" class="menu-thumb">`
            : `<div class="menu-thumb-placeholder">🍽️</div>`;

        const statusBadge = item.is_deleted
            ? `<span class="status-badge status-unknown">Đã ẩn</span>`
            : (item.is_available
                ? `<span class="status-badge status-delivered">Còn hàng</span>`
                : `<span class="status-badge status-outfordelivery">Hết hàng</span>`);

        const actions = item.is_deleted
            ? `<button type="button" class="secondary-btn" onclick="restoreItem(${item.item_id})">Khôi phục</button>`
            : `
                <button type="button" class="secondary-btn" onclick="openItemForm(${item.item_id})">Sửa</button>
                <button type="button" class="secondary-btn" onclick="toggleAvailability(${item.item_id})">${item.is_available ? 'Đánh dấu hết hàng' : 'Đánh dấu còn hàng'}</button>
                <button type="button" class="chip-delete-btn" onclick="deleteItem(${item.item_id}, '${escapeHtml(item.item_name)}')">Xóa</button>
              `;

        return `
            <tr>
                <td>${thumb}</td>
                <td>${escapeHtml(item.item_name)}</td>
                <td>${item.category_name ? escapeHtml(item.category_name) : '<span class="text-muted">Không có</span>'}</td>
                <td class="price-cell">${formatCurrency(item.price)} đ</td>
                <td>${statusBadge}</td>
                <td class="action-cell">${actions}</td>
            </tr>
        `;
    }).join('');
}

// ---------- 4. CRUD DANH MỤC ----------
document.getElementById('category-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('cat-name').value.trim();
    const order = parseInt(document.getElementById('cat-order').value, 10) || 0;
    if (!name) return;

    try {
        const res = await fetch('categories_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', category_name: name, display_order: order }),
        });
        const result = await res.json();
        if (!result.success) { alert(result.message); return; }

        document.getElementById('cat-name').value = '';
        document.getElementById('cat-order').value = '0';
        loadCategories();
    } catch (err) {
        alert('Lỗi kết nối server.');
    }
});

async function deleteCategory(id, name) {
    if (!confirm(`Xóa danh mục "${name}"? Các món thuộc danh mục này sẽ chuyển về "Không thuộc danh mục", không bị xóa.`)) return;

    try {
        const res = await fetch('categories_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', category_id: id }),
        });
        const result = await res.json();
        if (!result.success) { alert(result.message); return; }
        loadCategories();
        loadMenuItems();
    } catch (err) {
        alert('Lỗi kết nối server.');
    }
}

// ---------- 5. FORM THÊM/SỬA MÓN ----------
function openItemForm(itemId = null) {
    const overlay = document.getElementById('item-form-overlay');
    const form = document.getElementById('item-form');
    const preview = document.getElementById('item-image-preview');
    selectedImageFile = null;
    form.reset();
    preview.innerHTML = '';

    if (itemId) {
        const item = menuItems.find(i => i.item_id === itemId);
        document.getElementById('item-form-title').textContent = `Sửa món: ${item.item_name}`;
        document.getElementById('item-id').value = item.item_id;
        document.getElementById('item-name').value = item.item_name;
        document.getElementById('item-description').value = item.description || '';
        document.getElementById('item-price').value = item.price;
        document.getElementById('item-category').value = item.category_id || '';
        if (item.image_url) {
            preview.innerHTML = `<img src="../${item.image_url}" class="menu-thumb">`;
        }
    } else {
        document.getElementById('item-form-title').textContent = 'Thêm món mới';
        document.getElementById('item-id').value = '';
    }

    overlay.classList.remove('hidden');
}

function closeItemForm() {
    document.getElementById('item-form-overlay').classList.add('hidden');
}

document.getElementById('add-item-btn').addEventListener('click', () => openItemForm(null));
document.getElementById('cancel-item-btn').addEventListener('click', closeItemForm);

document.getElementById('item-image').addEventListener('change', (e) => {
    selectedImageFile = e.target.files[0] || null;
    const preview = document.getElementById('item-image-preview');
    if (selectedImageFile) {
        preview.innerHTML = `<span class="text-muted">Đã chọn: ${escapeHtml(selectedImageFile.name)}</span>`;
    }
});

document.getElementById('item-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const itemId = document.getElementById('item-id').value;
    const payload = {
        action: itemId ? 'update' : 'create',
        item_name: document.getElementById('item-name').value.trim(),
        description: document.getElementById('item-description').value.trim(),
        price: parseFloat(document.getElementById('item-price').value),
        category_id: document.getElementById('item-category').value || null,
    };
    if (itemId) payload.item_id = parseInt(itemId, 10);

    try {
        const res = await fetch('menu_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const result = await res.json();
        if (!result.success) { alert(result.message); return; }

        // item_id để upload ảnh: món mới lấy từ response, món cũ dùng lại id đang sửa
        const finalItemId = itemId ? parseInt(itemId, 10) : result.item_id;

        if (selectedImageFile) {
            await uploadItemImage(finalItemId, selectedImageFile);
        }

        closeItemForm();
        loadMenuItems();
    } catch (err) {
        alert('Lỗi kết nối server.');
    }
});

// Upload ảnh dùng FormData (multipart), KHÔNG dùng JSON.stringify như các API khác
async function uploadItemImage(itemId, file) {
    const formData = new FormData();
    formData.append('item_id', itemId);
    formData.append('image', file);

    try {
        const res = await fetch('upload_image.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (!result.success) {
            alert('Lưu món thành công nhưng lỗi upload ảnh: ' + result.message);
        }
    } catch (err) {
        alert('Lưu món thành công nhưng lỗi kết nối khi upload ảnh.');
    }
}

// ---------- 6. TOGGLE / XÓA / KHÔI PHỤC MÓN ----------
async function toggleAvailability(itemId) {
    try {
        const res = await fetch('menu_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_availability', item_id: itemId }),
        });
        const result = await res.json();
        if (!result.success) { alert(result.message); return; }
        loadMenuItems();
    } catch (err) {
        alert('Lỗi kết nối server.');
    }
}

async function deleteItem(itemId, name) {
    if (!confirm(`Ẩn món "${name}" khỏi menu? (Không xóa vĩnh viễn, có thể khôi phục lại sau)`)) return;

    try {
        const res = await fetch('menu_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', item_id: itemId }),
        });
        const result = await res.json();
        if (!result.success) { alert(result.message); return; }
        loadMenuItems();
    } catch (err) {
        alert('Lỗi kết nối server.');
    }
}

async function restoreItem(itemId) {
    try {
        const res = await fetch('menu_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'restore', item_id: itemId }),
        });
        const result = await res.json();
        if (!result.success) { alert(result.message); return; }
        loadMenuItems();
    } catch (err) {
        alert('Lỗi kết nối server.');
    }
}

// ---------- HELPERS ----------
function formatCurrency(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

// ---------- INIT ----------
// Không tự chạy khi trang vừa load nữa - dashboard.php giờ gộp nhiều tab,
// tab Menu chỉ nên load dữ liệu khi admin thực sự mở tab này (xem js/tabs.js).
async function initMenuTab() {
    await loadCategories();
    await loadMenuItems();

    document.getElementById('filter-category').addEventListener('change', renderMenuItems);
    document.getElementById('show-deleted').addEventListener('change', loadMenuItems);
}