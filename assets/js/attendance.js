(() => {
    const form = document.getElementById('attendance-form');
    if (!form) return;
    const panels = [...document.querySelectorAll('[data-method-panel]')];
    const status = document.getElementById('attendance-status');
    const submitButton = form.querySelector('button[type="submit"]');
    const methodInputs = [...form.querySelectorAll('input[name="method"]')];

    const currentMethod = () => form.querySelector('input[name="method"]:checked')?.value || 'manual';
    const updatePanels = () => {
        const method = currentMethod();
        panels.forEach((panel) => { panel.hidden = panel.dataset.methodPanel !== method; });
    };
    methodInputs.forEach((input) => input.addEventListener('change', updatePanels));
    updatePanels();

    const send = async (payload) => {
        const response = await fetch(form.dataset.api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': form.dataset.csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) throw new Error(data.error || 'ثبت درخواست انجام نشد.');
        return data;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const method = currentMethod();
        const payload = {
            action: 'submit',
            method,
            event_type: form.elements.event_type.value
        };
        if (method === 'qr') {
            payload.qr_token = form.elements.qr_token.value.trim();
            if (!payload.qr_token) {
                status.textContent = 'برای ثبت با QR، کد موقت شرکت را اسکن کنید.';
                return;
            }
        }
        if (method === 'remote') {
            if (!form.elements.location_consent.checked) {
                status.textContent = 'برای ثبت دورکاری، اجازه صریح موقعیت مکانی را فعال کنید.';
                return;
            }
            if (!navigator.geolocation) {
                status.textContent = 'مرورگر شما از دریافت موقعیت مکانی پشتیبانی نمی‌کند.';
                return;
            }
            submitButton.disabled = true;
            status.textContent = 'در انتظار اجازه و دریافت موقعیت فعلی…';
            try {
                const position = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: false,
                    timeout: 15000,
                    maximumAge: 0
                }));
                payload.latitude = position.coords.latitude;
                payload.longitude = position.coords.longitude;
                payload.accuracy = position.coords.accuracy;
                payload.location_consent = true;
            } catch (error) {
                submitButton.disabled = false;
                status.textContent = error.code === 1 ? 'دسترسی مکانی مجاز نشد؛ تنظیمات مرورگر را بررسی کنید.' : 'موقعیت دریافت نشد. اتصال امن HTTPS و GPS را بررسی کنید.';
                return;
            }
        }
        submitButton.disabled = true;
        status.textContent = 'در حال ثبت…';
        try {
            const data = await send(payload);
            status.textContent = data.message || 'درخواست ثبت شد.';
            if (method === 'qr') {
                form.elements.qr_token.value = '';
                history.replaceState(null, '', location.pathname);
            }
            setTimeout(() => window.location.reload(), 1200);
        } catch (error) {
            status.textContent = error.message;
            submitButton.disabled = false;
        }
    });
})();
