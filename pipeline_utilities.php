<?php
declare(strict_types=1);

/***********************************************************************************
 * GENERAL PURPOSE UTILITIES TO SUPPORT PIPELINE PHPS
 ***********************************************************************************/

// gets primary key for specified domain.
function get_primary_key($db, $domain):string
{
    $primary_key = '';
    $query = "select primary_key from config.domain where name='{$domain}';";
    $result = pg_query($db, $query);
    if ($result) {
        $row = pg_fetch_row($result);
        $primary_key = $row[0];
    }
    return $primary_key;
}

// get domain attributes in an array
function get_domain_attributes($db, $domain):array
{
    $attributes = [];
    $query = "select column_name, ordinal_position from information_schema.columns ";
    $query .= "where table_schema='config' and table_name='domain' ";
    $query .= "order by ordinal_position;";
    $result_attributes = pg_query($db, $query);

    if (empty($domain)) {
        $query = "select * from config.domain";
    } else {
        $query = "select * from config.domain where name='{$domain}';";
    }
    $result_domain = pg_query($db, $query);
    $row_domain = pg_fetch_assoc($result_domain);
    $attributes_text = print_r($row_domain, true);
    // echo "<div style='background-color: #ABBAEA;'>query: {$query}</div>";
    while ($row_attribute = pg_fetch_assoc($result_attributes)) {
        $attributes[$row_attribute['column_name']] = $row_domain[$row_attribute['column_name']];
    }
    return $attributes;
}

function get_concept_id($db, $domain, $source_key)
{
    $domain_attributes = get_domain_attributes($db, $domain);
    $query = "select {$domain_attributes['concept_name']}_concept_id from silver.{$domain} ";
    $query .= "where {$domain_attributes['primary_key']}={$source_key};";
    // echo 'Lookup OHDSI code query: ' . $query;
    $result = pg_query($db, $query);
    if ($result) {
        $row = pg_fetch_row($result);
        return $row[0];
    }
}

function get_concept_array($db, $concept_id):array
{
    $query = "select * from athena.concept where concept_id={$concept_id};";
    $result = pg_query($db, $query);
    return pg_fetch_array($result);
}

// Generate an array in memory of domain attributes for specified domain
function get_source_array($db, $source, $domain):array
{
    $primary_key = get_primary_key($db, $domain);
    $query .= "select * from silver.{$domain} where {$primary_key}=" . $source . " and not(coalesce(inferred,false));";
    $result = pg_query($db, $query);
    if ($result) {
        return pg_fetch_array($result);
    }
}

/*
 * Utility functions for UPN data factory pipeline visualization
 */

// function used in div tag for page sections to display that tab after submitting form
function display_tab($tab_name, $default = false):void
{
    $tab_shown = $_POST['tab_shown'];
    if ($tab_shown) {
        echo($tab_shown == $tab_name ? 'block' : 'none');
    } else {
        echo($default ? 'block' : 'none');
    }
}

// utility function to populate orgs dropdown list
function orgs_dropdown_group($db, $default_value=''):void
{
    $query = "select name, display, default_item from config.org order by display;";
    $result = pg_query($db, $query);// echo $result;
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            // dropdown_option($row['name'], $row['display'], pg_bool($row['default_item']));
            dropdown_option($row['name'], $row['display'], $default_value);
        }
    }
}

// utility function to populate domains radio group of specified type
function domains_radio_group($db, $group, $type, $default=''):void
{
    $query = "select name, display, configurable, in_pipeline from config.domain order by " . ( $type=='config' ? "name" : "sequence" ) . ";";
    /*
    if ($type=='config') {
        $query .= "name;";
    } else {
        $query .= "sequence;";
    }
    */
    $result = pg_query($db, $query);

    // default to first radio button if no default specified
    $first_row = true;
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            if ($type=='config' && pg_bool($row['configurable']) || $type=='pipeline' && pg_bool($row['in_pipeline'])) {
                $default_radio = ($default=='' ? $first_row : ($row['name']==$default) );
                radio_option($group, $row['name'], $row['display'], $default_radio);
                $first_row = false;
            }
        }
    }
}

