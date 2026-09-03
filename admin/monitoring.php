<?php
/**
 * UPHS Online Payment Monitoring
 * 
 * @author Nico Roell D. Garce
 * @title UPHS Web Administrator 2025
 * @description Payment monitoring system for super admin to view and manage payment transactions
 */

require_once __DIR__ . '/../includes/config.php';
paymentsRequireAdmin();

$user = getUserById($_SESSION['user_id']);
$userRole = $_SESSION['user_role'];

$page_title = 'Payment Monitoring - Payments Hub';

$error = '';
$success = '';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Build query with proper escaping
$whereConditions = [];

if (!empty($search)) {
    $searchEscaped = mysqli_real_escape_string($con, $search);
    $whereConditions[] = "(txnid LIKE '%{$searchEscaped}%' OR refno LIKE '%{$searchEscaped}%' OR message LIKE '%{$searchEscaped}%')";
}

if (!empty($statusFilter)) {
    $statusEscaped = mysqli_real_escape_string($con, $statusFilter);
    $whereConditions[] = "status = '{$statusEscaped}'";
}

if (!empty($dateFrom)) {
    $dateFromEscaped = mysqli_real_escape_string($con, $dateFrom);
    $whereConditions[] = "DATE(transdate) >= '{$dateFromEscaped}'";
}

if (!empty($dateTo)) {
    $dateToEscaped = mysqli_real_escape_string($con, $dateTo);
    $whereConditions[] = "DATE(transdate) <= '{$dateToEscaped}'";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination (count unique txnid)
$countQuery = "SELECT COUNT(DISTINCT txnid) as total FROM return_data $whereClause";
$countResult = mysqli_query($con, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

// Get data (group by txnid to show only unique transactions)
$query = "SELECT txnid, refno, status, message, MAX(transdate) as transdate, amount 
          FROM return_data 
          $whereClause 
          GROUP BY txnid 
          ORDER BY transdate DESC 
          LIMIT $perPage OFFSET $offset";
$result = mysqli_query($con, $query);

$payments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $payments[] = $row;
}

// Handle export
if (isset($_GET['export'])) {
    if ($_GET['export'] === 'csv') {
        // Export all filtered data to CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="payment_monitoring_' . date('Y-m-d_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, ['Transaction ID', 'Reference No.', 'Status', 'Description', 'Transaction Date', 'Amount']);
        
        // Get all data for export (no pagination, group by txnid)
        $exportQuery = "SELECT txnid, refno, status, message, MAX(transdate) as transdate, amount 
                        FROM return_data 
                        $whereClause 
                        GROUP BY txnid 
                        ORDER BY transdate DESC";
        $exportResult = mysqli_query($con, $exportQuery);
        
        while ($row = mysqli_fetch_assoc($exportResult)) {
            fputcsv($output, [
                $row['txnid'],
                $row['refno'],
                $row['status'],
                $row['message'],
                $row['transdate'],
                number_format($row['amount'], 2, '.', ',')
            ]);
        }
        
        fclose($output);
        exit;
    }
}

// Get unique statuses for filter
$statusQuery = "SELECT DISTINCT status FROM return_data WHERE status IS NOT NULL AND status != '' ORDER BY status";
$statusResult = mysqli_query($con, $statusQuery);
$statuses = [];
while ($row = mysqli_fetch_assoc($statusResult)) {
    $statuses[] = $row['status'];
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<nav class="admin-tabs" aria-label="Admin sections">
  <a href="<?= $payments_base ?>admin/">Dashboard</a>
  <a href="<?= $payments_base ?>admin/users">Users</a>
  <a href="<?= $payments_base ?>admin/students">Student Management</a>
  <a href="<?= $payments_base ?>admin/monitoring" class="active">Payment Monitoring</a>
  <a href="<?= $payments_base ?>admin/qr">QR Codes</a>
  <a href="<?= $payments_base ?>admin/backup">Backup Portal</a>
  <a href="<?= $payments_base ?>admin/logout" class="admin-tab-logout">Logout</a>
</nav>
<div class="admin-content" style="padding:0;overflow:hidden">
<style>
    .monitoring-container {
        padding: 2rem;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .filters-container {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
    }
    
    .filters-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-dark);
        font-size: 0.9rem;
    }
    
    .filter-input, .filter-select {
        padding: 0.75rem;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }
    
    .filter-input:focus, .filter-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(28, 77, 161, 0.1);
    }
    
    .filter-actions {
        display: flex;
        gap: 0.75rem;
        align-items: end;
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(28, 77, 161, 0.3);
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
    }
    
    .btn-success {
        background: #28a745;
        color: white;
    }
    
    .btn-success:hover {
        background: #218838;
    }
    
    .btn-danger {
        background: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background: #c82333;
    }
    
    .btn i {
        font-size: 1rem;
    }
    
    .data-table-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .table-header {
        padding: 1rem 1.5rem;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .table-header h3 {
        margin: 0;
        font-size: 1.2rem;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        background: #f8f9fa;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--text-dark);
        border-bottom: 2px solid #e1e5e9;
        font-size: 0.8rem;
    }
    
    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid #e9ecef;
        font-size: 0.7rem;
        color: var(--text-dark);
    }
    
    .data-table tr:hover {
        background: #f8f9fa;
    }
    
    .status-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .status-success {
        background: #d4edda;
        color: #155724;
    }
    
    .status-failed {
        background: #f8d7da;
        color: #721c24;
    }
    
    .status-pending {
        background: #fff3cd;
        color: #856404;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        padding: 1.5rem;
        background: white;
        border-top: 1px solid #e9ecef;
    }
    
    .pagination a, .pagination span {
        padding: 0.5rem 1rem;
        border: 1px solid #e1e5e9;
        border-radius: 6px;
        text-decoration: none;
        color: var(--text-dark);
        transition: all 0.3s ease;
    }
    
    .pagination a:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .pagination .current {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .no-data {
        padding: 3rem;
        text-align: center;
        color: var(--text-light);
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
        margin-top: 2rem;
    }
    
    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .stat-card h4 {
        margin: 0 0 0.5rem 0;
        font-size: 0.9rem;
        color: var(--text-light);
        font-weight: 500;
    }
    
    .stat-card .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
    }
    
    .stat-card .btn {
        white-space: nowrap;
    }
    
    @media (max-width: 768px) {
        .monitoring-container {
            padding: 1rem;
        }
        
        .filters-row {
            grid-template-columns: 1fr;
        }
        
        .data-table {
            font-size: 0.8rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem 0.5rem;
        }
        
        .stat-card {
            flex-direction: column;
            align-items: flex-start !important;
        }
        
        .stat-card .btn {
            margin-top: 1rem;
            width: 100%;
            text-align: center;
        }
    }
