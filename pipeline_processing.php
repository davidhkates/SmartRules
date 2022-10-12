<?php
declare(strict_types=1);

/***********************************************************************************
 * DATA PIPELINE PROCESSING LOGIC
 ***********************************************************************************/

// clear out records in the pipeline table(s) for the given org and domain
function delete_pipeline_tables($db, $org, $domain=''):void
{
    delete_pipeline_bronze($db, $org, $domain);
    delete_pipeline_silver($db, $org, $domain);
}

function delete_pipeline_bronze($db, $org, $domain=''):void
{
    // Get tables to clear for specified domain and org
    $query = "select target_table from config.s3_import where org='{$org}' ";
    if ($domain != '') {
        $query .= "and domain='" . $domain . "' ";
    }
    $query .= "order by sequence;";
    // echo 'Get tables to delete query: ' . $query;
    $result = pg_query($db, $query);

    // delete records in raw table(s) associated with specified org and domain
    while ($row = pg_fetch_assoc($result)) {
        $command = "delete from " . $org . "." . $row['target_table'] . ";";
        // echo 'Delete records in relevant tables command: ' . $command;
        pg_query($db, $command);
    }
}

function delete_pipeline_silver($db, $org, $domain=''):void
{
    // delete structured (silver) data derived from this org's raw data
    if ($domain!='') {
        $command = "delete from silver." . $domain . " where source_org='" . $org . "';";
        pg_query($db, $command);
    } else {
        $query = "select name from config.domain where in_pipeline;";
        $result = pg_query($db, $query);
        while ($row = pg_fetch_assoc($result)) {
            $command = "delete from silver." . $row['name'] . " where source_org='" . $org . "';";
            pg_query($db, $command);
        }
    }
}

$json = file_get_contents("./inc/aws_config.json");
$aws_config = json_decode($json, TRUE);

// import tables from organization's S3 bucket and load in bronze zone tables
function load_pipeline_bronze($db, $org, $domain=''):void
{
    // echo '<script>alert("Loading bronze tables")</script>';

    // global $aws_config;
    $json_string = file_get_contents("./inc/aws_config.json");
    // echo "AWS Configuration File contents: " . $json_string . "<br/>";

    if (!$json_string) {
        echo "AWS Configuration file not found.  Should be @ ./inc/aws_config.json";
        exit;
    } else {
        $aws_config = json_decode($json_string, TRUE);
        if (!$aws_config) {
            echo 'Error parsing JSON AWS config file: ' . json_last_error_msg();
            exit;
        } else {
            $org_bucket = $aws_config["s3_buckets"][$org];
        }
    }

    // Get filename(s) to import for specified domain and org
    $query = "select filename, target_table from config.s3_import where org='{$org}'";
    if ($domain!='') {
        $query .= " and domain='{$domain}' ";
    }
    $query .= "order by sequence;";
    $result = execute_query($db, $query);
    // echo 'Query to get relevant tables: ' . $query . "\n";

    // get associated files
    while ($row = pg_fetch_assoc($result)) {
        $s3import = "select aws_s3.table_import_from_s3('{$org}.{$row['target_table']}','','(format csv, header)', ";
        $s3import .= "aws_commons.create_s3_uri('{$org_bucket['name']}', '{$row['filename']}.csv', '{$org_bucket['region']}'));";
        // echo 'Ingest bronze files import statement: ' . $s3import . "<br/>";
        execute_query($db, $s3import);
    }
}

// load silver zone for specified org and domain
function load_pipeline_silver($db, $org, $domain=''):void
{
    // get domain tables array if specific domain not specified
    if ($domain=='') {
        $domain_array = get_pipeline_tables($db);
    } else {
        $domain_array[] = $domain;
    }

    // load structured (silver) table from new raw data
    foreach ($domain_array as $domain_load) {
        $sql = get_pipeline_sql($db, $domain_load, $org);
        $load_command = str_replace('$org', $org, $sql);
        // echo 'Load silver SQL: ' . $sql;
        execute_query($db, $load_command, $org, $domain_load);
    }
}

