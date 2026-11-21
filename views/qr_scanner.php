<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/layout.php';
requireLogin();

renderPageStart('Live QR Scanner');
?>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<style>
    .scanner-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 1.5rem; align-items: start; }
    @media (max-width: 820px) { .scanner-grid { grid-template-columns: 1fr; } }
    #reader { width: 100%; border-radius: 10px; overflow: hidden; border: 2px solid var(--border); }
    .scan-result-box { padding: 0.95rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-weight: 600; display: none; font-size: 0.92rem; }
    .res-success { background: var(--success-soft); color: #065f46; border: 1px solid #a7f3d0; }
    .res-warning { background: var(--warning-soft); color: #92400e; border: 1px solid #fde68a; }
    .res-error   { background: var(--danger-soft); color: #991b1b; border: 1px solid #fecaca; }
</style>

<div class="scanner-grid">
    <!-- Left: Live Camera Feed -->
    <div class="card">
        <h2 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Gate QR Code Scanner</h2>
        <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 1.1rem;">
            Hold a printed or mobile Visitor Pass QR code in front of the camera for instant Check-In / Check-Out.
        </p>

        <div id="reader"></div>

        <div style="margin-top: 1.25rem; border-top: 1px solid var(--border); padding-top: 1rem;">
            <label for="manualId">Manual Pass ID Entry (No Camera Fallback)</label>
            <form id="manualForm" style="display: flex; gap: 0.5rem;">
                <input type="text" id="manualId" placeholder="Enter Visit ID (e.g., 1) or paste QR text" required>
                <button type="submit" class="btn">Verify Pass</button>
            </form>
        </div>
    </div>

    <!-- Right: Live Scan Status & Recent Gate Log -->
    <div class="card">
        <h3 style="font-size: 1.05rem; margin-bottom: 0.85rem;">Verification Status</h3>
        <div id="resultBanner" class="scan-result-box"></div>

        <div id="visitorDetails" style="font-size: 0.92rem; color: var(--muted); margin-bottom: 1.25rem;">
            Waiting for QR code scan...
        </div>

        <h4 style="font-size: 0.9rem; margin-bottom: 0.6rem; border-top: 1px solid var(--border); padding-top: 0.9rem;">Session Scan History</h4>
        <ul id="scanHistory" style="list-style: none; display: flex; flex-direction: column; gap: 0.45rem; font-size: 0.86rem;">
            <li style="color: var(--muted);">No passes scanned in this session yet.</li>
        </ul>
    </div>
</div>

<script>
    let isProcessing = false;
    const resultBanner = document.getElementById("resultBanner");
    const visitorDetails = document.getElementById("visitorDetails");
    const scanHistory = document.getElementById("scanHistory");
    let firstScan = true;

    async function processQrPayload(qrText) {
        if (isProcessing) return;
        isProcessing = true;

        try {
            const response = await fetch("/api/scan_qr.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ qr_data: qrText })
            });
            const data = await response.json();

            resultBanner.style.display = "block";
            resultBanner.className = "scan-result-box " + (
                data.status === "success" ? "res-success" :
                data.status === "warning" ? "res-warning" : "res-error"
            );
            resultBanner.textContent = data.message;

            if (data.status === "success" && data.visit) {
                visitorDetails.innerHTML = `
                    <div style="background: #f8fafc; padding: 0.9rem; border-radius: 8px; border: 1px solid var(--border); color: var(--text); line-height: 1.75;">
                        <strong>Pass ID:</strong> #${data.visit.visit_id}<br>
                        <strong>Visitor:</strong> ${data.visit.visitor_name}<br>
                        <strong>Organization:</strong> ${data.visit.organization || "Individual"}<br>
                        <strong>Host:</strong> ${data.visit.host_name}<br>
                        <strong>Gate Action:</strong> <span class="badge-role" style="background: var(--primary-soft); color: var(--primary);">${data.action}</span>
                    </div>
                `;

                if (firstScan) {
                    scanHistory.innerHTML = "";
                    firstScan = false;
                }
                const li = document.createElement("li");
                li.style.cssText = "padding: 0.55rem 0.75rem; background: #f8fafc; border: 1px solid var(--border); border-radius: 6px;";
                li.textContent = `[${new Date().toLocaleTimeString()}] #${data.visit.visit_id} ${data.visit.visitor_name} — ${data.action}`;
                scanHistory.prepend(li);
            }
        } catch (err) {
            resultBanner.style.display = "block";
            resultBanner.className = "scan-result-box res-error";
            resultBanner.textContent = "Network error while verifying QR code.";
        }

        setTimeout(() => { isProcessing = false; }, 3000);
    }

    const html5QrcodeScanner = new Html5QrcodeScanner(
        "reader",
        { fps: 10, qrbox: { width: 240, height: 240 } },
        false
    );
    html5QrcodeScanner.render((decodedText) => {
        processQrPayload(decodedText);
    });

    document.getElementById("manualForm").addEventListener("submit", (e) => {
        e.preventDefault();
        const val = document.getElementById("manualId").value.trim();
        if (val) {
            processQrPayload(val);
            document.getElementById("manualId").value = "";
        }
    });
</script>

<?php renderPageEnd(); ?>