</style>

<div class="monitoring-container">
    <!-- Statistics -->
    <div class="stats-row">
        <div class="stat-card">
            <h4>Total Records</h4>
            <div class="stat-value" id="total-records"><?php echo number_format($totalRecords); ?></div>
        </div>
        <div class="stat-card" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h4>Current Page</h4>
                <div class="stat-value" id="page-info"><?php echo $page; ?> / <?php echo $totalPages; ?></div>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <?php
                $exportParams = [];
                if (!empty($search)) $exportParams['search'] = $search;
                if (!empty($statusFilter)) $exportParams['status'] = $statusFilter;
                if (!empty($dateFrom)) $exportParams['date_from'] = $dateFrom;
                if (!empty($dateTo)) $exportParams['date_to'] = $dateTo;
                $exportQueryString = !empty($exportParams) ? '?' . http_build_query($exportParams) . '&' : '?';
                ?>
                <a href="monitoring?<?php echo ltrim($exportQueryString,'?'); ?>&export=csv" id="export-csv-btn" class="btn btn-success" title="Export to CSV">
                    <i class="fas fa-file-csv"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-container">
        <div class="filters-row">
            <div class="filter-group">
                <label class="filter-label">Search</label>
                <input type="text" id="filter-search" class="filter-input" placeholder="Transaction ID, Reference No., or Description" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select id="filter-status" class="filter-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Date From</label>
                <input type="date" id="filter-date-from" class="filter-input" value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Date To</label>
                <input type="date" id="filter-date-to" class="filter-input" value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <button type="button" id="clear-filters" class="btn btn-secondary">
                <i class="fas fa-times"></i> Clear
            </button>
            <div id="loading-indicator" style="display: none; padding: 0.75rem 1.5rem; color: var(--primary-color);">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
        </div>
    </div>
    
    <!-- Data Table -->
    <div class="data-table-container">
        <div class="table-header">
            <h3>Payment Transactions</h3>
        </div>
        
        <div id="table-content">
            <?php if (empty($payments)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                    <p>No payment records found.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Reference No.</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Transaction Date</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody id="payments-tbody">
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['txnid']); ?></td>
                                <td><?php echo htmlspecialchars($payment['refno']); ?></td>
                                <td>
                                    <?php
                                    $status = strtolower($payment['status']);
                                    $badgeClass = 'status-pending';
                                    if (strpos($status, 'success') !== false || strpos($status, 'approved') !== false || strpos($status, 'paid') !== false) {
                                        $badgeClass = 'status-success';
                                    } elseif (strpos($status, 'fail') !== false || strpos($status, 'error') !== false || strpos($status, 'reject') !== false) {
                                        $badgeClass = 'status-failed';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($payment['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['message']); ?></td>
                                <td><?php echo date('M d, Y H:i:s', strtotime($payment['transdate'])); ?></td>
                                <td><strong>₱<?php echo number_format($payment['amount'], 2, '.', ','); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div id="pagination-container">
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="#" class="pagination-link" data-page="<?php echo $page - 1; ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="#" class="pagination-link" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="#" class="pagination-link" data-page="<?php echo $page + 1; ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let currentPage = <?php echo $page; ?>;
let filterTimeout;

function getStatusBadgeClass(status) {
    const statusLower = status.toLowerCase();
    if (statusLower.includes('success') || statusLower.includes('approved') || statusLower.includes('paid')) {
        return 'status-success';
    } else if (statusLower.includes('fail') || statusLower.includes('error') || statusLower.includes('reject')) {
        return 'status-failed';
    }
    return 'status-pending';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const month = months[date.getMonth()];
    const day = date.getDate();
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    return `${month} ${day}, ${year} ${hours}:${minutes}:${seconds}`;
}

function formatAmount(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function loadPayments(page = 1) {
    const search = document.getElementById('filter-search').value;
    const status = document.getElementById('filter-status').value;
    const dateFrom = document.getElementById('filter-date-from').value;
    const dateTo = document.getElementById('filter-date-to').value;
    
    const loadingIndicator = document.getElementById('loading-indicator');
    const tableContent = document.getElementById('table-content');
    
    loadingIndicator.style.display = 'block';
    tableContent.style.opacity = '0.5';
    
    const params = new URLSearchParams({
        search: search,
        status: status,
        date_from: dateFrom,
        date_to: dateTo,
        page: page
    });
    
    fetch('ajax-monitoring?' + params.toString())
        .then(response => response.json())
        .then(data => {
            loadingIndicator.style.display = 'none';
            tableContent.style.opacity = '1';
            
            currentPage = data.pagination.current_page;
            
            // Update statistics
            document.getElementById('total-records').textContent = parseInt(data.pagination.total_records).toLocaleString();
            document.getElementById('page-info').textContent = `${data.pagination.current_page} / ${data.pagination.total_pages}`;
            
            // Update export CSV link
            const exportCsvUrl = 'monitoring?' + params.toString() + '&export=csv';
            document.getElementById('export-csv-btn').href = exportCsvUrl;
            
            // Update table
            const tbody = document.getElementById('payments-tbody');
            if (data.payments.length === 0) {
                tableContent.innerHTML = `
                    <div class="no-data">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                        <p>No payment records found.</p>
                    </div>
                `;
            } else {
                let tableHTML = `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Reference No.</th>
                                <th>Status</th>
                                <th>Description</th>
                                <th>Transaction Date</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="payments-tbody">
                `;
                
                data.payments.forEach(payment => {
                    const badgeClass = getStatusBadgeClass(payment.status);
                    tableHTML += `
                        <tr>
                            <td>${escapeHtml(payment.txnid)}</td>
                            <td>${escapeHtml(payment.refno)}</td>
                            <td>
                                <span class="status-badge ${badgeClass}">
                                    ${escapeHtml(payment.status)}
                                </span>
                            </td>
                            <td>${escapeHtml(payment.message)}</td>
                            <td>${formatDate(payment.transdate)}</td>
                            <td><strong>${formatAmount(payment.amount)}</strong></td>
                        </tr>
                    `;
                });
                
                tableHTML += `
                        </tbody>
                    </table>
                `;
                
                // Update pagination
                if (data.pagination.total_pages > 1) {
                    tableHTML += '<div id="pagination-container"><div class="pagination">';
                    
                    if (data.pagination.current_page > 1) {
                        tableHTML += `<a href="#" class="pagination-link" data-page="${data.pagination.current_page - 1}"><i class="fas fa-chevron-left"></i> Previous</a>`;
                    }
                    
                    const startPage = Math.max(1, data.pagination.current_page - 2);
                    const endPage = Math.min(data.pagination.total_pages, data.pagination.current_page + 2);
                    
                    for (let i = startPage; i <= endPage; i++) {
                        if (i === data.pagination.current_page) {
                            tableHTML += `<span class="current">${i}</span>`;
                        } else {
                            tableHTML += `<a href="#" class="pagination-link" data-page="${i}">${i}</a>`;
                        }
                    }
                    
                    if (data.pagination.current_page < data.pagination.total_pages) {
                        tableHTML += `<a href="#" class="pagination-link" data-page="${data.pagination.current_page + 1}">Next <i class="fas fa-chevron-right"></i></a>`;
                    }
                    
                    tableHTML += '</div></div>';
                } else {
                    tableHTML += '<div id="pagination-container"></div>';
                }
                
                tableContent.innerHTML = tableHTML;
                
                // Re-attach pagination event listeners
                attachPaginationListeners();
            }
            
            // Scroll to top of table
            document.querySelector('.data-table-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
        })
        .catch(error => {
            loadingIndicator.style.display = 'none';
            tableContent.style.opacity = '1';
            console.error('Error loading payments:', error);
            alert('Error loading payment data. Please try again.');
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function attachPaginationListeners() {
    document.querySelectorAll('.pagination-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = parseInt(this.getAttribute('data-page'));
            loadPayments(page);
        });
    });
}

// Filter event listeners
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('filter-search');
    const statusSelect = document.getElementById('filter-status');
    const dateFromInput = document.getElementById('filter-date-from');
    const dateToInput = document.getElementById('filter-date-to');
    const clearBtn = document.getElementById('clear-filters');
    
    // Set initial export URLs
    function updateExportUrls() {
        const params = new URLSearchParams({
            search: searchInput.value,
            status: statusSelect.value,
            date_from: dateFromInput.value,
            date_to: dateToInput.value
        });
        const exportCsvUrl = 'monitoring?' + params.toString() + '&export=csv';
        document.getElementById('export-csv-btn').href = exportCsvUrl;
    }
    
    // Initialize export URLs
    updateExportUrls();
    
    // Debounced search input
    searchInput.addEventListener('input', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            currentPage = 1;
            updateExportUrls();
            loadPayments(1);
        }, 500);
    });
    
    // Immediate filter changes
    statusSelect.addEventListener('change', function() {
        currentPage = 1;
        updateExportUrls();
        loadPayments(1);
    });
    
    dateFromInput.addEventListener('change', function() {
        currentPage = 1;
        updateExportUrls();
        loadPayments(1);
    });
    
    dateToInput.addEventListener('change', function() {
        currentPage = 1;
        updateExportUrls();
        loadPayments(1);
    });
    
    // Clear filters
    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        statusSelect.value = '';
        dateFromInput.value = '';
        dateToInput.value = '';
        currentPage = 1;
        updateExportUrls();
        loadPayments(1);
    });
    
    // Initial pagination listeners
    attachPaginationListeners();
});
</script>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>





