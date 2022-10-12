<?php
declare(strict_types=1);

/***********************************************************************************
 * LOGIC FOR DISPLAYING, SELECTING, AND ADDING MAPPED/DERIVED CONCEPTS
 ***********************************************************************************/
function display_mapped_codes($db, $ohdsi_code, $vocab_id):void
{
    if ($ohdsi_code) {
        $query = "select a.concept_id, a.concept_name, a.vocabulary_id, a.concept_code, a.standard_concept, ";
        $query .= "reference_source || case when reference_type='' then '' else ' (' || reference_type || ')' end as derivation, ";
        $query .= "m.confidence_level ";
        $query .= "from workarea.mapping m inner join athena.concept a on m.concept_id=a.concept_id ";
        $query .= "where not m.included ";
        if ($vocab_id != 'all') {
            $query .= "and a.vocabulary_id like '" . $vocab_id . "%' ";
        }
        $query .= "order by a.vocabulary_id, reference_type, m.confidence_level;";
        $result = pg_query($db, $query);

        // Display table with athena relationships and ancestors associated with specified code
        if ($result) {
            $displayed_codes_array = [];
            $columns = pg_num_fields($result);
            echo "<tbody>";
            while ($row = pg_fetch_row($result)) {

                echo "<tr>";

                // set row styling and disable checkbox if this code has already been displayed
                $mapped_code = $row[0];
                $cell_style = '';
                $disable_checkbox = false;
                if (in_array($mapped_code, $displayed_codes_array, true)) {
                    $disable_checkbox = true;
                    $cell_style = ' style="font-style:italic; color:#D3D3D3;"';
                }
                // array_push($displayed_codes_array, $mapped_code);
                $displayed_codes_array[] = $mapped_code;
                // display checkbox and pre-check if this is a descendant a distance of 1 away or relationship of "Maps to"
                $pre_check = ( ($row[6]=="Maps to") || ($row[6]=='descendant (1)') );
                checkbox_group('add-concept', $mapped_code, '', $pre_check, $disable_checkbox, true);

                // continue with remaining columns
                for ($x = 0; $x < $columns; $x++) {
                    echo "<td{$cell_style}>" . $row[$x] . "</td>";
                }
                echo "</tr>\n";
            }
            echo "</tr></tbody>";
        }
    }
}

// function load_mapping_table($db, $ohdsi_code, $id, $org, $domain)
function load_mapping_table($db, $domain, $ohdsi_code):void
{
    // check to make sure a non-null OHDSI code was specified
    if ($ohdsi_code) {
        // remove any current rows in mapping table
        $query = "delete from workarea.mapping;";
        pg_query($db, $query);

        // get ancestors associated with this OHDSI concept code
        $query = "insert into workarea.mapping (concept_id, source_id, concept_type, reference_source, reference_type, confidence_level, included) ";
        $query .= "select case when ancestor_concept_id={$ohdsi_code} then descendant_concept_id else ancestor_concept_id end, ";
        $query .= "{$ohdsi_code}, '{$domain}', 'athena', 'ancestor', ";
        $query .= "concat(case when ancestor_concept_id={$ohdsi_code} then 'ancestor (' else 'descendant (' end, min_levels_of_separation, ')'), false ";
        $query .= "from athena.concept_ancestor where ancestor_concept_id={$ohdsi_code} or descendant_concept_id={$ohdsi_code};";
        // echo 'Insert associated query: ' . $query;
        execute_query($db, $query);

        // get relationships associated with this OHDSI concept code
        /*
        $query = "insert into workarea.mapping (concept_id, source_id, concept_type, reference_source, reference_type, confidence_level, included) ";
        $query .= "select case when concept_id_1={$ohdsi_code} then concept_id_2 else concept_id_1 end, ";
        $query .= "{$ohdsi_code}, '{$domain}', 'athena', 'relationship', ";
        $query .= "concat(relationship_id, case when concept_id_1={$ohdsi_code} then ' (left)' else ' (right)' end), false ";
        $query .= "from athena.concept_relationship where concept_id_1={$ohdsi_code} or concept_id_2={$ohdsi_code};";
        */
        $query = "insert into workarea.mapping (concept_id, source_id, concept_type, reference_source, reference_type, confidence_level, included) ";
        $query .= "select concept_id_2, {$ohdsi_code}, '{$domain}', 'athena', 'relationship', relationship_id, false ";
        $query .= "from athena.concept_relationship where concept_id_1={$ohdsi_code};";
        // echo 'Relationships query:' . $query;
        execute_query($db, $query);
    }
}

// invoke UMLS API to get information for SNOMED codes
function umls_lookup_snomed($db, $ohdsi_code):void
{
    // check to make sure a non-null OHDSI code was specified
    if ($ohdsi_code) {
        // see if current concept is a SNOMED or RxNorm code
        $query = "select vocabulary_id, concept_code from athena.concept where concept_id=" . $ohdsi_code . ";";
        $result = pg_query($db, $query);
        if ($result) {
            $row = pg_fetch_row($result);
            if ($row[0] == 'SNOMED') {
                $snomed_code = $row[1];

                // lookup SNOMED code on UMLS
                $umls_key = "6dd25f94-2733-4a89-8fe5-4f3649e49940";
                $umls_url = "https://uts-ws.nlm.nih.gov/rest";
                $umls_url .= "/search/current?apiKey=" . $umls_key;
                $umls_url .= "&string=" . $snomed_code . "&inputType=sourceUi&searchType=exact&sabs=SNOMEDCT_US";
                // echo 'UMLS URL: ' . $umls_url;
                $umls_json = file_get_contents($umls_url);

                // grab certain JSON elements
                $umls = json_decode($umls_json, true);
                header('Content-type:text/html;charset=utf-8');
                $umls_results = $umls["result"]["results"];

                // display results in table
                echo "<tbody>";
                // foreach ($umls_results as $key => $value) {
                foreach ($umls_results as $value) {
                    // echo "{$key} => {$value} ";
                    $row = $value;
                    echo '<tr><td>' . $row["ui"] . '</td><td>' . $row["rootSource"] . '</td>';
                    echo '<td><a href="' . $row["uri"] . '">' . $row["uri"] . '</a></td><td>' . $row["name"] . '</td></tr>';
                }
                echo "</tbody>";
            }
        }
    }
}

function harmonize_code_vocab($db, $org, $domain=''):void
{
    // build domain tables array including only domains with target vocabulary if specific domain not specified
    if ($domain == '') {
        $domain_array = get_pipeline_tables($db, true);
    } else {
        $domain_array[] = $domain;
    }

    foreach ($domain_array as $domain_map) {
        $query = "select insert_mapped_sql from config.pipeline_process_sql where domain='$domain_map';";
        $result = pg_query($db, $query);
        if (pg_num_rows($result) == 1 && !pg_field_is_null($result, "insert_mapped_sql")) {
            $sql = pg_fetch_result($result, 'insert_mapped_sql');
            $map_command = str_replace('$org', $org, $sql);
            // echo 'Mapping command: ' . $map_command;
            execute_query($db, $map_command);
        }
    }
}

function harmonize_domain_disabled($db, $domain):void
{
    // echo "disabled" if default_vocab for this domain is NULL
    $query = "select default_vocab from config.domain where name='{$domain}';";
    $result = pg_query($db, $query);

    if (!$result || pg_field_is_null($result,'default_vocab')) {
        echo "disabled";
    }
}

?>