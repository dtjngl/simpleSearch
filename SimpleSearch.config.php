<?php namespace ProcessWire;

$config = array(
      'limit' => array(
        'label' => 'Limit',
        'type' => 'integer',
        'value' => '',
        'columnWidth' => 50,
      ),
      'sublimit' => array(
        'label' => 'Sublimit',
        'type' => 'integer',
        'value' => '',
        'columnWidth' => 50,
      ),
      'sender_name' => array(
        'label' => 'Sender Name',
        'type' => 'text',
        'value' => '',
        'columnWidth' => 50,
      ),
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
  );


// class SimpleSearchConfig extends ModuleConfig {

//     public function getInputfields() {

//         $inputfields = parent::getInputfields();

//         // Define your module's configuration fields here
//         // For example, you can add a text inputfield with language support:

//         $f = $this->modules->get('InputfieldInteger');
//         $f->attr('name', 'limit');
//         $f->label = $this->_('Limit for category detail view');
//         $f->description = $this->_('Limit for category detail view');
//         $f->useLanguages = false; // This enables multilingual support
//         $f->columnWidth = 100;
//         $inputfields->add($f);

//         $f = $this->modules->get('InputfieldInteger');
//         $f->attr('name', 'sublimit');
//         $f->label = $this->_('Limit for categories overview ');
//         $f->description = $this->_('Limit for categories overview');
//         $f->useLanguages = false; // This enables multilingual support
//         $f->columnWidth = 100;
//         $inputfields->add($f);

//         $f = $this->modules->get('InputfieldTextarea');
//         $f->attr('name', 'search_criteria');
//         // $f->label = $this->_('Search criteria format');
//         $f->label = $this->_('Search criteria format');
//         $f->description = $this->_('Search criteria format');
//         $f->useLanguages = true; // This enables multilingual support
//         $f->columnWidth = 100;
//         $inputfields->add($f);

//         $f = $this->modules->get('InputfieldText');
//         $f->attr('name', 'search_overview');
//         $f->label = $this->_('Search overview format');
//         $f->description = $this->_('Search overview format');
//         $f->useLanguages = true; // This enables multilingual support
//         $f->columnWidth = 100;
//         $inputfields->add($f);

//         $f = $this->modules->get('InputfieldText');
//         $f->attr('name', 'pagination_string');
//         $f->label = $this->_('Pagination string format');
//         $f->description = $this->_('Pagination string format');
//         $f->useLanguages = true; // This enables multilingual support
//         $f->columnWidth = 100;
//         $inputfields->add($f);

//         // Add other inputfields as needed

//         return $inputfields;
//     }

// }