function tables_radio_group($db, $group, $org, $default=""):void
{
    $query = "select table_name from information_schema.tables WHERE table_schema='{$org}'";
    $result = pg_query($db, $query);

    // default to first radio button if no default specified
    $first_row = true;
    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $default_radio = ($default=='' ? $first_row : ($row['table_name']==$default) );
            radio_option($group, $row['table_name'], $row['table_name'], $default_radio);
            $first_row = false;
        }
    }
}

function save_cohort($db, $elements):void
{
    // remove any prior cohort entries for this project
    $project_id = $elements['project_group'];
    if ($project_id) {
        $query = "delete from config.cohort where project_id=" . $project_id . ";";
        pg_query($db, $query);

        // add select patients to this project cohort
        foreach ($elements['cohort'] as $key => $value) {
            echo "{$key} => {$value} ";
            if ($value) {
                echo 'Inserting';
                $query = "insert into config.cohort (project_id, upn_id) values (" . $project_id . ", " . $value . ");";
                pg_query($db, $query);
            }
        }
    }
}

function bool_to_string($value):string
{
    $return_value = 'false';
    if ($value=='on') {
        $return_value = 'true';
    }
    return $return_value;
}

function dropdown_option($value, $display, $default_value=''):void
{
    echo "<option value=\"{$value}\"";
    if ($value==$default_value) {
        echo " selected";
    }
    echo ">{$display}</option>";
}

function radio_option($group, $value, $display='', $default=false, $disabled=false, $wrap=false):void
{
    wrap_cell($wrap, true);
    echo "<input type='radio' name='{$group}' id='{$value}' value='{$value}'";
    echo " onclick='this.form.submit()';";
    // if ($_POST[$group] == $value || (!$_POST[$group] && $default)) {
    if ($_POST[$group] == $value || $default) {
        echo ' checked';
    }
    if ($disabled) {
        echo ' disabled';
    }
    echo '>';
    if ($display!='') {
        echo "<label for=\"{$value}\">{$display}</label><br>\n";
    }
    wrap_cell($wrap, false);
}

function checkbox_group($group, $value, $display='', $checked=false, $disabled=false, $wrap=false):void
{
    // wrap checkbox in a table data element <td> tag
    wrap_cell($wrap, true);

    // create checkbox group
    echo "<input type=\"checkbox\" name=\"{$group}[]\" id=\"{$value}\" name=\"{$value}\" value=\"{$value}\"";
    // echo ' onclick="this.form.submit();"';
    if ($_POST[$value] == 'on' || $checked) {
        echo ' checked';
    }
    if ($disabled) {
        echo ' disabled';
    }
    echo '>';

    // show label is specified
    if ($display!='') {
        echo "<label for=\"{$value}\">{$display}</label><br>";
    }

    // close table data element
    wrap_cell($wrap, false);
}

function button_group($group, $value, $display='', $disabled=false, $wrap=false):void
{
    // create command button and hidden controls
    wrap_cell($wrap, true);
    echo "<input type=\"submit\" name=\"submit_details\" value=\"{$display}\"";

    if ($disabled) {
        echo ' disabled';
    }
    echo "<input type=\"hidden\" name=\"{$group}[]\" id=\"{$value}\" value=\"{value}\">";
    wrap_cell($wrap, false);
}

function textbox_group($group, $value, $display='', $size=20, $disabled=false, $wrap=false):void
{
    // create checkbox group
    wrap_cell($wrap, true);
    // echo '<input type="text" size="{$size}" name="{$group}[]" id="{$value}" value="{$display}">';
    echo "<input type=\"text\" size={$size} name=\"{$group}[]\" id=\"{$value}\" value=\"{$display}\"";
    if ($disabled) {
        echo ' disabled';
    }
    echo '>';
    wrap_cell($wrap, false);
}

