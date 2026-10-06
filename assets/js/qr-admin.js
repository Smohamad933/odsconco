(() => {
    const root = document.getElementById('qr-admin');
    if (!root) return;
    const status = document.getElementById('qr-status');
    const button = document.getElementById('create-qr');
    const result = document.getElementById('qr-result');
    const codeBox = document.getElementById('qr-code');
    const urlBox = document.getElementById('qr-url');
    const expiry = document.getElementById('qr-expiry');
    let timer = null;

    const post = async (payload) => {
        const response = await fetch(root.dataset.api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': root.dataset.csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) throw new Error(data.error || 'درخواست انجام نشد.');
        return data;
    };

    button?.addEventListener('click', async () => {
        button.disabled = true;
        status.textContent = 'در حال ساخت QR…';
        try {
            const data = await post({ action: 'qr-create', event_type: document.getElementById('qr-event-type').value });
            result.hidden = false;
            codeBox.innerHTML = '';
            urlBox.textContent = data.url;
            if (window.QRCode) {
                new window.QRCode(codeBox, { text: data.url, width: 144, height: 144, colorDark: '#193642', colorLight: '#ffffff', correctLevel: window.QRCode.CorrectLevel?.M ?? 1 });
            } else {
                codeBox.textContent = 'برای نمایش QR، ارتباط با کتابخانه کد QR برقرار نشد.';
            }
            let remaining = Number(data.seconds) || 90;
            const renderTime = () => {
                expiry.textContent = remaining > 0 ? `اعتبار کد: ${remaining} ثانیه` : 'این کد منقضی شده است؛ برای استفاده کد تازه بسازید.';
                if (remaining <= 0) {
                    clearInterval(timer);
                    result.classList.add('is-expired');
                    codeBox.style.filter = 'grayscale(1) opacity(.38)';
                }
                remaining -= 1;
            };
            clearInterval(timer);
            result.classList.remove('is-expired');
            codeBox.style.filter = '';
            renderTime();
            timer = setInterval(renderTime, 1000);
            status.textContent = 'کد QR آماده شد.';
        } catch (error) {
            status.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });

    document.querySelectorAll('.attendance-review').forEach((reviewButton) => {
        reviewButton.addEventListener('click', async () => {
            const decision = reviewButton.dataset.decision;
            const note = decision === 'rejected' ? (window.prompt('دلیل رد درخواست (اختیاری):', '') || '') : '';
            reviewButton.disabled = true;
            try {
                await post({ action: 'review', id: Number(reviewButton.dataset.id), decision, note });
                const row = reviewButton.closest('[data-attendance-row]');
                if (row) row.remove();
                const statusText = document.getElementById('qr-status');
                if (statusText) statusText.textContent = 'درخواست بازبینی شد؛ فهرست به‌روز شد.';
                setTimeout(() => window.location.reload(), 500);
            } catch (error) {
                window.alert(error.message);
                reviewButton.disabled = false;
            }
        });
    });
})();
