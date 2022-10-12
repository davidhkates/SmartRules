<form id="mapping_form" action="" method="post">
    <input type="hidden" id="tab_shown" name="tab_shown" value="mapping"/>

    <?php
    $source_id = ( $_POST['pipeline-silver'] ?: $_POST['source_id']);
    $org_map = ( $_POST['org'] ?: $_POST['org_map']);
    $domain_map = ( $_POST['domain'] ?: $_POST['domain_map']);
    $ohdsi_code = $_POST['ohdsi_code'];
    echo '<input type="hidden" id="source_id" name="source_id" value="' . $source_id . '"/>';
    echo '<input type="hidden" id="org_map" name="org_map" value="' . $org_map . '"/>';
    echo '<input type="hidden" id="domain_map" name="domain_map" value="' . $domain_map . '"/>';

    if ($source_id) {
        $ohdsi_code = get_concept_id($db, $domain_map, $source_id);
        $ohdsi_code_header = ' (OHDSI Code: ' . $ohdsi_code . ')';
    }

    // get default vocabulary from domain table
    $domain_attributes = get_domain_attributes($db, $domain_map);
    $vocab_id = ($_POST['vocab_id'] ?: ($domain_attributes['default_vocab'] ?: 'all'));
    load_mapping_table($db, $domain_map, $ohdsi_code);

    // Add concepts to silver and gold tables if Add Concepts submit button pressed
    if ( $_POST['submit_mapping']=='Add Concepts') {
        // echo 'Concepts being added: ' . print_r($_POST['add-concept']);
        foreach ($_POST['add-concept'] as $inferred_concept) {
            copy_inferred_concept($db, $domain_map, $source_id, $inferred_concept);
        }
        // update gold zone with new (inferred) concepts
        load_pipeline_gold($db, $org_map, $domain_map);
    }
    ?>

    <table style="layout-table;">
        <tr>
            <td colspan="3" style="background-color: #336791; color: white;"><h2>Ontology Mapping
                    Tools <?php echo $ohdsi_code_header; ?></h2></td>
        </tr>

        <tr>
            <td style="width:5%; vertical-align:top;">
                <p>OHDSI&nbsp;Concept&nbsp;Code:</p>
                <p><input type="text" name="ohdsi_code" id="ohdsi_code" value="<?php echo $ohdsi_code; ?>"/>
                    <label for="ohdsi_code">OHDSI Concept</label></p>

                <?php
                /*
                checkbox_group('codetype', 'vocab-SNOMED', 'SNOMED', true);
                checkbox_group('codetype', 'vocab-RxNorm', 'RxNorm');
                checkbox_group('codetype', 'vocab-ICD10', 'ICD-10');
                checkbox_group('codetype', 'vocab-CPT4', 'CPT4');
                */

                radio_option('vocab_id', 'all', 'All', ($vocab_id=='all'));
                radio_option('vocab_id', 'SNOMED', 'SNOMED', ($vocab_id=='SNOMED'));
                radio_option('vocab_id', 'RxNorm', 'RxNorm', ($vocab_id=='RxNorm'));
                radio_option('vocab_id', 'ICD10', 'ICD-10', ($vocab_id=='ICD10'));
                radio_option('vocab_id', 'CPT', 'CPT4', false, true);
                ?>

                <p><input type="submit" name="submit_mapping" value="Lookup"/>
                    <input type="submit" name="submit_mapping" value="Add Concepts" id="map-concepts"></p><br/>
                <p><i>Informaticists/coders would use this page to add related codes</i></p>

            </td>
            <td style="width:90%;">
                <h3>Potential Matches or Similar Concepts</h3>
                <div class="tableFixHead">
                    <table id="mapping_data_table" class="display compact" style="width:100%;">
                        <thead>
                        <tr>
                            <th>Select</th>
                            <th>OHDSI Concept</th>
                            <th>Description</th>
                            <th>Vocabulary</th>
                            <th>Code Value</th>
                            <th>Standard</th>
                            <th>Reference</th>
                            <th>Relationship</th>
                        </tr>
                        </thead>
                        <?php display_mapped_codes($db, $ohdsi_code, $vocab_id); ?>
                    </table>
                </div>

                <h3>Synonyms</h3>
                <div class="tableFixHead">
                    <table style="width:100%;">
                        <thead>
                        <tr>
                            <th>Synonym</th>
                            <!--th>Language</th-->
                        </tr>
                        </thead>
                        <?php
                        if ($ohdsi_code) {
                            // $query = "select cs.concept_synonym_name, c.concept_name ";
                            $query = "select cs.concept_synonym_name ";
                            $query .= "from athena.concept_synonym cs inner join athena.concept c on cs.language_concept_id=c.concept_id ";
                            $query .= "where cs.concept_id=" . $ohdsi_code;
                            display_table($db, $query);
                        }
                        ?>
                    </table>
                </div>

                <?php
                if ($source_vocab=='SNOMED') {
                    echo '<h3>UMLS Concepts</h3>';
                    echo '<div class="tableFixHead">';
                    echo '<table style="width:100%;"><thead><tr>';
                    echo '<th>CUI</th><th>Root</th><th>URI</th><th>Name</th>';
                    echo '</tr></thead>';

                    // call UMLS API and display returned information
                    umls_lookup_snomed($db, $ohdsi_code);
                    echo '</table></div>';
                }
                ?>
            </td>
            <td style="width:5%;">&nbsp;</td>
        </tr>

    </table>
</form>
