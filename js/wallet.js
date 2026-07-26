// js/wallet.js
document.getElementById('topup-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const amountInput = document.getElementById('topup-amount');
    const messageEl = document.getElementById('topup-message');
    const amount = parseFloat(amountInput.value);

    try {
        const response = await fetch('api/wallet_topup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ amount }),
        });
        const result = await response.json();

        if (result.success) {
            messageEl.textContent = 'Nạp tiền thành công!';
            messageEl.className = 'success-text';
            setTimeout(() => location.reload(), 900); // reload để cập nhật số dư + lịch sử
        } else {
            messageEl.textContent = result.message;
            messageEl.className = 'error-text';
        }
    } catch (err) {
        messageEl.textContent = 'Lỗi kết nối đến server.';
        messageEl.className = 'error-text';
    }
});