<?php
declare(strict_types=1);

/***********************************************************************************
 * DATA PIPELINE DISPLAY LOGIC
 ***********************************************************************************/
// display row elements for the specified data domain and zone (silver and gold, bronze works differently below)
function display_pipeline_zone($db, $org, $domain, $zone):void {
    // get domain configuration attributes from config.domain
    $query = "select select_for_mapping, sort_order from config.domain where name='{$domain}';";
    $result = pg_query($db, $query);
    $row = pg_fetch_assoc($result);
    $sort_order = $row['sort_order'];
    $format_table = pg_bool($row['select_for_mapping']);
    // radio button selection for silver zone ONLY
    $select_type = ( ( $format_table && ($zone=='silver') ) ? 'radio' : '');
    // TODO - if we are never going to allow selection in gold, simplify group name (i.e., remove zone)
    $group_name = ( $select_type=='radio' ? 'pipeline-' . $zone : '');

    // get column headings for this table based on domain and zone
    // silver zone fields are in config.field_display, gold in config.field_output
    // NOTE: this code depends on the column headings in both being the same
    $config_table = ( $zone=='silver' ? 'field_display' : 'field_output' );
    $query = "select column_name, display_heading, ordinal_position from config.{$config_table} ";
    $query .= "where config_domain='{$domain}' order by ordinal_position;";
    $result = pg_query($db, $query);
    if (pg_num_rows($result)==0) {
        echo "Need to add columns to config.field_display for domain: {$domain}";
    } else {
        // Generate HTML for table and header row
        echo "<table class='display table-heading' id='{$zone}-pipeline-table' style='width:100%;'><thead><tr>";
        if ($select_type!='') {
            echo "<th>Select</th>";
        }
        while ($row = pg_fetch_assoc($result)) {
            if ($row['ordinal_position'] > 0) {
                echo "<th>{$row['display_heading']}</th>";
            }
        }
        echo '</tr></thead>';

        // Generate table rows - build query to pass to display_table function
        $query = "select column_name from config.{$config_table} where config_domain='{$domain}' order by ordinal_position;";
        $result = pg_query($db, $query);
        pg_fetch_assoc($result, 0);   // re-initialize query
        $row = pg_fetch_assoc($result);
        $query = "select " . $row['column_name'];
        while ($row = pg_fetch_assoc($result)) {
            $query .= ", " . $row['column_name'];
        }

        // schema and domain depend on which zone is being displayed
        $query .= " from {$zone}.{$domain} where source_org='{$org}' order by ";
        $query .= ($sort_order=='' ? '1' : $sort_order) . ";";
        display_table($db, $query, $select_type, $group_name, $format_table, $zone);
        echo "</table>";
    }
}

function display_pipeline_bronze($db, $org, $domain):void {
    // get column headings for this table based on domain, org and zone
    $query = "select column_name, display_heading, field_name, data_type from config.field_import ";
    $query .= "where config_domain='{$domain}' and config_org='{$org}' order by ordinal_position;";
    $result = pg_query($db, $query);
    if (pg_num_rows($result)==0) {
        // get column headings for this table based on domain and default org (cdm)
        $query = "select column_name, display_heading, field_name, data_type from config.field_import ";
        $query .= "where config_domain='{$domain}' and config_org='cdm' order by ordinal_position;";
        $result = pg_query($db, $query);
    }

    if (pg_num_rows($result)==0) {
        echo "Need to add columns to config.field_import for domain: {$domain}";
    } else {
        // Generate HTML for table and header row and build query for content to display
        $query_fields = "";
        // echo '<div class="tableFixHead"><table style="width:100%;"><thead><tr>';
        echo "<table class='display table-heading' id='bronze-pipeline-table' style='width:100%;'><thead><tr>";
        // echo '<tr style="color:white;font-family:sans-serif;font-size=0.9em;">';
        while ($row = pg_fetch_assoc($result)) {
            $heading = $row['display_heading'];
            if ($row['data_type'] != 'ignore' && $heading != '') {
                echo "<th>{$heading}</th>";
                $query_fields .= $row['column_name'] . ', ';
            }
            // echo return_nonblank("<th>{$heading}</th>", $heading);
        }
        echo '</tr></thead>';

        // construct bronze query from table in org schema and selected domain
        if (strlen($query_fields) == 0) {
            echo 'Need to define at least one heading for import data (bronze zone) for this domain';
        } else {
            $query = "select " . substr($query_fields, 0, -2) . " from {$org}.{$domain} order by 1";
            display_table($db, $query);
        }
        // echo "</table></div>";
        echo "</table>";
    }
}

