<?php namespace ProcessWire; 

class SimpleSearch extends WireData implements Module, ConfigurableModule {

    // protected $page;

    public static function getModuleInfo() {

        return array(
            'title' => 'Simple Search',
            'version' => '1.0.0',
            'summary' => 'A simple search module for ProcessWire.',
            // 'autoload' => true,
            // 'singular' => true,
            'icon' => 'search',
            'author' => 'FRE:D',
            'installs' => [], // Optional array of module names that this module should install, if any
            'requires' => [], // Optional array of module names that are required for this module to run, if any
            'settings' => [
                'limit' => 20, // Default value for the limit setting
                'sublimit' => 10, // Default value for the sublimit setting
            ],
        );

    }

    public function __construct() {
        $simpleSearchSettings = wire('modules')->getConfig($this);
        foreach ($simpleSearchSettings as $key => $value) {
            $this->$key = $value;
        }
    }

    public function init() {

        // Define the pages to search
        // $this->indexedCategories = ["", "project", "article"];
        $this->allResultsLabel = __("Alle Inhalte");
        
        $this->q = '';
        $this->cat = 0;
        
        $this->results = new WireArray;
        $this->totals = new WireArray;
        $this->labels = new WireArray;

        $this->inputSanitized = $this->sanitizeInput();

        $this->start = $this->limit * ($this->input->pageNum() - 1);

        // Explicitly load the MarkupPagerNav module
        $this->pager = $this->modules->get('MarkupPagerNav');
        

        // Get the indexed templates from the module configuration
        $indexedTemplates = $this->config->indexedTemplates;

        // If no indexed templates are selected, fallback to indexing all templates
        if (empty($indexedTemplates)) {
            $indexedTemplates = $this->getDefaultIndexedTemplates();
        }

        $this->indexedCategories = $indexedTemplates;
    
    }

    protected function getDefaultIndexedTemplates() {
        // Return an array of template names you want to index by default.
        // For example, to index all pages from the "project" and "article" templates:
        return ['', 'project', 'article'];
    }

    protected function updateStart() {

        $start = $this->limit * ($this->input->pageNum() - 1);
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
    
    public function handleSearch($page) {

        echo $page->title;

        $this->currentPage = $page;

        // Check if the search form was submitted (i.e., input variable exists)
        if ($this->q) {

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
                $this->labels->set($cat, $this->templates->get("$category")->label);                

                $allTotals += $total;

            }

            $this->results->prepend('');
            $this->totals->prepend($allTotals);
            $this->labels->prepend($this->allResultsLabel);

            // Update the total count for all results
            $this->totals->set(0, $allTotals);

        } 
        
    }
    
        
    protected function filterCurrentLanguage($items, $q) {

        $filteredItems = new PageArray;

        foreach ($items as $item) {
            $fields = $this->getUniqueFieldsFromTemplate($item->template);
            $foundInCurrentLanguage = false;

            foreach ($fields as $field) {
                $fieldValue = $item->getLanguageValue($language, $field);
                if (stripos($fieldValue, $q) !== false) {
                    // echo '<h1>'.strlen($fieldValue).'</h1>';
                    echo '<h1>'.stripos($fieldValue, $q) . ' – ' . $field . ' – ' . $item->template . '</h1>';    
                    $foundInCurrentLanguage = true;
                    break;
                } else {
                    // echo '<h1>'.stripos($fieldValue, $q).'</h1>';
                }
            }
    

            if ($foundInCurrentLanguage == true) {
                $filteredItems->add($item);
            }
        }
    
        return $filteredItems;
    }
    

    protected function createSelector($q, $category) {
        $selector = "template=$category";
        $fields = $this->getUniqueFieldsFromTemplate($category);
        $this->uniqueFields = $fields;
    
        $subselectors = array();

        foreach ($fields as $field) {
            $subselector = "$field~=$q";
            if ($field instanceof Field && $field->type instanceof FieldtypeLanguage) {
                // Add OR condition for each language
                foreach ($languages as $language) {
                    if ($language->id !== $currentLanguageId) {
                        $langField = $field->name . $language->id;
                        $subselector .= ", $langField~=$q";
                    }
                }
            }
            $subselectors[] = "($subselector)";
        }
        
        $selector .= ", (" . implode('|', $subselectors) . ")";
        return $selector;
    }


    // Helper method to extract unique fields from an array of templates
    protected function getUniqueFieldsFromTemplate($category) {

        $fields = [];

        // Replace this loop with the logic to extract the fields from the templates
        // For example, if each template has a property "$fields" containing an array of field names:
        $template = $this->templates->get("$category");
        foreach ($template->fields as $field) {

            if (strpos($field->type, "Text") == false && strpos($field->type, "Title") == false) continue;
            $fields[] = $field->name; // Store the name of the field in the $fields array
        }

        return array_unique($fields);

    }
    

