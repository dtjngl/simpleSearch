<?php namespace ProcessWire;

    // Indexed templates settings
    $templates = $this->wire('templates');
    $templateOptions = array();

    foreach ($templates as $template) {
        $templateOptions[$template->name] = $template->name;
    }

    $config = array(

        'sublimit' => array(
            'name' => 'sublimit',
            'type' => 'integer',
            'label' => __('Subcategory Search Limit'),
            'description' => __('The number of search results to display per category on the search overview (all results)'),
            // 'notes' => __('Enter 0 for no limit.'),
            'required' => true,
            'value' => 10,
        ),

        'limit' => array(
            'name' => 'limit',
            'type' => 'integer',
            'label' => __('Default Search Limit'),
            'description' => __('The number of search results to display per page (paginated) on the filtered category view.'),
            // 'notes' => __('Enter 0 for no limit.'),
            'required' => true,
            'value' => 20,
        ),

        'indexedTemplates' => array(
            'name' => 'indexed_templates',
            'type' => 'asmSelect',
            'label' => __('Indexed Templates'),
            'description' => __('Select the templates whose pages should be indexed for search.'),
            'notes' => __('Only pages from selected templates will be included in search results.'),
            'options' => $templateOptions,
            // 'options' => array('la', 'le', 'lu'),
            'tags' => true,
            'collapsed' => 'collapsed',
            'required' => false, // Set the field as not required
            'value' => null, // Default value for the indexedTemplates setting
        ),

    );