</main>
<script>
if('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js');
let deferredPrompt;
window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    document.getElementById('pwa-install-bar').style.display = 'flex';
});
function pwaInstall() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(r => {
        if (r.outcome === 'accepted') document.getElementById('pwa-install-bar').style.display = 'none';
        deferredPrompt = null;
    });
}
function pwaClose() { document.getElementById('pwa-install-bar').style.display = 'none'; }
window.addEventListener('appinstalled', () => { document.getElementById('pwa-install-bar').style.display = 'none'; });
</script>
<div id="pwa-install-bar" style="display:none;position:fixed;bottom:0;left:0;right:0;background:var(--panel,#1a1a2e);border-top:1px solid rgba(168,85,247,.3);padding:12px 16px;align-items:center;gap:12px;z-index:9999;box-shadow:0 -2px 12px rgba(0,0,0,.4)">
    <div style="flex:1;min-width:0">
        <div style="font-size:.9rem;font-weight:600;color:var(--txt,#e5e5e5)">Install TrackView</div>
        <div style="font-size:.75rem;color:var(--mut,#999);margin-top:2px">App jaisa use karo — no browser bar</div>
    </div>
    <button onclick="pwaInstall()" style="background:#7c3aed;color:#fff;border:none;padding:8px 20px;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;white-space:nowrap">Install</button>
    <button onclick="pwaClose()" style="background:none;border:none;color:var(--mut,#666);font-size:1.2rem;cursor:pointer;padding:4px 8px">✕</button>
</div>
</body>
</html>
