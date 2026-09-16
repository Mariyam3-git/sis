<?php
require_once '../config.php';
requireLogin();
if (!hasRole(['agent','supervisor'])) { redirectTo(appPath('dashboard.php')); }

// ══════════════════════════════════════════════════════════════════
//  WHO IS LOGGED IN
// ══════════════════════════════════════════════════════════════════
$current_user_id   = $_SESSION['user_id']   ?? 0;
$current_user_name = $_SESSION['full_name'] ?? 'Unknown';
$current_user_role = $_SESSION['role']      ?? 'agent';

function makeStamp($name, $role) {
    $lbl = ($role === 'supervisor') ? 'supervisor' : 'agent';
    return '[Recorded by ' . $lbl . ': ' . $name . ' @ ' . date('d/m/Y H:i') . ']';
}
function makeEditStamp($name, $role) {
    $lbl = ($role === 'supervisor') ? 'supervisor' : 'agent';
    return '[Edited by ' . $lbl . ': ' . $name . ' @ ' . date('d/m/Y H:i') . ']';
}
function parseStamps($notes) {
    $r = ['type'=>'unknown','name'=>'','time'=>'','edited_name'=>'','edited_time'=>'','edited_role'=>''];
    if (empty($notes)) return $r;
    if (preg_match('/\[Recorded by (supervisor|agent): ([^\@]+) @ ([^\]]+)\]/i', $notes, $m)) {
        $r['type'] = strtolower(trim($m[1]));
        $r['name'] = trim($m[2]);
        $r['time'] = trim($m[3]);
    }
    if (preg_match('/\[Edited by (supervisor|agent): ([^\@]+) @ ([^\]]+)\]/i', $notes, $m2)) {
        $r['edited_name'] = trim($m2[2]);
        $r['edited_time'] = trim($m2[3]);
        $r['edited_role'] = strtolower(trim($m2[1]));
    }
    return $r;
}