function load_pipeline_gold($db, $org, $domain=''):void
{
    // get domain tables array if specific domain not specified
    if ($domain == '') {
        $domain_array = get_pipeline_tables($db);
    } else {
        $domain_array[] = $domain;
    }

    foreach ($domain_array as $domain_load) {
        /*
        // delete refined (gold) records ONLY for patients in pipeline (silver.person) - upsert of sorts
        // $command = "delete from gold.{$domain_load} g using silver.person as s ";
        // $command .= "where g.upn_id = s.upn_id and source_org='{$org}';";
        $command = "delete from gold.{$domain_load} where source_org='{$org}';";
        pg_query($db, $command);
        log_query_results($db, $command);
        */

        // get schema from silver zone of relevant domain
        $query = "select column_name, ordinal_position from information_schema.columns ";
        $query .= "where table_schema='silver' and table_name='" . $domain_load . "' ";
        // $query .= "and source_org='" . $org . "' ";
        $query .= "order by ordinal_position;";
        $result = pg_query($db, $query);

        // echo "<div style='background-color: #ABBAEA;'>{$domain_load}</div>";

        if ($result) {
            // build select into part of SQL statement
            $query = "insert into gold." . $domain_load . " (";
            $row = pg_fetch_assoc($result);
            $query .= $row['column_name'];
            while ($row = pg_fetch_assoc($result)) {
                $query .= ", " . $row['column_name'];
            }

            // build field names part of SQL statement
            $query .= ") select distinct ";
            $row = pg_fetch_assoc($result, 0);
            $query .= $row['column_name'];
            pg_fetch_assoc($result);  // for some reason, need to slough off a row
            $conflict_fields = '';
            while ($row = pg_fetch_assoc($result)) {
                $column_name = $row['column_name'];
                $query .= ", " . $column_name;
                $conflict_fields .= ( empty($conflict_fields) ? '' : ", ") . "{$column_name}=excluded.{$column_name}";
            }

            // build from and where clauses of SQL statement, add conflict clause to do upsert
            $domain_attributes = get_domain_attributes($db, $domain_load);
            // $attributes_text = print_r($domain_attributes, true);
            // echo "<div style='background-color: #ABBAEA;'>{$attributes_text}</div>";
            $query .= " from silver." . $domain_load . " where source_org='" . $org . "' ";
            $query .= "on conflict (upn_id";
            $primary_key = $domain_attributes['primary_key'];
            if (isset($primary_key)) {
                $query .= ", {$primary_key}";
            }
            $concept_id = $domain_attributes['concept_name'];
            if (isset($concept_id)) {
                $query .= ", {$concept_id}_concept_id";
            }
            $query .= ") do update set {$conflict_fields};";
            // echo 'Gold query statement: ' . $query;
            execute_query($db, $query);
        }
    }
}

