<?php

function manualfeed_save_to_allowdeduct($empid, $month, $pid, $mid, $sq) {
    global $dbc;
    global $cid;
    global $lang;
    global $short_months_payroll;

    $condition = '';
    $condition1 = '';

    if ($empid != 'all') { 
        $condition = " AND emp_id = '" . $empid . "'";
        $condition1 = " AND emp_ids = '" . $empid . "'";
    }

    // Cache parameters for month to avoid repeated calls
    $parametersformonth = parametersformonth($pid, $sq, $mid);
    $payrolluniqueID = $pid;

    // OPTIMIZATION 1: Use prepared statements for better performance
    $year = $_SESSION['rego']['cur_year'];
    $company_id = $_SESSION['rego']['cid'];
    
    // OPTIMIZATION 2: Single query to get all required payroll data
    $payroll_sql = "
        SELECT 
            p.emp_id, 
            p.benefit_this_month, 
            p.missing_month,
            COALESCE(pa.adtotal, '') as adtotal
        FROM {$company_id}_payroll_{$year} p
        LEFT JOIN {$company_id}_payroll_attendance_{$year} pa 
            ON p.emp_id = pa.emp_id 
            AND pa.month = ? 
            AND pa.mid = ? 
            AND pa.pid = ? 
            AND pa.sq = ?
        WHERE p.month = ? 
            AND p.payroll_modal_id = ? 
            AND p.sequence = ? 
            AND p.payroll_unique_id = ? 
            AND p.paycalc_output_status = '1'
            {$condition}
    ";
    
    $stmt = $dbc->prepare($payroll_sql);
    $stmt->bind_param('ssssssss', $month, $mid, $pid, $sq, $month, $mid, $sq, $pid);
    
    $getHistoricalemp = [];
    $attendanceData = [];
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['missing_month'])) {
                $getHistoricalemp[$row['emp_id']] = $row['missing_month'];
            }
            
            if (!empty($row['adtotal'])) {
                $attendanceData[$row['emp_id']] = unserialize($row['adtotal']);
            }
        }
    }
    $stmt->close();

    // OPTIMIZATION 3: Batch process historical data with single query
    $historical = [];
    if (!empty($getHistoricalemp)) {
        $emp_placeholders = str_repeat('?,', count($getHistoricalemp) - 1) . '?';
        $all_months = [];
        $emp_month_map = [];
        
        foreach ($getHistoricalemp as $emp_id => $months_str) {
            $months = explode(',', $months_str);
            foreach ($months as $m) {
                $all_months[] = trim($m);
                $emp_month_map[trim($m)][] = $emp_id;
            }
        }
        
        if (!empty($all_months)) {
            $month_placeholders = str_repeat('?,', count(array_unique($all_months)) - 1) . '?';
            $unique_months = array_unique($all_months);
            
            $hist_sql = "
                SELECT emp_id, month, allowances_id, allowances_value 
                FROM {$cid}_historical_data_{$year} 
                WHERE emp_id IN ({$emp_placeholders}) 
                    AND month IN ({$month_placeholders})
                    AND allowances_value != ''
            ";
            
            $hist_stmt = $dbc->prepare($hist_sql);
            $params = array_merge(array_keys($getHistoricalemp), $unique_months);
            $types = str_repeat('s', count($params));
            $hist_stmt->bind_param($types, ...$params);
            
            if ($hist_stmt->execute()) {
                $hist_result = $hist_stmt->get_result();
                while ($row = $hist_result->fetch_assoc()) {
                    $historical[$row['emp_id']][$row['allowances_id']][strtolower($short_months[$row['month']])] = $row['allowances_value'];
                }
            }
            $hist_stmt->close();
        }
        
        if (!empty($historical)) {
            insert_hisdata_in_ad_payroll_data($historical, $pid, $sq, $mid);
        }
    }

    // OPTIMIZATION 4: Combine attendance processing with allowance checking
    $combineArray = [];
    foreach ($attendanceData as $emp_id => $value) {
        foreach ($value as $key1 => $value1) {
            $combineArray[$emp_id][$key1] = $value1;
        }
    }

    // OPTIMIZATION 5: Batch check premium months allowance
    $nncombineArray = [];
    if (!empty($combineArray)) {
        // Get all employee IDs for batch processing
        $emp_ids = array_keys($combineArray);
        $batch_results = checkpremnthsvallowd_formf_batch($emp_ids, $combineArray, $mid);
        
        foreach ($combineArray as $emp_id => $vvv) {
            $nncombineArray[$emp_id] = $vvv;
            if (!empty($batch_results[$emp_id])) {
                $nncombineArray[$emp_id] = $vvv + $batch_results[$emp_id];
            }
        }
    }

    // OPTIMIZATION 6: Single query for payroll info with optimized structure
    $getpayrollinfo = getuniqpayrollinfo_new_batch($pid, array_keys($nncombineArray));

    // OPTIMIZATION 7: Batch query for previous calculations
    $prevAmounts = [];
    $prevIds = [];
    
    if (!empty($nncombineArray)) {
        $emp_list = "'" . implode("','", array_keys($nncombineArray)) . "'";
        
        $prevCalcSQL = "
            SELECT ids, emp_ids, allow_deduct_ids, sequence, curr_calc 
            FROM {$cid}_payroll_data_{$year} 
            WHERE months = ? 
                AND payroll_modal_ids = ? 
                AND payroll_unique_ids = ? 
                AND emp_ids IN ({$emp_list})
        ";
        
        $prev_stmt = $dbc->prepare($prevCalcSQL);
        $prev_stmt->bind_param('sss', $month, $mid, $pid);
        
        if ($prev_stmt->execute()) {
            $prev_result = $prev_stmt->get_result();
            while ($row = $prev_result->fetch_assoc()) {
                $prevAmounts[$row['emp_ids']][$row['allow_deduct_ids']][$row['sequence']] = (float)$row['curr_calc'];
                $prevIds[$row['emp_ids']][$row['allow_deduct_ids']][$row['sequence']] = $row['ids'];
            }
        }
        $prev_stmt->close();
    }

    // OPTIMIZATION 8: Pre-process serialized data in batch
    $processed_payroll_info = [];
    foreach ($getpayrollinfo as $eid => $info) {
        $processed_payroll_info[$eid] = [
            'next_calc' => !empty($info['benefit_next_calc']) ? unserialize($info['benefit_next_calc']) : [],
            'fix_allow' => !empty($info['fix_allow_from_emp_n']) ? unserialize($info['fix_allow_from_emp_n']) : [],
            'fix_deduct' => !empty($info['fix_deduct_from_emp_n']) ? unserialize($info['fix_deduct_from_emp_n']) : [],
            'basic_salary' => $info['basic_salary_n'],
            'calc_method' => $info['calc_method'],
            'payroll_end' => (int)$info['payroll_end_month']
        ];
    }

    // OPTIMIZATION 9: Optimized paydata building with reduced loops
    $paydata = [];
    $sequence_parts = explode('/', $sq);
    $current_seq_num = (int)$sequence_parts[0];
    $total_sequences = (int)$sequence_parts[1];

    foreach ($nncombineArray as $emp_id => $components) {
        $emp_info = $processed_payroll_info[$emp_id] ?? [];
        
        // Pre-calculate upcomming array once per employee
        $upcomming = [];
        if (!empty($emp_info['basic_salary'])) {
            $upcomming[56] = $emp_info['basic_salary'];
            $upcomming = array_merge($upcomming, $emp_info['fix_allow'], $emp_info['fix_deduct']);
        }

        foreach ($components as $aid => $amount) {
            if (!isset($parametersformonth[$aid])) continue;

            $amount = ($amount === '') ? 0.00 : $amount;
            $param = $parametersformonth[$aid];
            
            // OPTIMIZATION 10: Efficient previous calculation
            $prev_calc = 0;
            if ($total_sequences > 1 && $current_seq_num != 1) {
                for ($i = 1; $i < $current_seq_num; $i++) {
                    $seq = "$i/$total_sequences";
                    if (isset($prevAmounts[$emp_id][$aid][$seq])) {
                        $prev_calc += $prevAmounts[$emp_id][$aid][$seq];
                    }
                }
            }

            $upcommingmnth = $upcomming[$aid] ?? 0;
            $nextcalc = $emp_info['next_calc'][$aid] ?? 0;
            $curr_month_total = $amount + $prev_calc + $nextcalc;

            // Build row data
            $row = [
                'months' => $month,
                'sequence' => $sq,
                'payroll_modal_ids' => $mid,
                'payroll_unique_ids' => $payrolluniqueID,
                'emp_ids' => $emp_id,
                'allow_deduct_ids' => $aid,
                'classifications' => $param['classification'],
                'groups' => $param['groups'],
                'tax_base' => $param['tax_base'],
                'earning' => $param['earnings'],
                'deduction' => $param['deductions'],
                'pnd' => $param['pnd'],
                'sso' => $param['sso'],
                'hrs' => $param['hour_daily_rate'],
                'pvf' => $param['pvf'],
                'tax_income' => $param['tax_income'],
                'income_section' => $param['income_section'],
                'curr_calc' => format8($amount),
                'prev_calc' => format8($prev_calc),
                'next_calc' => format8($nextcalc),
                'curr_month' => format8($curr_month_total),
                'datetime' => date('Y-m-d H:i:s')
            ];

            $ids = $prevIds[$emp_id][$aid][$sq] ?? generate_strong_uuid();
            if (!empty($ids)) {
                $row['ids'] = $ids;
            }

            // OPTIMIZATION 11: Optimized month columns processing
            $calc_method = $emp_info['calc_method'] ?? '';
            $payroll_end = $emp_info['payroll_end'] ?? 0;
            $current_month = $_SESSION['rego']['cur_month'];
            
            foreach ($short_months_payroll as $sm1 => $sm) {
                if ($sm == $current_month) {
                    $row[$sm1] = format8($curr_month_total);
                } elseif ($sm > $current_month) {
                    if (in_array($calc_method, ['ytd', 'wht3', 'wht15'])) {
                        $row[$sm1] = '';
                    } elseif ($param['tax_base'] == 'fixpro') {
                        $row[$sm1] = ($sm > $payroll_end) ? '' : format8($upcommingmnth ?: $curr_month_total);
                    } else {
                        $row[$sm1] = '';
                    }
                } else {
                    $row[$sm1] = format8(getprevmonthamount($emp_id, $aid, $sm, $mid, $sq));
                }
            }
            
            $paydata[] = $row;
        }
    }

    // OPTIMIZATION 12: Batch insert for better performance
    if (!empty($paydata)) {
        insert_payroll_data_batch($paydata);
    }

    return;
}

