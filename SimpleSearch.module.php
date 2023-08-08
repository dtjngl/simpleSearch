<?php namespace ProcessWire; 

class SimpleSearch extends WireData implements Module, ConfigurableModule {

    /**
     * Get the module configuration inputfields
     *
     * @return InputfieldWrapper

    * @param string $key The key to fetch the value for.
    * @param string $fallbackValue The fallback value if the language-specific value is not found or is an array.
    * @return string The language-specific value or the fallback value.

    */

    public static function getModuleInfo() {

        return array(
            'title' => 'Simple Search',
            'version' => '1.0.0',
            'summary' => 'A simple search module for ProcessWire.',
            'autoload' => true,
            'singular' => true,
            'icon' => 'search',
            'author' => 'FRE:D',
            'installs' => [], // Optional array of module names that this module should install, if any
            'requires' => [], // Optional array of module names that are required for this module to run, if any
        );

    }

    public function init() {

        // Define the pages to search
        // $this->indexedCategories = ["", "project", "article"];
        
        $this->q = '';
        $this->cat = 0;
        
        $this->results = new WireArray;
        $this->totals = new WireArray;
        $this->labels = new WireArray;

        $this->inputSanitized = $this->sanitizeInput();

        $this->start = (int)$this->limit * ($this->input->pageNum() - 1);

        // Explicitly load the MarkupPagerNav module
        $this->pager = $this->modules->get('MarkupPagerNav');
        
        $this->setIndexedcategories();

        // // Get the indexed templates from the module configuration
        // $indexedTemplates = $this->config->indexedTemplates;

        // // If no indexed templates are selected, fallback to indexing all templates
        // if (empty($indexedTemplates)) {
        //     $indexedTemplates = $this->getDefaultIndexedTemplates();
        // }

        // $this->indexedCategories = $indexedTemplates;
    

    }

    public function ready() {

        $this->addHookAfter("ProcessTemplate::buildEditForm", $this, 'addSimpleSearchCategoryField');

        // Hook to save "seo_rules" field value when the template is saved
        $this->addHookBefore("ProcessTemplate::executeSave", $this, 'saveSimpleSearchCategoryFieldValue');

    }

    public function __uninstall() {
        // // Loop through all the fields created by your module and delete them
        // foreach ($this->fields as $field) {
        //     if ($field->flags && Field::flagSystem) continue; // Skip system fields
        //     if ($field->className === 'FieldtypeSimpleSearch') {
        //         $this->fields->delete($field);
        //     }
        // }
    }

    public function __construct() {
    
        $simpleSearchSettings = wire('modules')->getConfig($this);
        foreach ($simpleSearchSettings as $key => $value) {
            $this->$key = $value;
        }

        // Store the path of the custom markup functions file
        $this->customMarkupFilePath = $this->config->paths->templates . $this->custom_search_results_markup;

        if (!empty($this->custom_search_results_markup) && file_exists($this->customMarkupFilePath)) {
            require_once($this->customMarkupFilePath);
        }
        
        // Store the path of the default markup functions file
        $this->defaultMarkupFilePath = __DIR__ . '/_default_markup_functions.php';

        // Include the default markup functions file to access renderDefaultMarkup function
        require_once($this->defaultMarkupFilePath);

    }


    public function addSimpleSearchCategoryField(HookEvent $event) {
        $languages = $this->wire('languages');
        $template = $event->arguments[0];
        $form = $event->return;
    
        $field = $this->modules->get("InputfieldText");
        $field->attr('id+name', 'simplesearch_category'); 
        $field->attr('value', $template->simplesearch_category);
        if ($languages) {
            $field->useLanguages = true;
            foreach ($languages as $language) {
                $field->set('value' . $language->id, $template->get("simplesearch_category__{$language->id}"));
            }
        }
        $field->label = $this->_('SimpleSearch Category');
        $field->description = $this->_('Enter the SimpleSearch category label for this template. If empty, pages using this template will NOT be indexed.');
        $field->notes = $this->_('tipp: use plural');

        // $form->insertAfter($field, $form->tags);

        // Find the "label" field in the form
        $labelField = $form->getChildByName('templateLabel');

        // Insert the "simplesearch_category" field after the "label" field
        $form->insertAfter($field, $labelField);

        // $this->message($form->simplesearch_category);
        
        $event->return = $form;
    }
    

    public function saveSimpleSearchCategoryFieldValue(HookEvent $event) {
        $template = $this->templates->get($this->input->post->id);
        $template->set('simplesearch_category', $this->input->post->simplesearch_category);
    
        $languages = $this->wire('languages');
        if ($languages) {
            foreach ($languages as $language) {
                $template->set("simplesearch_category__{$language->id}", $this->input->post->{"simplesearch_category__$language->id"});
            }
        }
    }
        