    public function renderCriteriaMarkup() {

        if (!$this->q) return;

        $html = '';

        if (isset($this->cat) && $this->cat > 0 ) {
            $html .= "in: ";
            $html .= $this->indexedCategories[$this->cat];    
        } else {
            $html .= "in: $this->allResultsLabel";
        }

        $html .= ", ";
        $html .= "Suchbegriff: ";
        $html .= $this->q;
        
        if (isset($this->sort) && $this->sort != '') {
            $html .= ", ";
            $html .= "Sortierung: ";
            $html .= $this->sort;
        }

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
            $total = $this->totals->eq($key);
            if ($total < 1) {
                $html .= '<strong class="grey">' . $this->labels->eq($key) . ' (' . $total . '), </strong>';
            } else {
                if ($cat == $key) {
                    $html .= '<strong>' . $this->labels->eq($key) . ' (' . $total . '), </strong>';
                } else {
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
                    $source = $this->indexedCategories[$cat];
                    // $html .= layout('search_' . $source, $item);
                    $html .= '<a href="'.$match->url.'" target="_blank">'.$match->title.'</a>';
                    $html .= $this->renderSnippet($match);
                    if($match->editable()):
                        $html .= '<p><a href="' . $match->editUrl() . '" target="_blank">Edit this page</a></p>';
                    endif;
                    $html .= '<hr>';
                }

                $html .= '</ul>';

                if ($total > $this->sublimit && $cat == 0) {
                    $html .= '<h3><a class="colorlinks" href="./?q=' . $this->q . '&cat=' . $key . '">mehr…</a></h3>';
                }
            
                $html .= '<hr>';
            }
        } else {
            $total = $this->totals->eq($cat); 
            $html .= '<h3><strong>' . $this->templates->get($this->indexedCategories[$cat])->label . ' (' . $total . ')</strong></h3>';
            $html .= '<ul class="nostyle">';

            $matches = $this->results->eq($cat);
            $start = $this->updateStart();
            $limit = $this->limit;
            $pagMatches = $matches->find("start=$start, limit=$limit");

            foreach ($pagMatches as $i => $match) {
                // $source = $this->templates->get($this->indexedCategories[$cat])->label;
                // $html .= layout('search_' . $source, $item);
                $ii = $i + 1;
                $html .= $ii . ' - ';
                $html .= '<a href="'.$match->url.'" target="_blank">'.$match->title.'</a>';
                $html .= $this->renderSnippet($match);
                if($match->editable()):
                    $html .= '<p><a href="' . $match->editUrl() . '" target="_blank">Edit this page</a></p>';
                endif;
                $html .= '<hr>';
            }

            $html .= '</ul>';
            $html .= '<hr>';

        }

        return $html;

    }

    protected function renderSnippet($match) {

        // Create an array to store snippets for each field
        $snippets = array();
        $html = '';
        
        // Find snippets for each field where the search term was found
        foreach ($this->uniqueFields as $field) {
            $content = strip_tags($match->$field); // Strip HTML tags from the content
            if (stripos($content, $this->q) !== false) {
                // Find the position of the search term in the content
                $position = stripos($content, $this->q);
                // Extract a snippet of text around the matched term
                $startPos = max(0, $position - 25); // Get 50 characters before the matched term
                $endPos = min(strlen($content), $position + 25); // Get 50 characters after the matched term
                
                // Highlight the search term within the snippet using <strong> tags
                $snippet = substr($content, $startPos, $position - $startPos) . "<strong>" . substr($content, $position, strlen($this->q)) . "</strong>" . substr($content, $position + strlen($this->q), $endPos - ($position + strlen($this->q)));
                
                // Add the snippet to the snippets array
                $snippets[$field] = $snippet;
            }
        }
        
        // Now, you can include the snippets in your result markup
        foreach ($snippets as $field => $snippet) {
            $html .= "<p><strong>$field</strong>...$snippet...</p>";
        }

        return $html;

    }

    public function renderPaginationString() {

        if (!$this->q) return;

        // pagination string :D

        $cat = $this->cat;

        $html = '';

        if ($cat > 0) {

            $matches = $this->results->eq($cat);
            $start = $this->updateStart();
            $limit = $this->limit;
            $pagMatches = $matches->find("start=$start, limit=$limit");

            if ($pagMatches->count) {
                $html .= '<span class="grey">' . $pagMatches->getPaginationString(array(
                    'label' => 'Einträge',
                    'zeroLabel' => '0 Einträge', // 3.0.127+ only
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
            $limit = $this->limit;
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
