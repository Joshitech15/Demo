/**
 * DataTable with Pagination
 * A comprehensive JavaScript class for handling paginated data tables
 */
class DataTable {
    constructor(options = {}) {
        // Default configuration
        this.config = {
            container: '#datatable-container',
            apiUrl: 'api.php',
            recordsPerPage: 10,
            searchDelay: 300,
            showSearch: true,
            showRecordsPerPage: true,
            showPaginationInfo: true,
            columns: [],
            sortable: true,
            responsive: true,
            loadingText: 'Loading...',
            noDataText: 'No data available',
            ...options
        };
        
        // Internal state
        this.currentPage = 1;
        this.searchTerm = '';
        this.isLoading = false;
        this.searchTimeout = null;
        this.sortColumn = null;
        this.sortDirection = 'asc';
        
        // Initialize
        this.init();
    }
    
    /**
     * Initialize the DataTable
     */
    init() {
        this.container = document.querySelector(this.config.container);
        if (!this.container) {
            console.error('DataTable container not found:', this.config.container);
            return;
        }
        
        this.render();
        this.attachEventListeners();
        this.loadData();
    }
    
    /**
     * Render the DataTable HTML structure
     */
    render() {
        const html = `
            <div class="datatable-wrapper">
                ${this.config.showSearch || this.config.showRecordsPerPage ? `
                <div class="datatable-header">
                    ${this.config.showRecordsPerPage ? `
                    <div class="datatable-length">
                        <label>
                            Show 
                            <select class="records-per-page">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            entries
                        </label>
                    </div>
                    ` : ''}
                    ${this.config.showSearch ? `
                    <div class="datatable-search">
                        <label>
                            Search: 
                            <input type="search" class="search-input" placeholder="Search records...">
                        </label>
                    </div>
                    ` : ''}
                </div>
                ` : ''}
                
                <div class="datatable-container">
                    <table class="datatable">
                        <thead>
                            <tr class="datatable-header-row">
                                <!-- Headers will be populated dynamically -->
                            </tr>
                        </thead>
                        <tbody class="datatable-body">
                            <!-- Data will be populated dynamically -->
                        </tbody>
                    </table>
                    
                    <div class="datatable-loading" style="display: none;">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">${this.config.loadingText}</div>
                    </div>
                    
                    <div class="datatable-no-data" style="display: none;">
                        ${this.config.noDataText}
                    </div>
                </div>
                
                <div class="datatable-footer">
                    ${this.config.showPaginationInfo ? `
                    <div class="datatable-info">
                        <!-- Pagination info will be populated dynamically -->
                    </div>
                    ` : ''}
                    
                    <div class="datatable-pagination">
                        <!-- Pagination controls will be populated dynamically -->
                    </div>
                </div>
            </div>
        `;
        
        this.container.innerHTML = html;
        
        // Cache DOM elements
        this.elements = {
            wrapper: this.container.querySelector('.datatable-wrapper'),
            table: this.container.querySelector('.datatable'),
            thead: this.container.querySelector('.datatable-header-row'),
            tbody: this.container.querySelector('.datatable-body'),
            loading: this.container.querySelector('.datatable-loading'),
            noData: this.container.querySelector('.datatable-no-data'),
            info: this.container.querySelector('.datatable-info'),
            pagination: this.container.querySelector('.datatable-pagination'),
            searchInput: this.container.querySelector('.search-input'),
            recordsPerPageSelect: this.container.querySelector('.records-per-page')
        };
    }
    
    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Search input
        if (this.elements.searchInput) {
            this.elements.searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.search(e.target.value);
                }, this.config.searchDelay);
            });
        }
        
        // Records per page select
        if (this.elements.recordsPerPageSelect) {
            this.elements.recordsPerPageSelect.addEventListener('change', (e) => {
                this.config.recordsPerPage = parseInt(e.target.value);
                this.currentPage = 1;
                this.loadData();
            });
        }
        
        // Pagination clicks (event delegation)
        if (this.elements.pagination) {
            this.elements.pagination.addEventListener('click', (e) => {
                e.preventDefault();
                const target = e.target.closest('[data-page]');
                if (target && !target.classList.contains('disabled')) {
                    this.goToPage(parseInt(target.dataset.page));
                }
            });
        }
        
        // Column sorting (event delegation)
        if (this.config.sortable && this.elements.thead) {
            this.elements.thead.addEventListener('click', (e) => {
                const th = e.target.closest('th[data-column]');
                if (th) {
                    this.sort(th.dataset.column);
                }
            });
        }
    }
    
    /**
     * Load data from API
     */
    async loadData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading(true);
        
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.config.recordsPerPage,
                search: this.searchTerm
            });
            
            const response = await fetch(`${this.config.apiUrl}?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderData(data);
                this.renderPagination(data.pagination, data.page_numbers);
                this.renderInfo(data.pagination);
            } else {
                this.showError('Failed to load data: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            this.showError('Network error: ' + error.message);
        } finally {
            this.isLoading = false;
            this.showLoading(false);
        }
    }
    
    /**
     * Render table headers
     */
    renderHeaders() {
        if (!this.config.columns.length) return;
        
        const headers = this.config.columns.map(column => {
            const sortClass = this.sortColumn === column.key ? 
                `sorted ${this.sortDirection}` : 'sortable';
            
            return `
                <th data-column="${column.key}" class="${sortClass}">
                    ${column.title}
                    ${this.config.sortable ? '<span class="sort-indicator"></span>' : ''}
                </th>
            `;
        }).join('');
        
        this.elements.thead.innerHTML = headers;
    }
    
    /**
     * Render table data
     */
    renderData(response) {
        const data = response.data || [];
        
        // Auto-detect columns if not provided
        if (!this.config.columns.length && data.length > 0) {
            this.config.columns = Object.keys(data[0]).map(key => ({
                key: key,
                title: key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ')
            }));
        }
        
        // Render headers
        this.renderHeaders();
        
        if (data.length === 0) {
            this.elements.tbody.innerHTML = '';
            this.elements.noData.style.display = 'block';
            this.elements.table.style.display = 'none';
            return;
        }
        
        this.elements.noData.style.display = 'none';
        this.elements.table.style.display = 'table';
        
        const rows = data.map(row => {
            const cells = this.config.columns.map(column => {
                const value = row[column.key] || '';
                return `<td data-label="${column.title}">${this.formatCellValue(value, column)}</td>`;
            }).join('');
            
            return `<tr>${cells}</tr>`;
        }).join('');
        
        this.elements.tbody.innerHTML = rows;
    }
    
    /**
     * Format cell value based on column type
     */
    formatCellValue(value, column) {
        if (column.formatter && typeof column.formatter === 'function') {
            return column.formatter(value);
        }
        
        // Auto-format common data types
        if (column.key.includes('date') && value) {
            return new Date(value).toLocaleDateString();
        }
        
        if (column.key.includes('salary') || column.key.includes('price')) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(value);
        }
        
        return value;
    }
    
    /**
     * Render pagination controls
     */
    renderPagination(pagination, pageNumbers) {
        if (!pagination || pagination.total_pages <= 1) {
            this.elements.pagination.innerHTML = '';
            return;
        }
        
        const prevClass = pagination.has_previous ? '' : 'disabled';
        const nextClass = pagination.has_next ? '' : 'disabled';
        
        let paginationHtml = `
            <div class="pagination">
                <a href="#" class="page-link ${prevClass}" data-page="${pagination.current_page - 1}">
                    Previous
                </a>
        `;
        
        // First page
        if (pageNumbers[0] > 1) {
            paginationHtml += `
                <a href="#" class="page-link" data-page="1">1</a>
                ${pageNumbers[0] > 2 ? '<span class="page-ellipsis">...</span>' : ''}
            `;
        }
        
        // Page numbers
        pageNumbers.forEach(page => {
            const activeClass = page === pagination.current_page ? 'active' : '';
            paginationHtml += `
                <a href="#" class="page-link ${activeClass}" data-page="${page}">${page}</a>
            `;
        });
        
        // Last page
        const lastPageNumber = pageNumbers[pageNumbers.length - 1];
        if (lastPageNumber < pagination.total_pages) {
            paginationHtml += `
                ${lastPageNumber < pagination.total_pages - 1 ? '<span class="page-ellipsis">...</span>' : ''}
                <a href="#" class="page-link" data-page="${pagination.total_pages}">${pagination.total_pages}</a>
            `;
        }
        
        paginationHtml += `
                <a href="#" class="page-link ${nextClass}" data-page="${pagination.current_page + 1}">
                    Next
                </a>
            </div>
        `;
        
        this.elements.pagination.innerHTML = paginationHtml;
    }
    
    /**
     * Render pagination info
     */
    renderInfo(pagination) {
        if (!this.elements.info || !pagination) return;
        
        const infoText = pagination.total_records > 0 
            ? `Showing ${pagination.start_record} to ${pagination.end_record} of ${pagination.total_records} entries`
            : 'No entries to show';
        
        if (pagination.search_term) {
            this.elements.info.innerHTML = `${infoText} (filtered from ${pagination.total_records} total entries)`;
        } else {
            this.elements.info.innerHTML = infoText;
        }
    }
    
    /**
     * Show/hide loading state
     */
    showLoading(show) {
        if (this.elements.loading) {
            this.elements.loading.style.display = show ? 'flex' : 'none';
        }
        if (this.elements.table) {
            this.elements.table.style.opacity = show ? '0.5' : '1';
        }
    }
    
    /**
     * Show error message
     */
    showError(message) {
        console.error('DataTable Error:', message);
        if (this.elements.tbody) {
            this.elements.tbody.innerHTML = `
                <tr>
                    <td colspan="100%" class="error-message">
                        <div class="alert alert-error">
                            ${message}
                        </div>
                    </td>
                </tr>
            `;
        }
    }
    
    /**
     * Navigate to specific page
     */
    goToPage(page) {
        if (page !== this.currentPage && page > 0) {
            this.currentPage = page;
            this.loadData();
        }
    }
    
    /**
     * Search data
     */
    search(term) {
        this.searchTerm = term;
        this.currentPage = 1;
        this.loadData();
    }
    
    /**
     * Sort data by column
     */
    sort(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
        
        this.currentPage = 1;
        this.loadData();
    }
    
    /**
     * Refresh data
     */
    refresh() {
        this.loadData();
    }
    
    /**
     * Update configuration
     */
    updateConfig(newConfig) {
        this.config = { ...this.config, ...newConfig };
        this.render();
        this.attachEventListeners();
        this.loadData();
    }
    
    /**
     * Destroy the DataTable
     */
    destroy() {
        if (this.container) {
            this.container.innerHTML = '';
        }
        clearTimeout(this.searchTimeout);
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DataTable;
}