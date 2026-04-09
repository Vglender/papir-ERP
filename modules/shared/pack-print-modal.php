<!--
  Pack print modal.
  JS interface: PackPrint.open(demandId)
-->
<div class="modal-overlay" id="packPrintModal" style="display:none">
    <div class="pack-modal-box">
        <div class="modal-head">
            <span id="packModalTitle">Друк пакету</span>
            <button class="modal-close" type="button" onclick="PackPrint.close()">&#x2715;</button>
        </div>
        <div class="pack-modal-body" id="packModalBody">
            <div class="pack-loading" id="packLoading">Завантаження…</div>
        </div>
        <div class="modal-footer" id="packModalFooter" style="display:none">
            <button class="btn btn-primary" id="packPrintAllBtn" type="button" onclick="PackPrint.printAll()">
                🖨 Друкувати все
            </button>
            <button class="btn" id="packQueueBtn" type="button" onclick="PackPrint.addToQueue()">
                📥 В чергу
            </button>
            <button class="btn" id="packRegenBtn" type="button" onclick="PackPrint.regenerate()">
                ↺ Перегенерувати
            </button>
            <button class="btn btn-ghost" type="button" onclick="PackPrint.close()">Закрити</button>
        </div>
    </div>
</div>

<!-- Hidden iframe for printing local PDFs -->
<iframe id="packPrintFrame" style="display:none;position:absolute;width:0;height:0"></iframe>

<style>
.pack-modal-box {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
    display: flex;
    flex-direction: column;
    width: 560px;
    max-width: 96vw;
    max-height: 80vh;
    overflow: hidden;
}
.pack-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px;
    min-height: 120px;
}
.pack-loading {
    text-align: center;
    color: #94a3b8;
    padding: 32px 0;
    font-size: 13px;
}

/* Profile selector */
.pack-profile-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f3f6;
}
.pack-profile-bar label {
    font-size: 11px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .4px;
    white-space: nowrap;
}
.pack-profile-bar select {
    flex: 1;
    height: 30px;
    font-size: 13px;
    padding: 0 8px;
}
.pack-profile-bar .btn {
    height: 30px;
    padding: 0 12px;
    font-size: 12px;
}

/* Items list */
.pack-items-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.pack-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    margin-bottom: 4px;
    transition: background .1s;
}
.pack-item:hover { background: #f8fafc; }
.pack-item.active { background: #eff6ff; }
.pack-item-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}
.pack-item-icon.ok { background: #dcfce7; }
.pack-item-icon.error { background: #fee2e2; }
.pack-item-icon.skip { background: #f3f4f6; }
.pack-item-info {
    flex: 1;
    min-width: 0;
}
.pack-item-label {
    font-size: 13px;
    font-weight: 500;
    color: #1e293b;
}
.pack-item-sub {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 1px;
}
.pack-item-actions {
    flex-shrink: 0;
    display: flex;
    gap: 4px;
}
.pack-item-actions .btn {
    height: 26px;
    padding: 0 8px;
    font-size: 11px;
}

/* Pack meta */
.pack-meta {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid #f1f3f6;
}

/* Generate button (when no pack exists) */
.pack-gen-area {
    text-align: center;
    padding: 20px 0;
}
.pack-gen-area .btn {
    height: 36px;
    padding: 0 20px;
    font-size: 14px;
}
</style>