function update_athena_files($db):void
{
    global $aws_config;
    $athena_bucket = $aws_config["s3_buckets"]["athena"];

    // Get table names in athena schema to build list of CSVs to retrieve from S3
    $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'athena' ORDER BY table_name;";
    $result = execute_query($db, $query);

    // display a table showing progress of the OHDSI Athena reference table loading
    // echo "<table class='display table-heading' style='width:64em;'><thead><tr>";
    echo "<table class='tableFixHead' id='loadAthenaProgress' style='width:64em;height:0;'><thead>";
    echo "<tr><th style='width:40em;'>Description</th><th style='width:8em;'>Start Time</th>";
    echo "<th style='width:8em;'>End Time</th><th style='width:8em;'>Elapsed Time</th></tr></thead><tbody>\n";

    // initialize display row count variable
    $progress_row = 0;

    // get associated files
    while ($row = pg_fetch_assoc($result)) {
        // store athena table name in variable for convenience/speed and get table OID
        $athena_table = $row['table_name'];
        $query = "SELECT 'athena.{$athena_table}'::regclass::oid;";
        $oid_result = pg_query($db, $query);
        $oid_record = pg_fetch_assoc($oid_result);
        $table_oid = $oid_record["oid"];

        // remove all rows from existing athena table
        $truncate_table = "truncate table athena.{$athena_table};";
        execute_query($db, $truncate_table);

        // save (to restore later) and remove the constraints (primary key, etc.) for this athena table
        $query = "SELECT conname AS \"name\", pg_get_constraintdef(oid) AS \"constraint\" FROM pg_constraint ";
        $query .= "WHERE contype IN ('f', 'p') AND conrelid = {$table_oid};";
        $constraints = execute_query($db, $query);
        while ($constraint = pg_fetch_assoc($constraints)) {
            $drop_constraint = "ALTER TABLE athena.{$athena_table} DROP CONSTRAINT {$constraint['name']};";
            execute_query($db, $drop_constraint);
        }

        // save (to restore later) and remove the remaining indexes for this athena table
        $query = "SELECT indexname, indexdef FROM pg_indexes WHERE schemaname='athena' AND tablename='{$athena_table}';";
        $indexes = execute_query($db, $query);
        while ($index = pg_fetch_assoc($indexes)) {
            $drop_index = "DROP INDEX athena.{$index['indexname']};";
            execute_query($db, $drop_index);
        }

        // import table from S3 (athena files are tab-delimited)
        $athena_file = strtoupper($athena_table) . '.csv';
        $tab_delimiter = "\t";
        $quote_char = "\007";   // ASCII character for bell, on the assumption it's not used in OHDSI files
        $options = "(format csv, header, delimiter ''{$tab_delimiter}'', quote ''{$quote_char}'')";
        $s3import = "select aws_s3.table_import_from_s3('athena.{$athena_table}','','{$options}', ";
        $s3import .= "aws_commons.create_s3_uri('{$athena_bucket["name"]}', '{$athena_file}', '{$athena_bucket["region"]}'));";
        // echo "Athena file import statement: " . $s3import . "<br/>";
        show_load_progress("Loading Athena OHDSI reference table: {$athena_table}<br/>", $progress_row);
        execute_query($db, $s3import);
        show_load_complete($progress_row++);

        // restore/rebuild constraint(s) and index(es) for this table
        pg_fetch_row($constraints, 0);
        while ($constraint = pg_fetch_assoc($constraints)) {
            $activity = "Indexing Athena OHDSI reference table: {$athena_table} {$constraint['constraint']}";
            // display_status("Indexing Athena OHDSI reference table: {$athena_table} {$constraint['constraint']}<br/>");
            show_load_progress($activity, $progress_row);
            $add_constraint = "ALTER TABLE athena.{$athena_table} ADD CONSTRAINT {$constraint['name']} {$constraint['constraint']};";
            execute_query($db, $add_constraint);
            show_load_complete($progress_row++);
        }

        pg_fetch_row($indexes, 0);
        while ($index = pg_fetch_assoc($indexes)) {
            $activity = "Indexing Athena OHDSI reference table: {$athena_table} {$index['indexdef']}<br/>";
            show_load_progress($activity, $progress_row);
            execute_query($db, $index['indexdef']);
            show_load_complete($progress_row++);
        }
    }
    echo "</tbody></table><input type='submit' value='Hide' onClick=\"$('$loadAthenaProgress').hide();\")><p>";
}