    protected function setIndexedcategories() {
        // Return an array of template names you want to index by default.
        // For example, to index all pages from the "project" and "article" templates:
        // return ['', 'project', 'article'];

        $indexedCategories = new WireArray;

        foreach ($this->templates as $temp) {
            if ($temp->simplesearch_category == '') continue;
            $indexedCategories->add($temp);
        }

        $indexedCategories->prepend('');

        $this->indexedCategories = $indexedCategories;

    }


    // public function getModuleConfigInputfields() {

    //     $inputfields = new InputfieldWrapper();

    //     return $inputfields;

    // }







    protected function updateStart() {

        $start = (int)$this->limit * ($this->input->pageNum() - 1);
        return $start;
    
    }


    protected function sanitizeInput() {

        // Sanitize the search term input
        $sanitizer = $this->wire('sanitizer');
        $input = $this->wire('input');
    
        // Sanitize and store the search term 'q'
        if (isset($input->get->q)) {
            $this->q = $sanitizer->text($input->get->q);
        } else {
            $this->q = null; // or $this->q = ''; if you prefer an empty string
        }
    
        // Sanitize and store the category 'cat'
        if (isset($input->get->cat)) {
            $this->cat = $sanitizer->int($input->get->cat);
        } else {
            $this->cat = 0; // or any other default value you want
        }

    }
    
    public function handleSearch() {

        // Check if the search form was submitted (i.e., input variable exists)

        if ($this->q) {

            $this->allResultsLabel = $this->checkAndGetLanguageValue('all_entries_label', '__');
        
            $indexedCategories = $this->indexedCategories;
            
            $allTotals = 0;

            foreach ($indexedCategories as $cat => $category) {

                if ($cat == 0) continue;

                // Pass the sanitized input to createSelector() to get the selector string.
                $selector = $this->createSelector($this->q, $category);

                $matches = $this->pages("$selector, start=0, limit=99999");

                // Filter the matches to include only pages with a matching value in the active language
                // $filteredMatches = $this->filterCurrentLanguage($matches, $this->q);
                $filteredMatches = $matches;

                // Calculate the total matches and the start index for the current page
                $total = count($filteredMatches);

                $this->results->set($cat, $filteredMatches);
                $this->totals->set($cat, $total);

                $string = $this->getLanguageString('simplesearch_category', '__');
                $categoryLabel = $this->templates->get($category)->$string;

                $this->labels->set($cat, $categoryLabel);

                $allTotals += $total;

            }

            $this->results->prepend('');
            $this->totals->prepend($allTotals);
            $this->labels->prepend($this->allResultsLabel);

            // Update the total count for all results
            $this->totals->set(0, $allTotals);

        } 
        
    }
    
        
    protected function createSelector($q, $category) {

        $selector = "template=" . $category;
        $search_operator = $this->search_operator;

        $fields = $this->getUniqueFieldsFromTemplate($category);
        $selector .= ", " . implode('|', $fields) . "$search_operator.$q";

        return $selector;

    }


    // Helper method to extract unique fields from an array of templates
    protected function getUniqueFieldsFromTemplate($category) {

        $fields = [];
        $allowedFieldTypes = [
            "FieldtypeTextLanguage",
            "FieldtypeTextareaLanguage",
            "FieldtypePageTitleLanguage",
        ];        

        // Replace this loop with the logic to extract the fields from the templates
        // For example, if each template has a property "$fields" containing an array of field names:
        $template = $this->templates->get("$category");
        foreach ($template->fields as $field) {

            // if (strpos($field->type, "Text") == false && strpos($field->type, "Title") == false) continue;
            if (!in_array($field->type, $allowedFieldTypes)) { continue; }
            $fields[] = $field->name; // Store the name of the field in the $fields array
        }

        return array_unique($fields);

    }


    public function renderMarkupForSearchCategory($match, $source) {

        $functionName = "renderSearchMarkup_{$source}";

        if (function_exists($functionName)) {
            return call_user_func($functionName, $match);
        }

        return $this->renderDefaultMarkup($match);


    }


    protected function renderDefaultMarkup($match) {

        $html = '';
        
        $html .= '<a href="'.$match->url.'" target="_blank"><h3>'.$match->title.'</h3></a>';
    
        $html .= $this->renderSnippet($match);

        return $html;
    
    }


