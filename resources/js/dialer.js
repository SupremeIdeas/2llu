// In-browser international dialer (Live Voice — Part B).
//
// The Twilio Voice JS SDK (@twilio/voice-sdk, WebRTC) is bundled via Vite — no
// CDN, CSP-safe — and dynamic-imported only on the dialer page so it never
// weighs down the main bundle. Livewire owns every cent (quote → pre-auth hold
// → settle); this module owns only the live audio + call-state UI, and reports
// the elapsed seconds back to Livewire for the client-side settlement fallback
// (the Twilio status webhook is the authoritative settle).

export function mountDialer(root) {
    const tokenUrl = root.dataset.tokenUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Elements the SDK drives.
    const panel = root.querySelector('[data-dialer-panel]');
    const stateEl = root.querySelector('[data-dialer-state]');
    const timerEl = root.querySelector('[data-dialer-timer]');
    const peerEl = root.querySelector('[data-dialer-peer]');
    const hangupBtn = root.querySelector('[data-dialer-hangup]');

    // The enclosing Livewire component (for the settlement fallback call).
    const wireEl = root.closest('[wire\\:id]');
    const wireId = wireEl?.getAttribute('wire:id');

    let device = null;
    let activeCall = null;
    let timerHandle = null;
    let limitHandle = null;
    let startedAt = 0;

    const setState = (label) => { if (stateEl) stateEl.textContent = label; };
    const showPanel = (show) => { if (panel) panel.classList.toggle('hidden', !show); };

    function startTimer() {
        startedAt = Date.now();
        stopTimer();
        timerHandle = setInterval(() => {
            const s = Math.floor((Date.now() - startedAt) / 1000);
            if (timerEl) {
                const mm = String(Math.floor(s / 60)).padStart(2, '0');
                const ss = String(s % 60).padStart(2, '0');
                timerEl.textContent = `${mm}:${ss}`;
            }
        }, 500);
    }
    function stopTimer() {
        if (timerHandle) { clearInterval(timerHandle); timerHandle = null; }
    }
    function elapsedSeconds() {
        return startedAt ? Math.floor((Date.now() - startedAt) / 1000) : 0;
    }

    function settle(callId, status) {
        const seconds = elapsedSeconds();
        stopTimer();
        if (limitHandle) { clearTimeout(limitHandle); limitHandle = null; }
        try {
            window.Livewire?.find(wireId)?.call('settleCall', callId, seconds, status);
        } catch (e) { /* webhook remains the authoritative settle */ }
    }

    function teardown() {
        try { activeCall?.disconnect(); } catch (e) { /* already gone */ }
        try { device?.destroy(); } catch (e) { /* already gone */ }
        activeCall = null;
        device = null;
    }

    async function connect({ callId, destination, fundedSeconds }) {
        if (peerEl) peerEl.textContent = destination;
        setState('Connecting…');
        showPanel(true);

        let token;
        try {
            const res = await fetch(tokenUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('token');
            token = (await res.json()).token;
        } catch (e) {
            setState('Could not start the call.');
            settle(callId, 'no-answer');
            setTimeout(() => showPanel(false), 2500);
            return;
        }

        let Device;
        try {
            ({ Device } = await import('@twilio/voice-sdk'));
        } catch (e) {
            setState('Voice unavailable on this device.');
            settle(callId, 'no-answer');
            setTimeout(() => showPanel(false), 2500);
            return;
        }

        try {
            device = new Device(token, { codecPreferences: ['opus', 'pcmu'] });
            activeCall = await device.connect({ params: { To: destination, CallId: String(callId) } });

            activeCall.on('ringing', () => setState('Ringing…'));
            activeCall.on('accept', () => { setState('In call'); startTimer(); });
            activeCall.on('reject', () => { setState('Call declined.'); settle(callId, 'no-answer'); setTimeout(() => showPanel(false), 2000); });
            activeCall.on('cancel', () => { settle(callId, 'no-answer'); showPanel(false); });
            activeCall.on('error', () => { setState('Call error — you were only billed for what connected.'); settle(callId, 'completed'); setTimeout(() => showPanel(false), 2500); });
            activeCall.on('disconnect', () => {
                setState('Call ended.');
                settle(callId, 'completed');
                setTimeout(() => showPanel(false), 1500);
                teardown();
            });

            // Client-side mirror of the server <Dial timeLimit>: hard-stop the
            // call when the funded block runs out (the wallet hold covers no more).
            if (fundedSeconds > 0) {
                limitHandle = setTimeout(() => {
                    setState('Funded minutes used up — call ended.');
                    try { activeCall?.disconnect(); } catch (e) { /* already gone */ }
                }, fundedSeconds * 1000);
            }
        } catch (e) {
            setState('Could not connect the call.');
            settle(callId, 'no-answer');
            setTimeout(() => showPanel(false), 2500);
            teardown();
        }
    }

    if (hangupBtn) {
        hangupBtn.addEventListener('click', () => {
            try { activeCall?.disconnect(); } catch (e) { showPanel(false); }
        });
    }

    // Livewire hands us the pre-authorised call to connect.
    window.Livewire?.on('voice-dial', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        connect({
            callId: data.callId,
            destination: data.destination,
            fundedSeconds: Number(data.fundedSeconds) || 0,
        });
    });

    window.addEventListener('beforeunload', teardown);
}