/*
function get_item_details($source, $domain)
{
    $query = "select upn_id, visit_occurrence_id" . $domain . "_concept_id from silver." . $domain;
    $query .= " where " . $domain . "_occurrence_id=" . $source . ";";
    $result = pg_query($db, $query);
    if ($result) {
        $row = pg_fetch_row($result);
        $ohdsi_code = $row[0];
    }
}
*/


// PostgreSQL UTILITY FUNCTIONS
function execute_query($db, $query, $org=NULL, $domain=NULL) {
    try {
        $result = try_query($db, $query);
        log_query_results($db, $query, $result);
        return $result;
    } catch (Exception $e) {
        // store error information in log table (config.run_report) and display message/trace
        log_event_message($db, 'PostgreSQL error', $query, $e->getMessage(), $e->getTraceAsString());
        echo <<< EOT
        <div style="background-color: pink;">
            <dl>
                <dt>Error</dt><dd><pre>{$e->getMessage()}</pre></dd>
                <dt>Query</dt><dd><pre>$query</pre></dd>
                <dt>Trace</dt><dd><pre>{$e->getTraceAsString()}</pre></dd>
            </dl>
        </div>
        EOT;
        /*
        echo '<b>Error performing PostgreSQL query:&nbsp;</b> ' . $e->getMessage() . '<br/>';
        echo '<b>SQL statement:&nbsp;</b> ' . substr($query, 0, 200) . (strlen($query)>200 ? '...' : '') . '<br/>';
        echo '<b>Execution trace:</b>';
        // remove first and last element of trace array (since first will always be try_query and last will be dashboard.php
        $trace_array = $e->getTrace();
        array_pop($trace_array);
        array_shift($trace_array);
        // display trace records on separate lines
        foreach($trace_array as $trace) {
            echo '<p style="margin-left:40px; margin-top:0; margin-bottom:0;">function - <b>' . $trace['function'] . '</b>, ';
            echo 'file - <b>' . $trace['file'] . '</b>, line - <b>' . $trace['line'] . '</b></p>';
        }
        echo '<br/>';
        }
    */
    }
}

/**
 * @throws exception
 */
function try_query($db, $query):PgSql\Result|false
{
    // the '@' symbol supresses the PHP default error messaging
    $result = @pg_query($db,$query);
    if (!$result) {
        throw new exception(pg_last_error($db));
    } else return $result;
}

// log results of PostgreSQL queries to run report table
function log_query_results($db, $query, $result=null, $trace=null):void
{
    if ($result) {
        $error_message = pg_result_error($result);
    } else {
        $error_message = pg_last_error($db);
    }
    $query_log = "insert into workarea.run_report (event_type, description, details, trace, event_date_time) values('PostgreSQL Query', \$\${$query}\$\$, \$\${$error_message}\$\$, \$\${$trace}\$\$, now());";
    pg_query($db, $query_log);
}

// log other events of note
function log_event_message($db, $type, $description, $message=null, $trace=null):void
{
    $query_log = "insert into workarea.run_report (event_type, description, details, trace, event_date_time) ";
    $query_log .= "values('{$type}', \$\${$description}\$\$, \$\${$message}\$\$, \$\${$trace}\$\$, now());";
    pg_query($db, $query_log);
}

// function to convert boolean values from PostgreSQL ('t' or 'f') to logical boolean
function pg_bool($value):bool
{
    return ($value=='t');
}

// function to check for null or blank field
function return_nonblank($value, $blank) {
    if ( ($blank ?: '')!='') {
        return $value;
    } else {
        return '';
    }
}

function date_to_null($value):string {
    if ($value) {
        return "'" . $value . "'";
    } else {
        return 'NULL';
    }
}

// add open (start=true) or close (start=false) table cell tag
function wrap_cell($wrap, $start=true):void
{
    if ($wrap) {
        if ($start) {
            echo '<td>';
        } else {
            echo '</td>';
        }
    }
}

/*
// TODO move to functions
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle):bool
    {
        return strpos($haystack, $needle)!==false;
    }
}
*/

?>