    public function renderSnippet($match, $start=25, $end=25) {

        $html = '';

        // Create an array to store snippets for each field
        $snippets = array();

        $uniqueFields = $this->getUniqueFieldsFromTemplate($match->template);


        // Find snippets for each field where the search term was found
        foreach ($uniqueFields as $field) {
            $content = strip_tags($match->$field); // Strip HTML tags from the content
            if (stripos($content, $this->q) !== false) {
                // Find the position of the search term in the content
                $position = stripos($content, $this->q);
                // Extract a snippet of text around the matched term
                $startPos = max(0, $position - $start); // Get 50 characters before the matched term
                $endPos = min(strlen($content), $position + $end); // Get 50 characters after the matched term
                
                // Highlight the search term within the snippet using <strong> tags
                $snippet = substr($content, $startPos, $position - $startPos) . "<strong>" . substr($content, $position, strlen($this->q)) . "</strong>" . substr($content, $position + strlen($this->q), $endPos - ($position + strlen($this->q)));
                
                // Add the snippet to the snippets array
                $snippets[$field] = $snippet;
            }
        }

        // Check if there is at least one snippet
        if (!empty($snippets)) {
            // Concatenate all the snippets into one string
            $combinedSnippet = '... ' . implode(' ... ', $snippets) . ' ...';

            // Wrap the combined snippets with <p></p> tags
            $html .= '<p>' . $combinedSnippet . '</p>';

        }

        return $html;
    
    }

    protected function checkAndGetLanguageValue(string $key, string $x='') {
        $fieldNameString = $this->getLanguageString($key, $x);
        return $this->$fieldNameString;
    }

    protected function getLanguageString(string $key, string $x='') {
        $language = $this->user->language;
        if ($language->name !== 'default') {
            $string = $key.$x.$language->id;
            return $string;
        } 
        return $key;
    }
        

    public function renderCriteriaMarkup() {

        $searchCriteriaFormat = $this->checkAndGetLanguageValue('search_criteria', '__');
    
        if (!$this->q) return;
    
        $html = $searchCriteriaFormat;
    
        $searchQuery = $this->q;
    
        $html = str_replace('{template}', $this->labels->eq($this->cat), $html);
        $html = str_replace('{q}', $searchQuery, $html);
    
        return $html;

    }


    public function renderOverviewMarkup() {

        if (!$this->q) return;

        $html = '';

        // overview :D

        $cat = $this->cat;
        $allTotals = $this->totals->eq(0);

        if ($allTotals > 0) {
            if ($cat == 0 || !$cat) {
                $html .= '<strong>' . $this->allResultsLabel . ' (' . $allTotals . '), </strong>';
            } else {
                $html .= '<a class="colorlinks" href="./?q=' . $this->q . '">' . $this->allResultsLabel . ' (' . $allTotals . '), </a>';
            }
        }

        // still overview :D

        foreach ($this->indexedCategories as $key => $content) {
            if ($key == 0) continue;
            // echo '<pre>';
            // print_r($this->templates->get($content)->$string);
            // echo '</pre>';

            $total = $this->totals->eq($key);
            if ($total < 1) {
                $html .= '<strong class="grey">' . $this->labels->eq($key) . ' (' . $total . '), </strong>';
            } else {
                if ($cat == $key) {
                    $html .= '<strong>' . $this->labels->eq($key) . ' (' . $total . '), </strong>';
                } else {
                    // $html .= '<a class="colorlinks" href="./?q=' . $this->q . '&cat=' . $key . '">' . $this->labels->eq($key) . ' (' . $total . '), </a>';
                    $html .= '<a class="colorlinks" href="./?q=' . $this->q . '&cat=' . $key . '">' . $this->labels->eq($key) . ' (' . $total . '), </a>';
                }
            }
        }

        $html .= '<br/><h4><hr>';

        return $html;

    }


