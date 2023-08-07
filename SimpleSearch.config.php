<?php namespace ProcessWire;

// Get the list of all templates visible to the guest user
$allTemplates = $this->templates->find('roles=, label!=');

// Create an empty array to hold the options
$templateOptions = [];

// Loop through each template and add it to the options array
foreach ($allTemplates as $template) {
    $templateOptions[$template->id] = $template->label;
}

$config = array(
  'search_criteria' => array(
    'label' => 'Search Criteria',
    'type' => 'InputfieldText',
    'value' => '',
    'columnWidth' => 50,
    'useLanguages' => true
  ),
  'pagination_string_entries' => array(
    'label' => 'Einträge Label',
    'type' => 'InputfieldText',
    'value' => '',
    'columnWidth' => 50,
    'useLanguages' => true
  ),
  'all_entries_label' => array(
    'label' => 'All Entries Label',
    'type' => 'InputfieldText',
    'value' => '',
    'columnWidth' => 50,
    'useLanguages' => true
  ),
  'custom_search_results_markup' => array(
    'label' => 'Custom Search Results Markup',
    'type' => 'text',
    'value' => '',
    'description' => 'optional search results markup functions file, find the default markup function in the module\'s folder.',
    'notes' => 'enter path relative to /site/templates/',
    'columnWidth' => 50,
  ),
  'limit' => array(
    'label' => 'Limit',
    'type' => 'integer',
    'value' => '',
    'columnWidth' => 25,
  ),
  'sublimit' => array(
    'label' => 'Sublimit',
    'type' => 'integer',
    'value' => '',
    'columnWidth' => 25,
  ),
  'search_operator' => array(
    'label' => 'Search Operator',
    'type' => 'InputfieldText',
    'value' => '~%=',
    'description' => $this->_('find all available operands on the PW documentation page https://processwire.com/docs/selectors/#operators'),
    'columnWidth' => 50,
  )
);