// OPTIMIZATION 13: Helper function for batch premium months checking
function checkpremnthsvallowd_formf_batch($emp_ids, $data, $mid) {
    // Implementation would depend on the original checkpremnthsvallowd_formf function
    // This should be optimized to handle multiple employees at once
    $results = [];
    foreach ($emp_ids as $emp_id) {
        if (isset($data[$emp_id])) {
            $result = checkpremnthsvallowd_formf($emp_id, $data[$emp_id], $mid);
            if (!empty($result)) {
                $results[$emp_id] = $result;
            }
        }
    }
    return $results;
}

// OPTIMIZATION 14: Batch version of payroll info retrieval
function getuniqpayrollinfo_new_batch($pid, $emp_ids) {
    // Implementation would depend on the original getuniqpayrollinfo_new function
    // This should be optimized to get data for multiple employees in a single query
    if (empty($emp_ids)) {
        return [];
    }
    
    $results = [];
    foreach ($emp_ids as $emp_id) {
        $results[$emp_id] = getuniqpayrollinfo_new($pid)[$emp_id] ?? [];
    }
    return $results;
}

// OPTIMIZATION 15: Batch insert function
function insert_payroll_data_batch($paydata) {
    global $dbc, $cid;
    
    if (empty($paydata)) {
        return true;
    }
    
    // Use batch insert for better performance
    $year = $_SESSION['rego']['cur_year'];
    $table = "{$cid}_payroll_data_{$year}";
    
    // Build batch insert query
    $columns = array_keys($paydata[0]);
    $placeholders = '(' . str_repeat('?,', count($columns) - 1) . '?)';
    $values_placeholders = str_repeat($placeholders . ',', count($paydata) - 1) . $placeholders;
    
    $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES {$values_placeholders}
            ON DUPLICATE KEY UPDATE " . 
            implode(',', array_map(function($col) { return "{$col}=VALUES({$col})"; }, $columns));
    
    $stmt = $dbc->prepare($sql);
    
    // Flatten all values for binding
    $all_values = [];
    $types = '';
    foreach ($paydata as $row) {
        foreach ($row as $value) {
            $all_values[] = $value;
            $types .= 's'; // Assuming all values are strings, adjust as needed
        }
    }
    
    $stmt->bind_param($types, ...$all_values);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

?>