// display data specified in query in a scrollable table w/ or w/out formatting
function display_table($db, $query, $select_type='', $group_name='', $format_inferred=false, $zone=''):void {
    // echo 'Table display query: ' . $query . '<br/>';
    $result = execute_query($db, $query);
    /*
    $result = pg_query($db, $query);
    log_query_results($db, $query, $result);
    if (pg_result_error($result)) {
        echo "Error displaying data for org: {$org}, domain: {$domain}";
    */
    if (!$result) {
        echo "An error occurred in PostgreSQL query: " . $query . "\n";
    } else {

        // display table rows and columns
        $starting_column = 0;
        $columns = pg_num_fields($result);
        echo "<tbody>";
        while ($row = pg_fetch_row($result)) {
            // echo "<tr id='concept-record' onclick=setConceptKey();>";
            echo "<tr id='concept-record'>";
            // if this is a table in either of the pipeline zones, first 3 columns have source_org, primary_key, and inferred
            if ($zone != '') {
                // $source_org = $row[0];  // really don't need source_org in first column
                $key_value = $row[1];
                $inferred_record = pg_bool($row[2]);
                $style_inferred = ( ($format_inferred && $inferred_record) ? ' style="font-style:italic; color:#0065A2;"' : '');

                switch ($select_type) {
                    case 'checkbox':
                        checkbox_group($group_name, $key_value, '', false, false, true);
                        break;
                    case 'radio':
                        // added this to mark the non-inferred (original source) concept in radio group (details page)
                        // TODO - clean up and make somewhat more elegant when time permits, if necessary
                        radio_option($group_name, $key_value, '', (($_POST[$group_name]==$key_value)&&!$inferred_record), false, true);
                        break;
                    case 'button':
                        button_group($group_name, $key_value, 'Map', false, true);
                        break;
                }
                $starting_column = 3;
            }

            // display remaining visible table columns
            for ($x = $starting_column; $x < $columns; $x++) {
                $value = $row[$x];
                echo "<td{$style_inferred}>{$value}</td>";
            }
            echo "</tr>\n";
        }
        echo "</tbody>";
    }
}

function get_silver_sql($db, $domain, $org='', $org_specific=false) {
    $query = "select load_pipeline_sql from config.pipeline_import_sql where domain='{$domain}' ";
    if ($org_specific) {
        $query .= "and org='{$org}';";
    } else {
        $query .= "and coalesce(org, 'cdm')='cdm';";
    }
    $row = pg_fetch_assoc(pg_query($db, $query));
    return $row['load_pipeline_sql'];
}

function get_domain_info($db, $domain, $field) {
    $query = "select {$field} from config.domain where name='{$domain}';";
    $row = pg_fetch_assoc(pg_query($db, $query));
    return $row[$field];
}

function show_load_progress($activity, $i):void {
    // echo "<tr><td>{$activity}</td><td>" . date('H:i:s') . "</td>";
    echo "<tr><td>{$activity}</td>";
    echo "<td id=\"progress_start_{$i}\">&nbsp;</td>";
    echo "<td id=\"progress_complete_{$i}\">&nbsp;</td>";
    echo "<td id=\"progress_elapsed_{$i}\">&nbsp;</td></tr>\n";
    show_load_elapsed($i);
    ob_flush();
    flush();
}