// copy specified source row in silver domain to a new inferred row, used in mapping
function copy_inferred_concept($db, $domain, $source_key, $inferred_concept):void
{
    // capture event in run log
    $message = "domain: {$domain}, source key: {$source_key}, inferred concept: {$inferred_concept}";
    log_event_message($db, "Adding inferred concept", $message);

    // get schema from silver zone of relevant domain
    $query = "select column_name, ordinal_position from information_schema.columns ";
    $query .= "where table_schema='silver' and table_name='" . $domain . "' ";
    // $query .= "and source_org='" . $org . "' ";
    $query .= "order by ordinal_position;";
    $result = pg_query($db, $query);

    if ($result) {
        // build select into part of SQL statement
        $query = "insert into silver." . $domain . " (";
        $row = pg_fetch_assoc($result);
        $query .= $row['column_name'];
        while ($row_columns = pg_fetch_assoc($result)) {
            $query .= ", " . $row_columns['column_name'];
        }

        // TODO - move domain_attributes to a parameter to avoid repeatedly calling to get static info
        $domain_attributes = get_domain_attributes($db, $domain);
        $concept_name = $domain_attributes['concept_name'] . '_concept';
        $inferred_array = get_concept_array($db, $inferred_concept);

        // build field names part of SQL statement, substitute with inferred concept info
        $query .= ") select ";
        $row = pg_fetch_assoc($result, 0);
        $query .= $row['column_name'];
        pg_fetch_assoc($result);  // for some reason, need to slough off a row
        while ($row_columns = pg_fetch_assoc($result)) {
            // $query .= ", " . ( $row['column_name']=='inferred' ? true : $row['column_name'] );
            switch ($row_columns['column_name']) {
                case 'inferred':
                    $value = 'true';
                    break;
                case $concept_name . '_id':
                    $value = $inferred_concept;
                    break;
                case $concept_name . '_name':
                    $value = "'" . $inferred_array['concept_name'] . "'";
                    break;
                case $concept_name . '_vocabulary_id':
                    $value = "'" . $inferred_array['vocabulary_id'] . "'";
                    break;
                case $concept_name . '_code':
                    $value = "'" . $inferred_array['concept_code'] . "'";
                    break;
                case $concept_name . '_standard':
                    $value = "'" . $inferred_array['standard_concept'] . "'";
                    break;
                case $concept_name . '_' . strtolower($domain_attributes['default_vocab']);
                    $value = ( $inferred_array['vocabulary_id']==$domain_attributes['default_vocab'] ?
                        "'" . $inferred_array['concept_code'] . "'" : 'NULL' );
                    $log_message = 'Concept vocabulary: ' . $inferred_array['vocabulary_id'] . ', target: ' . $domain_attributes['default_vocab'] . ', value: ' . $value;
                    log_event_message($db, 'Insert concept vocabulary', $log_message);
                    break;
                default:
                    $value = $row_columns['column_name'];
                }
            $query .= ", " . $value;
        }
        // build from and where clauses of SQL statement
        $query .= " from silver." . $domain . " where {$domain_attributes['primary_key']}=" . $source_key;
        $query .= " and inferred is not true;";
        // echo 'Copy inferred query statement: ' . $query . "<br/>";
        // pg_query($db, $query);
        execute_query($db, $query);
    }
}

// export tables to SBPLA-exposed S3 buckets
function push_pipeline_tables($db, $domain=''):void
{
    $s3export = "select * from aws_s3.query_export_to_s3('select * from gold." . $domain . "', ";
    $s3export .= "aws_commons.create_s3_uri('upn-sbpla-omop', '" . $domain . ".csv', 'us-west-2'));";
    execute_query($db, $s3export);
}

// Get SQL for mapping bronze to silver.  Use org-specific SQL if requested and defined,
// otherwise use the "default" mapping for this domain
function get_pipeline_sql($db, $domain, $org, $org_specific=true):string
{
    $sql = '';
    $org_lookup = $org_specific;
    if ($org_lookup) {
        $query = "select load_pipeline_sql from config.pipeline_import_sql ";
        $query .= "where domain='{$domain}' and org='{$org}';";
        // echo 'Org-specific SQL lookup: ' . $query;
        $result = pg_query($db, $query);
        // echo 'Number of rows: ' . pg_num_rows($result);
        $org_lookup = (pg_num_rows($result)!=0);
    }

    if (!$org_lookup) {
        $query = "select load_pipeline_sql from config.pipeline_import_sql ";
        $query .= "where domain='{$domain}' and coalesce(org, 'cdm')='cdm';";
        $result = pg_query($db, $query);
    }

    if (pg_num_rows($result) == 0) {
        echo "No SQL defined for this domain: {$domain} and org: {$org}<br/>";
    } else {
        $row = pg_fetch_assoc($result);
        $sql = $row['load_pipeline_sql'];
    }
    return $sql;
}

// return array of domain names for pipeline
function get_pipeline_tables($db, $harmonize=false):array
{
    $domains_array = [];
    $query = "select name from config.domain where in_pipeline";
    // add check to see if target vocabulary is specified in where clause if indicated
    $query .= ($harmonize ? " and default_vocab is not null;" : ";");
    $result = pg_query($db, $query);
    while ($row = pg_fetch_assoc($result)) {
        $domains_array[] = $row['name'];
    }
    return $domains_array;
}
?>