    public function renderResultsMarkup() {

        if (!$this->q) return;
        
        $allTotals = $this->totals->eq(0);
        $cat = $this->cat;

        $html = '';

        // results :D

        if ($cat == 0) {
            foreach ($this->results as $key => $matches) {
                if ($key == 0) continue; 
                $total = $this->totals->eq($key); 
                if ($total < 1) continue;

                $html .= '<h3><a class="colorlinks" href="./?q=' . $this->q . '&cat=' . $key . '">' . $this->labels->eq($key) . ' (' . $total . ')</a></h3>';
                $html .= '<ul class="nostyle">';

                $limit = $this->sublimit;

                $matches->filter("limit=$limit");

                foreach ($matches as $i => $match) {
                    // $html .= layout('search_' . $source, $item);
                    $source = $match->template->name;
                    $html .= $this->renderMarkupForSearchCategory($match, $source);
                    // if($match->editable()):
                    //     $html .= '<p><a href="' . $match->editUrl() . '" target="_blank">Edit this page</a></p>';
                    // endif;
                    $html .= '<hr>';
                }

                $html .= '</ul>';

                if ($total > $this->sublimit) {
                    $html .= '<h3><a class="colorlinks" href="./?q=' . $this->q . '&cat=' . $key . '">mehr…</a></h3>';
                }
            
                $html .= '<hr>';
            }
        } else {
            $total = $this->totals->eq($cat); 
            $html .= '<h3><strong>' . $this->labels->eq($cat) . ' (' . $total . ')</strong></h3>';
            $html .= '<ul class="nostyle">';

            $matches = $this->results->eq($cat);
            $start = $this->updateStart();
            $limit = (int)$this->limit;
            $pagMatches = $matches->find("start=$start, limit=$limit");

            foreach ($pagMatches as $i => $match) {
                $source = $match->template->name;
                $html .= $this->renderMarkupForSearchCategory($match, $source);
                // if($match->editable()):
                //     $html .= '<p><a href="' . $match->editUrl() . '" target="_blank">Edit this page</a></p>';
                // endif;
                $html .= '<hr>';
            }

            $html .= '</ul>';
            $html .= '<hr>';

        }

        return $html;

    }


    public function renderPaginationString() {

        if (!$this->q) return;

        $pagination_string_entries = $this->checkAndGetLanguageValue('pagination_string_entries', '__');

        // pagination string :D

        $cat = $this->cat;

        $html = '';

        if ($cat > 0) {

            $matches = $this->results->eq($cat);
            $start = $this->updateStart();
            $limit = (int)$this->limit;
            $pagMatches = $matches->find("start=$start, limit=$limit");

            if ($pagMatches->count) {
                $html .= '<span class="grey">' . $pagMatches->getPaginationString(array(
                    'label' => $pagination_string_entries,
                    'zeroLabel' => '0 '.$pagination_string_entries, // 3.0.127+ only
                    'usePageNum' => false,
                    'count' => $pagMatches->count(),
                    'start' => $pagMatches->getStart(),
                    'limit' => $pagMatches->getLimit(),
                    'total' => $this->totals->eq($cat)
                    // 'count' => $pagMatches->count(),
                    // 'start' => $this->updateStart(),
                    // 'limit' => $this->limit,
                    // 'total' => $this->totals->eq($cat)
                )) . '</span>';
            }
        }

        return $html;        
        
    }

    public function renderFilters() {

        $html = '<form action="./" method="get">';
        $qValue = isset($this->q) ? htmlentities($this->q) : '';
        $html .= '<label for="q">Search:</label>';
        $html .= '<input type="text" id="q" name="q" value="' . $qValue . '">';
        $html .= '<input type="submit" value="Search">';
        $html .= '</form>';
    
        return $html;

    }
    
    public function renderPaginationMarkup() {
    
        $options = array(
            'listClass' => 'pagination noselect uk-flex uk-flex-wrap uk-flex-center',
            'linkMarkup' => "<a href='{url}?q={$this->q}&cat={$this->cat}'>{out}</a>",
            'currentItemClass' => 'current',
            'separatorItemLabel' => '…',
            'separatorItemClass' => 'uk-disabled',
            'previousItemClass' => 'nextprev',
            'nextItemClass' => 'nextprev',
            'currentLinkMarkup' => '<span class="current">{out}</span>',
            'nextItemLabel' => '<span uk-icon="icon: arrow-right; ratio: 1.8;"></span>',
            'previousItemLabel' => '<span uk-icon="icon: arrow-left; ratio: 1.8;"></span>',
            'numPageLinks' => '4',
            'lastItemClass' => ''
        );
        
        $html = '';
    
        // Get the total number of results for the current category
        $totalResults = $this->totals->eq($this->cat);
    
        $pager = $this->wire('modules')->get('MarkupPagerNav');

        // Check if we need to render pagination links
        if ($this->cat > 0 && $this->q != '') {
            // Update the 'total' option in the $options array with the total number of results
            $options['total'] = $totalResults;
    
            // Create a new WireArray containing only the paginated matches
            $matches = $this->results->eq($this->cat);
            $start = $this->updateStart();
            $limit = (int)$this->limit;
            $matches->setStart($start);
            $matches->setLimit($limit);
        
            // Render the pagination links
            $html .= '<section>';
            $html .= '<div class="uk-flex uk-flex-center">' . $pager->render($matches, $options) . '</div>';
            $html .= '</section>';
        } 
    
        // Return the stored HTML
        return $html;
    }
        
}