function show_load_complete($i):void {
    echo "<script>";
    // echo "$('#progress_complete_{$i}').html('" . date('H:i:s') . "');";
    echo "localTime = getLocalTime();";
    echo "$('#progress_complete_{$i}').html(localTime);";
    echo "clearInterval(progressInterval);";
    // echo "$('#progress_elapsed_{$i}').html('Done');";
    echo "</script>";
    ob_flush();
    flush();
}

function show_load_elapsed($i):void {
    echo "<script>";
    echo "localTime = getLocalTime();";
    echo "$('#progress_start_{$i}').html(localTime);";
    echo "$('#progress_elapsed_{$i}').html('00:01');";
    echo "clearCounter();";
    echo "setProgressRow({$i});";
    echo "var progressInterval = setInterval(progressTimer, 1000);";
    echo "</script>";
}

/***********************************************************************************
 * LOGIC TO GENERATE AND DISPLAY DATA QUALITY INFORMATION
 ***********************************************************************************/
function display_missing_table($db, $org, $domain):void {
    // get primary key for selected domain
    $primary_key = get_primary_key($db, $domain);
    if ($primary_key) {
        // get fields to check for missing
        $query = "select source_field, datatype from config.quality where domain='" . $domain . "' and validation='missing' order by sequence;";
        // echo 'Missing data query: ' . $query;
        $result = pg_query($db, $query);
        if ($result) {
            while ($row = pg_fetch_row($result)) {

                // find any missing elements
                $query = "select upn_id, " . $primary_key . ", '" . $row[0] . "' ";
                $query .= "from silver." . $domain . " where source_org='" . $org . "' ";
                $query .= "and coalesce(" . ($row[1] == 'varchar' ? $row[0] : 'cast(' . $row[0] . ' as varchar)') . ", '')='' ";
                $query .= "or coalesce(" . ($row[1] == 'varchar' ? $row[0] : 'cast(' . $row[0] . ' as varchar)') . ", '')='0' ";
                $query .= "order by 1;";
                // echo 'Missing query: ' . $query;
                display_table($db, $query);
            }
        }
    }
}

function display_mismatch_table($db, $org, $domain):void {
    // get primary key field
    $field_name = 'Gender';
    $primary_key = get_primary_key($db, $domain);
    $query = "select source_field, mapped_field, datatype from config.quality where domain='" . $domain . "' and validation='compare' order by sequence;";
    $result = pg_query($db, $query);
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $query = "select upn_id, " . $primary_key . ", '" . $field_name . "', ";
            $query .= $row['source_field'] . ", " . $row['mapped_field'] . " ";
            $query .= " from silver." . $domain . " where source_org='" . $org . "' and ";
            $query .=  ( $row['datatype'] == 'varchar' ? "lower( " . $row['source_field'] . ")!=lower(" . $row['mapped_field'] . ")" :
                $row['source_field'] . "!=" . $row['output_field']);
            $query .= " order by 1;";
            // echo 'Mismatch query: ' . $query;
            display_table($db, $query);
        }
    }
}

function display_duplicate_keys($db, $org, $domain):void {
    // get primary key field
    $primary_key = get_primary_key($db, $domain);
    $query = "select '{$domain}', 'Duplicate', '{$primary_key}', {$primary_key}, count({$primary_key}) from {$org}.{$domain} ";
    $query .= " group by {$primary_key} having count({$primary_key})>1;";
    // echo 'Find duplicates query: ' . $query;
    display_table($db, $query);
}

function display_missing_index($db, $org, $domain_parent, $domain_child, $field_child):void {
    $query = "select '{$domain_child} => {$domain_parent}', 'Missing Foreign Key', '{$field_child}', c.{$field_child}, '-' ";
    $query .= "from {$org}.{$domain_child} c left join {$org}.{$domain_parent} p ";
    $query .= "on cast(c.{$field_child} as varchar)=cast(p.{$field_child} as varchar) ";
    $query .= "where p.{$field_child} is null;";
    display_table($db, $query);
}

/***********************************************************************************
 * SUMMARY DATA VISUALIZATION
 ***********************************************************************************/