// ── ADD MULTIPLE SERVICES (NEW) ───────────────────────────────────
// Inserts one sales row per service line, all under the same visitor_id
if (isset($_POST['add_multi_sale'])) {
    $visitor_id = intval($_POST['visitor_id']);
    $agent_id   = $current_user_id;
    $outlet_id  = $_SESSION['outlet_id'] ?? 0;
    $sale_date  = $conn->real_escape_string((string)($_POST['sale_date_multi'] ?? ''));
    $handle_timing = getPostedHandleTiming();

    $products  = $_POST['ms_product']  ?? [];
    $amounts   = $_POST['ms_amount']   ?? [];
    $statuses  = $_POST['ms_status']   ?? [];
    $notesArr  = $_POST['ms_notes']    ?? [];

    $stamp = makeStamp($current_user_name, $current_user_role);
    $inserted = 0;

    foreach ($products as $i => $raw_product) {
        $product = $conn->real_escape_string(trim($raw_product));
        if ($product === '') continue;  // skip blank rows

        $amount     = floatval($amounts[$i] ?? 0);
        $pmt_status = $conn->real_escape_string($statuses[$i] ?? 'Paid');
        $raw_note   = $conn->real_escape_string(trim($notesArr[$i] ?? ''));
        $notes      = $conn->real_escape_string($stamp) . ($raw_note ? ' — ' . $raw_note : '');

        $stmt = $conn->prepare("
            INSERT INTO sales (outlet_id, agent_id, visitor_id, product, amount, notes, payment_date, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiisdsss", $outlet_id, $agent_id, $visitor_id, $product, $amount, $notes, $sale_date, $pmt_status);
        $stmt->execute();
        $sale_id = mysqli_insert_id($conn);
        setSaleHandleTime($conn, $sale_id, $handle_timing['started_at'], $handle_timing['completed_at'], $handle_timing['seconds']);
        $inserted++;
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ── EDIT SALE ─────────────────────────────────────────────────────
if (isset($_POST['edit_sale'])) {
    $sale_id    = intval($_POST['sale_id']);
    $amount     = floatval($_POST['amount']);
    $product    = $conn->real_escape_string((string)($_POST['product'] ?? ''));
    $sale_date  = $conn->real_escape_string((string)($_POST['sale_date'] ?? ''));
    $pmt_status = $conn->real_escape_string((string)($_POST['payment_status'] ?? ''));
    $raw_notes  = trim($_POST['notes']);
    $edit_stamp = makeEditStamp($current_user_name, $current_user_role);
    $clean      = preg_replace('/\s*\[Edited by [^\]]+\]/i', '', $raw_notes);
    $notes      = $conn->real_escape_string(trim($clean) . ' ' . $edit_stamp);
    $stmt = $conn->prepare("UPDATE sales SET amount=?,product=?,notes=?,sale_date=?,payment_status=? WHERE id=?");
    $stmt->bind_param("dssssi", $amount, $product, $notes, $sale_date, $pmt_status, $sale_id);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ── ADD SINGLE SALE ───────────────────────────────────────────────
if (isset($_POST['add_sale'])) {
    $visitor_id   = intval($_POST['visitor_id']);
    $agent_id     = $current_user_id;
    $outlet_id    = $_SESSION['outlet_id'] ?? 0;
    $amount       = floatval($_POST['amount']);
    $product      = $conn->real_escape_string((string)($_POST['product'] ?? ''));
    $sale_date    = $conn->real_escape_string((string)($_POST['sale_date'] ?? ''));
    $handle_timing = getPostedHandleTiming();
    $pmt_status   = $conn->real_escape_string((string)($_POST['payment_status'] ?? ''));
    $raw_notes    = $conn->real_escape_string(trim($_POST['notes'] ?? ''));
    $create_stamp = $conn->real_escape_string(makeStamp($current_user_name, $current_user_role));
    $notes        = $create_stamp . ($raw_notes ? ' — ' . $raw_notes : '');
    $stmt = $conn->prepare("INSERT INTO sales (outlet_id,agent_id,visitor_id,product,amount,notes,payment_date,payment_status) VALUES(?,?,?,?,?,?,?,?)");
    $stmt->bind_param("iiisdsss", $outlet_id, $agent_id, $visitor_id, $product, $amount, $notes, $sale_date, $pmt_status);
    $stmt->execute();
    $sale_id = mysqli_insert_id($conn);
    setSaleHandleTime($conn, $sale_id, $handle_timing['started_at'], $handle_timing['completed_at'], $handle_timing['seconds']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ── MARK AS PAID ──────────────────────────────────────────────────
if (isset($_POST['mark_as_paid'])) {
    $sale_id = intval($_POST['sale_id']);
    $stmt = $conn->prepare("UPDATE sales SET payment_status='Paid' WHERE id=?");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ── UPDATE PAYMENT STATUS ─────────────────────────────────────────
if (isset($_POST['update_payment_status'])) {
    $sale_id        = intval($_POST['sale_id']);
    $payment_status = $conn->real_escape_string((string)($_POST['payment_status'] ?? ''));
    $stmt = $conn->prepare("UPDATE sales SET payment_status=? WHERE id=?");
    $stmt->bind_param("si", $payment_status, $sale_id);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ── UPDATE VISITOR STATUS ─────────────────────────────────────────
if (isset($_POST['update_status'])) {
    $visitor_id = intval($_POST['visitor_id']);
    $status     = $_POST['status'] ?? 'Waiting';
    markVisitorStatus($conn, $visitor_id, $status);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ── DELETE SALE ───────────────────────────────────────────────────
if (isset($_POST['delete_sale'])) {
    $sale_id = intval($_POST['sale_id']);
    $vq = $conn->prepare("SELECT visitor_id FROM sales WHERE id=?");
    $vq->bind_param("i", $sale_id);
    $vq->execute();
    $vrow = $vq->get_result()->fetch_assoc();
    $vid  = $vrow['visitor_id'] ?? null;
    $stmt = $conn->prepare("DELETE FROM sales WHERE id=?");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    if ($vid) {
        $s2 = $conn->prepare("DELETE FROM visitors WHERE id=?");
        $s2->bind_param("i", $vid);
        $s2->execute();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>



<td>
    <?php
    $row_notes  = $row['notes'] ?? '';
    $row_stamps = parseStamps($row_notes);
    $visitor_id_for_row = $row['id'] ?? $row['visitor_id'] ?? 0;
    ?>
    <div class="btn-group">
        <button type="button"
                class="btn btn-sm btn-outline-secondary dropdown-toggle"
                data-bs-toggle="dropdown" aria-expanded="false">
            Actions
        </button>
        <ul class="dropdown-menu dropdown-menu-end sis-u-019128fe">

            
            <?php if ($row_stamps['name']): ?>
            <li>
                <span class="dd-badge by-<?php echo $row_stamps['type']; ?>">
                    <?php echo $row_stamps['type'] === 'supervisor' ? '👁 Entered by supervisor:' : '✅ Recorded by agent:'; ?><br>
                    <strong><?php echo htmlspecialchars($row_stamps['name']); ?></strong>
                    &nbsp;·&nbsp;<?php echo htmlspecialchars($row_stamps['time']); ?>
                    <?php if ($row_stamps['edited_name']): ?>
                    <br><span class="sis-u-390bcf8e">✏️ Edited: <?php echo htmlspecialchars($row_stamps['edited_name']); ?> @ <?php echo htmlspecialchars($row_stamps['edited_time']); ?></span>
                    <?php endif; ?>
                </span>
            </li>
            <li><hr class="dropdown-divider my-1"></li>
            <?php endif; ?>

            
            <li>
                <button type="button" class="dropdown-item text-primary fw-semibold"
                        onclick="openEditSale({
                            id:        '<?php echo $row['sale_id'] ?? $row['id']; ?>',
                            product:   '<?php echo addslashes(htmlspecialchars($row['product'] ?? '')); ?>',
                            amount:    '<?php echo $row['amount'] ?? ''; ?>',
                            date:      '<?php echo $row['sale_date'] ?? ''; ?>',
                            status:    '<?php echo $row['payment_status'] ?? ''; ?>',
                            notes:     '<?php echo addslashes(htmlspecialchars($row_notes)); ?>',
                            rec_type:  '<?php echo $row_stamps['type']; ?>',
                            rec_name:  '<?php echo addslashes(htmlspecialchars($row_stamps['name'])); ?>',
                            rec_time:  '<?php echo addslashes(htmlspecialchars($row_stamps['time'])); ?>',
                            edit_name: '<?php echo addslashes(htmlspecialchars($row_stamps['edited_name'])); ?>',
                            edit_time: '<?php echo addslashes(htmlspecialchars($row_stamps['edited_time'])); ?>'
                        })">
                    ✏️ Edit Sale
                </button>
            </li>

            
            <li>
                <button type="button" class="dropdown-item text-success fw-semibold"
                        onclick="openMultiService('<?php echo $visitor_id_for_row; ?>', '<?php echo addslashes(htmlspecialchars($row['name'] ?? 'this customer')); ?>')">
                    ➕ Add Services
                </button>
            </li>

            <li><hr class="dropdown-divider my-1"></li>

            
            <li>
                <button type="button" onclick="submitMarkPaid(<?php echo $row['sale_id'] ?? $row['id']; ?>)" class="dropdown-item text-success">
                    ✅ Mark Paid
                </button>
            </li>
            <li>
                <button type="button" onclick="submitUpdatePayment(<?php echo $row['sale_id'] ?? $row['id']; ?>, 'Pending')" class="dropdown-item text-warning">
                    🕐 Mark Pending
                </button>
            </li>
            <li>
                <button type="button" onclick="submitUpdatePayment(<?php echo $row['sale_id'] ?? $row['id']; ?>, 'Unpaid')" class="dropdown-item text-danger">
                    ❌ Mark Unpaid
                </button>
            </li>

            <li><hr class="dropdown-divider my-1"></li>

            
            <li>
                <button type="button" onclick="submitUpdateStatus(<?php echo $visitor_id_for_row; ?>, 'In Progress')" class="dropdown-item">
                    🔄 In Progress
                </button>
            </li>
            <li>
                <button type="button" onclick="submitUpdateStatus(<?php echo $visitor_id_for_row; ?>, 'Completed')" class="dropdown-item text-success">
                    ✔️ Completed
                </button>
            </li>

            <li><hr class="dropdown-divider my-1"></li>

            
            <li>
                <button type="button" class="dropdown-item text-danger fw-semibold"
                        onclick="openDeleteModal(<?php echo $row['sale_id'] ?? $row['id']; ?>)">
                    🗑️ Delete Sale
                </button>
            </li>

        </ul>
    </div>
</td>



<div class="es-overlay" id="multiServiceOverlay" onclick="msCloseBackdrop(event)">
    <div class="es-modal lg sis-u-cbe2e5bd">

        <div class="es-modal-hdr green">
            <span class="sis-u-e061ff02">➕</span>
            <h6>Add Services — <span id="msCustomerName">Customer</span></h6>
            <button class="x-btn" onclick="closeMultiService()">✕</button>
        </div>

        <div class="sis-u-d0eedc56">
            <div class="es-modal-body">

                <form method="POST" id="multiServiceForm" data-customer-service-form="1" onsubmit="return window.SISHandleTimer ? window.SISHandleTimer.stopFormBeforeSubmit(this) : true;">
                    <input type="hidden" name="add_multi_sale" value="1">
                    <input type="hidden" name="visitor_id" id="ms_visitor_id">
                    <?php renderHandleTimerFields('Same Customer Handle Time', true, 'same_customer'); ?>

                    
                    <div class="sis-u-157a9c70">
                        <label class="es-label">Sale Date <span class="sis-u-0a62d1be">(applies to all services below)</span></label>
                        <input type="date" class="es-input sis-u-792cf699" name="sale_date_multi" id="ms_date"
                               required>
                    </div>

                    
                    <div id="msRowsContainer"></div>

                    
                    <button type="button" class="btn-add-svc" onclick="msAddRow()">
                        + Add Another Service
                    </button>

                    
                    <div class="ms-summary" id="msSummary">
                        <div class="sis-u-2cee9d87">
                            📋 Summary
                        </div>
                        <div id="msSummaryRows"></div>
                        <div class="ms-sum-total">
                            <span>Total</span>
                            <span id="msSummaryTotal">D 0.00</span>
                        </div>
                    </div>

                    <div class="es-footer sis-u-f4e33083">
                        <button type="button" class="es-btn cancel" onclick="closeMultiService()">Cancel</button>
                        <button type="submit" class="es-btn save-green">💾 Save All Services</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>



<div class="es-overlay" id="editSalePanel" onclick="esBackdrop(event)">
    <div class="es-modal sm">

        <div class="es-modal-hdr blue">
            <span class="sis-u-e061ff02">✏️</span>
            <h6>Edit Sale Record</h6>
            <button class="x-btn" onclick="closeEditSale()">✕</button>
        </div>

        <div class="es-modal-body">
            <div class="es-audit-box by-unknown" id="es_audit_box">
                <div class="sis-u-7860ff90">📋 Audit Info</div>
                <div id="es_audit_content">Loading…</div>
            </div>

            <form method="POST" id="editSaleForm">
                <input type="hidden" name="edit_sale" value="1">
                <input type="hidden" name="sale_id" id="es_id">

                <div class="sis-u-c58d5bea">
                    <label class="es-label">Product / Service</label>
                    <input type="text" class="es-input" name="product" id="es_product" required>
                </div>

                <div class="sis-u-c58d5bea">
                    <label class="es-label">Amount (D)</label>
                    <div class="es-input-group">
                        <span class="pfx">D</span>
                        <input type="number" class="es-input" name="amount" id="es_amount" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="sis-u-c58d5bea">
                    <label class="es-label">Sale Date</label>
                    <input type="date" class="es-input" name="sale_date" id="es_date" required>
                </div>

                <div class="sis-u-c58d5bea">
                    <label class="es-label">Payment Status</label>
                    <select class="es-select" name="payment_status" id="es_status">
                        <option value="Paid">Paid</option>
                        <option value="Pending">Pending</option>
                        <option value="Unpaid">Unpaid</option>
                    </select>
                </div>

                <div class="sis-u-157a9c70">
                    <label class="es-label">Notes <span class="sis-u-0a62d1be">(edit stamp added automatically)</span></label>
                    <textarea class="es-input sis-u-ae925e10" name="notes" id="es_notes" rows="2"></textarea>
                </div>

                <div class="es-footer">
                    <button type="button" class="es-btn cancel" onclick="closeEditSale()">Cancel</button>
                    <button type="button" class="es-btn save-blue" onclick="document.getElementById('editSaleForm').submit()">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>



<div class="es-overlay" id="deleteModal" onclick="delBackdrop(event)">
    <div class="del-box">
        <h5>⚠️ Confirm Delete</h5>
        <p>Are you sure you want to delete this sale?<br>This cannot be undone.</p>
        <form method="POST" id="deleteSaleForm">
            <input type="hidden" name="delete_sale" value="1">
            <input type="hidden" name="sale_id" id="delete_sale_id">
            <div class="sis-u-1c3eddbf">
                <button type="button" class="es-btn cancel" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="es-btn sis-u-aa1458f3">Delete</button>
            </div>
        </form>
    </div>
</div>



<script>var SERVICES = [
    'SIM Registration',
    'Cash Power',
    'Data Bundle',
    'SIM Swap',
    'SIM Replacement',
    'Free SIM',
    'Promo SIM',
    'Top-Up',
];

var msRowCount = 0;
function openMultiService(visitorId, customerName) {
    document.getElementById('ms_visitor_id').value  = visitorId;
    document.getElementById('msCustomerName').textContent = customerName;
    document.getElementById('ms_date').value        = todayDate();
    document.getElementById('msRowsContainer').innerHTML = '';
    msRowCount = 0;
    msAddRow(); // start with one row
    updateSummary();
    document.getElementById('multiServiceOverlay').classList.add('open');
    if (window.SISHandleTimer) window.SISHandleTimer.resetForForm(document.getElementById('multiServiceForm'));
    document.body.style.overflow = 'hidden';
}

function closeMultiService() {
    document.getElementById('multiServiceOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

function msCloseBackdrop(e) {
    if (e.target.id === 'multiServiceOverlay') closeMultiService();
}
function msAddRow() {
    msRowCount++;
    var n   = msRowCount;
    var div = document.createElement('div');
    div.className = 'ms-row';
    div.id = 'msRow_' + n;
    var opts = '<option value="">-- Select or type --</option>';
    SERVICES.forEach(function(s) {
        opts += '<option value="' + s + '">' + s + '</option>';
    });

    div.innerHTML =
        '<span class="ms-row-num">Service ' + n + '</span>' +
        (n > 1 ? '<button type="button" class="ms-remove" onclick="msRemoveRow(' + n + ')" title="Remove">✕</button>' : '') +

        '<div class="ms-grid g2 sis-u-a0889079">' +
            '<div>' +
                '<label class="es-label">Service / Product</label>' +
                '<select class="es-select ms-product-sel" data-row="' + n + '" onchange="msSyncProduct(this,' + n + ')">' +
                    opts +
                '</select>' +
            '</div>' +
            '<div>' +
                '<label class="es-label">Or type custom</label>' +
                '<input type="text" class="es-input" name="ms_product[]" id="ms_product_' + n + '"' +
                    ' placeholder="e.g. Special Bundle" oninput="updateSummary()">' +
            '</div>' +
        '</div>' +

        '<div class="ms-grid g3">' +
            '<div>' +
                '<label class="es-label">Amount (D)</label>' +
                '<div class="es-input-group">' +
                    '<span class="pfx">D</span>' +
                    '<input type="number" class="es-input" name="ms_amount[]" id="ms_amount_' + n + '"' +
                        ' step="0.01" min="0" placeholder="0.00" oninput="updateSummary()">' +
                '</div>' +
            '</div>' +
            '<div>' +
                '<label class="es-label">Status</label>' +
                '<select class="es-select" name="ms_status[]">' +
                    '<option value="Paid">✅ Paid</option>' +
                    '<option value="Pending">🕐 Pending</option>' +
                    '<option value="Unpaid">❌ Unpaid</option>' +
                '</select>' +
            '</div>' +
            '<div>' +
                '<label class="es-label">Quick Note</label>' +
                '<div class="ms-qnotes">' +
                    '<button type="button" class="ms-qnote b" onclick="msSetNote(' + n + ',\'Free SIM\')">🆓 Free</button>' +
                    '<button type="button" class="ms-qnote r" onclick="msSetNote(' + n + ',\'Damaged SIM\')">💔 Dmg</button>' +
                    '<button type="button" class="ms-qnote p" onclick="msSetNote(' + n + ',\'SIM Replacement\')">🔁 Repl</button>' +
                    '<button type="button" class="ms-qnote g" onclick="msSetNote(' + n + ',\'Promo SIM\')">🎁 Promo</button>' +
                '</div>' +
                '<input type="text" class="es-input" name="ms_notes[]" id="ms_notes_' + n + '"' +
                    ' placeholder="Note (optional)">' +
            '</div>' +
        '</div>';

    document.getElementById('msRowsContainer').appendChild(div);
    document.getElementById('ms_product_' + n).focus();
}
function msSyncProduct(sel, n) {
    var txt = document.getElementById('ms_product_' + n);
    if (sel.value) txt.value = sel.value;
    updateSummary();
    sel.selectedIndex = 0; // reset dropdown so agent can pick again
}

function msRemoveRow(n) {
    var el = document.getElementById('msRow_' + n);
    if (el) el.remove();
    updateSummary();
}

function msSetNote(n, text) {
    document.getElementById('ms_notes_' + n).value = text;
}
function updateSummary() {
    var rows     = document.querySelectorAll('.ms-row');
    var total    = 0;
    var lines    = [];
    var summary  = document.getElementById('msSummary');
    var sumRows  = document.getElementById('msSummaryRows');

    rows.forEach(function(row) {
        var n       = row.id.replace('msRow_', '');
        var prod    = (document.getElementById('ms_product_' + n) || {}).value || '';
        var amt     = parseFloat((document.getElementById('ms_amount_' + n) || {}).value) || 0;
        if (prod || amt) {
            total += amt;
            lines.push({ p: prod || '(unnamed)', a: amt });
        }
    });

    if (lines.length > 0) {
        summary.classList.add('show');
        sumRows.innerHTML = lines.map(function(l) {
            return '<div class="ms-sum-row"><span>' + escHtml(l.p) + '</span><span>D ' + l.a.toFixed(2) + '</span></div>';
        }).join('');
        document.getElementById('msSummaryTotal').textContent = 'D ' + total.toFixed(2);
    } else {
        summary.classList.remove('show');
    }
}
function openEditSale(data) {
    document.getElementById('es_id').value      = data.id      || '';
    document.getElementById('es_product').value = data.product || '';
    document.getElementById('es_amount').value  = data.amount  || '';
    document.getElementById('es_date').value    = data.date    || '';

    var cleanNotes = (data.notes || '')
        .replace(/\[Recorded by (supervisor|agent): [^\]]+\]/gi, '')
        .replace(/\[Edited by (supervisor|agent): [^\]]+\]/gi, '')
        .replace(/^\s*—\s*/, '').trim();
    document.getElementById('es_notes').value = cleanNotes;

    var sel = document.getElementById('es_status');
    for (var i = 0; i < sel.options.length; i++) {
        sel.options[i].selected = (sel.options[i].value === data.status);
    }

    var box  = document.getElementById('es_audit_box');
    var cont = document.getElementById('es_audit_content');
    box.className = 'es-audit-box';
    var html = '';

    if (data.rec_name) {
        var isSup = (data.rec_type === 'supervisor');
        box.classList.add(isSup ? 'by-supervisor' : 'by-agent');
        html = '<div class="sis-u-67dbae0c">' +
               (isSup ? '👁 <strong>Supervisor:</strong>' : '✅ <strong>Agent:</strong>') +
               ' <span class="audit-pill">' + escHtml(data.rec_name) + '</span>' +
               ' <span class="audit-pill">' + escHtml(data.rec_time) + '</span></div>';
    } else {
        box.classList.add('by-unknown');
        html = '<span class="sis-u-0051c4a2">No stamp — record predates this system.</span>';
    }
    if (data.edit_name) {
        html += '<div class="sis-u-19416bd6">✏️ <strong>Edited:</strong>' +
                ' <span class="audit-pill">' + escHtml(data.edit_name) + '</span>' +
                ' <span class="audit-pill">' + escHtml(data.edit_time) + '</span></div>';
    }

    cont.innerHTML = html;
    document.getElementById('editSalePanel').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeEditSale() {
    document.getElementById('editSalePanel').classList.remove('open');
    document.body.style.overflow = '';
}
function esBackdrop(e) { if (e.target.id === 'editSalePanel') closeEditSale(); }
function openDeleteModal(id) {
    document.getElementById('delete_sale_id').value = id;
    document.getElementById('deleteModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
    document.body.style.overflow = '';
}
function delBackdrop(e) { if (e.target.id === 'deleteModal') closeDeleteModal(); }
function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function todayDate() {
    return new Date().toISOString().split('T')[0];
}
function makeInput(name, value) {
    var i = document.createElement('input');
    i.type = 'hidden'; i.name = name; i.value = value;
    return i;
}
function submitMarkPaid(id) {
    var f = document.createElement('form'); f.method='POST';
    f.appendChild(makeInput('mark_as_paid','1'));
    f.appendChild(makeInput('sale_id',id));
    document.body.appendChild(f); f.submit();
}
function submitUpdatePayment(id, status) {
    var f = document.createElement('form'); f.method='POST';
    f.appendChild(makeInput('update_payment_status','1'));
    f.appendChild(makeInput('sale_id',id));
    f.appendChild(makeInput('payment_status',status));
    document.body.appendChild(f); f.submit();
}
function submitUpdateStatus(vid, status) {
    var f = document.createElement('form'); f.method='POST';
    f.appendChild(makeInput('update_status','1'));
    f.appendChild(makeInput('visitor_id',vid));
    f.appendChild(makeInput('status',status));
    document.body.appendChild(f); f.submit();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditSale();
        closeDeleteModal();
        closeMultiService();
    }
});
</script>