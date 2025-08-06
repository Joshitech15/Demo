<?php

/**
 * DataTable Pagination Class
 * Handles pagination logic for DataTables with search and filtering support
 */
class Pagination {
    private $data;
    private $totalRecords;
    private $recordsPerPage;
    private $currentPage;
    private $totalPages;
    private $searchTerm;
    private $filteredData;
    
    public function __construct($data = [], $recordsPerPage = 10) {
        $this->data = $data;
        $this->recordsPerPage = $recordsPerPage;
        $this->totalRecords = count($data);
        $this->searchTerm = '';
        $this->filteredData = $data;
    }
    
    /**
     * Set the current page
     */
    public function setCurrentPage($page) {
        $this->currentPage = max(1, intval($page));
        return $this;
    }
    
    /**
     * Set records per page
     */
    public function setRecordsPerPage($recordsPerPage) {
        $this->recordsPerPage = max(1, intval($recordsPerPage));
        return $this;
    }
    
    /**
     * Apply search filter to data
     */
    public function search($searchTerm) {
        $this->searchTerm = trim($searchTerm);
        
        if (empty($this->searchTerm)) {
            $this->filteredData = $this->data;
        } else {
            $this->filteredData = array_filter($this->data, function($record) {
                // Convert record to string for searching
                $searchString = '';
                if (is_array($record)) {
                    $searchString = implode(' ', array_values($record));
                } elseif (is_object($record)) {
                    $searchString = implode(' ', array_values((array)$record));
                } else {
                    $searchString = (string)$record;
                }
                
                return stripos($searchString, $this->searchTerm) !== false;
            });
        }
        
        return $this;
    }
    
    /**
     * Apply custom filter to data
     */
    public function filter($filterCallback) {
        if (is_callable($filterCallback)) {
            $this->filteredData = array_filter($this->filteredData, $filterCallback);
        }
        return $this;
    }
    
    /**
     * Get paginated data for current page
     */
    public function getPaginatedData() {
        $this->calculatePagination();
        
        $offset = ($this->currentPage - 1) * $this->recordsPerPage;
        
        return array_slice($this->filteredData, $offset, $this->recordsPerPage);
    }
    
    /**
     * Calculate pagination metadata
     */
    private function calculatePagination() {
        $this->totalRecords = count($this->filteredData);
        $this->totalPages = ceil($this->totalRecords / $this->recordsPerPage);
        
        // Ensure current page is within valid range
        if ($this->currentPage > $this->totalPages && $this->totalPages > 0) {
            $this->currentPage = $this->totalPages;
        }
    }
    
    /**
     * Get pagination information
     */
    public function getPaginationInfo() {
        $this->calculatePagination();
        
        $startRecord = $this->totalRecords > 0 ? (($this->currentPage - 1) * $this->recordsPerPage) + 1 : 0;
        $endRecord = min($this->currentPage * $this->recordsPerPage, $this->totalRecords);
        
        return [
            'current_page' => $this->currentPage,
            'total_pages' => $this->totalPages,
            'total_records' => $this->totalRecords,
            'records_per_page' => $this->recordsPerPage,
            'start_record' => $startRecord,
            'end_record' => $endRecord,
            'has_previous' => $this->currentPage > 1,
            'has_next' => $this->currentPage < $this->totalPages,
            'search_term' => $this->searchTerm
        ];
    }
    
    /**
     * Get page numbers for pagination controls
     */
    public function getPageNumbers($maxVisible = 5) {
        $this->calculatePagination();
        
        if ($this->totalPages <= $maxVisible) {
            return range(1, $this->totalPages);
        }
        
        $half = floor($maxVisible / 2);
        $start = max(1, $this->currentPage - $half);
        $end = min($this->totalPages, $start + $maxVisible - 1);
        
        // Adjust start if we're near the end
        if ($end - $start < $maxVisible - 1) {
            $start = max(1, $end - $maxVisible + 1);
        }
        
        return range($start, $end);
    }
    
    /**
     * Get complete response data for AJAX requests
     */
    public function getResponse() {
        return [
            'data' => $this->getPaginatedData(),
            'pagination' => $this->getPaginationInfo(),
            'page_numbers' => $this->getPageNumbers()
        ];
    }
}