function display_summary_tables($db):void {
    // get result sets for relevant data categories
    $result_person = get_summary_results($db,'person');
    $result_visit = get_summary_results($db,'visit_occurrence');
    $result_condition = get_summary_results($db,'condition_occurrence');
    $result_procedure = get_summary_results($db,'procedure_occurrence');
    $result_drug = get_summary_results($db,'drug_exposure');

    // assumes there are records in all tables for each org
    if ($result_person) {
        echo "<tbody>";
        while ($row_person = pg_fetch_row($result_person)) {
            echo "<tr><td>" . $row_person[0] . "</td><td>" . $row_person[1] . "</td>";
            display_summary_cell($result_visit, 1);
            display_summary_cell($result_condition, 1);
            display_summary_cell($result_procedure, 1);
            display_summary_cell($result_drug, 1);
            echo "</tr>";
        }
    }
    echo "</tbody>";
}

function get_summary_results($db, $domain):PgSql\Result|false {
    $query = "select source_org, count(*) from gold.{$domain} group by source_org;";
    return execute_query($db, $query);
}

function display_summary_cell($result, $column):void {
    echo "<td>";
    if ($result) {
        $row = pg_fetch_row($result);
        echo $row[$column];
    } else {
        echo "-";
    }
    echo "</td>";
}

/***********************************************************************************
 * MANAGE PROJECTS AND COHORTS
 ***********************************************************************************/

function display_cohort($db, $project_id, $show_filter):void {
    // get all patients
    $query = "select upn_id, age_years, gender_concept_name, race_concept_name, ethnicity_concept_name from gold.person order by upn_id;";
    $result = execute_query($db, $query);
    if (!$result) {
        echo "An error occurred in PostgreSQL query: " . $query . "\n";
        exit;
    }

    $columns = pg_num_fields($result);
    echo "<tbody>";
    // while ($row = pg_fetch_assoc($result)) {
    while ($row = pg_fetch_row($result)) {
        // see if this patient is already in cohort
        $selected = false;
        if ($project_id) {
            $query_find = "select upn_id from config.cohort where project_id=" . $project_id . " and upn_id=" . $row[0] . ";";
            // echo 'Patient project lookup: ' . $query;
            $result_find = pg_query($db, $query_find);
            if ($result_find) {
                if (pg_num_rows($result_find) > 0) {
                    $selected = true;
                }
            }
        }

        // filter based on $show_filter value
        if ($show_filter=='all' || $selected && $show_filter=='members' || !$selected && $show_filter=='available') {
            echo "<tr>";

            // display checkbox on/off based on $selected
            checkbox_group('cohort', $row[0], '', $selected, false, true);

            // display the other patient attributes
            for ($x = 0; $x < $columns; $x++) {
                $value = $row[$x];
                echo "<td>" . $value . "</td>";
            }
            echo "</tr>";
        }
    }
    echo "</tbody>";
}

/***********************************************************************************
 * MANAGE TO DO LIST
 ***********************************************************************************/

function display_todos($db):void {
    $query = 'select description, complete, priority from workarea.todo order by sequence;';
    $result = pg_query($db, $query);
    while ($row = pg_fetch_assoc($result)) {
        echo '<li>';
        if (pg_bool($row['complete'])) {
            echo '<s>';
        }
        echo $row['description'];
        if (pg_bool($row['complete'])) {
            echo '</s>';
        }
        echo '</li>';
    }
}

function output_to_json($db, $org, $domain):void {
    $domain_attributes = get_domain_attributes($db, $domain);
    $domain_name = $domain_attributes['concept_name'];
    $query = "select upn_id, {$domain_name}_concept_id, {$domain_name}_concept_vocabulary_id, {$domain_name}_concept_code ";
    $query .="from gold.{$domain} where source_org='{$org}' order by upn_id;";
    // echo 'Output to JSON query: ' . $query;
    $result = pg_query($db, $query);

    if ($result) {
        while ($row = pg_fetch_array($result)) {
            echo json_encode($row) . "\r\n";
        }
    }
}

?>